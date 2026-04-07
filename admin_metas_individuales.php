<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin(2);

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');

$user_id_default = $_SESSION['user_id'] ?? '';
$periodo_default = $_SESSION['periodo'] ?? '';
$periodo_activo = (int)($_SESSION['periodo'] ?? 0);

$user_id = $_GET['user_id'] ?? '';
$periodo_ = $_GET['periodo'] ?? '';
$unidad_id = $_GET['unidad_id'] ?? '';
$modo = $_GET['modo'] ?? '';
$q = trim($_GET['q'] ?? '');
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina = 25;
$offset = ($pagina - 1) * $por_pagina;

$periodo_seleccionado = !empty($periodo_) ? (int)$periodo_ : (int)$periodo_default;
$registros_autocorregidos = 0;

if ($periodo_seleccionado > 0) {
    // Si la suma de ponderación no es 100, regresamos estatus a capturado y limpiamos calificación
    $stmt = $pdo->prepare("UPDATE calificaciones c
        JOIN (
            SELECT user_id, COALESCE(SUM(ponderacion), 0) AS suma_ponderacion
            FROM metas
            WHERE periodo = ?
            GROUP BY user_id
        ) m ON m.user_id = c.user_id
        SET c.estatus_metas = 1,
            c.individuales = 0
        WHERE c.periodo = ?
          AND m.suma_ponderacion <> 100
          AND (c.estatus_metas <> 1 OR COALESCE(c.individuales, 0) <> 0)");
    $stmt->execute([$periodo_seleccionado, $periodo_seleccionado]);
    $registros_autocorregidos += (int)$stmt->rowCount();

    // Si ya no existen metas para ese usuario/periodo, estatus sin captura
    $stmt = $pdo->prepare("UPDATE calificaciones c
        SET c.estatus_metas = 0,
            c.individuales = 0
        WHERE c.periodo = ?
          AND NOT EXISTS (
              SELECT 1 FROM metas m
              WHERE m.user_id = c.user_id
                AND m.periodo = c.periodo
          )
          AND (c.estatus_metas <> 0 OR COALESCE(c.individuales, 0) <> 0)");
    $stmt->execute([$periodo_seleccionado]);
    $registros_autocorregidos += (int)$stmt->rowCount();
}

// Consulta usuarios, unidades y periodos
$unidades = $pdo->query("SELECT id, nombre FROM unidades ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$sqlUsuarios = "SELECT user_id, nombre, apellido_paterno, apellido_materno, RFC
                FROM usuarios
                WHERE 1";
$paramsUsuarios = [];
if ($unidad_id !== '') {
    $sqlUsuarios .= " AND unidad_id = :unidad_id";
    $paramsUsuarios['unidad_id'] = (int)$unidad_id;
}
$sqlUsuarios .= " ORDER BY nombre ASC";
$stmtUsuarios = $pdo->prepare($sqlUsuarios);
foreach ($paramsUsuarios as $clave => $valor) {
    $stmtUsuarios->bindValue(':' . $clave, $valor, PDO::PARAM_INT);
}
$stmtUsuarios->execute();
$usuarios = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);
$periodos = $pdo->query("SELECT DISTINCT anio FROM periodos ORDER BY anio DESC")->fetchAll();

// Consulta resumen por usuario para el periodo seleccionado
$sql = "SELECT
            u.user_id,
            u.nombre,
            u.apellido_paterno,
            u.apellido_materno,
            u.RFC,
            pu.puesto,
            COUNT(m.id) AS metas_capturadas,
            COALESCE(SUM(m.ponderacion), 0) AS suma_ponderacion,
            ROUND(COALESCE(SUM(CASE WHEN m.resultado_final > 0 THEN (m.resultado_final * m.ponderacion / 100) ELSE 0 END), 0), 2) AS resultado_final,
            COALESCE(c.estatus_metas, 0) AS estatus_metas,
            (SELECT COUNT(*) FROM metas ma WHERE ma.user_id = u.user_id AND ma.periodo = :periodo_activo) AS metas_periodo_activo
        FROM usuarios u
        LEFT JOIN puestos pu ON u.puesto_id = pu.id
        LEFT JOIN metas m ON m.user_id = u.user_id AND m.periodo = :periodo_sel
        LEFT JOIN calificaciones c ON c.user_id = u.user_id AND c.periodo = :periodo_sel
        WHERE 1";

$params = [
    'periodo_sel' => $periodo_seleccionado,
    'periodo_activo' => $periodo_activo
];

if (!empty($user_id)) {
    $sql .= " AND u.user_id = :user_id";
    $params['user_id'] = $user_id;
}
if ($unidad_id !== '') {
    $sql .= " AND u.unidad_id = :unidad_id";
    $params['unidad_id'] = (int)$unidad_id;
}
if ($q !== '') {
    $sql .= " AND (u.nombre LIKE :q OR u.apellido_paterno LIKE :q OR u.apellido_materno LIKE :q OR u.RFC LIKE :q)";
    $params['q'] = "%{$q}%";
}

$sql .= " GROUP BY u.user_id, u.nombre, u.apellido_paterno, u.apellido_materno, u.RFC, pu.puesto, c.estatus_metas
          ORDER BY u.nombre ASC, u.apellido_paterno ASC, u.apellido_materno ASC
          LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $clave => $valor) {
    $stmt->bindValue(':' . $clave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$resumen = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sql_total = "SELECT COUNT(*)
FROM usuarios u
WHERE 1";
$params_total = [];
if (!empty($user_id)) {
    $sql_total .= " AND u.user_id = :user_id";
    $params_total['user_id'] = $user_id;
}
if ($unidad_id !== '') {
    $sql_total .= " AND u.unidad_id = :unidad_id";
    $params_total['unidad_id'] = (int)$unidad_id;
}
if ($q !== '') {
    $sql_total .= " AND (u.nombre LIKE :q OR u.apellido_paterno LIKE :q OR u.apellido_materno LIKE :q OR u.RFC LIKE :q)";
    $params_total['q'] = "%{$q}%";
}

$stmt_total = $pdo->prepare($sql_total);
foreach ($params_total as $clave => $valor) {
    $stmt_total->bindValue(':' . $clave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt_total->execute();
$total_registros = (int)$stmt_total->fetchColumn();
$total_paginas = max(1, (int)ceil($total_registros / $por_pagina));

$query_base = $_GET;
unset($query_base['pagina']);
$query_base = http_build_query($query_base);
$query_base = $query_base !== '' ? $query_base . '&' : '';

$metas_edicion = [];
if ($modo === 'editar' && !empty($user_id) && $periodo_activo > 0) {
    $stmt = $pdo->prepare("SELECT id, indicador, unidad, ponderacion, resultado_final FROM metas WHERE user_id = ? AND periodo = ? ORDER BY id DESC");
    $stmt->execute([$user_id, $periodo_activo]);
    $metas_edicion = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es" dir="ltr">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title><?php echo htmlspecialchars($nombre_sistema); ?></title>

	<!-- Global stylesheets -->
	<link href="assets/fonts/inter/inter.css" rel="stylesheet" type="text/css">
	<link href="assets/icons/phosphor/styles.min.css" rel="stylesheet" type="text/css">
	<link href="assets/css/ltr/all.min.css" id="stylesheet" rel="stylesheet" type="text/css">
	<!-- /global stylesheets -->

	<!-- Core JS files -->
	<script src="assets/demo/demo_configurator.js"></script>
	<script src="assets/js/bootstrap/bootstrap.bundle.min.js"></script>
	<link href="assets/icons/icomoon/styles.min.css" rel="stylesheet" type="text/css">

	<!-- /core JS files -->

	<!-- Theme JS files -->
	<script src="assets/js/jquery/jquery.min.js"></script>
	<script src="assets/js/vendor/tables/datatables/datatables.min.js"></script>
	<script src="assets/js/vendor/notifications/bootbox.min.js"></script>
	<script src="assets/js/vendor/forms/selects/select2.min.js"></script>

	<script src="assets/js/app.js"></script>
	<script src="assets/demo/pages/components_modals.js"></script>
    <script src="assets/demo/pages/components_buttons.js"></script>
	<script src="assets/demo/pages/datatables_basic.js"></script>
	<script src="assets/demo/pages/form_select2.js"></script>
	<!-- /theme JS files -->
</head>

<body>

<?php require_once('assets/main_navbar.php'); ?>

	<!-- Page content -->
	<div class="page-content">

	<?php require_once('assets/main_navigation.php'); ?>

		<!-- Main content -->
		<div class="content-wrapper">

			<!-- Inner content -->
			<div class="content-inner">

				<!-- Page header -->
				<div class="page-header page-header-light shadow">

					<div class="page-header-content d-lg-flex border-top">
						<div class="d-flex">
							<div class="breadcrumb py-2">
								<a href="index.php" class="breadcrumb-item"><i class="ph-house"></i></a>
								<a href="#" class="breadcrumb-item">Administracion</a>
								<span class="breadcrumb-item active">Metas Individuales</span>
							</div>

							<a href="#breadcrumb_elements" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
								<i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
							</a>
						</div>

					</div>
				</div>
				<!-- /page header -->

				<!-- Content area -->
				<div class="content">

                <?php if ($registros_autocorregidos > 0): ?>
                    <div class="alert alert-warning border-0 alert-dismissible fade show" id="alerta-auto-ajuste">
                        <span class="fw-semibold">⚠️ Se actualizaron automáticamente <?= (int)$registros_autocorregidos ?> registro(s) de estatus para el periodo <?= (int)$periodo_seleccionado ?> por inconsistencia en metas (ponderación distinta de 100% o sin metas).</span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['info'])): ?>
                    <?php
                    $clase_alerta = 'alert-success'; // valor por defecto

                    switch ($_GET['info']) {
                        case 1:
                            $texto = '✅ Meta guardada correctamente.';
                            $clase_alerta = 'alert-success';
                            break;
                        case 2:
                            $texto = '✏️ Meta actualizada.';
                            $clase_alerta = 'alert-info';
                            break;
                        case 3:
                            $texto = '🗑️ Meta eliminada.';
                            $clase_alerta = 'alert-danger';
                            break;
                        case 9:
                            $texto = '✅ Periodo cerrado correctamente';
                            $clase_alerta = 'alert-danger';
                            break;
                        default:
                            $texto = 'info no reconocido.';
                            $clase_alerta = 'alert-secondary';
                            break;
                    }
                    ?>

                    <div class="alert <?= $clase_alerta ?> border-0 alert-dismissible fade show" id="alerta-auto">
										<span class="fw-semibold"> <?= $texto ?>
										<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
								    </div>
                    <?php endif; ?>


                    <!-- Basic table -->
					<div class="card">
						<div class="card-header">
							<h5 class="mb-0">Administración de Metas Individuales</h5>
						</div>

						<div class="card-body">
							Selecciona.<br/>
                            <p>&nbsp;</p>

    <form class="row g-3 mb-3" method="get">
        <div class="col-md-4">
            <label class="form-label">Usuario</label>
            <select name="user_id" class="form-select select">
                <option value="">Todos</option>
                <?php  foreach ($usuarios as $u): ?>
                    <option value="<?= $u['user_id'] ?>" <?= ($user_id == $u['user_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['nombre']." ".$u['apellido_paterno']." ".$u['apellido_materno']." (".$u['RFC'].")") ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Unidad Administrativa</label>
            <select name="unidad_id" class="form-select select">
                <option value="">Todas</option>
                <?php foreach ($unidades as $unidad): ?>
                    <option value="<?= (int)$unidad['id'] ?>" <?= ((string)$unidad_id === (string)$unidad['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($unidad['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Periodo</label>
            <select name="periodo" class="form-select">
                <option value="">Todos</option>
                <?php foreach ($periodos as $per): ?>
                    <option value="<?= $per['anio'] ?>" <?= ((int)$periodo_seleccionado === (int)$per['anio']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($per['anio']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Buscar</label>
            <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($q) ?>" placeholder="Nombre o RFC">
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <button type="submit" class="btn btn-sm btn-primary me-2">Filtrar</button>
            <a href="admin_metas_individuales.php" class="btn btn-sm btn-secondary">Limpiar</a>
        </div>
    </form>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="text-muted">Mostrando <?= count($resumen) ?> de <?= $total_registros ?> usuarios</span>
    </div>

    <div class="table-responsive">
    <table class="table table-striped">
            <thead class="thead-light">
            <tr class="bg-primary text-white">
                    <td>Usuario</td>
                    <th>Metas Capturadas</th>
                    <th>Periodo</th>
                    <th>Suma Ponderación</th>
                    <th>Estatus</th>
                    <th>Resultado Final</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resumen as $fila): ?>
                    <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="flex-fill">
                                <a class="fw-semibold"><?= htmlspecialchars($fila['nombre'] . ' ' . $fila['apellido_paterno'] . ' ' . $fila['apellido_materno']) ?></a>
                                <div class="fs-sm text-muted">
                                <?= htmlspecialchars($fila['puesto'] ?? '') ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td><?= (int)$fila['metas_capturadas'] ?></td>
                    <td><?= htmlspecialchars((string)$periodo_seleccionado) ?></td>
                    <td><?= number_format((float)$fila['suma_ponderacion'], 2) ?>%</td>
                        <td>
                            <?php
                            $estatus_metas = (int)($fila['estatus_metas'] ?? 0);
                            if ($estatus_metas === 0) {
                                echo 'Sin captura';
                            } elseif ($estatus_metas === 1) {
                                echo 'Capturado';
                            } elseif ($estatus_metas === 2) {
                                echo 'Resultado propuesto';
                            } elseif ($estatus_metas === 3) {
                                echo 'Con calificación';
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td><?= ((float)$fila['resultado_final'] > 0) ? number_format((float)$fila['resultado_final'], 2) . '%' : '-' ?></td>
                        <td>
                            <a href="admin_metas_individuales.php?user_id=<?= (int)$fila['user_id'] ?>&periodo=<?= (int)$periodo_seleccionado ?>&modo=editar" class="btn btn-sm btn-primary">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p>&nbsp;</p>
        <a href="metas_individuales_agregar.php" class="btn btn-sm btn-success">Agregar</a>

    </div>

    <?php if ($total_paginas > 1): ?>
    <nav aria-label="Paginación de metas individuales" class="mt-3">
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="admin_metas_individuales.php?<?= $query_base ?>pagina=<?= max(1, $pagina - 1) ?>">Anterior</a>
            </li>
            <?php for ($i = max(1, $pagina - 2); $i <= min($total_paginas, $pagina + 2); $i++): ?>
                <li class="page-item <?= ($i === $pagina) ? 'active' : '' ?>">
                    <a class="page-link" href="admin_metas_individuales.php?<?= $query_base ?>pagina=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= ($pagina >= $total_paginas) ? 'disabled' : '' ?>">
                <a class="page-link" href="admin_metas_individuales.php?<?= $query_base ?>pagina=<?= min($total_paginas, $pagina + 1) ?>">Siguiente</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>

    <?php if ($modo === 'editar' && !empty($user_id)): ?>
        <p>&nbsp;</p>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Edición de Metas del Periodo Activo (<?= (int)$periodo_activo ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($metas_edicion)): ?>
                    <div class="alert alert-warning">El usuario seleccionado no tiene metas en el periodo activo.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Meta</th>
                                    <th>Unidad</th>
                                    <th>Ponderación</th>
                                    <th>Resultado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($metas_edicion as $meta): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($meta['indicador']) ?></td>
                                        <td><?= htmlspecialchars($meta['unidad']) ?></td>
                                        <td><?= number_format((float)$meta['ponderacion'], 2) ?>%</td>
                                        <td><?= ((float)$meta['resultado_final'] > 0) ? number_format((float)$meta['resultado_final'], 2) . '%' : '-' ?></td>
                                        <td>
                                            <a href="admin_meta_editar.php?id=<?= (int)$meta['id'] ?>&return_user_id=<?= (int)$user_id ?>&return_periodo=<?= (int)$periodo_seleccionado ?>&return_modo=editar" class="btn btn-sm btn-primary">Editar</a>
                                            <a href="admin_metas_eliminar.php?id=<?= (int)$meta['id'] ?>&return_user_id=<?= (int)$user_id ?>&return_periodo=<?= (int)$periodo_seleccionado ?>&return_modo=editar" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar esta meta individual?');">Eliminar</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

</div>
</div>
</div>
				<!-- /content area -->

				<?php require_once('assets/footer.php'); ?>

			</div>
			<!-- /inner content -->

		</div>
		<!-- /main content -->

	</div>
	<!-- /page content -->
