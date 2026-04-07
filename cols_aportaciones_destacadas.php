<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin();

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');
$user_id = $_SESSION['user_id']; 

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);
$periodo = $_GET['periodo'] ?? $_SESSION['periodo'];
$periodo = (int)$periodo;
$colaborador_id = $_GET['colaborador_id'];

// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['editar'])) {
		$stmt = $pdo->prepare("SELECT estatus_aportaciones_destacadas FROM calificaciones WHERE user_id = ? AND periodo = ?");
		$stmt->execute([$colaborador_id, $periodo]);
		$estatus_cierre = (int)($stmt->fetchColumn() ?? 0);
		if ($estatus_cierre >= 1) {
			header("Location: cols_aportaciones_destacadas.php?info=10&colaborador_id=$colaborador_id&periodo=$periodo");
			exit();
		}

        $id = $_POST['id'];
        $comentarios = trim($_POST['comentarios']); // Recupera el texto del textarea

        // Actualiza estatus y comentarios al mismo tiempo
		$stmt = $pdo->prepare("UPDATE aportaciones_destacadas SET validado = 1, estatus = 2, comentarios = ? WHERE id = ?");
        $stmt->execute([$comentarios, $id]);

		header("Location: cols_aportaciones_destacadas.php?info=1&colaborador_id=$colaborador_id&periodo=$periodo");
        exit();
    }
}

