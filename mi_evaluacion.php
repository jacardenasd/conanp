<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require 'includes/session.php';
require 'config/db.php';
require 'includes/variables.php';
require 'includes/cedulas_firmadas.php';

checkLogin(1);
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
$carga_cedula_bloqueada_admin = modulo_bloqueado_por_periodo('cedula_firmada', (int)$periodo);
asegurar_columna_capacitacion_contabiliza($pdo);

asegurar_tabla_cedulas_firmadas($pdo);

$stmt = $pdo->prepare("SELECT * FROM calificaciones_colectivas WHERE unidad_id = ? AND periodo = ?");
$stmt->execute([$unidad_id, $periodo]);
$resultado = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM calificaciones WHERE user_id = ? AND periodo = ?");
$stmt->execute([$user_id, $periodo]);
$resultado_ind = $stmt->fetch(PDO::FETCH_ASSOC);

// Validar suma de horas
$stmt = $pdo->prepare("SELECT SUM(horas) FROM capacitacion WHERE user_id = ? AND periodo = ? AND validado = ? AND COALESCE(contabiliza_horas, 1) = 1");
$stmt->execute([$user_id, $periodo, 1]);
$suma = $stmt->fetchColumn();


if ($resultado_ind) 
{
	$estatus_metas = $resultado_ind['estatus_metas'];
	$estatus_aportaciones_destacadas = $resultado_ind['estatus_aportaciones_destacadas'];
	$estatus_actividades_extraordinarias = $resultado_ind['estatus_actividades_extraordinarias'];
} else {
	$estatus_metas = 0;
	$estatus_aportaciones_destacadas = 0;
	$estatus_actividades_extraordinarias = 0;
}
if ($resultado AND isset($resultado)) 
{
	$estatus_colectivas = $resultado['estatus'];
} else {
	$estatus_colectivas = 0;
}

if ($estatus_metas == 0){ $estatus_metas_ind = 'Sin Captura';}
else if ($estatus_metas == 1){ $estatus_metas_ind = 'Capturadas';}
else if ($estatus_metas == 2){ $estatus_metas_ind = 'Con resultado propuesto';}
else if ($estatus_metas == 3){ $estatus_metas_ind = 'Calificadas';}
else { $estatus_metas_ind = '-';}

if ($estatus_colectivas == 0){ $estatus_metas_col = 'Sin Captura';}
else if ($estatus_colectivas == 1){ $estatus_metas_col = 'Capturadas';}
else if ($estatus_colectivas == 2){ $estatus_metas_col = 'Calificadas';}
else { $estatus_colectivas = '-';}

if ($estatus_aportaciones_destacadas == 0){ $estatus_aportaciones_destacada = 'Sin Captura/Validación';}
else if ($estatus_aportaciones_destacadas ==1){ $estatus_aportaciones_destacada = 'Con Captura/Validación';}
else { $estatus_aportaciones_destacada = '-';}

if ($estatus_actividades_extraordinarias == 0){ $estatus_actividades_extraordinaria = 'Sin Captura/Validación';}
else if ($estatus_actividades_extraordinarias == 1){ $estatus_actividades_extraordinaria = 'Con Captura/Validación';}
else { $estatus_actividades_extraordinaria = '-';}

$estatus_gerenciales = (int)($resultado_ind['estatus_gerenciales'] ?? 0);
if ($estatus_gerenciales === 0){ $estatus_gerencial = 'Sin evaluación';}
else if ($estatus_gerenciales === 2){ $estatus_gerencial = 'Autoevaluado, pendiente de jefe';}
else if ($estatus_gerenciales >= 3){ $estatus_gerencial = 'Evaluado por jefe';}
else { $estatus_gerencial = 'En proceso';}



