<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';

if (!function_exists('asegurar_columna_capacitacion_contabiliza')) {
    function asegurar_columna_capacitacion_contabiliza(PDO $pdo): void {
        $stmt = $pdo->query("SHOW COLUMNS FROM capacitacion LIKE 'contabiliza_horas'");
        $columna = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$columna) {
            $pdo->exec("ALTER TABLE capacitacion ADD COLUMN contabiliza_horas TINYINT(1) NOT NULL DEFAULT 1 AFTER validado");
        }
    }
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin(2);

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');

$periodo_actual = $_SESSION['periodo'];
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id']; 
asegurar_columna_capacitacion_contabiliza($pdo);

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);

$periodo = $_GET['periodo'] ?? '';
$empleado = $_GET['empleado'] ?? '';
$unidad_id = $_GET['unidad_id'] ?? '';
$validado = $_GET['validado'] ?? '';
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$q = trim($_GET['q'] ?? '');
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina = 25;
$offset = ($pagina - 1) * $por_pagina;

// Obtener listas
$usuarios = $pdo->query("SELECT user_id, CONCAT(nombre, ' ', apellido_paterno, ' ', apellido_materno) AS nombre_completo FROM usuarios ORDER BY nombre ASC")->fetchAll();
$unidades = $pdo->query("SELECT id, nombre FROM unidades ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$modalidades = $pdo->query("SELECT * FROM capacitacion_modalidades ORDER BY modalidad ASC")->fetchAll();
$finalidades = $pdo->query("SELECT * FROM capacitacion_finalidades ORDER BY finalidad ASC")->fetchAll();
$categorias = $pdo->query("SELECT * FROM capacitacion_categorias ORDER BY categoria ASC")->fetchAll();

// Consulta
$params = [];
$sql = "SELECT u.puesto_nombre, c.observaciones, c.created_at, c.id, c.user_id, c.nombre_curso, c.horas, c.calificacion, c.institucion, c.correo, c.telefono, 
               c.fecha_inicio, c.fecha_fin, c.archivo_pdf, c.validado, c.periodo,
               c.contabiliza_horas,
               CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS empleado,
               cm.modalidad AS modalidad_texto,
               cf.finalidad AS finalidad_texto,
               cc.categoria AS categoria_texto
        FROM capacitacion c
        JOIN usuarios u ON c.user_id = u.user_id
        LEFT JOIN capacitacion_modalidades cm ON c.modalidad = cm.id
        LEFT JOIN puestos p ON u.puesto_id = p.id
        LEFT JOIN capacitacion_finalidades cf ON c.finalidad = cf.id
        LEFT JOIN capacitacion_categorias cc ON c.categoria = cc.id
        WHERE 1=1";

if ($periodo != '') {
    $sql .= " AND c.periodo = ?";
    $params[] = $periodo;
}
if ($empleado != '') {
    $sql .= " AND c.user_id = ?";
    $params[] = $empleado;
}
if ($unidad_id !== '') {
    $sql .= " AND u.unidad_id = ?";
    $params[] = (int)$unidad_id;
}
if ($validado !== '') {
    $sql .= " AND c.validado = ?";
    $params[] = $validado;
}
if ($fecha_inicio != '' && $fecha_fin != '') {
    $sql .= " AND c.fecha_inicio BETWEEN ? AND ?";
    $params[] = $fecha_inicio;
    $params[] = $fecha_fin;
}
if ($q !== '') {
    $sql .= " AND (c.nombre_curso LIKE ? OR u.nombre LIKE ? OR u.apellido_paterno LIKE ? OR u.apellido_materno LIKE ?)";
    $q_like = "%{$q}%";
    $params[] = $q_like;
    $params[] = $q_like;
    $params[] = $q_like;
    $params[] = $q_like;
}

$sql_count = "SELECT COUNT(*)
        FROM capacitacion c
        JOIN usuarios u ON c.user_id = u.user_id
        LEFT JOIN capacitacion_modalidades cm ON c.modalidad = cm.id
        LEFT JOIN puestos p ON u.puesto_id = p.id
        LEFT JOIN capacitacion_finalidades cf ON c.finalidad = cf.id
        LEFT JOIN capacitacion_categorias cc ON c.categoria = cc.id
        WHERE 1=1";

$params_count = $params;
if ($periodo != '') {
    $sql_count .= " AND c.periodo = ?";
}
if ($empleado != '') {
    $sql_count .= " AND c.user_id = ?";
}
if ($unidad_id !== '') {
    $sql_count .= " AND u.unidad_id = ?";
}
if ($validado !== '') {
    $sql_count .= " AND c.validado = ?";
}
if ($fecha_inicio != '' && $fecha_fin != '') {
    $sql_count .= " AND c.fecha_inicio BETWEEN ? AND ?";
}
if ($q !== '') {
    $sql_count .= " AND (c.nombre_curso LIKE ? OR u.nombre LIKE ? OR u.apellido_paterno LIKE ? OR u.apellido_materno LIKE ?)";
}

$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params_count);
$total_registros = (int)$stmt_count->fetchColumn();
$total_paginas = max(1, (int)ceil($total_registros / $por_pagina));

