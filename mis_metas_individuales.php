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
$unidad_id = $_SESSION['unidad_id'];
$user_id = $_SESSION['user_id']; 

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);
$periodo = $_SESSION['periodo'];
$clave_bloqueo_pdf_metas = 'bloquear_pdf_metas_individuales_' . (int)$periodo;
$bloquear_pdf_metas = obtener_variable($clave_bloqueo_pdf_metas);
if ($bloquear_pdf_metas === null || $bloquear_pdf_metas === '') {
	$bloquear_pdf_metas = ((int)$periodo === 2025)
		? (obtener_variable('bloquear_pdf_metas_individuales_2025') ?? '1')
		: '0';
}
$carga_pdf_metas_bloqueada = ((string)$bloquear_pdf_metas === '1');

// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();

// Obtener metas del usuario
$stmt = $pdo->prepare("SELECT * FROM metas WHERE user_id = ? AND periodo = ?");
$stmt->execute([$user_id, $periodo]);
$metas = $stmt->fetchAll();
$totalMetas = count($metas); 

//tiene metas
$stmt = $pdo->prepare("SELECT COUNT(*) FROM metas WHERE user_id = ? AND periodo = ?");
$stmt->execute([$user_id, $periodo]);
$tiene_metas = $stmt->fetchColumn() > 0;

//evaluacion especial
$stmt = $pdo->prepare("SELECT COUNT(*) FROM especiales WHERE user_id = ? AND periodo = ?");
$stmt->execute([$user_id, $periodo]);
$especial = $stmt->fetchColumn() > 0;

$periodo_flexible_2025 = ((int)$periodo === 2025);
$permite_captura_flexible = ($estatus_periodo === 'Captura' || $estatus_periodo === 'Evaluación' || $especial || $periodo_flexible_2025);
$permite_propuesta_flexible = ($estatus_periodo === 'Evaluación' || $especial || $periodo_flexible_2025);

// Validar suma de ponderación
$stmt = $pdo->prepare("SELECT SUM(ponderacion) FROM metas WHERE user_id = ? AND periodo = ?");
$stmt->execute([$user_id, $periodo]);
$suma = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT estatus_metas FROM calificaciones WHERE user_id = ? AND periodo = ?");
$stmt->execute([$user_id, $periodo]);
$resultado = $stmt->fetch(PDO::FETCH_ASSOC);


// Eliminar meta si se confirma vía POST
if (isset($_GET['eliminar_confirmado']) && $_GET['eliminar_confirmado'] == 1) {
  $stmt = $pdo->prepare("UPDATE calificaciones SET archivo_individuales = NULL WHERE user_id = ? AND periodo = ?");
  $stmt->execute([$user_id, $periodo]); 
  header("Location: mis_metas_individuales.php?info=5");
  exit;
}

// Cambio Febrero 2026 - C1: Procesar "Finalizar carga" de metas individuales
if (isset($_POST['finalizar_carga_individuales'])) {
	// Verificar que hay PDF cargado y metas capturadas
	$stmt_check = $pdo->prepare("SELECT archivo_individuales FROM calificaciones WHERE user_id = ? AND periodo = ?");
	$stmt_check->execute([$user_id, $periodo]);
	$archivo = $stmt_check->fetchColumn();
	
	if (!empty($archivo) && $tiene_metas && $suma == 100) {
		// Marcar como finalizado en BD usando la función de finalizaciones.php
		require_once 'includes/finalizaciones.php';
		finalizar_metas_pdf($pdo, $user_id, $periodo, $user_id);
		
		header("Location: mis_metas_individuales.php?info=6");
		exit;
	} else {
		header("Location: mis_metas_individuales.php?info=7");
		exit;
	}
}

// Verificar si ya están finalizadas
$stmt_finalizado = $pdo->prepare("SELECT finalizado_metas FROM calificaciones WHERE user_id = ? AND periodo = ?");
$stmt_finalizado->execute([$user_id, $periodo]);
$finalizado_metas = $stmt_finalizado->fetchColumn() ?? 0;