$mostrar_boton = false;
$mensaje_boton = [];
if ($user_id) {
    // Validar estatus individuales
    $stmt = $pdo->prepare("SELECT * FROM calificaciones WHERE user_id = ? AND periodo = ?");
    $stmt->execute([$user_id, $periodo]);
    $cal = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cal) {
        // Cambio #7: Validaciones obligatorias para descarga de cédula
        $puede_descargar = true;
        
        // 1. Metas Individuales (OBLIGATORIO - estatus = 3)
        if ($cal['estatus_metas'] != 3) {
            $puede_descargar = false;
            $mensaje_boton[] = 'Metas Individuales deben estar evaluadas por tu jefe';
        }
        
        // 2. Autoevaluación Gerencial (OBLIGATORIO - estatus >= 3)
        if (($cal['estatus_gerenciales'] ?? 0) < 3) {
            $puede_descargar = false;
            $mensaje_boton[] = 'Autoevaluación Gerencial debe estar evaluada por tu jefe';
        }
        
		// 3. Metas Colectivas (OBLIGATORIO si la unidad tiene metas)
		$requiere_colectivas = ((int)($_SESSION['permite_metas_colectivas'] ?? 0) === 1);
		if (!$requiere_colectivas) {
			$stmt = $pdo->prepare("SELECT COUNT(*) FROM metas_colectivas WHERE unidad_id = ? AND periodo = ?");
			$stmt->execute([$unidad_id, $periodo]);
			$requiere_colectivas = ((int)($stmt->fetchColumn() ?? 0)) > 0;
		}

		if ($requiere_colectivas) {
			$stmt = $pdo->prepare("SELECT estatus FROM calificaciones_colectivas WHERE unidad_id = ? AND periodo = ?");
			$stmt->execute([$unidad_id, $periodo]);
			$estatus_colectiva = (int)($stmt->fetchColumn() ?? 0);
            
			if ($estatus_colectiva < 2) {
				$puede_descargar = false;
				$mensaje_boton[] = 'Metas Colectivas deben estar evaluadas';
			}
		}

		// 4. Aportaciones/Actividades capturadas deben estar validadas o descartadas por Admin
		$pendientes_admin = 0;

		$stmt = $pdo->prepare("SELECT COUNT(*) FROM aportaciones_destacadas WHERE user_id = ? AND periodo = ? AND COALESCE(validado_rh, 0) = 0 AND COALESCE(rechazado_por_rh, 0) = 0");
		$stmt->execute([$user_id, $periodo]);
		$pendientes_admin += (int)($stmt->fetchColumn() ?? 0);

		$stmt = $pdo->prepare("SELECT COUNT(*) FROM actividades_extraordinarias WHERE user_id = ? AND periodo = ? AND COALESCE(validado_rh, 0) = 0 AND COALESCE(rechazado_por_rh, 0) = 0");
		$stmt->execute([$user_id, $periodo]);
		$pendientes_admin += (int)($stmt->fetchColumn() ?? 0);

		if ($pendientes_admin > 0) {
			$puede_descargar = false;
			$mensaje_boton[] = 'Tienes aportaciones/actividades pendientes de validación o descarte por RH.';
		}
        
        // Capacitación, Actividades y Aportaciones son OPCIONALES
        $mostrar_boton = $puede_descargar;
    }
}

