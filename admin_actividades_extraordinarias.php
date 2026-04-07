<?php
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
$user_id = $_SESSION['user_id']; 

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);
$periodo = $_SESSION['periodo'];


$colaborador_id = $_GET['colaborador_id'] ?? '';
$periodo_actual = $_GET['periodo'] ?? $_SESSION['periodo'];
$unidad_id = $_GET['unidad_id'] ?? '';

// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
if (isset($_POST['editar'])) {
$id = $_POST['id'];
$stmt = $pdo->prepare("UPDATE actividades_extraordinarias SET validado_rh = 1, rechazado_por_rh = 0 WHERE id = ?");
$stmt->execute([$id]);
header("Location: admin_actividades_extraordinarias.php?info=1&colaborador_id=$colaborador_id&periodo=$periodo_actual");
exit();
}
if (isset($_POST['rechazar'])) {
$id = $_POST['id'];
$stmt = $pdo->prepare("UPDATE actividades_extraordinarias SET validado_rh = 0, rechazado_por_rh = 1 WHERE id = ?");
$stmt->execute([$id]);
header("Location: admin_actividades_extraordinarias.php?info=2&colaborador_id=$colaborador_id&periodo=$periodo_actual");
exit();
}
}


$sql = "SELECT a.*, CONCAT(TRIM(us.nombre), ' ', TRIM(us.apellido_paterno), ' ', TRIM(us.apellido_materno)) AS nombre_completo
		FROM actividades_extraordinarias a
		INNER JOIN usuarios us ON us.user_id = a.user_id
		WHERE a.periodo = ?";
$paramsActividades = [$periodo_actual];