if ($resultado) {$estatus_metas = $resultado['estatus_metas'];} else {$estatus_metas = 0;} 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_pdf'])) {
	if ($carga_pdf_metas_bloqueada) {
		header("Location: mis_metas_individuales.php?info=8");
		exit;
	}

  $user_id = $_SESSION['user_id']; 

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);

  if (isset($_FILES['archivo_individual']) && $_FILES['archivo_individual']['error'] === UPLOAD_ERR_OK) {
    $nombre_original = $_FILES['archivo_individual']['name'];
    $nombre_final = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $nombre_original);
    $ruta_destino = 'firmas_individuales/' . $nombre_final;

    if (!is_dir('firmas_individuales')) {
      mkdir('firmas_individuales', 0755, true);
    }

    move_uploaded_file($_FILES['archivo_individual']['tmp_name'], $ruta_destino);

    $fecha_carga = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("UPDATE calificaciones SET archivo_individuales = ?, fecha_archivo_individuales = ? WHERE user_id = ? AND periodo = ?");
    $stmt->execute([$nombre_final, $fecha_carga, $user_id, $periodo]);

	header("Location: mis_metas_individuales.php?info=1");
	exit;

  } else {

	header("Location: mis_metas_individuales.php?info=4");
	exit;

  }
}

$stmt = $pdo->prepare("SELECT * FROM calificaciones WHERE user_id = ? AND periodo = ?");
$stmt->execute([$user_id, $periodo]);
$calificacion = $stmt->fetch(PDO::FETCH_ASSOC); 

$archivoIndividualDisponible = false;
if (!empty($calificacion['archivo_individuales'])) {
	$rutaArchivoIndividual = __DIR__ . '/firmas_individuales/' . $calificacion['archivo_individuales'];
	$archivoIndividualDisponible = is_file($rutaArchivoIndividual);
}

$archivos = $pdo->query("SELECT * FROM archivos WHERE tipo = 1 ORDER BY fecha_subida DESC")->fetchAll();

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
	<script>
  setTimeout(() => {
  const alerta = document.getElementById('alerta-auto');
  if (alerta) {
    alerta.classList.remove('show');
    alerta.classList.add('fade');
    setTimeout(() => alerta.remove(), 500); // Espera a que termine la animación
  }
}, 4000);
  </script>
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
								<span class="breadcrumb-item active">Metas Individuales  <?= $periodo ?></span>
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
                            $texto = '🗑️ Meta eliminada correctamente.';
                            $clase_alerta = 'alert-danger';
                            break;
                        case 5:
                            $texto = '🗑️ Archivo eliminado correctamente.';
                            $clase_alerta = 'alert-danger';
                            break;
                        case 6:
                            $texto = '✅ Carga de metas finalizada. La edición ha sido bloqueada.';
                            $clase_alerta = 'alert-success';
                            break;
                        case 7:
                            $texto = '⚠️ Para finalizar la carga debes tener el PDF cargado y que la ponderación sume 100%.';
                            $clase_alerta = 'alert-warning';
                            break;
						case 8:
							$texto = '⚠️ La carga de PDF para Metas Individuales 2025 fue bloqueada por administracion.';
							$clase_alerta = 'alert-warning';
							break;
                        case 4:
                              $texto = '⚠️ Error al subir el archivo';
                              $clase_alerta = 'alert-warning';
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
								Bienvenido a esta sección dónde podrás cargar y consultar tus metas individuales.
							</div>
							<img src="assets/images/avatars/Guacamole.png" alt="Guía" class="rounded-circle mr-3" width="70" height="70">
						</div>