$puede_subir_cedula_firmada = $mostrar_boton && !$carga_cedula_bloqueada_admin;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_cedula_firmada'])) {
	if ($carga_cedula_bloqueada_admin) {
		header("Location: mi_evaluacion.php?info=cedula_bloqueada_admin");
		exit;
	}

	if (!$puede_subir_cedula_firmada) {
		header("Location: mi_evaluacion.php?info=cedula_bloqueada");
		exit;
	}

	if (($_POST['confirmacion_final'] ?? '0') !== '1') {
		header("Location: mi_evaluacion.php?info=cedula_confirmacion");
		exit;
	}

	$stmt = $pdo->prepare("SELECT id FROM cedulas_firmadas WHERE user_id = ? AND periodo = ? LIMIT 1");
	$stmt->execute([$user_id, $periodo]);
	$cedulaExistente = $stmt->fetch(PDO::FETCH_ASSOC);

	if ($cedulaExistente) {
		header("Location: mi_evaluacion.php?info=cedula_existente");
		exit;
	}

	if (!isset($_FILES['cedula_pdf']) || $_FILES['cedula_pdf']['error'] !== UPLOAD_ERR_OK) {
		header("Location: mi_evaluacion.php?info=cedula_error");
		exit;
	}

	$archivoTemporal = $_FILES['cedula_pdf']['tmp_name'];
	$archivoOriginal = $_FILES['cedula_pdf']['name'];
	$archivoTipo = $_FILES['cedula_pdf']['type'];
	$archivoExtension = strtolower(pathinfo($archivoOriginal, PATHINFO_EXTENSION));
	$archivoTamano = (int)$_FILES['cedula_pdf']['size'];

	if ($archivoTamano > 4 * 1024 * 1024) {
		header("Location: mi_evaluacion.php?info=cedula_peso");
		exit;
	}

	$tiposPermitidos = ['application/pdf', 'application/x-pdf', 'application/x-bzpdf', 'application/x-gzpdf'];
	$archivoHandler = fopen($archivoTemporal, 'r');
	$fileMagic = $archivoHandler ? fread($archivoHandler, 4) : '';
	if ($archivoHandler) {
		fclose($archivoHandler);
	}
	$esPdfValido = ($archivoExtension === 'pdf') && (in_array($archivoTipo, $tiposPermitidos, true) || (strpos($fileMagic, '%PDF') === 0));

	if (!$esPdfValido) {
		header("Location: mi_evaluacion.php?info=cedula_pdf");
		exit;
	}

	$nombreArchivo = limpiar_nombre_archivo($archivoOriginal);
	$directorioDestino = 'cedulas_firmadas';
	if (!is_dir($directorioDestino)) {
		mkdir($directorioDestino, 0755, true);
	}

	$rutaDestino = $directorioDestino . '/' . $nombreArchivo;
	if (!move_uploaded_file($archivoTemporal, $rutaDestino)) {
		header("Location: mi_evaluacion.php?info=cedula_guardado");
		exit;
	}

	$stmt = $pdo->prepare("INSERT INTO cedulas_firmadas (user_id, periodo, nombre_archivo) VALUES (?, ?, ?)");
	$stmt->execute([$user_id, $periodo, $nombreArchivo]);

	header("Location: mi_evaluacion.php?info=cedula_ok");
	exit;
}

$stmt = $pdo->prepare("SELECT id, nombre_archivo, fecha_subida FROM cedulas_firmadas WHERE user_id = ? AND periodo = ? LIMIT 1");
$stmt->execute([$user_id, $periodo]);
$cedula_firmada = $stmt->fetch(PDO::FETCH_ASSOC);

