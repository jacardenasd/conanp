<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';
checkLogin(2);

$unidad_id_default = $_SESSION['unidad_id'] ?? '';
$periodo_default = $_SESSION['periodo'] ?? '';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');

// Filtros
$unidad_id = $_GET['unidad_id'] ?? '';
$periodo = $_GET['periodo'] ?? '';
$periodo_activo = (int)($_SESSION['periodo'] ?? 0);

function resetear_estatus_colectivas_periodo_activo(PDO $pdo, int $unidad_id, int $periodo_objetivo, int $periodo_activo): void {
    if ($periodo_activo <= 0 || $periodo_objetivo !== $periodo_activo) {
        return;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM metas_colectivas WHERE unidad_id = ? AND periodo = ?");
    $stmt->execute([$unidad_id, $periodo_objetivo]);
    $total_metas = (int)$stmt->fetchColumn();

    $nuevo_estatus = ($total_metas > 0) ? 1 : 0;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM calificaciones_colectivas WHERE unidad_id = ? AND periodo = ?");
    $stmt->execute([$unidad_id, $periodo_objetivo]);
    $existe_calificacion = (int)$stmt->fetchColumn();

    if ($existe_calificacion > 0) {
        $stmt = $pdo->prepare("UPDATE calificaciones_colectivas SET estatus = ?, resultado = 0 WHERE unidad_id = ? AND periodo = ?");
        $stmt->execute([$nuevo_estatus, $unidad_id, $periodo_objetivo]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO calificaciones_colectivas (unidad_id, periodo, estatus, resultado) VALUES (?, ?, ?, 0)");
        $stmt->execute([$unidad_id, $periodo_objetivo, $nuevo_estatus]);
    }
}

// Consulta de unidades y periodos para los filtros
$unidades = $pdo->query("SELECT id, nombre FROM unidades ORDER BY nombre ASC")->fetchAll();
$periodos = $pdo->query("SELECT DISTINCT anio FROM periodos ORDER BY anio DESC")->fetchAll();

// Consulta principal
$sql = "SELECT mc.*, u.nombre AS unidad_nombre 
        FROM metas_colectivas mc
        LEFT JOIN unidades u ON mc.unidad_id = u.id
        WHERE 1";

$params = [];
if (!empty($unidad_id)) {
    $sql .= " AND mc.unidad_id = :unidad_id";
    $params['unidad_id'] = $unidad_id;
}

if (!empty($periodo)) {
    $sql .= " AND mc.periodo = :periodo";
    $params['periodo'] = $periodo;
}

$sql .= " ORDER BY mc.periodo DESC, mc.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$metas = $stmt->fetchAll();

if (isset($_POST['eliminar_confirmado']) && isset($_POST['id_eliminar'])) {
    $meta_id_eliminar = (int)$_POST['id_eliminar'];

    $stmt = $pdo->prepare("SELECT unidad_id, periodo FROM metas_colectivas WHERE id = ? LIMIT 1");
    $stmt->execute([$meta_id_eliminar]);
    $meta_eliminar = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("DELETE FROM metas_colectivas WHERE id = ?");
    $stmt->execute([$meta_id_eliminar]);

    if ($meta_eliminar) {
        resetear_estatus_colectivas_periodo_activo(
            $pdo,
            (int)$meta_eliminar['unidad_id'],
            (int)$meta_eliminar['periodo'],
            $periodo_activo
        );
    }

    $redir_unidad = $unidad_id ?: ($meta_eliminar['unidad_id'] ?? '');
    $redir_periodo = $periodo ?: ($meta_eliminar['periodo'] ?? '');
    header("Location: admin_metas_colectivas_detalle.php?info=3&unidad_id=" . urlencode((string)$redir_unidad) . "&periodo=" . urlencode((string)$redir_periodo));
    exit;
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
								<a href="index.php" class="breadcrumb-item"><i class="ph-house"></i></a>
								<a href="#" class="breadcrumb-item">Administracion</a>
								<span class="breadcrumb-item active">Metas Colectivas</span>
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
							<h5 class="mb-0">Administración de Metas Colectivas</h5>
						</div>

						<div class="card-body">
							Utiliza el filtro para mostrar resultados.<br/>
                            <p>&nbsp;</p>


    <form class="row g-3 mb-3" method="get">
        <div class="col-md-4">
            <label for="unidad_id" class="form-label">Unidad</label>
            <select name="unidad_id" id="unidad_id" class="form-select">
            <option value="">Todos</option>
            <?php foreach ($unidades as $unidad): ?>
                    <option value="<?= $unidad['id'] ?>" <?= ($unidad_id == $unidad['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($unidad['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
        <label for="periodo" class="form-label">Periodo</label>
            <select name="periodo" id="periodo" class="form-select">
            <option value="">Todos</option>
            <?php foreach ($periodos as $p): ?>
                    <option value="<?= $p['anio'] ?>" <?= ($periodo == $p['anio']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['anio']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <button type="submit" class="btn btn-sm btn-primary me-2">Filtrar</button>
            <a href="admin_metas_colectivas_detalle.php" class="btn btn-sm btn-secondary">Limpiar</a>
        </div>
    </form>

    <div class="table-responsive">
    <table class="table datatable-pagination">
            <thead class="thead-light">
            <tr class="bg-primary text-white">
                    <th>Meta Colectiva</th>
                    <th>Unidad de Medida</th>
                    <th>Ponderación</th>
                    <th>Estatus</th>
                    <th>Resultado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($metas as $meta): ?>
                    <tr>
                    <td><?= htmlspecialchars($meta['indicador']) ?></td>
                    <td><?= htmlspecialchars($meta['unidad']) ?></td>
                        <td><?= htmlspecialchars($meta['ponderacion']) ?>%</td>
                        <td><?= ($meta['estatus'] == 'evaluado') ? 'Evaluado' : 'No evaluado' ?></td>
                        <td><?= is_numeric($meta['resultado']) ? number_format($meta['resultado'], 1)."%" : '-' ?></td>
                        <td>
                        <a href="meta_editar_colectivas_admin.php?id=<?= $meta['id'] ?>&unidad_id=<?= $meta['unidad_id'] ?>&periodo=<?= $meta['periodo'] ?>" class="btn btn-sm btn-primary">Editar</a>
                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#confirmarEliminar<?= $meta['id'] ?>">Eliminar</button>
                        </td>
                    </tr>

                    <!-- Modal Confirmación de Eliminación -->
                    <div class="modal fade" id="modalEliminar<?= $meta['id'] ?>" tabindex="-1" aria-labelledby="modalLabel<?= $meta['id'] ?>" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title" id="modalLabel<?= $meta['id'] ?>">Confirmar Eliminación</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                </div>
                                <div class="modal-body">
                                    ¿Estás seguro que deseas eliminar esta meta colectiva?
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <a href="metas_colectivas_eliminar.php?id=<?= $meta['id'] ?>" class="btn btn-danger">Eliminar</a>
                                </div>
                            </div>
                        </div>
                    </div>

<!-- Modal  Eliminación -->
<div class="modal fade" id="confirmarEliminar<?= $meta['id'] ?>" tabindex="-1">
<div class="modal-dialog modal-lg">
	<div class="modal-content">
    <form method="post">
    <div class="modal-header bg-danger text-white border-0">
        <h5 class="modal-title">Confirmación de Borrado</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        ¿Estás seguro de que deseas eliminar la Meta:<br/> <strong><?= $meta['indicador'] ?></strong>?
      </div>
      <div class="modal-footer">
        <button name="eliminar_confirmado" class="btn btn-sm btn-danger">Eliminar</button>
		<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
		<input type="hidden" name="id_eliminar" value="<?= $meta['id'] ?>"></input>
      </div>
    </form>
  </div>
</div>
</div>
<!-- /Modal  Eliminación -->

<?php endforeach; ?>
            </tbody>
        </table>

        <p>&nbsp;</p>
        <a href="metas_colectivas_agregar.php" class="btn btn-sm btn-success">Agregar</a>
        <a href="admin_metas_colectivas.php" class="btn btn-sm btn-secondary">Regresar</a>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        $('#tablaMetas').DataTable();
    });
</script>

                        
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