//solo muestra aportaciones destacadas que tienen archivo de comprobación cargado por el usuario
$stmt = $pdo->prepare("SELECT * FROM aportaciones_destacadas where user_id = ? AND periodo = ? ORDER BY id");
$stmt->execute([$colaborador_id, $periodo]);
$aportaciones_destacadas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT estatus_aportaciones_destacadas FROM calificaciones WHERE user_id = ? AND periodo = ?");
$stmt->execute([$colaborador_id, $periodo]);
$estatus_cierre = (int)($stmt->fetchColumn() ?? 0);
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
								<a href="#" class="breadcrumb-item">Mi evaluación</a>
								<span class="breadcrumb-item active">Aportaciones Destacadas</span>
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
                        case 9:
                            $texto = '✏️ Proceso cerrado correctamente.';
                            $clase_alerta = 'alert-info';
                            break;
						case 10:
							$texto = '🔒 Proceso cerrado. No se permiten cambios.';
							$clase_alerta = 'alert-warning';
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



					<div class="card mb-4 shadow-sm" style="max-width: 500px;">
					<div class="card-body d-flex">
						<img src="assets/images/avatars/E-commerce-2.png" class="rounded-circle me-3" width="60">
						<div>
						<h6 class="card-title mb-1">📌 Guía del Sistema</h6>
						<p class="card-text small mb-0">Aquí podrás cargar tus Aportaciones Destacadas.</p>
						</div>
					</div>
					</div>	


					<!-- /card titles and subtitles -->
					<div class="row">
						<div class="col-lg-10">
							<div class="card">
								<div class="card-header">
									<h6 class="mb-0">Aportaciones destacadas</h6>
								</div>

									<div class="card-body">
									<p>Son las acciones realizadas por iniciativa de la persona Servidora Pública Evaluada, cuyos resultados puedan ser verificados y documentados; que contribuyan a mejorar el desempeño de sus funciones o que impliquen una contribución al desarrollo de capital humano en otras personas servidoras públicas, en su caso, contribuyan en la mejora de la CONANP o aporten beneficios a la población. Deben cumplir con cada uno de los siguientes requisitos:
										<ol type="A">
										<li>Que el(la) Servidor(a) Público(a) Evaluado(a)(a) haya alcanzado una calificación satisfactoria en el cumplimiento individual de sus metas.</li>
										<li>Que la aportación destacada no sea una actividad o acción contemplada en algún otro rubro de la evaluación del desempeño.</li>
										<li>Que se trate de una acción voluntaria no contemplada inicialmente en los planes y programas de trabajo, ni solicitada expresamente por los(as) superiores del(la) Evaluado(a)</li>
										<li>Que la aportación haya facilitado, mejorado, optimizado o fortalecido las funciones de los compañeros de trabajo, el logro de las metas estratégicas o haya aportado beneficio a la ciudadanía.</li>
										<li>La aportación destacada no debió generar presiones presupuestales adicionales, ni perjudicar o afectar negativamente los objetivos de otra área/UR/Dirección Regional/Dirección General.</li>
										<li>Que haya sido en su momento, consultada e informada oportunamente a los superiores y aprobada por ellos.</li>
										<li>Que se cuente con evidencia documental para su verificación.</li>
										</ol>
										
										Recuerda que, al terminar el proceso de aportaciones destacadas, deberás dar clic en el botón de "terminar". Una vez que hayas terminado, ya no se podrá editar la información.</p>
									</div>
							</div>
            			</div>

						<div class="col-lg-2">
							<div class="card">
              					<div class="card-header">
									<h6 class="mb-0">Cierre de Captura</h6>
								</div>
                				<div class="card-body text-center">
									<p>Da clic para terminar el proceso de validación.</p>
								<?php if ($estatus_periodo === "Evaluación" AND $estatus_cierre == 0) { ?>
									<button class="btn btn-indigo btn-sm" data-bs-toggle="modal" data-bs-target="#confirmarcierre"><i class="ph-lock-key me-2"></i>Terminar</button>
								<?php } ?>
								</div>
							</div>
            			</div>
					</div>
					<!-- /card titles and subtitles -->


					<div class="card">
					<div class="table-responsive">
						<table class="table table-xl">
							<thead class="thead-light">
							<tr class="bg-info text-white">
									<th>ID</th>
									<th>Descipción de la Aportación</th>
									<th style="width:15%">Estatus</th>
									<th style="width:15%">Archivo</th>
									<th style="width:15%">Acciones</th>
								</tr>
							</thead>
							<tbody>
							<?php if (count($aportaciones_destacadas) === 0): ?>
								<tr><td colspan="4">No se encuentran registros en el periodo seleccionado.</td></tr>
							<?php else: ?>
								<?php foreach ($aportaciones_destacadas as $u): ?>
								<tr>
									<td><?= $u['id'] ?></td>
									<td><?= htmlspecialchars($u['descripcion']) ?></td>
									<td><?php if ($u['validado'] == 1){ echo "<i class='ph-check-circle fs-base lh-base align-top text-success me-1'></i><span class='text-success me-1'>Validada</span>";} else { echo  "<i class='ph-circle-dashed fs-base lh-base align-top text-danger me-1'></i> <span class='text-danger me-1'>Sin validar</span>";} ?></td>
									<td><?php if ($u['archivo_pdf']): ?><a href="aportaciones/<?= $u['archivo_pdf'] ?>" target="_blank" class="btn btn-sm btn-warning">Ver</a><?php else: ?><span class="text-muted">No se cargó archivo</span><?php endif; ?></td> 
									<td>

									<?php if ($estatus_periodo === "Evaluación") { // primero ver si el periodo esta el evaluar ?>
									<?php if ($estatus_cierre >= 1) { ?>
										<span class="badge bg-secondary">Proceso cerrado</span>
									<?php } else { ?>
									<?php if ($u['validado'] == 0 ) { ?>
										<button class="btn btn-success btn-sm btn-editar" data-bs-toggle="modal" data-bs-target="#modalEditar<?= $u['id'] ?>">Validar</button>
									<?php } else { ?>
										<span class="badge bg-success">Validada</span>
									<?php } ?>
									<?php } ?>
								<?php } else { ?>
									<span class="text-muted">Periodo cerrado</span>
								<?php } ?>
								</td>
							</tr>

				<!-- Modal  Eliminación -->
				<div class="modal fade" id="modalEditar<?= $u['id'] ?>" tabindex="-1">
				<div class="modal-dialog modal-lg">
					<div class="modal-content">
					<form method="post"  enctype="multipart/form-data">
					<input type="hidden" name="id" id="idEditar">
					<div class="modal-header">
						<h5 class="modal-title">Validar Aportación Destacada</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						¿Estás seguro de que deseas validar la Aportación: <br/>
						<strong><?=  $extracto = substr(strip_tags($u['descripcion']), 0, 100) . '...'; ?></strong>?


						<p>&nbsp;</p>
						<label for="comentarios">Comentarios:</label><br>
						<textarea name="comentarios" id="comentarios" rows="2" cols="50"  class="form-control"></textarea><br>						

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



								<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
						</div>
						</div>
					<!-- /basic table -->

                    <a href="mis_colaboradores.php" class="btn btn-secondary btn-sm">Regresar</a>

				</div>
				<!-- /content area -->

				<?php require_once('assets/footer.php'); ?>

			</div>
			<!-- /inner content -->

		</div>
		<!-- /main content -->

	</div>
	<!-- /page content -->

<!-- Modal  Eliminación -->
<div class="modal fade" id="confirmarcierre" tabindex="-1">
<div class="modal-dialog">
	<div class="modal-content">
  <form method="post" action="cierre_periodo_aportaciones_destacadas.php">
    <div class="modal-header">
        <h5 class="modal-title">Confirmación de Cierre</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        ¿Estás seguro de que quieres cerrar la evaluación?
      </div>
      <div class="modal-footer">
        <button type="submit" name="cierre_periodo" class="btn btn-sm btn-success">Terminar</button>
		<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <input type="hidden" name="colaborador_id" value="<?= $colaborador_id ?>"></input>
    <input type="hidden" name="periodo" value="<?= $periodo ?>"></input>
      </div>
    </form>
  </div>
</div>
</div>
<!-- /Modal  Eliminación -->

</body>
</html>
