<?php
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
checkLogin();

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');
$periodo  = $_SESSION['periodo'];
$unidad_id  = $_SESSION['unidad_id'];
$user_id = $_SESSION['user_id'];
$tipo_usuario = $_SESSION['tipo_usuario'] ?? null;
asegurar_columna_capacitacion_contabiliza($pdo);

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);

// Determinar si usuario ve el recuadro de Avance de Evaluación
// Solo SPC (tipo_usuario = 1) y Primer Nivel de Ingreso (tipo_usuario = 2) ven el recuadro
$tipos_avance = obtener_variable('mostrar_avance_evaluacion_tipos') ?? '1,2';
$tipos_permitidos = array_map('intval', explode(',', $tipos_avance));
$mostrar_avance = (isset($tipo_usuario) && !empty($tipo_usuario) && in_array($tipo_usuario, $tipos_permitidos));


$archivos = $pdo->query("SELECT * FROM archivos ORDER BY fecha_subida DESC")->fetchAll();
$avisos = $pdo->query("SELECT a.*, CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS autor FROM avisos a JOIN usuarios u ON a.usuario_id = u.user_id WHERE tipo = 1 ORDER BY a.fecha DESC limit 4")->fetchAll();

$stmt = $pdo->prepare("SELECT m.id AS mensaje_id, m.asunto, m.mensaje, m.fecha, u.nombre AS remitente, u.foto, u.estatus, TIME(m.fecha) AS hora, ( SELECT COUNT(*)  FROM mensajes_respuestas r  WHERE r.mensaje_id = m.id AND r.remitente_id = :uid AND r.leido = 0 ) AS mensajes_respuestas_no_leidas FROM mensajes m JOIN usuarios u ON m.remitente_id = u.user_id WHERE m.destinatario_id = :uid ORDER BY m.fecha DESC LIMIT 4");
$stmt->execute(['uid' => $user_id]);
$mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM calificaciones_colectivas WHERE unidad_id = ? AND periodo = ?");
$stmt->execute([$unidad_id, $periodo]);
$resultado = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM calificaciones WHERE user_id = ? AND periodo = ?");
$stmt->execute([$user_id, $periodo]);
$resultado_ind = $stmt->fetch(PDO::FETCH_ASSOC);

// Validar competencias
$stmt = $pdo->prepare("SELECT COUNT(id) FROM competencias_evaluacion WHERE user_id = ? AND periodo = ?");
$stmt->execute([$user_id, $periodo]);
$gerenciales = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($resultado_ind) 
{
	$estatus_metas = (int)($resultado_ind['estatus_metas'] ?? 0);
	$estatus_aportaciones_destacadas = (int)($resultado_ind['estatus_aportaciones_destacadas'] ?? 0);
	$estatus_actividades_extraordinarias = (int)($resultado_ind['estatus_actividades_extraordinarias'] ?? 0);
	$estatus_gerenciales = (int)($resultado_ind['estatus_gerenciales'] ?? 0);
} else {
	$estatus_metas = 0;
	$estatus_aportaciones_destacadas = 0;
	$estatus_actividades_extraordinarias = 0;
	$estatus_gerenciales = 0;
}
if ($resultado AND isset($resultado)) 
{
	$estatus_colectivas = $resultado['estatus'];
} else {
	$estatus_colectivas = 0;
}

// Mapear estados a etiquetas y color del semáforo (rojo/amarillo/verde)
$map_metas = [
	0 => ['color' => 'bg-danger',  'texto' => 'Sin captura'],
	1 => ['color' => 'bg-warning', 'texto' => 'Capturadas'],
	2 => ['color' => 'bg-warning', 'texto' => 'Con resultado propuesto'],
	3 => ['color' => 'bg-success', 'texto' => 'Calificadas'],
];

$map_colectivas = [
	0 => ['color' => 'bg-danger',  'texto' => 'Sin captura'],
	1 => ['color' => 'bg-warning', 'texto' => 'Capturadas'],
	2 => ['color' => 'bg-success', 'texto' => 'Calificadas'],
];

$map_aportaciones = [
	0 => ['color' => 'bg-danger',  'texto' => 'Sin captura/validación'],
	1 => ['color' => 'bg-warning', 'texto' => 'Capturadas/en validación'],
	2 => ['color' => 'bg-success', 'texto' => 'Validadas'],
	3 => ['color' => 'bg-success', 'texto' => 'Validadas'],
];

$map_actividades = $map_aportaciones;