$archivos = $pdo->query("SELECT * FROM archivos WHERE tipo = 4 ORDER BY fecha_subida DESC")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title>CONANP - Sistema de Evaluación del Desempeño</title>

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
	<script src="assets/js/vendor/notifications/bootbox.min.js"></script>

	<script src="assets/js/app.js"></script>
	<script src="assets/demo/pages/components_modals.js"></script>
    <script src="assets/demo/pages/components_buttons.js"></script>
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

				<div class="d-flex align-items-start justify-content-end mb-4">
				<div class="bg-light border p-3 rounded shadow-sm text-end" style="max-width: 80%;">
					<strong>ℹ️ Consejo:</strong>  Consulta los archivos y material de apoyo en la sección de <a href="archivos_descargar.php" class="text-decoration-underline">documentación</a>.
				</div>
				<img src="assets/images/avatars/Nogravity-1.png" alt="Guía" class="rounded-circle ms-3" width="60" height="60">
				</div>

				<div class="card">
				<div class="card-header">
					<h5 class="mb-0 fw-semibold text-primary">
					<i class="ph-clipboard-text me-2 text-secondary"></i>Mi Evaluación
					</h5>
				</div>

				<div class="card-body">
					<p>
					En esta sección podrás consultar los diferentes elementos que integran la evaluación del desempeño.
					</p>
				</div>

				<?php if ($mostrar_boton) {?>
				<div class="card-footer d-flex align-items-center justify-content-between bg-success bg-opacity-10">
				<div>
					<h6 class="mb-1"><i class="ph-check-circle text-success me-2"></i>Cédula de Evaluación Disponible</h6>
					<p class="mb-0 text-muted">Todos los elementos obligatorios han sido evaluados. Puedes descargar tu cédula.</p>
				</div>
				<a href="reporte_cedula_resultados.php?user_id=<?php echo $user_id ?>&periodo=<?php echo $periodo ?>" class="btn btn-danger">
					<i class="ph-download me-2"></i>Descargar Cédula
				</a>
				</div>
				<?php } else if (!empty($mensaje_boton)) { ?>
			<div class="card-footer d-flex align-items-center justify-content-between bg-warning bg-opacity-10">
				<div>
					<h6 class="mb-2"><i class="ph-warning text-warning me-2"></i>Requisitos Pendientes para Descargar Cédula</h6>
					<ul class="mb-2">
						<?php foreach ($mensaje_boton as $msg): ?>
							<li><?= htmlspecialchars($msg) ?></li>
						<?php endforeach; ?>
					</ul>
					<small class="text-muted">Nota: Actividades Extraordinarias y Aportaciones Destacadas son opcionales.</small>
				</div>
				<button class="btn btn-secondary" disabled>
					<i class="ph-download me-2"></i>Descargar Cédula
				</button>
			</div>
			<?php } ?>

		</div>

				<!-- Info blocks -->
				<div class="row">
						<div class="col-lg-3">
							<div class="card">
								<div class="card-body text-center">
									<div class="d-inline-flex bg-warning bg-opacity-10 text-warning rounded-pill p-2 mb-3 mt-1">
										<i class="ph-list ph-2x m-1"></i>
									</div>
									<h5 class="card-title">Metas Individuales</h5>
									<p class="mb-3">Es la valoración del cumplimiento Individual de las Funciones, Objetivos y Metas. Se refiere a la evaluación de las Metas Individuales que cada persona servidora pública registró en el ejercicio inmediato anterior.</p>
									<p>Estatus actual: <span class="badge bg-warning"><?php echo $estatus_metas_ind;?></span></p>
									<a href="mis_metas_individuales.php" class="btn btn-warning mb-1">Ir a la sección</a>
								</div>
							</div>
						</div>

						<div class="col-lg-3">
							<div class="card">
								<div class="card-body text-center">
									<div class="d-inline-flex bg-danger bg-opacity-10 text-danger rounded-pill p-2 mb-3 mt-1">
										<i class="ph-user-circle ph-2x m-1"></i>
									</div>
									<h5 class="card-title">Autoevaluación Gerencial</h5>
									<p class="mb-3">Se evalúan los estándares de actuación profesional en materia gerencial. Este rubro también es Evaluado como Capacidades de Desarrollo Administrativo y Calidad (Visión Estratégica, Liderazgo, Orientación a Resultados, Negociación y Trabajo en Equipo).</p>
									<p>Estatus actual: <span class="badge bg-danger"><?php echo $estatus_gerencial ?></span></p>
									<a href="mi_autoevaluacion.php" class="btn btn-danger mb-1">Evaluar</a>
								</div>
							</div>
						</div>

						<div class="col-lg-3">
							<div class="card">
								<div class="card-body text-center">
									<div class="d-inline-flex bg-info bg-opacity-10 text-info rounded-pill p-2 mb-3 mt-1">
										<i class="ph-user-circle-plus ph-2x m-1"></i>
									</div>
									<h5 class="card-title">Actividades Extraordinarias</h5>
										<p class="mb-3">Se refiere a los encargos temporales del despacho, la suplencia de personas servidoras públicas en el ejercicio de atribuciones legales, las comisiones oficiales que se determinen relevantes para el cumplimiento de los objetivos institucionales, que resulten significativas para el desarrollo del capital humano y, en su caso, contribuyan a la mejora de la CONANP.</p>
									<p>Estatus actual: <span class="badge bg-info"><?php echo $estatus_actividades_extraordinaria ?></span></p>
									<a href="mis_actividades_extraordinarias.php" class="btn btn-info mb-1">Capturar</a>
								</div>
							</div>
						</div>

						<div class="col-lg-3">
							<div class="card">
								<div class="card-body text-center">
									<div class="d-inline-flex bg-secondary bg-opacity-10 text-secondary rounded-pill p-2 mb-3 mt-1">
										<i class="ph-user-gear ph-2x m-1"></i>
									</div>
									<h5 class="card-title">Aportaciones Destacadas</h5>
									<p class="mb-3">Son las acciones realizadas por iniciativa de la persona Servidora Pública Evaluada, cuyos resultados puedan ser verificados y documentados; que contribuyan a mejorar el desempeño de sus funciones o que impliquen una contribución al desarrollo de capital humano en otras personas servidoras públicas, en su caso, contribuyan en la mejora de la CONANP o aporten beneficios a la población.</p>
									<p>Estatus actual: <span class="badge bg-secondary"><?php echo $estatus_aportaciones_destacada ?></span></p>
									<a href="mis_aportaciones_destacadas.php" class="btn btn-secondary mb-1">Capturar</a>
								</div>
							</div>
						</div>
					</div>
					<!-- /info blocks -->

					<div class="card mt-3">
						<div class="card-header">
							<h5 class="mb-0 fw-semibold text-primary"><i class="ph-seal-check me-2 text-secondary"></i>Cédula de evaluación firmada (Periodo <?= htmlspecialchars($periodo) ?>)</h5>
						</div>
						<div class="card-body">
							<?php if (isset($_GET['info']) && $_GET['info'] === 'cedula_ok'): ?>
								<div class="alert alert-success">La cédula firmada se cargó correctamente.</div>
							<?php elseif (isset($_GET['info']) && $_GET['info'] === 'cedula_confirmacion'): ?>
								<div class="alert alert-warning">Debes confirmar que el archivo corresponde a la versión final firmada.</div>
							<?php elseif (isset($_GET['info']) && $_GET['info'] === 'cedula_existente'): ?>
								<div class="alert alert-info">Ya existe una cédula firmada para este periodo. Solo puedes visualizarla.</div>
							<?php elseif (isset($_GET['info']) && $_GET['info'] === 'cedula_bloqueada'): ?>
								<div class="alert alert-warning">La carga está deshabilitada hasta que se habilite la descarga de tu cédula para este periodo.</div>
							<?php elseif (isset($_GET['info']) && $_GET['info'] === 'cedula_bloqueada_admin'): ?>
								<div class="alert alert-warning">La carga de cédula firmada para 2025 está bloqueada por administración.</div>
							<?php elseif (isset($_GET['info']) && $_GET['info'] === 'cedula_peso'): ?>
								<div class="alert alert-danger">El archivo excede el tamaño máximo permitido (4 MB).</div>
							<?php elseif (isset($_GET['info']) && $_GET['info'] === 'cedula_pdf'): ?>
								<div class="alert alert-danger">El archivo debe ser un PDF válido.</div>
							<?php elseif (isset($_GET['info']) && ($_GET['info'] === 'cedula_error' || $_GET['info'] === 'cedula_guardado')): ?>
								<div class="alert alert-danger">No se pudo cargar el archivo. Intenta nuevamente.</div>
							<?php endif; ?>

							<?php if ((int)$periodo === 2025): ?>
								<div class="alert alert-warning py-2 px-3">
									<strong>AVISO:</strong> En caso de obtener una calificaci&oacute;n final &ldquo;Sobresaliente&rdquo; en tu Evaluaci&oacute;n del Desempe&ntilde;o, deber&aacute;s presentar evidencias documentales que sustenten dicho resultado, conforme a las metas evaluadas, y remitirlas al correo <a href="mailto:capacitacion@conanp.gob.mx">capacitacion@conanp.gob.mx</a> para su revisi&oacute;n y validaci&oacute;n.
								</div>
							<?php endif; ?>

							<?php if ($cedula_firmada): ?>
								<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
									<div>
										<h6 class="mb-1 text-success"><i class="ph-check-circle me-1"></i>Cédula final cargada</h6>
										<p class="mb-0 text-muted">La cédula firmada de este periodo ya fue registrada y no puede eliminarse desde esta sección.</p>
									</div>
									<a href="cedulas_firmadas/<?= htmlspecialchars($cedula_firmada['nombre_archivo']) ?>" target="_blank" class="btn btn-success">
										<i class="ph-eye me-2"></i>Ver cédula cargada
									</a>
								</div>
							<?php else: ?>
								<p class="mb-3">Carga el PDF firmado de tu cédula de evaluación final. Una vez cargado, quedará disponible solo para consulta.</p>
								<form id="formCedulaFirmada" method="post" enctype="multipart/form-data" class="row g-3">
									<input type="hidden" name="subir_cedula_firmada" value="1">
									<input type="hidden" name="confirmacion_final" id="confirmacionFinalCedula" value="0">
									<div class="col-md-8">
										<input type="file" name="cedula_pdf" id="cedulaPdfInput" class="form-control" accept="application/pdf" <?= $puede_subir_cedula_firmada ? 'required' : 'disabled' ?>>
									</div>
									<div class="col-md-4 d-flex align-items-end">
										<button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#modalConfirmarCedulaFinal" <?= $puede_subir_cedula_firmada ? '' : 'disabled' ?>>
											<i class="ph-upload-simple me-2"></i>Subir cédula firmada
										</button>
									</div>
									<?php if (!$puede_subir_cedula_firmada): ?>
										<div class="col-12">
											<?php if ($carga_cedula_bloqueada_admin): ?>
												<small class="text-muted">Bloqueado por administración para el periodo 2025.</small>
											<?php else: ?>
												<small class="text-muted">Disponible al concluir la evaluación del periodo.</small>
											<?php endif; ?>
										</div>
									<?php endif; ?>
								</form>
							<?php endif; ?>
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

	<div id="modalConfirmarCedulaFinal" class="modal fade" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Confirmar versión final firmada</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body">
					<p class="mb-2">Confirma que el archivo que vas a subir corresponde a la versión final firmada de tu cédula de evaluación.</p>
					<div class="form-check">
						<input class="form-check-input" type="checkbox" id="aceptoVersionFinalCedula">
						<label class="form-check-label" for="aceptoVersionFinalCedula">Estoy de acuerdo y confirmo que es la versión final firmada.</label>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
					<button type="button" id="btnConfirmarSubidaCedula" class="btn btn-primary" disabled>Confirmar y subir</button>
				</div>
			</div>
		</div>
	</div>

	<script>
		document.addEventListener('DOMContentLoaded', function() {
			var check = document.getElementById('aceptoVersionFinalCedula');
			var botonConfirmar = document.getElementById('btnConfirmarSubidaCedula');
			var inputConfirmacion = document.getElementById('confirmacionFinalCedula');
			var formulario = document.getElementById('formCedulaFirmada');
			var inputArchivo = document.getElementById('cedulaPdfInput');

			if (!check || !botonConfirmar || !inputConfirmacion || !formulario || !inputArchivo) {
				return;
			}

			check.addEventListener('change', function() {
				botonConfirmar.disabled = !check.checked;
			});

			botonConfirmar.addEventListener('click', function() {
				if (!inputArchivo.files || !inputArchivo.files.length) {
					alert('Selecciona un archivo PDF antes de confirmar.');
					return;
				}

				if (!check.checked) {
					return;
				}

				inputConfirmacion.value = '1';
				formulario.submit();
			});
		});
	</script>
</body>
</html>
