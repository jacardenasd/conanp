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
$permite_metas_colectivas = $_SESSION['permite_metas_colectivas'];

// VALIDACIÓN: Solo usuarios con permisos pueden acceder a metas colectivas
if ($permite_metas_colectivas != 1) {
    header('Location: index.php');
    exit();
}

$unidad_id = $_SESSION['unidad_id'];
$periodo = $_SESSION['periodo'];


// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();


// Obtener metas_colectivas de la UR
$stmt = $pdo->prepare("SELECT * FROM metas_colectivas WHERE unidad_id = ? AND periodo = ?");
$stmt->execute([$unidad_id, $periodo]);
$metas_colectivas = $stmt->fetchAll();
$totalmetas_colectivas = count($metas_colectivas);  

// Validar suma de ponderación
$stmt = $pdo->prepare("SELECT SUM(ponderacion) FROM metas_colectivas WHERE unidad_id = ? AND periodo = ?");
$stmt->execute([$unidad_id, $periodo]);
$suma = $stmt->fetchColumn(); 

//tiene metas
$stmt = $pdo->prepare("SELECT COUNT(*) FROM metas_colectivas WHERE unidad_id = ? AND periodo = ?");
$stmt->execute([$unidad_id, $periodo]);
$tiene_metas = $stmt->fetchColumn() > 0;

$stmt = $pdo->prepare("SELECT * FROM calificaciones_colectivas WHERE unidad_id = ? AND periodo = ?");
$stmt->execute([$unidad_id, $periodo]);
$calificacion = $stmt->fetch(PDO::FETCH_ASSOC);

if ($calificacion AND isset($calificacion)) 
{
	$estatus_colectivas = $calificacion['estatus'];
	$archivo_ya_cargado = !empty($calificacion['archivo_colectivas']);
} else {
	$estatus_colectivas = 0;
	$archivo_ya_cargado = false;
}

if ($estatus_colectivas == 0){ $estatus_metas_col = 'Sin Captura';}
else if ($estatus_colectivas == 1){ $estatus_metas_col = 'Capturadas';}
else if ($estatus_colectivas == 2){ $estatus_metas_col = 'Calificadas';}
else { $estatus_colectivas = '-';}

$stmt = $pdo->prepare("SELECT estatus FROM calificaciones_colectivas WHERE unidad_id = ? AND periodo = ?");
$stmt->execute([$unidad_id, $periodo]);
$resultado = $stmt->fetch(PDO::FETCH_ASSOC);

if ($resultado) {$estatus_metas = $resultado['estatus'];} else {$estatus_metas = 0;}