$map_gerenciales = [
	0 => ['color' => 'bg-danger',  'texto' => 'Sin captura'],
	1 => ['color' => 'bg-warning', 'texto' => 'Capturada'],
	2 => ['color' => 'bg-warning', 'texto' => 'En revisión del jefe'],
	3 => ['color' => 'bg-success', 'texto' => 'Evaluada y aprobada'],
];

// Fallbacks si no hay clave
$meta_ind = $map_metas[$estatus_metas] ?? $map_metas[0];
$meta_col = $map_colectivas[$estatus_colectivas] ?? $map_colectivas[0];
$aport = $map_aportaciones[$estatus_aportaciones_destacadas] ?? $map_aportaciones[0];
$act = $map_actividades[$estatus_actividades_extraordinarias] ?? $map_actividades[0];
$ger = $map_gerenciales[$estatus_gerenciales] ?? $map_gerenciales[0];

// Validar suma de horas
$stmt = $pdo->prepare("SELECT SUM(horas) FROM capacitacion WHERE user_id = ? AND periodo = ? AND validado = 1 AND COALESCE(contabiliza_horas, 1) = 1");
$stmt->execute([$user_id, $periodo]);
$suma = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title><?php echo $nombre_sistema; ?></title>

	<!-- Global stylesheets -->
	<link href="assets/fonts/inter/inter.css" rel="stylesheet" type="text/css">
	<link href="assets/icons/phosphor/styles.min.css" rel="stylesheet" type="text/css">
	<link href="assets/css/ltr/all.min.css" id="stylesheet" rel="stylesheet" type="text/css">
	<!-- /global stylesheets -->

	<!-- Core JS files -->
		<script src="assets/js/bootstrap/bootstrap.bundle.min.js"></script>
	<link href="assets/icons/icomoon/styles.min.css" rel="stylesheet" type="text/css">

	<link href="assets/icons/icomoon/styles.min.css" rel="stylesheet" type="text/css">
	<!-- /core JS files -->

	<!-- Theme JS files -->
	<script src="assets/js/app.js"></script>
	<!-- /theme JS files -->

</head>

<body>

<?php require_once('assets/main_navbar.php'); ?>


	<!-- Page content -->
	<div class="page-content">

	<?php if (isset($_SESSION['user_id'])) { ?>

		<?php require_once('assets/main_navigation.php'); ?>

		<?php } ?>

		<!-- Main content -->
		<div class="content-wrapper">

			<!-- Inner content -->
			<div class="content-inner">

				<!-- Page header -->
				<div class="page-header page-header-light shadow">

					<div class="page-header-content d-lg-flex border-top">
						<div class="d-flex">
							<div class="breadcrumb py-2">
								<a href="#" class="breadcrumb-item"><i class="ph-house"></i></a>
								<span class="breadcrumb-item active">Inicio</span>
							</div>
						</div>

					</div>
				</div>
				<!-- /page header -->


				<!-- Content area -->
				<div class="content">

				
				<div class="d-flex align-items-start mb-4">
				<img src="assets/images/avatars/E-commerce-2.png" alt="Guía" class="rounded-circle mr-3" width="70" height="70">
				<div class="bg-light border p-3 rounded shadow-sm">
					Te damos la bienvenida a nuestro Sistema de Evaluación del Desempeño en la CONANP.
				</div>
				</div>

							
	<div class="card">
	<div class="card-header d-flex align-items-center">
		<i class="ph-user-circle fs-3 text-success me-2"></i>
		<h5 class="mb-0">Bienvenido(a) al Sistema de Evaluación del Desempeño</h5>
	</div>

	<div class="card-body">
		<p>
			La <strong>Evaluación del Desempeño</strong> es el mecanismo institucional para medir y valorar el rendimiento y productividad de las personas servidoras públicas de carrera, e identificar el desarrollo del capital humano y su contribución a los objetivos institucionales. Se considera:
		</p>

		<ul class="list-unstyled mb-4">
			<li class="mb-2"><i class="ph-check-circle text-success me-2"></i>
				<strong>Valoración del cumplimiento individual</strong> de funciones y metas del puesto que ocupa o haya ocupado provisionalmente, con base en indicadores cuantificables y verificables.
			</li>
			<li class="mb-2"><i class="ph-check-circle text-success me-2"></i>
				<strong>Valoración cuantitativa</strong> del cumplimiento de los objetivos y metas asignados conforme a sus funciones.
			</li>
			<li><i class="ph-check-circle text-success me-2"></i>
				<strong>Valoración cualitativa</strong> del comportamiento profesional, capacidades y aplicación de los valores del Sistema en el cumplimiento de metas.
			</li>
		</ul>

		<p class="mb-0">
			<i class="ph-info text-muted me-2"></i> Para dudas o aclaraciones, comunícate al teléfono <strong>55 5449 7000</strong> extensiones <strong>17005, 17097 y 17218</strong> o al correo <a href="mailto:capacitacion@conanp.gob.mx" class="text-decoration-underline">capacitacion@conanp.gob.mx</a>.
		</p>
	</div>
