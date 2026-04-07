
<?php
// ============================================
// MIS COLABORADORES + FLUJO TERMINAR BLOQUEADOR
// Cambio #6: Agregar botones TERMINAR que cierran definitivamente
// ============================================

require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';
require 'includes/finalizaciones.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin();
asegurar_columna_capacitacion_contabiliza($pdo);

if (!function_exists('obtener_estatus_prerequisitos_colaborador')) {
	function obtener_estatus_prerequisitos_colaborador($pdo, $colaborador_id, $periodo) {
		$stmt = $pdo->prepare("SELECT estatus_metas, estatus_gerenciales FROM calificaciones WHERE user_id = ? AND periodo = ?");
		$stmt->execute([$colaborador_id, $periodo]);
		$estatus = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

		$estatus_metas = (int)($estatus['estatus_metas'] ?? 0);
		$estatus_gerenciales = (int)($estatus['estatus_gerenciales'] ?? 0);

		$stmt = $pdo->prepare("SELECT COUNT(*) AS total_metas,
									  SUM(CASE WHEN resultado IS NOT NULL AND resultado <> '' AND resultado <> 0 THEN 1 ELSE 0 END) AS metas_con_propuesta
							   FROM metas
							   WHERE user_id = ? AND periodo = ?");
		$stmt->execute([$colaborador_id, $periodo]);
		$metas = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

		$total_metas = (int)($metas['total_metas'] ?? 0);
		$metas_con_propuesta = (int)($metas['metas_con_propuesta'] ?? 0);
		$metas_listas = ($total_metas > 0 && $metas_con_propuesta >= $total_metas);

		$stmt = $pdo->prepare("SELECT COUNT(*) FROM competencias_evaluacion WHERE user_id = ? AND periodo = ? AND tipo = 'auto'");
		$stmt->execute([$colaborador_id, $periodo]);
		$autoeval_registros = (int)($stmt->fetchColumn() ?? 0);
		$gerenciales_listas = ($autoeval_registros > 0);

		$estatus_metas_efectivo = max($estatus_metas, $metas_listas ? 2 : 0);
		$estatus_gerenciales_efectivo = max($estatus_gerenciales, $gerenciales_listas ? 2 : 0);

		return [
			'estatus_metas' => $estatus_metas,
			'estatus_gerenciales' => $estatus_gerenciales,
			'estatus_metas_efectivo' => $estatus_metas_efectivo,
			'estatus_gerenciales_efectivo' => $estatus_gerenciales_efectivo,
			'metas_listas' => $metas_listas,
			'gerenciales_listas' => $gerenciales_listas,
			'puede_evaluar_jefe' => ($estatus_metas_efectivo >= 2 && $estatus_gerenciales_efectivo >= 2),
		];
	}
}

// ============================================
// PROCESAR ACCIONES DE TERMINACIÓN
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    $meta_id = intval($_POST['meta_id'] ?? 0);
    
    // Usuario TERMINA su captura
    if ($accion === 'usuario_termina') {
        if (usuario_termino_captura($pdo, $meta_id, $_SESSION['user_id'])) {
            $_SESSION['mensaje'] = '✅ Tu captura fue finalizada. Tu jefe ahora puede evaluar.';
        }
    }
    // Jefe TERMINA la evaluación
    elseif ($accion === 'jefe_termina') {
        if (jefe_termino_evaluacion($pdo, $meta_id, $_SESSION['user_id'])) {
            $_SESSION['mensaje'] = '✅ Evaluación finalizada. El proceso está bloqueado de forma definitiva.';
        }
    }
    
    header('Location: mis_colaboradores.php');
    exit;
}

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');
$puesto_id = $_SESSION['puesto_id'];
$el_nivel = 6;
$user_id = $_SESSION['user_id']; 

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);
$unidad_id = $_SESSION['unidad_id'];
$periodo = $_SESSION['periodo'];

// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();