if ($colaborador_id != '') {
	$sql .= " AND a.user_id = ?";
	$paramsActividades[] = $colaborador_id;
}
if ($unidad_id !== '') {
	$sql .= " AND us.unidad_id = ?";
	$paramsActividades[] = (int)$unidad_id;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($paramsActividades);
$actividades_extraordinarias = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Consulta usuarios, unidades y periodos
$unidades = $pdo->query("SELECT id, nombre FROM unidades ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$sqlUsuarios = "SELECT user_id, nombre, apellido_paterno, apellido_materno, RFC
				FROM usuarios
				WHERE 1";
$paramsUsuarios = [];
if ($unidad_id !== '') {
	$sqlUsuarios .= " AND unidad_id = ?";
	$paramsUsuarios[] = (int)$unidad_id;
}
$sqlUsuarios .= " ORDER BY nombre ASC";
$stmtUsuarios = $pdo->prepare($sqlUsuarios);
$stmtUsuarios->execute($paramsUsuarios);
$usuarios = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);
$periodos = $pdo->query("SELECT DISTINCT anio FROM periodos ORDER BY anio DESC")->fetchAll();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM calificaciones WHERE user_id = ? AND periodo = ? AND estatus_actividades_extraordinarias = ?");
$stmt->execute([$colaborador_id, $periodo_actual, 0]);
$tiene_actividades = $stmt->fetchColumn();

// Borrar registro y recalcular semáforo/puntaje
if (isset($_GET['borrar']) AND $_GET['borrar'] == 1) { 
    $id = $_GET['id'];
    
    // Obtener user_id y periodo antes de eliminar
    $stmt = $pdo->prepare("SELECT user_id, periodo FROM actividades_extraordinarias WHERE id = ?");
    $stmt->execute([$id]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($registro) {
        $affected_user_id = $registro['user_id'];
        $affected_periodo = $registro['periodo'];
        
        // Eliminar el registro
        $stmt = $pdo->prepare("DELETE FROM actividades_extraordinarias WHERE id = ?");
        $stmt->execute([$id]);
        
        // Recalcular: contar cuántas actividades quedan
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM actividades_extraordinarias WHERE user_id = ? AND periodo = ?");
        $stmt->execute([$affected_user_id, $affected_periodo]);
        $count = $stmt->fetchColumn();
        
        // Actualizar estatus según lo que queda
        if ($count == 0) {
            // Sin registros, resetear estatus a 0 y puntaje a 0
            $stmt = $pdo->prepare("UPDATE calificaciones SET estatus_actividades_extraordinarias = 0, actividades_extraordinarias = 0 WHERE user_id = ? AND periodo = ?");
            $stmt->execute([$affected_user_id, $affected_periodo]);
        } else {
            // Recalcular sumatoria: contar solo las validadas
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM actividades_extraordinarias WHERE user_id = ? AND periodo = ? AND validado = 1");
            $stmt->execute([$affected_user_id, $affected_periodo]);
            $validadas = $stmt->fetchColumn();
            
            // Si ya no hay validadas, cambiar estatus a 1 (capturado/pendiente validación)
            if ($validadas == 0) {
                $stmt = $pdo->prepare("UPDATE calificaciones SET estatus_actividades_extraordinarias = 1, actividades_extraordinarias = 0 WHERE user_id = ? AND periodo = ?");
                $stmt->execute([$affected_user_id, $affected_periodo]);
            } else {
                // Actualizar sumatoria
                $stmt = $pdo->prepare("UPDATE calificaciones SET actividades_extraordinarias = ? WHERE user_id = ? AND periodo = ?");
                $stmt->execute([$validadas, $affected_user_id, $affected_periodo]);
            }
        }
    }

    header("Location: admin_actividades_extraordinarias.php?info=3");
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
								<a href="#" class="breadcrumb-item">Administración</a>
								<span class="breadcrumb-item active">Actividades Extraordinarias</span>
							</div>
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
                            $texto = '✅ Registro guardado correctamente.';
                            $clase_alerta = 'alert-success';
                            break;
						case 2:
							$texto = '❌ Registro rechazado correctamente.';
							$clase_alerta = 'alert-warning';
							break;
                        case 9:
                            $texto = '✏️ Proceso cerrado correctamente.';
                            $clase_alerta = 'alert-info';
                            break;
                        case 3:
                            $texto = '🗑️ Registro eliminado correctamente.';
                            $clase_alerta = 'alert-danger';
                            break;
                            case 4:
                              $texto = '⚠️ El archivo es muy pesado';
                              $clase_alerta = 'alert-warning';
                              break;
                          default:
                            $texto = 'info no reconocido.';
                            $clase_alerta = 'alert-secondary';
                            break;
                    }
                    ?>

                    <div class="alert <?= $clase_alerta ?> border-0 alert-dismissible fade show">
										<span class="fw-semibold"> <?= $texto ?>
										<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
								    </div>

                    <?php endif; ?>


					<!-- /card titles and subtitles -->
					<div class="row">
						<div class="col-lg-12">
							<div class="card">
								<div class="card-header">
									<h6 class="mb-0">Actividades Extraordinarias</h6>
								</div>

									<div class="card-body">
									<p>Evalua.</p>
									</div>
							</div>
            			</div>
            			</div>
					<!-- /card titles and subtitles -->

	<form class="row g-3 mb-3" method="get" action="admin_actividades_extraordinarias.php">
		<div class="col-md-4">
            <label class="form-label">Usuario</label>
			<select name="colaborador_id" class="form-select select">
				<option value="">Todos</option>
                <?php  foreach ($usuarios as $u): ?>
                    <option value="<?= $u['user_id'] ?>" <?= ($colaborador_id == $u['user_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['nombre']." ".$u['apellido_paterno']." ".$u['apellido_materno']." ".$u['RFC']) ?>
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
		<div class="col-md-3">
            <label class="form-label">Periodo</label>
            <select name="periodo" class="form-select">
                <?php foreach ($periodos as $per): ?>
                    <option value="<?= $per['anio'] ?>" <?= ($periodo_actual == $per['anio']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($per['anio']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
		<div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-sm btn-primary me-2">Filtrar</button>
			<a href="admin_actividades_extraordinarias.php" class="btn btn-sm btn-secondary">Limpiar</a>
        </div>
    </form>

					<div class="card">
					<div class="table-responsive">
						<table class="table table-xl">
							<thead class="thead-light">
							<tr class="bg-primary text-white">
									<th>ID</th>
									<th>Nombre</th>
									<th>Descipción de la Aportación</th>
									<th style="width:15%">Validado</th>
									<th style="width:15%">Validado RH</th>
									<th style="width:15%">Archivo</th>
									<th style="width:15%">Acciones</th>
								</tr>
							</thead>
							<tbody>
							<?php if (count($actividades_extraordinarias) === 0): ?>
								<tr><td colspan="7">No se encuentran registros en el periodo seleccionado.</td></tr>
							<?php else: ?>
								<?php foreach ($actividades_extraordinarias as $u): ?>
								<tr>
									<td><?= $u['id'] ?></td>
									<td><?= htmlspecialchars($u['nombre_completo']) ?></td>
									<td><?= htmlspecialchars($u['descripcion']) ?></td>
									<td><?php if ($u['validado'] == 1){ echo "<i class='ph-check-circle fs-base lh-base align-top text-success me-1'></i><span class='text-success me-1'>Validada</span>";} else { echo  "<i class='ph-circle-dashed fs-base lh-base align-top text-danger me-1'></i> <span class='text-danger me-1'>Sin validar</span>";} ?></td>
									<td>
									<?php
									if ($u['validado_rh'] == 1) {
										echo "<i class='ph-check-circle fs-base lh-base align-top text-success me-1'></i><span class='text-success me-1'>Validada</span>";
									} elseif ((int)($u['rechazado_por_rh'] ?? 0) === 1) {
										echo "<i class='ph-x-circle fs-base lh-base align-top text-warning me-1'></i><span class='text-warning me-1'>Rechazada</span>";
									} else {
										echo  "<i class='ph-circle-dashed fs-base lh-base align-top text-danger me-1'></i> <span class='text-danger me-1'>Sin validar</span>";
									}
									?>
									</td>
									<td><?php if ($u['archivo_pdf']): ?><a href="actividades/<?= $u['archivo_pdf'] ?>" target="_blank" class="btn btn-sm btn-warning">Ver</a><?php else: ?><span class="text-muted">No se cargó archivo</span><?php endif; ?></td> 
									<td>


										<?php if ($u['validado_rh'] == 0 && (int)($u['rechazado_por_rh'] ?? 0) === 0) { ?>
											<button class="btn btn-success btn-sm btn-editar" data-bs-toggle="modal" data-bs-target="#modalEditar<?= $u['id'] ?>">Validar</button>
											<button class="btn btn-warning btn-sm btn-editar" data-bs-toggle="modal" data-bs-target="#modalRechazar<?= $u['id'] ?>">Rechazar</button>
										<?php } else if ((int)($u['rechazado_por_rh'] ?? 0) === 1) { ?>
											<button class="btn btn-warning btn-sm btn-editar">Rechazada</button>
										<?php } else { ?>
											<button class="btn btn-success btn-sm btn-editar">Validada</button>
										<?php }  ?>

										<button class="btn btn-danger btn-sm btn-editar" data-bs-toggle="modal" data-bs-target="#modalEliminar<?= $u['id'] ?>">Eliminar</button>

									</td>
								</tr>

				<!-- Modal  Eliminación -->
				<div class="modal fade" id="modalEditar<?= $u['id'] ?>" tabindex="-1">
				<div class="modal-dialog modal-lg">
					<div class="modal-content">
					<form method="post"  enctype="multipart/form-data">
					<input type="hidden" name="id" id="idEditar">
					<div class="modal-header">
						<h5 class="modal-title">Validar Actividad Extraordinaria</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						¿Estás seguro de que deseas validar la Actividad: <br/>
						<strong><?= $u['descripcion'] ?></strong>?
					</div>
					<div class="modal-footer">
						<button name="editar" class="btn btn-sm btn-success">Validar</button>
						<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
						<input type="hidden" name="id" value="<?= $u['id'] ?>"></input>
					</div>
					</form>
					</div>
				</div>
				</div>
				<!-- /Modal  Eliminación -->


				<!-- Modal Rechazar -->
				<div class="modal fade" id="modalRechazar<?= $u['id'] ?>" tabindex="-1">
				<div class="modal-dialog modal-lg">
					<div class="modal-content">
					<form method="post"  enctype="multipart/form-data">
					<input type="hidden" name="id" id="idRechazar">
					<div class="modal-header bg-warning text-dark">
						<h5 class="modal-title">Rechazar Actividad Extraordinaria</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						¿Estás seguro de que deseas rechazar la Actividad: <br/>
						<strong><?= $u['descripcion'] ?></strong>?
					</div>
					<div class="modal-footer">
						<button name="rechazar" class="btn btn-sm btn-warning">Rechazar</button>
						<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
						<input type="hidden" name="id" value="<?= $u['id'] ?>"></input>
					</div>
					</form>
					</div>
				</div>
				</div>
				<!-- /Modal Rechazar -->


				<!-- Modal  Eliminación -->
				<div class="modal fade" id="modalEliminar<?= $u['id'] ?>" tabindex="-1">
				<div class="modal-dialog modal-lg">
					<div class="modal-content">
					<input type="hidden" name="id" id="idEditar">
					<div class="modal-header bg-danger text-white">
						<h5 class="modal-title">Eliminar Actividad Extraordinaria</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						¿Estás seguro de que deseas eliminar la Actividad Extraordinaria: <br/>
						<strong><?= $u['descripcion'] ?></strong>?
					</div>
					<div class="modal-footer">
              			<a href="admin_actividades_extraordinarias.php?id=<?= $u['id'] ?>&borrar=1" class="btn btn-sm btn-danger">Eliminar</a>
						<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
					</div>
					</div>
				</div>
				</div>
				<!-- /Modal  Eliminación -->


				<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
						</div>
						</div>
					<!-- /basic table -->


				</div>
				<!-- /content area -->

				<?php require_once('assets/footer.php'); ?>

			</div>
			<!-- /inner content -->

		</div>
		<!-- /main content -->

	</div>
	<!-- /page content -->

</body>
</html>