</div>

<?php if ($_SESSION['tipo_usuario'] == 1 OR $_SESSION['tipo_usuario'] == 2) { ?>

<div class="card">
	<div class="card-header d-flex align-items-center">
		<i class="ph-traffic-signal fs-4 text-primary me-2"></i>
		<h5 class="mb-0">Avance de evaluación</h5>
	</div>

	<div class="card-body">
		<p class="text-muted">Consulta el estatus de cada sección de tu evaluación:</p>
		<!-- SOLO SE MUESTRA PARA PERSONAL SPC Y PRIMER NIVEL DE INGRESO -->
		<?php if ($mostrar_avance): ?>
		<div class="row g-3">
					<div class="col-md-6 d-flex align-items-center">
				<span class="rounded-circle <?php echo $meta_ind['color']; ?> me-3" style="width: 20px; height: 20px; display: inline-block;"></span>
				<span><b>Metas individuales:</b> <?php echo $meta_ind['texto']; ?>.</span>
					</div>
					<div class="col-md-6 d-flex align-items-center">
				<span class="rounded-circle <?php echo $meta_col['color']; ?> me-3" style="width: 20px; height: 20px; display: inline-block;"></span>
				<span><b>Metas colectivas:</b> <?php echo $meta_col['texto']; ?>.</span>
					</div>
					<div class="col-md-6 d-flex align-items-center">
				<span class="rounded-circle <?php echo $aport['color']; ?> me-3" style="width: 20px; height: 20px; display: inline-block;"></span>
				<span><b>Aportaciones Destacadas:</b> <?php echo $aport['texto']; ?>.</span>
				</div>
				<div class="col-md-6 d-flex align-items-center">
				<span class="rounded-circle <?php echo $act['color']; ?> me-3" style="width: 20px; height: 20px; display: inline-block;"></span>
				<span><b>Actividades Extraordinarias:</b> <?php echo $act['texto']; ?>.</span>
				</div>
				<div class="col-md-6 d-flex align-items-center">
				<span class="rounded-circle <?php echo $ger['color']; ?> me-3" style="width: 20px; height: 20px; display: inline-block;"></span>
				<span><b>Gerenciales:</b> <?php echo $ger['texto']; ?>.</span>
				</div>
				<div class="col-md-6 d-flex align-items-center">
				<?php if ($suma >= 40) {?>
					<span class="rounded-circle bg-success me-3" style="width: 20px; height: 20px; display: inline-block;"></span>
					<span><b>Capacitación:</b> <?php echo $suma ?> horas reportadas y validadas.</span>
				<?php } else if ($suma > 0) { ?>
					<span class="rounded-circle bg-warning me-3" style="width: 20px; height: 20px; display: inline-block;"></span>
					<span><b>Capacitación:</b> <?php echo $suma ?> horas reportadas y validadas (pendiente completar 40).</span>
				<?php } else {?>
					<span class="rounded-circle bg-danger me-3" style="width: 20px; height: 20px; display: inline-block;"></span>
					<span><b>Capacitación:</b> Sin horas reportadas.</span>
				<?php }  ?>
				</div>
					</div>


		<hr class="my-4">

		<div class="d-flex justify-content-start flex-wrap gap-3">
			<span class="d-flex align-items-center">
				<span class="bg-success rounded-circle me-2" style="width: 15px; height: 15px;"></span> Completado
			</span>
			<span class="d-flex align-items-center">
				<span class="bg-warning rounded-circle me-2" style="width: 15px; height: 15px;"></span> En proceso
			</span>
			<span class="d-flex align-items-center">
				<span class="bg-danger rounded-circle me-2" style="width: 15px; height: 15px;"></span> No iniciado
			</span>
		</div>
		<?php else: ?>
		<div class="alert alert-info">
			<i class="ph-info me-2"></i>
			<strong>Acceso limitado:</strong> Este recuadro solo es visible para Personal SPC y Primer Nivel de Ingreso.
		</div>
		<?php endif; ?>
	</div>
</div>

<?php } ?>