$jefe_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT DISTINCT u.user_id, u.nombre, u.apellido_paterno, u.apellido_materno, COALESCE(NULLIF(TRIM(u.puesto_nombre), ''), p.puesto, '') AS puesto, c.periodo, c.individuales, c.aportaciones_destacadas, c.actividades_extraordinarias, c.capacitacion, c.gerenciales, c.estatus_aportaciones_destacadas, c.estatus_actividades_extraordinarias, c.estatus_capacitacion, c.fecha_archivo_individuales, c.estatus_metas, c.estatus_gerenciales  FROM usuarios AS u LEFT JOIN puestos AS p ON u.puesto_id = p.id LEFT JOIN calificaciones AS c ON u.user_id = c.user_id AND c.periodo = ? WHERE u.jefe_id = ? ORDER BY u.apellido_paterno ASC, u.apellido_materno ASC"); 
$stmt->execute([$periodo, $jefe_id]);
$colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC);

function obtener_calificacion_gerencial_respaldo($pdo, $colaborador_id, $periodo) {
	$sql = "SELECT ROUND(SUM(eval.avg_valor * pesos.peso) / NULLIF(SUM(pesos.peso), 0), 2) AS calificacion_final
			FROM usuarios u
			JOIN (
				SELECT user_id, competencia_id, AVG(valor) AS avg_valor
				FROM competencias_evaluacion
				WHERE user_id = ? AND periodo = ? AND tipo = 'jefe' AND valor > 0
				GROUP BY user_id, competencia_id
			) AS eval ON eval.user_id = u.user_id
			JOIN (
				SELECT nivel, competencia_id,
					CASE competencia_id
						WHEN 1 THEN vision
						WHEN 2 THEN liderazgo
						WHEN 3 THEN orientacion
						WHEN 4 THEN negociacion
						WHEN 5 THEN trabajo
					END AS peso
				FROM (
					SELECT nivel, 1 AS competencia_id, vision, liderazgo, orientacion, negociacion, trabajo FROM competencias_pesos
					UNION ALL
					SELECT nivel, 2 AS competencia_id, vision, liderazgo, orientacion, negociacion, trabajo FROM competencias_pesos
					UNION ALL
					SELECT nivel, 3 AS competencia_id, vision, liderazgo, orientacion, negociacion, trabajo FROM competencias_pesos
					UNION ALL
					SELECT nivel, 4 AS competencia_id, vision, liderazgo, orientacion, negociacion, trabajo FROM competencias_pesos
					UNION ALL
					SELECT nivel, 5 AS competencia_id, vision, liderazgo, orientacion, negociacion, trabajo FROM competencias_pesos
				) AS pesos_ext
			) AS pesos ON u.puesto_nivel = pesos.nivel AND eval.competencia_id = pesos.competencia_id
			WHERE u.user_id = ?";

	$stmt = $pdo->prepare($sql);
	$stmt->execute([$colaborador_id, $periodo, $colaborador_id]);
	$calificacion = $stmt->fetchColumn();

	if ($calificacion === false || $calificacion === null) {
		$stmt_fallback = $pdo->prepare("SELECT ROUND(AVG(valor), 2) FROM competencias_evaluacion WHERE user_id = ? AND periodo = ? AND tipo = 'jefe' AND valor > 0");
		$stmt_fallback->execute([$colaborador_id, $periodo]);
		$calificacion = $stmt_fallback->fetchColumn();
	}

	return $calificacion ?? 0;
}

// Validar califcaciones colectivas
$stmt = $pdo->prepare("SELECT resultado FROM calificaciones_colectivas WHERE unidad_id = ? AND periodo = ?");
$stmt->execute([$unidad_id, $periodo]);
$resultado_colectivas = $stmt->fetchColumn();