<div class="card">
  <div class="card-header">
    <h5 class="mb-0 fw-semibold text-primary">
      <i class="ph-target me-2 text-secondary"></i>Metas Individuales
    </h5>
  </div>

  <div class="card-body">
    <p class="mb-4">
      Tus metas individuales deben ser <strong>observables, medibles y realistas</strong>, y reflejar un resultado específico del desempeño.<br>
      No podrás concluir tu proceso hasta que hayas cargado tu <strong>formato firmado</strong>.
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
						<div class="col-lg-3">
							<div class="card">
								<div class="card-img-actions mx-1 mt-1">
										<img src="assets/images/demo/flat/1.png" class="img-fluid card-img" alt="">
									</a>
								</div>

								<div class="card-body">
									<div class="mb-3">
										<h6 class="d-flex flex-nowrap my-1">
											
										<?php if ($estatus_metas == 1 && $permite_propuesta_flexible) {?>
											<a  class="me-2">Proponer Resultados de Metas</a>
										<?php } elseif ($permite_captura_flexible) {?>
											<a  class="me-2">Captura de Metas Individuales</a>
										<?php } else {?>
											<a  class="me-2">Periodo Cerrado</a>
										<?php } ?>

										
										
										</h6>
									</div>
									<p>Al seleccionar esta opción, se desplegará una sección donde podrás registrar tus metas individuales.</p>

									<?php if ($estatus_metas == 1): ?>
									    <p>Estatus actual: <span class="badge bg-success ms-auto">Capturadas</span></p>
									<?php elseif ($estatus_metas == 2): ?>
										<p>Estatus actual: <span class="badge bg-success ms-auto">Con resultado propuesto</span></p>
									<?php elseif ($estatus_metas == 3): ?>
										<p>Estatus actual: <span class="badge bg-success ms-auto">Evaluadas</span></p>
									<?php elseif ($estatus_metas == 0): ?>
										<p>Estatus actual: <span class="badge bg-danger ms-auto">Sin Captura</span></p>
									<?php else: ?>
										<p>Estatus actual: <span class="badge bg-secondary ms-auto">No disponible</span></p>
									<?php endif; ?>

									
								</div>
								<div class="card-footer d-sm-flex justify-content-sm-between align-items-sm-center">
								<?php if ($estatus_metas == 1 && $permite_propuesta_flexible) {?>
									<a href="metas_individuales.php" class="btn btn-sm btn-success">Proponer</a>
								<?php } elseif ($permite_captura_flexible) {?>
									<a href="metas_individuales.php" class="btn btn-sm btn-success">Capturar</a>
								<?php } else {?>
								Periodo Cerrado
								<?php } ?>
								</div>
							</div>
						</div>

						<div class="col-lg-3">
							<div class="card">
								<div class="card-img-actions mx-1 mt-1">
										<img src="assets/images/demo/flat/2.png" class="img-fluid card-img" alt="">
									</a>
								</div>

								<div class="card-body">
									<div class="mb-3">
										<h6 class="d-flex flex-nowrap my-1">
											<a class="me-2">Descarga de Metas Individuales</a>
										</h6>
									</div>
									<p>Da clic en el botón para  descargar en Excel tus metas de desempeño en el formato correspondiente.</p>
								</div>
								<div class="card-footer d-sm-flex justify-content-sm-between align-items-sm-center">
									<?php if ($estatus_metas > 0): ?>
                  					<a href="generar_excel_individual.php?user_id=<?= $user_id ?>&periodo=<?= $periodo ?>" class="btn btn-sm btn-danger">Descargar</a>
									  <?php else: ?>
									⚠️ Aún no tienes metas capturadas para este periodo.
									<?php endif; ?>
								</div>
							</div>
						</div>

            <div class="col-lg-3">
							<div class="card">
								<div class="card-img-actions mx-1 mt-1">
										<img src="assets/images/demo/flat/3.png" class="img-fluid card-img" alt="">
									</a>
								</div>

								<div class="card-body">
									<div class="mb-3">
										<h6 class="d-flex flex-nowrap my-1">
											<a  class="me-2">Carga de Metas Individuales Firmadas</a>
										</h6>
									</div>
									<p>Recuerda que debes cargar el formato de metas individuales firmado por tu superior jerárquico (en formato PDF).</p>
									<p>Estatus actual:
														<?php if (!empty($calificacion['archivo_individuales']) && $archivoIndividualDisponible): ?>
															<span class="badge bg-success ms-auto">Cargado</span>
														<?php elseif (!empty($calificacion['archivo_individuales'])): ?>
															<span class="badge bg-danger ms-auto">Archivo no disponible</span>
														<?php else: ?>
															<span class="badge bg-warning ms-auto">Pendiente</span>
														<?php endif; ?>
									</p>
								</div>
								<div class="card-footer d-sm-flex justify-content-sm-between align-items-sm-center">

														<?php if (!empty($calificacion['archivo_individuales'])): ?>
															<?php if ($finalizado_metas == 1): ?>
																<!-- Ya finalizado - solo mostrar estado -->
																<span class="badge bg-info">✓ Carga Finalizada</span>
															<?php else: ?>
																<!-- PDF cargado pero no finalizado - mostrar botones -->
																<?php if ($archivoIndividualDisponible): ?>
																	<a href="firmas_individuales/<?= rawurlencode($calificacion['archivo_individuales']) ?>" class="btn btn-sm btn-warning" target="_blank">Descargar</a>
																<?php else: ?>
																	<span class="badge bg-danger">Archivo no disponible</span>
																<?php endif; ?>
																<button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#modalBorrarPDF">Borrar</button>
																<?php if ($suma == 100): ?>
																	<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalFinalizarCarga">Finalizar carga</button>
																<?php endif; ?>
															<?php endif; ?>
														<?php else: ?>

															<?php if ($estatus_metas > 0): ?>
																<?php if ($carga_pdf_metas_bloqueada): ?>
																	<span class="badge bg-danger">Bloqueado por administracion para 2025</span>
																<?php else: ?>
																	<button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalSubirPDF">Cargar</button>
																<?php endif; ?>
															<?php else: ?>
															⚠️ Aún no tienes metas capturadas para este periodo.
															<?php endif; ?>
															
														<?php endif; ?>
								</div>
							</div>
						</div>

            <div class="col-lg-3">
							<div class="card">
								<div class="card-img-actions mx-1 mt-1">
										<img src="assets/images/demo/flat/4.png" class="img-fluid card-img" alt="">
									</a>
								</div>

								<div class="card-body">
									<div class="mb-3">
										<h6 class="d-flex flex-nowrap my-1">
											<a href="#" class="me-2">Metas del Año Anterior</a>
										</h6>
									</div>
									<p>Este espacio puedes consultar el historial de tus metas registradas en años anteriores. No se pueden editar.</p>
								</div>
								<div class="card-footer d-sm-flex justify-content-sm-between align-items-sm-center">
								<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalHistorico">Consultar</button>
								</div>
							</div>
						</div>

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
        <p>Por favor, carga el archivo PDF con tus metas evaluadas y firmadas.</p><p>&nbsp;</p>
        <input type="file" name="archivo_individual" accept="application/pdf" class="form-control" required>
      </div>
      <div class="modal-footer">
        <button name="subir_pdf" class="btn btn-sm btn-warning">Cargar</button>
      </div>
    </form>
  </div>