<?php
// Initialize columns
$col1 = [];
$col2 = [];

// Distribute avisos into two columns
foreach ($avisos as $index => $aviso) {
	if ($index % 2 == 0) {
		$col1[] = $aviso;
	} else {
		$col2[] = $aviso;
	}
}

function renderAvisoMedia($a, $id) {
	if (empty($a)) return;
	
	$imagen = (!empty($a['imagen']) && file_exists("imagenes_avisos/" . $a['imagen'])) 
		? "imagenes_avisos/" . htmlspecialchars($a['imagen']) 
		: "imagenes_avisos/cover2.jpg"; // Fallback
	$extracto = substr(strip_tags($a['mensaje']), 0, 120) . '...';
	?>
	<div class="d-sm-flex align-items-sm-start mb-3">
		<a href="#" class="d-inline-block position-relative me-sm-3 mb-3 mb-sm-0" data-bs-toggle="modal" data-bs-target="#modalAviso<?= $id ?>">
			<img src="<?= $imagen ?>" class="flex-shrink-0 rounded" height="100" alt="">
		</a>

		<div class="flex-fill">
			<h6 class="mb-1"><a href="#" data-bs-toggle="modal" data-bs-target="#modalAviso<?= $id ?>"><?= htmlspecialchars($a['titulo']) ?></a></h6>
			<ul class="list-inline list-inline-bullet text-muted mb-2">
				<li class="list-inline-item">Publicado por <?= htmlspecialchars($a['autor']) ?></li>
				<li class="list-inline-item"><?= date('d/m/Y', strtotime($a['fecha'])) ?></li>
			</ul>
			<?= htmlspecialchars($extracto) ?>
		</div>
	</div>

	<!-- Modal -->
	<div class="modal fade" id="modalAviso<?= $id ?>" tabindex="-1" aria-labelledby="modalLabel<?= $id ?>" aria-hidden="true">
		<div class="modal-dialog modal-lg modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="modalLabel<?= $id ?>"><?= htmlspecialchars($a['titulo']) ?></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body">
					<?php if (!empty($a['imagen']) && file_exists("imagenes_avisos/" . $a['imagen'])): ?>
						<img src="imagenes_avisos/<?= htmlspecialchars($a['imagen']) ?>" class="img-fluid mb-3 rounded">
					<?php endif; ?>
					<p><?= convertir_urls_en_links(nl2br($a['mensaje'])); ?></p>
				</div>
				<div class="modal-footer">
					<small class="text-muted">Publicado por <?= htmlspecialchars($a['autor']) ?> el <?= date('d/m/Y', strtotime($a['fecha'])) ?></small>
				</div>
			</div>
		</div>
	</div>
	<?php
}
?>

<div class="card">
	<div class="card-header">
		<h5 class="mb-0">📢 Avisos y Noticias</h5>
	</div>
	<div class="card-body">
		<div class="row">
			<!-- Columna 1 -->
			<div class="col-xl-6">
				<?php foreach ($col1 as $i => $a) renderAvisoMedia($a, 'col1_' . $i); ?>
			</div>

			<!-- Columna 2 -->
			<div class="col-xl-6">
				<?php foreach ($col2 as $i => $a) renderAvisoMedia($a, 'col2_' . $i); ?>
			</div>
		</div>
	</div>
</div>




				<div class="card">
					<div class="card-header">
						<h5 class="mb-0">💬 Mensajes recientes</h5>
					</div>

					<div class="card-body">
						<?php if (empty($mensajes)): ?>
							<p class="text-muted">No tienes mensajes recientes.</p>
						<?php else: ?>
							<?php foreach ($mensajes as $m): ?>
								<div class="d-flex align-items-start mb-3">
									<div class="status-indicator-container me-3 position-relative">
										<img src="fotos/<?php if ($m['foto'] != '') { echo $m['foto']; } else { echo 'default.png'; } ?>" class="rounded-circle" width="40" height="40" alt="">
										<span class="status-indicator 
											<?= match ($m['estatus']) {
												'online' => 'bg-success',
												'offline' => 'bg-secondary',
												'away' => 'bg-warning',
												default => 'bg-danger'
											} ?>"></span>
										<?php if ($m['mensajes_respuestas_no_leidas'] > 0): ?>
											<span class="badge bg-warning text-black position-absolute top-0 start-100 translate-middle rounded-pill">
												<?= $m['mensajes_respuestas_no_leidas'] ?>
											</span>
										<?php endif; ?>
									</div>

									<div class="flex-fill">
										<div class="d-flex justify-content-between align-items-center">
											<div class="fw-semibold"><a href="#"><?= htmlspecialchars($m['remitente']) ?></a></div>
											<span class="fs-sm text-muted"><?= $m['hora'] ?></span>
										</div>
										<?= htmlspecialchars(substr(strip_tags($m['mensaje']), 0, 60)) ?>...
									</div>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>