$archivos = $pdo->query("SELECT * FROM archivos WHERE tipo = 5 ORDER BY fecha_subida DESC")->fetchAll();

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
	<script src="assets/demo/pages/dashboard.js"></script>
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
								<a href="#" class="breadcrumb-item">Mis colaboradores</a>
							</div>
						</div>

					</div>
				</div>
				<!-- /page header -->

				<!-- Content area -->
				<div class="content">

        		<div class="d-flex mb-3">
				<img src="assets/images/avatars/E-commerce-2.png" class="rounded-circle me-3" width="50" height="50">
				<div class="bg-white border border-primary p-3 rounded-start rounded-pill shadow-sm">
					<strong>📝 Tip:</strong> Es mejor evaluar todos los rubros de tu colaborador al mismo tiempo.
				</div>
				</div>

				<?php if ($estatus_periodo !='Evaluación') {  ?>
					
					<div class="alert alert-danger border-0 alert-dismissible fade show" id="alerta-auto">
						<span class="fw-semibold"> El periodo de evaluación está en <?php echo $estatus_periodo ?>.
						<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
					</div>
					<?php }  ?>


				<?php if (isset($_GET['info'])): ?>
                    <?php
                    $clase_alerta = 'alert-success'; // valor por defecto

                    switch ($_GET['info']) {
                        case 1:
                            $texto = '✅ Colaborador evaluado correctamente.';
                            $clase_alerta = 'alert-success';
                            break;
							case 'error':
								$texto = isset($_GET['msg']) ? urldecode($_GET['msg']) : 'Ocurrió un error inesperado.';
								$clase_alerta = 'alert-danger';
								break;
						case 5:
							$texto = '✅ Colaborador evaluado correctamente';
							$clase_alerta = 'alert-danger';
							break;
						case 7:
							$texto = '⚠️ No puedes evaluar individuales aún. El colaborador debe capturar metas y proponer resultado en todas sus metas individuales.';
							$clase_alerta = 'alert-warning';
							break;
						case 11:
							$texto = '⚠️ No puedes evaluar competencias aún. El colaborador debe finalizar su autoevaluación gerencial.';
							$clase_alerta = 'alert-warning';
							break;
							case 8:
								$texto = '⚠️ Acción no permitida. Solo puedes evaluar a tus colaboradores asignados.';
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


<div class="card">
  <div class="card-header">
    <h5 class="mb-0 fw-semibold text-primary">
      <i class="ph-users-three me-2 text-secondary"></i>Mis Colaboradores
    </h5>
  </div>

  <div class="card-body">
		<p class="mb-4">
			A continuación, encontrarás el listado del personal que te reporta de manera directa e indirecta. Deberás realizar la evaluación correspondiente para cada uno de los factores definidos en el sistema.
		</p>

		<p class="mb-4">
			En caso de que alguna de las personas listadas no pertenezca a tu área de adscripción, solicita la corrección al área responsable a través del correo electrónico capacitacion@conanp.gob.mx
		</p>

    <div class="d-flex align-items-start p-3 bg-light rounded-3 border-start border-success border-4">
      <i class="ph-lifebuoy text-success fs-3 me-3"></i>
      <div class="flex-grow-1">
        Consulta los archivos y material de apoyo en la sección de 
        <a href="archivos_descargar.php" class="text-decoration-underline">documentación</a>.
      </div>
    </div>
  </div>
</div>


				<?php if ($estatus_periodo !='Evaluación') {  ?>
					
		<!-- Customers -->
		<div class="card">
			<div class="card-body">
				<table class="table table-xl">
					<thead class="thead-light">
					<tr class="bg-info text-white">
									<th>Nombre</th>
									<th>Individuales</th>
									<th>Aportaciones dest.</th>
									<th>Act. Extraordinarias</th>
									<th>Capacitación</th>
									<th>Gerencial</th>
								</tr>
							</thead>
							<tbody>
							<tr>
					<?php if (count($colaboradores) === 0): ?>
					<tr><td colspan="8">EL periodo seleccionado actualmente, está cerrado, o no se cuentan con evaluaciones todavía.</td></tr>
					<?php else: ?>
					<?php foreach ($colaboradores as $c): 
					$nombre = "{$c['nombre']} {$c['apellido_paterno']} {$c['apellido_materno']}";
					$colaborador_id = $c['user_id'];
					$individuales = $c['individuales'];
					$aportaciones_destacadas = $c['aportaciones_destacadas'];
					$estatus_actividades_extraordinarias = $c['estatus_actividades_extraordinarias'];
					$estatus_aportaciones_destacadas = $c['estatus_aportaciones_destacadas'];
					$actividades_extraordinarias = $c['actividades_extraordinarias'];
					$capacitacion = $c['capacitacion'];
					$gerenciales = $c['gerenciales'];
					$prerequisitos = obtener_estatus_prerequisitos_colaborador($pdo, (int)$colaborador_id, (int)$periodo);
					$estatus_metas = (int)$prerequisitos['estatus_metas_efectivo'];
					$estatus_gerenciales = (int)$prerequisitos['estatus_gerenciales_efectivo'];
					$metas_listas = (bool)$prerequisitos['metas_listas'];
					if ((int)$estatus_gerenciales >= 3 && (float)($gerenciales ?? 0) <= 0) {
						$gerenciales = obtener_calificacion_gerencial_respaldo($pdo, $colaborador_id, $periodo);
					}


					$stmt = $pdo->prepare("SELECT COUNT(*) FROM metas WHERE user_id = ? AND periodo = ?");
					$stmt->execute([$colaborador_id, $periodo]);
					$cantidad_metas = (int)$stmt->fetchColumn();

					$stmt = $pdo->prepare("SELECT COUNT(*) FROM aportaciones_destacadas WHERE user_id = ? AND periodo = ?");
					$stmt->execute([$colaborador_id, $periodo]);
					$cantidad_aportaciones_destacadas = (int)$stmt->fetchColumn();

					$stmt = $pdo->prepare("SELECT COUNT(*) FROM actividades_extraordinarias WHERE user_id = ? AND periodo = ?");
					$stmt->execute([$colaborador_id, $periodo]);
					$cantidad_actividades_extraordinarias = (int)$stmt->fetchColumn();

					$stmt = $pdo->prepare("SELECT SUM(horas) FROM capacitacion WHERE user_id = ? AND periodo = ? AND validado = ? AND validado_rh = ? AND COALESCE(contabiliza_horas, 1) = 1");
					$stmt->execute([$colaborador_id, $periodo, 1, 1]);
					$cantidad_horas_capacitacion = (float)($stmt->fetchColumn() ?? 0); 
					?>

									<td>
										<div class="d-flex align-items-center">
											<a class="d-block me-3">
												<img src="fotos/<?= htmlspecialchars($c['foto'] ?? 'default.png') ?>" width="40" height="40" class="rounded-circle" alt="">
											</a>

											<div class="flex-fill">
												<a class="fw-semibold"><?= htmlspecialchars($nombre) ?></a>
												<div class="fs-sm text-muted">
													<?= $c['puesto'] ?>
												</div>
											</div>
										</div>
									</td>
									<td>
										<?php 
										if ($cantidad_metas === 0) { 
											echo "<i class='ph-circle-dashed fs-base lh-base align-top  me-1'></i>Sin captura";
										    } else { 
											echo "<a href='evaluar_individuales.php?colaborador_id=$colaborador_id&periodo=$periodo'><i class='ph-hand fs-base lh-base align-top text-primary me-1'></i> <span class='text-primary me-1'>Con Captura</span></a>";
											} ?>
									</td>
									<td>
										<?php 
										if ($cantidad_aportaciones_destacadas == 0) { 
										echo "<i class='ph-circle-dashed fs-base lh-base align-top  me-1'></i>Sin captura";
										} else { 
										echo "<a href='cols_aportaciones_destacadas.php?colaborador_id=$colaborador_id&periodo=$periodo'><i class='ph-hand fs-base lh-base align-top text-primary me-1'></i> <span class='text-primary me-1'>Con captura</span></a>"; } ?>
									</td>
									<td>
										<?php 
										if ($cantidad_actividades_extraordinarias == 0) { 
										echo "<i class='ph-circle-dashed fs-base lh-base align-top  me-1'></i>Sin captura";
										} else { 
										echo "<a href='cols_actividades_extraordinarias.php?colaborador_id=$colaborador_id&periodo=$periodo'><i class='ph-hand fs-base lh-base align-top text-primary me-1'></i> <span class='text-primary me-1'>Con captura</span></a>"; } ?>
									</td>
									<td>
									<?php if ($cantidad_horas_capacitacion == 0 OR $cantidad_horas_capacitacion == '') { ?>
											<div>
											<i class="ph-circle-dashed fs-base lh-base align-top me-1"></i>
											Sin horas
											</div>
									<?php  } else { echo "<i class='ph-check-circle fs-base lh-base align-top text-success me-1'></i> <span class='text-success me-1'>$cantidad_horas_capacitacion horas</span>"; } ?>
									</td>
									<td>
									<?php if ($estatus_gerenciales < 3) { 
									echo "<i class='ph-circle-dashed fs-base lh-base align-top me-1'></i>Pendiente de jefe"; 
									} else { 
									echo "<i class='ph-check-circle fs-base lh-base align-top text-success me-1'></i> <span class='text-success me-1'>Evaluado</span>"; } ?>
									</td>
								</tr>
					<?php endforeach; ?>
	    			<?php endif; ?>


							</tbody>
						</table>
						</div>
						</div>
					<!-- /customers -->

					<?php } else { ?>


					<!-- Customers -->
			<div class="card">
			<div class="card-body">
				<table class="table table-xl">
					<thead class="thead-light">
					<tr class="bg-info text-white">
									<th>Nombre</th>
									<th>Individuales</th>
									<th>Aportaciones dest.</th>
									<th>Act. Extraordinarias</th>
									<th>Capacitación</th>
									<th>Gerencial</th>
									<th></th>
								</tr>
							</thead>
							<tbody>

					<tr>
					<?php if (count($colaboradores) === 0): ?>
					<tr><td colspan="8">EL periodo seleccionado actualmente, está cerrado, o no se cuentan con evaluaciones todavía.</td></tr>
					<?php else: ?>
					<?php foreach ($colaboradores as $c): 
					$nombre = "{$c['nombre']} {$c['apellido_paterno']} {$c['apellido_materno']}";
					$colaborador_id = $c['user_id'];
					$individuales = $c['individuales'];
					$aportaciones_destacadas = $c['aportaciones_destacadas'];
					$estatus_actividades_extraordinarias = $c['estatus_actividades_extraordinarias'];
					$estatus_aportaciones_destacadas = $c['estatus_aportaciones_destacadas'];
					$actividades_extraordinarias = $c['actividades_extraordinarias'];
					$capacitacion = $c['capacitacion'];
					$gerenciales = $c['gerenciales'];
					$prerequisitos = obtener_estatus_prerequisitos_colaborador($pdo, (int)$colaborador_id, (int)$periodo);
					$estatus_metas = (int)$prerequisitos['estatus_metas_efectivo'];
					$estatus_gerenciales = (int)$prerequisitos['estatus_gerenciales_efectivo'];
					$metas_listas = (bool)$prerequisitos['metas_listas'];
					if ((int)$estatus_gerenciales >= 3 && (float)($gerenciales ?? 0) <= 0) {
						$gerenciales = obtener_calificacion_gerencial_respaldo($pdo, $colaborador_id, $periodo);
					}


					$stmt = $pdo->prepare("SELECT COUNT(*) FROM metas WHERE user_id = ? AND periodo = ?");
					$stmt->execute([$colaborador_id, $periodo]);
					$cantidad_metas = (int)$stmt->fetchColumn();

					$stmt = $pdo->prepare("SELECT COUNT(*) FROM aportaciones_destacadas WHERE user_id = ? AND periodo = ?");
					$stmt->execute([$colaborador_id, $periodo]);
					$cantidad_aportaciones_destacadas = (int)$stmt->fetchColumn();

					$stmt = $pdo->prepare("SELECT COUNT(*) FROM actividades_extraordinarias WHERE user_id = ? AND periodo = ?");
					$stmt->execute([$colaborador_id, $periodo]);
					$cantidad_actividades_extraordinarias = (int)$stmt->fetchColumn();

					$stmt = $pdo->prepare("SELECT SUM(horas) FROM capacitacion WHERE user_id = ? AND periodo = ? AND validado = ? AND COALESCE(contabiliza_horas, 1) = 1");
					$stmt->execute([$colaborador_id, $periodo, 1]);
					$cantidad_horas_capacitacion = (float)($stmt->fetchColumn() ?? 0); 
					?>

									<td>
										<div class="d-flex align-items-center">
											<a class="d-block me-3">
												<img src="fotos/<?= htmlspecialchars($c['foto'] ?? 'default.png') ?>" width="40" height="40" class="rounded-circle" alt="">
											</a>

											<div class="flex-fill">
												<a class="fw-semibold"><?= htmlspecialchars($nombre) ?></a>
												<div class="fs-sm text-muted">
													<?= $c['puesto'] ?>
												</div>
											</div>
										</div>
									</td>
									<td>
										<?php 
										$individuales_valor = (float)($individuales ?? 0);
										$individuales_evaluado = ($individuales_valor > 0 || (int)$estatus_metas >= 3);

										if ($cantidad_metas == 0) { 
											echo "<i class='ph-circle-dashed fs-base lh-base align-top  me-1'></i>Sin captura";
										    } else if ($individuales_evaluado) {
											$calif_txt = number_format($individuales_valor, 1) . "%";
											if ($individuales_valor >= 90) {
											echo "<a href='evaluar_individuales.php?colaborador_id=$colaborador_id&periodo=$periodo'><i class='ph-check-circle fs-base lh-base align-top text-success me-1'></i> <span class='text-success me-1'>Sobresaliente: $calif_txt</span></a>";
											} elseif ($individuales_valor >= 70) {
											echo "<a href='evaluar_individuales.php?colaborador_id=$colaborador_id&periodo=$periodo'><i class='ph-check-circle fs-base lh-base align-top text-primary me-1'></i> <span class='text-primary me-1'>Satisfactorio: $calif_txt</span></a>";
											} elseif ($individuales_valor >= 60) {
											echo "<a href='evaluar_individuales.php?colaborador_id=$colaborador_id&periodo=$periodo'><i class='ph-check-circle fs-base lh-base align-top text-yellow me-1'></i> <span class='text-yellow me-1'>No satisfactorio: $calif_txt</span></a>";
											} elseif ($individuales_valor >= 50) {
											echo "<a href='evaluar_individuales.php?colaborador_id=$colaborador_id&periodo=$periodo'><i class='ph-check-circle fs-base lh-base align-top text-warning me-1'></i> <span class='text-warning me-1'>No aprobatorio: $calif_txt</span></a>";
											} else {
											echo "<a href='evaluar_individuales.php?colaborador_id=$colaborador_id&periodo=$periodo'><i class='ph-check-circle fs-base lh-base align-top text-danger me-1'></i> <span class='text-danger me-1'>Deficiente: $calif_txt</span></a>";
											}
										    } else if (!$metas_listas) {
											echo "<i class='ph-clock-countdown fs-base lh-base align-top text-warning me-1'></i><span class='text-warning me-1'>Pendiente requisitos</span>";
										    } else if ($individuales_valor == 0) { 
											echo "<a href='evaluar_individuales.php?colaborador_id=$colaborador_id&periodo=$periodo'><i class='ph-hand fs-base lh-base align-top text-primary me-1'></i> <span class='text-primary me-1'>Evaluar</span></a>";
											} else {
											echo "<a href='evaluar_individuales.php?colaborador_id=$colaborador_id&periodo=$periodo'><i class='ph-hand fs-base lh-base align-top text-primary me-1'></i> <span class='text-primary me-1'>Evaluar</span></a>";
										} ?>
									</td>
									<td>
										<?php 
										if ($cantidad_aportaciones_destacadas == 0) { 
										echo "<i class='ph-circle-dashed fs-base lh-base align-top  me-1'></i>Sin captura";
										} else if ($estatus_aportaciones_destacadas == 0) { 
										echo "<a href='cols_aportaciones_destacadas.php?colaborador_id=$colaborador_id&periodo=$periodo'><i class='ph-hand fs-base lh-base align-top text-primary me-1'></i> <span class='text-primary me-1'>Evaluar</span></a>";  
										} else { 
										echo "<a href='cols_aportaciones_destacadas.php?colaborador_id=$colaborador_id'><i class='ph-check-circle fs-base lh-base align-top text-success me-1'></i> <span class='text-success me-1'>Evaluado: +$aportaciones_destacadas</span></a>"; } ?>
									</td>
									<td>
									<?php 
										if ($cantidad_actividades_extraordinarias == 0) { 
										echo "<i class='ph-circle-dashed fs-base lh-base align-top  me-1'></i>Sin captura";
										} else if ($estatus_actividades_extraordinarias == 0) { 
										echo "<a href='cols_actividades_extraordinarias.php?colaborador_id=$colaborador_id&periodo=$periodo'><i class='ph-hand fs-base lh-base align-top text-primary me-1'></i> <span class='text-primary me-1'>Evaluar</span></a>";  
										} else { 
										echo "<a href='cols_actividades_extraordinarias.php?colaborador_id=$colaborador_id'><i class='ph-check-circle fs-base lh-base align-top text-success me-1'></i> <span class='text-success me-1'>Evaluado: +$actividades_extraordinarias</span></a>"; } ?>
									</td>
									<td>
									<?php if ($cantidad_horas_capacitacion == 0 OR $cantidad_horas_capacitacion == '') { ?>
											<div>
											<i class="ph-circle-dashed fs-base lh-base align-top me-1"></i>
											Sin horas
											</div>
									<?php  } else { echo "<i class='ph-check-circle fs-base lh-base align-top text-success me-1'></i> <span class='text-success me-1'>$cantidad_horas_capacitacion horas</span>"; } ?>
									</td>
									<td>
									<?php if ($estatus_gerenciales < 2) {
									echo "<i class='ph-circle-dashed fs-base lh-base align-top me-1'></i>Pendiente autoevaluación";
									} elseif ($estatus_gerenciales < 3) { 
									echo "<a href='evaluar_competencias_colaborador.php?colaborador=$colaborador_id&periodo=$periodo'><i class='ph-hand text-primary fs-base lh-base align-top me-1'></i> <span class='text-primary me-1'>Evaluar</span></a>"; 
									} else { echo "<a href='evaluar_competencias_colaborador.php?colaborador=$colaborador_id'><i class='ph-check-circle fs-base lh-base align-top text-success me-1'></i> <span class='text-success me-1'>Evaluado: $gerenciales</span></a>"; } ?>
									</td>
									<td class="pl-0"></td>
								</tr>
					<?php endforeach; ?>
	    			<?php endif; ?>


							</tbody>
						</table>
						</div>
						</div>
					<!-- /customers -->
						
					<?php }  ?>

					</div>
				<!-- /content area -->

				<?php require_once('assets/footer.php'); ?>

			</div>
			<!-- /inner content -->

		</div>
		<!-- /main content -->

	</div>
	<!-- /page content -->

<!-- Modal Agregar -->

</body>
</html>