// Cambio Febrero 2026 - A3: Procesar "Finalizar carga" de metas colectivas
if (isset($_POST['finalizar_carga_colectivas'])) {
	// Verificar que hay PDF cargado y metas capturadas
	$stmt_check = $pdo->prepare("SELECT archivo_colectivas FROM calificaciones_colectivas WHERE unidad_id = ? AND periodo = ?");
	$stmt_check->execute([$unidad_id, $periodo]);
	$archivo = $stmt_check->fetchColumn();
	
	if (!empty($archivo) && $tiene_metas && $suma == 100) {
		// Marcar como finalizado en BD
		$stmt_finalizar = $pdo->prepare("
			UPDATE calificaciones_colectivas 
			SET finalizado_colectivas = 1, 
			    fecha_finalizacion_colectivas = NOW(),
			    usuario_finalizacion_id = ?
			WHERE unidad_id = ? AND periodo = ?
		");
		$stmt_finalizar->execute([$user_id, $unidad_id, $periodo]);
		
		header("Location: mis_metas_colectivas.php?info=6");
		exit;
	} else {
		header("Location: mis_metas_colectivas.php?info=7");
		exit;
	}
}

// Verificar si ya están finalizadas
$stmt_finalizado = $pdo->prepare("SELECT finalizado_colectivas FROM calificaciones_colectivas WHERE unidad_id = ? AND periodo = ?");
$stmt_finalizado->execute([$unidad_id, $periodo]);
$finalizado_colectivas = $stmt_finalizado->fetchColumn() ?? 0;


// Procesar eliminación de archivo PDF si se confirma vía GET
if (isset($_GET['eliminar_pdf_confirmado']) && $_GET['eliminar_pdf_confirmado'] == 1) {
  $stmt = $pdo->prepare("UPDATE calificaciones_colectivas SET archivo_colectivas = NULL, fecha_archivo_colectivas = NULL WHERE unidad_id = ? AND periodo = ?");
  $stmt->execute([$unidad_id, $periodo]); 
  header("Location: mis_metas_colectivas.php?info=5");
  exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_pdf'])) {
	$user_id = $_SESSION['user_id']; 

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);

	// Verificar si ya existe un archivo cargado para este periodo
	$stmt_check = $pdo->prepare("SELECT archivo_colectivas FROM calificaciones_colectivas WHERE unidad_id = ? AND periodo = ?");
	$stmt_check->execute([$unidad_id, $periodo]);
	$archivo_existente = $stmt_check->fetchColumn();
	
	if (!empty($archivo_existente)) {
		header("Location: mis_metas_colectivas.php?info=5");
		exit;
	}
  
	if (isset($_FILES['archivo_colectivas']) && $_FILES['archivo_colectivas']['error'] === UPLOAD_ERR_OK) {
	  $nombre_original = $_FILES['archivo_colectivas']['name'];
	  $nombre_final = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $nombre_original);
	  $ruta_destino = 'firmas_colectivas/' . $nombre_final;
  
	  if (!is_dir('firmas_colectivas')) {
		mkdir('firmas_colectivas', 0755, true);
	  }
  
	  move_uploaded_file($_FILES['archivo_colectivas']['tmp_name'], $ruta_destino);
  
	  $fecha_carga = date('Y-m-d H:i:s');
	  $stmt = $pdo->prepare("UPDATE calificaciones_colectivas SET archivo_colectivas = ?, fecha_archivo_colectivas = ? WHERE unidad_id = ? AND periodo = ?");
	  $stmt->execute([$nombre_final, $fecha_carga, $unidad_id, $periodo]);
  
	  header("Location: mis_metas_colectivas.php?info=1");
	  exit;
  
	} else {
  
	  header("Location: mis_metas_colectivas.php?info=4");
	  exit;
  
	}
  }