<div class="row">

				<div class="col-xl-6">
					<!-- Lista de archivos -->
					<div class="card">
						<div class="card-header d-flex align-items-center">
							<i class="ph-folder me-2 fs-4 text-primary"></i>
							<h5 class="mb-0">Documentos disponibles para descarga</h5>
						</div>

						<div class="list-group list-group-flush">
							<?php foreach ($archivos as $a): 
								$archivo = $a['nombre_archivo'];
								$titulo  = $a['titulo'];
								$ext     = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
								$icono   = match($ext) {
									'pdf'   => 'ph-file-pdf text-danger',
									'doc', 'docx' => 'ph-file-doc text-primary',
									'xls', 'xlsx' => 'ph-file-xls text-success',
									'zip', 'rar'  => 'ph-file-zip text-warning',
									default => 'ph-file text-muted'
								};
								$fecha   = isset($a['fecha_subida']) ? date('d/m/Y', strtotime($a['fecha_subida'])) : null;
							?>
								<a href="documentos/<?= rawurlencode($archivo) ?>" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center" title="Descargar <?= $titulo ?>" download>
									<i class="<?= $icono ?> me-3 fs-4"></i>
									<div class="flex-fill">
										<div class="fw-semibold"><?= htmlspecialchars($titulo) ?></div>
										<?php if ($fecha): ?>
											<small class="text-muted">Publicado el <?= $fecha ?></small>
										<?php endif; ?>
									</div>
									<i class="ph-download text-muted ms-2"></i>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
					<!-- /Lista de archivos -->
				</div>



				<div class="col-xl-6">
					<?php if ($_SESSION['tipo_usuario'] == 1) {
						$eventos = $pdo->query("SELECT * FROM calendario_evaluacion WHERE tipo = 1  AND visible = 1 ORDER BY id")->fetchAll(); 
						} else {
						$eventos = $pdo->query("SELECT * FROM calendario_evaluacion WHERE tipo != 1 AND visible = 1 ORDER BY id")->fetchAll(); 
						}
					?>

					<div class="card">
						<div class="card-header d-flex align-items-center">
							<i class="ph-calendar-blank me-2 fs-4 text-primary"></i>
							<h5 class="mb-0">Calendario de evaluación</h5>
						</div>

						<div class="card-body">
							<?php if (empty($eventos)): ?>
								<p class="text-muted mb-0">No hay eventos registrados en el calendario.</p>
							<?php else: ?>
								<p class="text-muted">A continuación se muestra el calendario de evaluación de desempeño.</p>
								
								<div class="table-responsive">
									<table class="table table-sm table-striped table-borderless align-middle">
										<thead class="table-light">
											<tr>
												<th>Etapa / Periodo</th>
												<?php if ($_SESSION['tipo_usuario'] != 1) { ?>
												<th>Inicio Evaluación</th>
												<th>Fin Evaluación</th>
												<?php } ?>
												<th>Inicio Evaluación</th>
												<th>Fin Evaluación</th>
											</tr>
										</thead>
										<tbody>
											<?php foreach ($eventos as $e): ?>
												<tr>
													<td class="fw-semibold"><?= htmlspecialchars($e['titulo']) ?></td>
													<?php if ($_SESSION['tipo_usuario'] != 1) { ?>
													<td><?= date('d/m/Y', strtotime($e['fecha_inicio_captura'])) ?></td>
													<td><?= date('d/m/Y', strtotime($e['fecha_fin_captura'])) ?></td>
													<?php } ?>
													<td><?= date('d/m/Y', strtotime($e['fecha_inicio_evaluacion'])) ?></td>
													<td><?= date('d/m/Y', strtotime($e['fecha_fin_evaluacion'])) ?></td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>
							<?php endif; ?>
						</div>
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

</body>
</html>