$sql .= " ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
$params[] = $por_pagina;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
foreach ($params as $idx => $valor) {
    $stmt->bindValue($idx + 1, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$capacitaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query_base = $_GET;
unset($query_base['pagina']);
$query_base = http_build_query($query_base);
$query_base = $query_base !== '' ? $query_base . '&' : '';
?>
<!DOCTYPE html>
<html>
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

	<script src="assets/js/app.js"></script>
	<script src="assets/demo/pages/components_modals.js"></script>
    <script src="assets/demo/pages/components_buttons.js"></script>
	<script src="assets/demo/pages/datatables_basic.js"></script>
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
								<a href="home.php" class="breadcrumb-item"><i class="ph-house"></i></a>
								<a href="#" class="breadcrumb-item">Administraicón</a>
								<span class="breadcrumb-item active">Puestos</span>
							</div>

							<a href="#breadcrumb_elements" class="btn btn-sm btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
								<i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
							</a>
						</div>

					</div>
				</div>
				<!-- /page header -->

				<!-- Content area -->
				<div class="content">

					<!-- Basic table -->
					<div class="card">
						<div class="card-header">
							<h5 class="mb-0">Administración de Cursos de Capacitación</h5>
						</div>

						<div class="card-body">
						<p>Selecciona el filtro para mostar el resultado.</p>
                        <p>&nbsp;</p>

                 

    <?php if (isset($_GET['info'])): ?>
        <div class="alert alert-info alert-dismissible fade show">
            <?php
            switch ($_GET['info']) {
                case 1: echo '✅ Curso guardado correctamente.'; break;
                case 2: echo '✏️ Curso actualizado correctamente.'; break;
                case 3: echo '🗑️ Curso eliminado correctamente.'; break;
                case 4: echo '⚠️ El archivo es muy pesado.'; break;
                case 5: echo '✅ Curso validado correctamente.'; break;
                case 6: echo '✅ Validación de curso removida correctamente.'; break;
                case 8: echo '❌ No tienes permiso para realizar esta acción.'; break;
                case 11: echo '✅ Constancia aceptada correctamente (sin sumar horas a la meta de 40h).'; break;
                case 10: echo '🚫 No se puede registrar capacitación durante el periodo de EVALUACIÓN.'; break;
                default: echo 'Información procesada.'; break;
            }
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <form method="get" class="row g-2 mb-3">
        <div class="col-md-2"><input type="number" name="periodo" class="form-control" placeholder="Periodo" value="<?= htmlspecialchars($periodo) ?>"></div>
        <div class="col-md-2">
            <select name="empleado" class="form-select">
                <option value="">-- Empleado --</option>
                <?php foreach ($usuarios as $u): ?>
                    <option value="<?= $u['user_id'] ?>" <?= $empleado == $u['user_id'] ? 'selected' : '' ?>><?= $u['nombre_completo'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="unidad_id" class="form-select">
                <option value="">-- Unidad --</option>
                <?php foreach ($unidades as $unidad): ?>
                    <option value="<?= (int)$unidad['id'] ?>" <?= ((string)$unidad_id === (string)$unidad['id']) ? 'selected' : '' ?>><?= htmlspecialchars($unidad['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="validado" class="form-select">
                <option value="">-- Validado --</option>
                <option value="1" <?= $validado === "1" ? 'selected' : '' ?>>Sí</option>
                <option value="0" <?= $validado === "0" ? 'selected' : '' ?>>No</option>
            </select>
        </div>
        <div class="col-md-1"><input type="date" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($fecha_inicio) ?>"></div>
        <div class="col-md-1"><input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($fecha_fin) ?>"></div>
        <div class="col-md-2"><input type="text" name="q" class="form-control" placeholder="Curso o empleado" value="<?= htmlspecialchars($q) ?>"></div>
        <div class="col-md-1 d-grid"><button class="btn btn-sm btn-primary" type="submit">Filtrar</button></div>
        <div class="col-md-1 d-grid"><a href="admin_capacitacion.php" class="btn btn-sm btn-outline-secondary">Borrar filtro</a></div>
    </form>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="text-muted">Mostrando <?= count($capacitaciones) ?> de <?= $total_registros ?> cursos</span>
    </div>

    <?php
    $hay_no_validados = false;
    foreach ($capacitaciones as $c) {
        if (!$c['validado']) {
            $hay_no_validados = true;
            break;
        }
    }
    ?>
                
        <div class="mb-3 d-flex justify-content-between">
            <div>
                <a class="btn btn-sm btn-success" href="capacitacion_excel.php?<?= http_build_query($_GET) ?>" target="_blank">📥 Exportar Excel</a>
                <?php if ($hay_no_validados === true) {  ?>
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalValidarTodos">🗹 Validar todo el filtro</button>
                <?php }  ?>
            </div>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAgregar" type="button">Agregar</button>
        </div>

        <table class="table table-striped">
            <thead class="thead-light">
            <tr class="bg-primary text-white">
                    <th>Empleado</th>
                    <th>Curso</th>
                    <th>Horas</th>
                    <th>Calificación</th>
                    <th>Registro</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Modalidad</th>
                    <th>Comentarios</th>
                    <th>Archivo</th>
                    <th>Validado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($capacitaciones as $c): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <a class="d-block me-3">
                                <img src="fotos/<?= htmlspecialchars($c['foto'] ?? 'default.png') ?>" width="40" height="40" class="rounded-circle" alt="">
                            </a>

                            <div class="flex-fill">
                                <a class="fw-semibold"><?= $c['empleado'] ?></a>
                                <div class="fs-sm text-muted">
                                <?= htmlspecialchars($c['puesto_nombre'] ?? '') ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td><?= $c['nombre_curso'] ?></td>
                    <td><?= $c['horas'] ?></td>
                    <td><?= $c['calificacion'] ?></td>
                    <td><?= htmlspecialchars(date('d/m/Y', strtotime($c['created_at']))) ?> </td>
                    <td><?= htmlspecialchars(date('d/m/Y', timestamp: strtotime($c['fecha_inicio']))) ?> </td>
                    <td><?= htmlspecialchars(date('d/m/Y', strtotime($c['fecha_fin']))) ?> </td>
                    <td><?= $c['modalidad_texto'] ?></td>
                    <td><?php if ($c['observaciones']  != '') { echo "Si";} else { echo "No";}?></td>
                    <td><?= $c['archivo_pdf'] ? "<a class='btn btn-sm btn-warning' href='capacitacion/{$c['archivo_pdf']}' target='_blank'>Ver</a>" : '' ?></td>
                    <td>
                        <?php if ($c['validado'] == 1): ?>
                            <?php if ((int)($c['contabiliza_horas'] ?? 1) === 1): ?>
                                <span class="badge bg-success">✅ Aceptada (suma horas)</span>
                            <?php else: ?>
                                <span class="badge bg-success">✅ Aceptada (sin sumar horas)</span>
                            <?php endif; ?>
                            <?php if ($role == 3): ?>
                                <a href="capacitacion_validar.php?id=<?= $c['id'] ?>&action=quitar_validacion" class="btn btn-sm btn-outline-danger ms-2">Quitar validación</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="capacitacion_validar.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-success">Aceptar y sumar horas</a>
                            <a href="capacitacion_validar.php?id=<?= $c['id'] ?>&action=aceptar_sin_suma" class="btn btn-sm btn-outline-success ms-1">Aceptar sin sumar</a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalEditar<?= $c['id'] ?>">Editar</button>
                        <?php if ($role == 3): ?>
                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#modalEliminar<?= $c['id'] ?>">Eliminar</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </form>

    <?php include 'capacitacion_agregar.php'; ?>
    <?php include 'capacitacion_editar.php'; ?>
    <?php include 'capacitacion_eliminar.php'; ?>
</div>

<?php if ($total_paginas > 1): ?>
<nav aria-label="Paginación de capacitación" class="mt-3">
    <ul class="pagination pagination-sm mb-0">
        <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
            <a class="page-link" href="admin_capacitacion.php?<?= $query_base ?>pagina=<?= max(1, $pagina - 1) ?>">Anterior</a>
        </li>
        <?php for ($i = max(1, $pagina - 2); $i <= min($total_paginas, $pagina + 2); $i++): ?>
            <li class="page-item <?= ($i === $pagina) ? 'active' : '' ?>">
                <a class="page-link" href="admin_capacitacion.php?<?= $query_base ?>pagina=<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
        <li class="page-item <?= ($pagina >= $total_paginas) ? 'disabled' : '' ?>">
            <a class="page-link" href="admin_capacitacion.php?<?= $query_base ?>pagina=<?= min($total_paginas, $pagina + 1) ?>">Siguiente</a>
        </li>
    </ul>
</nav>
<?php endif; ?>



<!-- Modal Confirmar Validación de Todos -->
<div class="modal fade" id="modalValidarTodos" tabindex="-1" aria-labelledby="modalValidarTodosLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="get" action="capacitacion_validar_todos.php" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalValidarTodosLabel">Validar todos los cursos del filtro</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        ¿Estás seguro de que deseas validar <strong>todos los cursos del filtro actual</strong>?
        <?php foreach ($_GET as $key => $val): ?>
          <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($val) ?>">
        <?php endforeach; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-sm btn-primary">Sí, validar todos</button>
      </div>
    </form>
  </div>
</div>





        </div>
    </div>
</div>


<?php require_once('assets/footer.php'); ?>

</div>
<!-- /inner content -->

</div>
<!-- /main content -->

</div>
<!-- /page content -->


<script>
document.getElementById("checkAll").addEventListener("change", function() {
    const checkboxes = document.querySelectorAll("input[name='seleccionados[]']");
    checkboxes.forEach(cb => cb.checked = this.checked);
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const checkboxes = document.querySelectorAll("input[name='seleccionados[]']");
    const boton = document.getElementById("btnValidarSeleccionados");
    const maestro = document.getElementById("checkAll");

    function actualizarEstadoBoton() {
        let algunoMarcado = false;
        checkboxes.forEach(cb => {
            if (cb.checked) {
                algunoMarcado = true;
            }
        });
        if (boton) {
            boton.disabled = !algunoMarcado;
        }
    }

    checkboxes.forEach(cb => cb.addEventListener("change", actualizarEstadoBoton));
    if (maestro) maestro.addEventListener("change", () => {
        checkboxes.forEach(cb => cb.checked = maestro.checked);
        actualizarEstadoBoton();
    });

    actualizarEstadoBoton();
});
</script>

</body>
</html>