$archivos = $pdo->query("SELECT * FROM archivos WHERE tipo = 2 ORDER BY fecha_subida DESC")->fetchAll();
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
								<a href="index.php" class="breadcrumb-item"><i class="ph-house"></i></a>
								<span class="breadcrumb-item active">Metas Colectivas  <?= $periodo ?></span>
							</div>
						</div>

					</div>
				</div>
				<!-- /page header -->

				<!-- Content area -->
				<div class="content">

                <?php if (isset($_GET['info'])): ?>
                    <?php
                    $clase_alerta = 'alert-success'; 

                    switch ($_GET['info']) {
                        case 1:
                            $texto = '✅ Documento guardado correctamente.';
                            $clase_alerta = 'alert-success';
                            break;
                        case 2:
                            $texto = '✏️ Meta actualizada correctamente.';
                            $clase_alerta = 'alert-info';
                            break;
                        case 3:
                            $texto = '🗑️ Meta eliminado correctamente.';
                            $clase_alerta = 'alert-danger';
                            break;
                        case 4:
                              $texto = '⚠️ El archivo es muy pesado';
                              $clase_alerta = 'alert-warning';
                              break;
                        case 5:
                              $texto = '🔒 Ya existe un archivo de Metas Colectivas cargado para este periodo. No se permite cargar otro archivo.';
                              $clase_alerta = 'alert-warning';
                              break;
                        case 6:
                              $texto = '✅ Carga de metas finalizada. La edición ha sido bloqueada.';
                              $clase_alerta = 'alert-success';
                              break;
                        case 7:
                              $texto = '⚠️ Para finalizar la carga debes tener el PDF cargado y que la ponderación sume 100%.';
                              $clase_alerta = 'alert-warning';
                              break;
                        case 9:
                              $texto = 'Periodo cerrado correctamente';
                              $clase_alerta = 'alert-success';
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


  
            <div class="d-flex align-items-start mb-4">
							<div class="bg-light border p-3 rounded shadow-sm flex-fill">
								Bienvenido a esta sección dónde podrás cargar y consultar las Metas Colectivas de tu UR.
							</div>
							<img src="assets/images/avatars/Guacamole.png" alt="Guía" class="rounded-circle mr-3" width="70" height="70">
						</div>


						<div class="card">
  <div class="card-header">
    <h5 class="mb-0 fw-semibold text-primary">
      <i class="ph-target me-2 text-secondary"></i>Metas Colectivas
    </h5>
  </div>

  <div class="card-body">
    <p class="mb-4">
      Las metas de la Unidad Administrativa deben ser <strong>observables, medibles y realistas</strong>, y reflejar un resultado específico del desempeño.<br>
      No podrás concluir el proceso de captura hasta quese haya cargado el <strong>formato firmado</strong>.
    </p>

    <div class="d-flex align-items-start p-3 bg-light rounded-3 border-start border-success border-4 mb-3">
      <i class="ph-lifebuoy text-success fs-3 me-3"></i>
      <div class="flex-grow-1">
        En la sección de <a href="archivos_descargar.php" class="text-decoration-underline">documentación</a> puedes consultar diversos archivos y material de apoyo.
      </div>
    </div>
  </div>
</div>


          <!-- Vertical cards -->
					<div class="row">

					<?php if ($permite_metas_colectivas === 1) { ?>

						<div class="col-lg-3">
							<div class="card">
								<div class="card-img-actions mx-1 mt-1">
										<img src="assets/images/demo/flat/5.png" class="img-fluid card-img" alt="">
									</a>
								</div>

									<div class="card-body">
									<div class="mb-3">
										<h6 class="d-flex flex-nowrap my-1">
											<a  class="me-2">
										<?php if ($estatus_periodo === 'Captura') {?>Captura
										<?php } elseif ($estatus_periodo === 'Evaluación') {?>Evaluación
										<?php } ?> de Metas Colectivas</a>
										</h6>
									</div>
									<p>Al seleccionar esta opción, se desprenderá una sección para registrar las Metas Colectivas.</p>
									<p>Estatus actual: <span class="badge bg-success ms-auto"><?php echo $estatus_metas_col; ?></span></p>
								</div>

								<div class="card-footer d-sm-flex justify-content-sm-between align-items-sm-center">
								<?php if ($estatus_periodo === 'Captura') {?>
									<a href="metas_colectivas.php" class="btn btn-sm btn-primary">Capturar</a>
								<?php } elseif ($estatus_periodo === 'Evaluación') {?>
									<a href="metas_colectivas.php" class="btn btn-sm btn-primary">Evaluar</a>
								<?php } elseif ($estatus_periodo === 'Cerrado') {?>
								Periodo Cerrado
								<?php } ?>
								</div>

							</div>
						</div>


						<div class="col-lg-3">
							<div class="card">
								<div class="card-img-actions mx-1 mt-1">
										<img src="assets/images/demo/flat/6.png" class="img-fluid card-img" alt="">
									</a>
								</div>

								<div class="card-body">
									<div class="mb-3">
										<h6 class="d-flex flex-nowrap my-1">
											<a class="me-2">Descarga de Metas Colectivas</a>
										</h6>
									</div>
									<p>Da clic en el botón para  descargar en Excel las Metas Colectivas en el formato correspondiente para firma.</p>
								</div>
								<div class="card-footer d-sm-flex justify-content-sm-between align-items-sm-center">

									<?php if ($estatus_metas > 0): ?>
                  					<a href="generar_excel_colectivas.php?unidad_id=<?= $unidad_id ?>&periodo=<?= $periodo ?>" class="btn btn-sm btn-danger">Descargar</a>
									  <?php else: ?>
									⚠️ Aún no tienes metas capturadas para este periodo.
									<?php endif; ?>
								</div>
							</div>
						</div>

           				<div class="col-lg-3">
							<div class="card">
								<div class="card-img-actions mx-1 mt-1">
										<img src="assets/images/demo/flat/7.png" class="img-fluid card-img" alt="">
									</a>
								</div>

                          <div class="card-body">
									<div class="mb-3">
										<h6 class="d-flex flex-nowrap my-1">
											<a  class="me-2">Carga de Metas Colectivas Firmadas</a>
										</h6>
									</div>
									<p>Recuerda que debes cargar el formato de Metas Colectivas firmadas por el Responsable de la UR (en formato PDF).</p>
									<p>Estatus actual:
										<?php if ($archivo_ya_cargado): ?>
											<span class="badge bg-success ms-auto">Cargado</span>
										<?php else: ?>
											<span class="badge bg-danger ms-auto">Sin Cargar</span>
										<?php endif; ?>
									</p>
									<p>Estatus actual:
														<?php if (!empty($calificacion['archivo_colectivas'])): ?>
															<span class="badge bg-success ms-auto">Cargado</span>
														<?php else: ?>
															<span class="badge bg-warning ms-auto">Pendiente</span>
														<?php endif; ?>
									</p>
								</div>
								<div class="card-footer d-sm-flex justify-content-sm-between align-items-sm-center">

								<?php if (!empty($calificacion['archivo_colectivas'])): ?>
										<?php if ($finalizado_colectivas == 1): ?>
											<!-- Ya finalizado - solo mostrar estado -->
											<span class="badge bg-info">✓ Carga Finalizada</span>
										<?php else: ?>
											<!-- PDF cargado pero no finalizado - mostrar botones -->
											<a href="firmas_colectivas/<?= $calificacion['archivo_colectivas'] ?>" class="btn btn-sm btn-success" target="_blank">Descargar</a>
											<button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#modalBorrarPDFColectivas">Borrar</button>
											<?php if ($suma == 100): ?>
												<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalFinalizarCargaColectivas">Finalizar carga</button>
											<?php endif; ?>
										<?php endif; ?>
									<?php else: ?>

										<?php if ($estatus_metas > 0): ?>
											<button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalSubirPDF">Cargar</button>
										<?php else: ?>
										⚠️ Aún no tienes metas capturadas para este periodo.
										<?php endif; ?>
										
									<?php endif; ?>
								</div>
							</div>
						</div>

						<?php } ?>

						<?php if ($permite_metas_colectivas === 1) { ?>
						<div class="col-lg-3">
							<div class="card">
								<div class="card-img-actions mx-1 mt-1">
										<img src="assets/images/demo/flat/8.png" class="img-fluid card-img" alt="">
									</a>
								</div>

								<div class="card-body">
									<div class="mb-3">
										<h6 class="d-flex flex-nowrap my-1">
											<a href="#" class="me-2">Consulta de Metas Colectivas</a>
										</h6>
									</div>
									<p>Este espacio puedes consultar el historial de Metas Colectivas por periodo.</p>
								</div>
								<div class="card-footer d-sm-flex justify-content-sm-between align-items-sm-center">
								<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalHistorico">Consultar</button>
								</div>
							</div>
						</div>
						<?php } ?>

					</div>
					<!-- //Vertical cards -->





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


<!-- Modal para consultar metas de otros periodos -->
<div class="modal fade" id="modalHistorico" tabindex="-1">
  <div class="modal-dialog">
  <div class="modal-content">
    <form method="get" action="generar_excel_colectivas.php">
	<div class="modal-header bg-primary text-white border-0">
	<h6 class="modal-title">Consultar Metas por Periodo</h6>
	<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
      <div class="modal-body">
        <p>Selecciona un periodo anterior para descargar las Metas Colectivas en formato Excel para consulta.</p>
        <div class="form-group">
          <label for="periodo_consulta">Periodo</label>
          <select name="periodo" id="periodo" class="form-control" required>
            <?php
            $stmt = $pdo->prepare("SELECT * FROM calificaciones_colectivas WHERE unidad_id = ? AND resultado > ? ORDER BY periodo DESC");
			$stmt->execute([$unidad_id, 0]);
            $resultados_previos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($resultados_previos as $p) {
              echo '<option value="' . $p['periodo'] . '">' . htmlspecialchars($p['periodo']) . '</option>';
            }
            ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-sm btn-primary">Descargar Excel</button>
		<input type="hidden" name="user_id" value="<?= $user_id ?>">
		<input type="hidden" name="unidad_id" value="<?= $unidad_id ?>">
      </div>
    </form>
  </div>
  </div>
  </div>

<!-- Modal para subir PDF firmado -->
<div id="modalSubirPDF" class="modal fade" tabindex="-1">
  <div class="modal-dialog">
  <div class="modal-content">
  <form method="post" enctype="multipart/form-data">
  <div class="modal-header bg-warning text-white border-0">
	<h6 class="modal-title">Cargar Documento Firmado</h6>
	<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
      <div class="modal-body">
        <?php if ($archivo_ya_cargado): ?>
          <div class="alert alert-warning border-0">
            <strong>🔒 Ya existe un archivo cargado</strong><br>
            Ya se ha cargado un archivo de Metas Colectivas firmadas para este periodo. No es posible cargar otro archivo.<br><br>
            Si necesitas reemplazar el archivo, por favor contacta al administrador del sistema.
          </div>
        <?php else: ?>
          <p>Por favor, carga el archivo PDF con las Metas Colectivas evaluadas y firmadas.</p>
          <p><strong>⚠️ Importante:</strong> Una vez cargado el archivo, no podrás reemplazarlo.</p>
          <p>&nbsp;</p>
          <input type="file" name="archivo_colectivas" accept="application/pdf" class="form-control" required>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <?php if (!$archivo_ya_cargado): ?>
          <button name="subir_pdf" class="btn btn-sm btn-warning">Cargar</button>
        <?php else: ?>
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>
</div>
</div>

<!-- Modal para borrar PDF Colectivas -->
<div id="modalBorrarPDFColectivas" class="modal fade" tabindex="-1">
  <div class="modal-dialog">
  <div class="modal-content">
  <form method="get">
  <div class="modal-header bg-danger text-white border-0">
	<h6 class="modal-title">Borrar Documento Firmado de Metas Colectivas</h6>
	<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
      <div class="modal-body">
        <p>¿Estás seguro de que deseas borrar el archivo de Metas Colectivas firmadas?</p>
        <p><strong>⚠️ Advertencia:</strong> Esta acción eliminará el documento cargado y podrás volver a cargar uno nuevo.</p>
      </div>
      <div class="modal-footer">
	<a href="mis_metas_colectivas.php?eliminar_pdf_confirmado=1" class="btn btn-sm btn-danger">Borrar</a>
	<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      </div>
	</form>
  </div>
</div>
</div>

<!-- Modal para FINALIZAR CARGA de Metas Colectivas - Cambio Febrero 2026 A3 -->
<div id="modalFinalizarCargaColectivas" class="modal fade" tabindex="-1">
  <div class="modal-dialog">
  <div class="modal-content">
  <form method="post">
  <div class="modal-header bg-primary text-white border-0">
	<h6 class="modal-title">Finalizar Carga de Metas Colectivas</h6>
	<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
      <div class="modal-body">
        <p><strong>¿Estás seguro de que deseas finalizar la carga de metas colectivas?</strong></p>
        <p>Al confirmar esta acción:</p>
        <ul>
          <li>Se bloqueará la edición de todas las metas colectivas</li>
          <li>No podrás agregar, editar o eliminar metas</li>
          <li>Ya no podrás borrar el archivo PDF cargado</li>
          <li>Solo un administrador podrá hacer cambios posteriores</li>
        </ul>
        <p class="text-danger"><strong>⚠️ Esta acción es IRREVERSIBLE</strong></p>
      </div>
      <div class="modal-footer">
	<button name="finalizar_carga_colectivas" class="btn btn-sm btn-primary">Finalizar Carga</button>
	<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      </div>
	</form>
  </div>
</div>
</div>


<?php
// Eliminar meta si se confirma vía POST
if (isset($_POST['eliminar_confirmado']) && isset($_POST['id_eliminar'])) {
  $stmt = $pdo->prepare("DELETE FROM metas_colectivas WHERE id = ? AND user_id = ?");
  $stmt->execute([$_POST['id_eliminar'], $user_id]);
  header("Location: metas_colectivas.php?mensaje=eliminado");
  exit;
}
?>