</div>
</div>

<!-- Modal para borrar PDF firmado -->
<div id="modalBorrarPDF" class="modal fade" tabindex="-1">
  <div class="modal-dialog">
  <div class="modal-content">
  <form method="post">
  <div class="modal-header bg-danger text-white border-0">
	<h6 class="modal-title">Borrar Documento Firmado</h6>
	<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
      <div class="modal-body">
        <p>¿Estas seguro de querar borrar el archivo cargado?</p><p>&nbsp;</p>
      </div>
      <div class="modal-footer">
	<a href="mis_metas_individuales.php?eliminar_confirmado=1" class="btn btn-sm btn-danger">Borrar</a>
      </div>
	</form>
  </div>
</div>
</div>

<!-- Modal para FINALIZAR CARGA de Metas Individuales - Cambio Febrero 2026 C1 -->
<div id="modalFinalizarCarga" class="modal fade" tabindex="-1">
  <div class="modal-dialog">
  <div class="modal-content">
  <form method="post">
  <div class="modal-header bg-primary text-white border-0">
	<h6 class="modal-title">Finalizar Carga de Metas Individuales</h6>
	<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
      <div class="modal-body">
        <p><strong>¿Estás seguro de que deseas finalizar la carga de metas individuales?</strong></p>
        <p>Al confirmar esta acción:</p>
        <ul>
          <li>Se bloqueará la edición de todas tus metas individuales</li>
          <li>No podrás agregar, editar o eliminar metas</li>
          <li>Ya no podrás borrar el archivo PDF cargado</li>
          <li>Solo tu superior jerárquico o un administrador podrá hacer cambios posteriores</li>
        </ul>
        <p class="text-danger"><strong>⚠️ Esta acción es IRREVERSIBLE</strong></p>
      </div>
      <div class="modal-footer">
	<button name="finalizar_carga_individuales" class="btn btn-sm btn-primary">Finalizar Carga</button>
	<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      </div>
	</form>
  </div>
</div>
</div>


<!-- Modal para consultar metas de otros periodos -->
<div class="modal fade" id="modalHistorico" tabindex="-1">
  <div class="modal-dialog">
  <div class="modal-content">
    <form method="get" action="generar_excel_individual.php">
	<div class="modal-header bg-primary text-white border-0">
	<h6 class="modal-title">Consultar Metas por Periodo</h6>
	<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
 </div>
      <div class="modal-body">
        <p>Selecciona un periodo anterior para descargar tus metas en formato Excel.</p>
        <div class="form-group">
          <label for="periodo_consulta">Periodo</label>
          <select name="periodo" id="periodo" class="form-control" required>
            <?php
            $stmt = $pdo->prepare("SELECT * FROM calificaciones WHERE user_id = ? AND periodo != ? ORDER BY periodo DESC");
			$stmt->execute([$user_id, $periodo]);
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
      </div>
    </form>
  </div> 
</div>




</body>
</html>
