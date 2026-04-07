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
$periodo  = $_SESSION['periodo'];
$unidad_id  = $_SESSION['unidad_id'];

$tiposArchivo = [
	1 => 'Metas Individuales',
	2 => 'Metas Colectivas',
	3 => 'Capacitación',
	4 => 'Mi evaluación',
	5 => 'Mis colaboradores',
	6 => 'Inicio'
];

$filtroTipo = isset($_GET['tipo']) ? (int) $_GET['tipo'] : 0;
$busqueda = trim($_GET['q'] ?? '');

$where = [];
$params = [];

if (isset($tiposArchivo[$filtroTipo])) {
	$where[] = 'tipo = :tipo';
	$params[':tipo'] = $filtroTipo;
}

if ($busqueda !== '') {
	$where[] = '(titulo LIKE :q OR descripcion LIKE :q)';
	$params[':q'] = "%{$busqueda}%";
}

$whereSql = count($where) > 0 ? (' WHERE ' . implode(' AND ', $where)) : '';

$rowsPerPage = 25;
$paginaActual = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM archivos {$whereSql}");
$countStmt->execute($params);
$totalArchivos = (int) $countStmt->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalArchivos / $rowsPerPage));

if ($paginaActual > $totalPaginas) {
	$paginaActual = $totalPaginas;
}

$offset = ($paginaActual - 1) * $rowsPerPage;

$stmt = $pdo->prepare("SELECT * FROM archivos {$whereSql} ORDER BY fecha_subida DESC LIMIT {$rowsPerPage} OFFSET {$offset}");
$stmt->execute($params);
$archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
								<a href="index.php" class="breadcrumb-item"><i class="ph-house"></i></a>
								<span class="breadcrumb-item active">Inicio</span>
								<a href="#" class="breadcrumb-item">Documentos para descarga</a>
							</div>
						</div>

					</div>
				</div>
				<!-- /page header -->


				<!-- Content area -->
				<div class="content">

				<div class="card mb-3">
					<div class="card-body">
						<form method="get" class="row g-3 align-items-end">
							<div class="col-md-4">
								<label for="tipo" class="form-label">Sección</label>
								<select id="tipo" name="tipo" class="form-select">
									<option value="0">Todas</option>
									<?php foreach ($tiposArchivo as $idTipo => $textoTipo): ?>
										<option value="<?= $idTipo ?>" <?= $filtroTipo === $idTipo ? 'selected' : '' ?>><?= htmlspecialchars($textoTipo) ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-md-6">
								<label for="q" class="form-label">Buscar</label>
								<input type="text" id="q" name="q" class="form-control" placeholder="Título o descripción" value="<?= htmlspecialchars($busqueda) ?>">
							</div>
							<div class="col-md-2 d-flex gap-2">
								<button type="submit" class="btn btn-primary w-100">Filtrar</button>
								<a href="archivos_descargar.php" class="btn btn-light w-100">Limpiar</a>
							</div>
						</form>
					</div>
				</div>

				
				<div class="d-flex align-items-start mb-4">
				<img src="assets/images/avatars/E-commerce-2.png" alt="Guía" class="rounded-circle mr-3" width="70" height="70">
				<div class="bg-light border p-3 rounded shadow-sm">
					Aqui puedes consultar los archivos de apoyo y material de consulta.
				</div>
				</div>

							


			<div class="row">
				<div class="col-xl-12">
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
								$urlPdf = 'documentos/' . rawurlencode($archivo);
								$tipoTexto = $tiposArchivo[(int)($a['tipo'] ?? 0)] ?? 'General';
								$ext     = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
								$icono   = match($ext) {
									'pdf'   => 'ph-file-pdf text-danger',
									'doc', 'docx' => 'ph-file-doc text-primary',
									'xls', 'xlsx' => 'ph-file-xls text-success',
									'zip', 'rar'  => 'ph-file-zip text-warning',
									default => 'ph-file text-muted'
								};
								$fecha   = isset($a['fecha_subida']) ? date('d/m/Y', strtotime($a['fecha_subida'])) : null;
								$descripcion = trim((string)($a['descripcion'] ?? ''));
							?>
								<div class="list-group-item d-flex align-items-center justify-content-between gap-3">
									<div class="d-flex align-items-center flex-fill min-w-0">
									<i class="<?= $icono ?> me-3 fs-4"></i>
										<div class="flex-fill">
										<div class="fw-semibold"><?= htmlspecialchars($titulo) ?></div>
										<small class="text-muted d-block"><?= htmlspecialchars($tipoTexto) ?></small>
										<?php if ($descripcion !== ''): ?>
											<small class="text-muted d-block"><?= htmlspecialchars(mb_strlen($descripcion) > 90 ? (mb_substr($descripcion, 0, 90) . '...') : $descripcion) ?></small>
										<?php endif; ?>
										<?php if ($fecha): ?>
											<small class="text-muted">Publicado el <?= $fecha ?></small>
										<?php endif; ?>
										</div>
									</div>
									<div class="d-flex gap-2 flex-shrink-0">
										<button
											type="button"
											class="btn btn-outline-primary btn-sm btn-preview-pdf"
											data-bs-toggle="modal"
											data-bs-target="#modalPreviewPdfDescarga"
											data-pdf-url="<?= htmlspecialchars($urlPdf, ENT_QUOTES, 'UTF-8') ?>"
											data-pdf-titulo="<?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?>"
										>Vista previa</button>
										<a href="<?= $urlPdf ?>" target="_blank" class="btn btn-primary btn-sm" title="Descargar <?= htmlspecialchars($titulo) ?>" download>Descargar</a>
									</div>
								</div>
							<?php endforeach; ?>
							<?php if (count($archivos) === 0): ?>
								<div class="list-group-item text-muted">No hay documentos para los filtros seleccionados.</div>
							<?php endif; ?>
						</div>
					</div>
					<!-- /Lista de archivos -->

					<div class="modal fade" id="modalPreviewPdfDescarga" tabindex="-1" aria-hidden="true">
						<div class="modal-dialog modal-xl modal-dialog-scrollable">
							<div class="modal-content">
								<div class="modal-header">
									<h5 class="modal-title" id="previewPdfTituloDescarga">Vista previa PDF</h5>
									<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
								</div>
								<div class="modal-body p-0">
									<iframe id="previewPdfFrameDescarga" src="" style="width:100%; height:75vh; border:0;" title="Vista previa PDF"></iframe>
								</div>
							</div>
						</div>
					</div>

					<?php if ($totalPaginas > 1): ?>
						<?php $queryBase = ['tipo' => $filtroTipo, 'q' => $busqueda]; ?>
						<nav aria-label="Paginación documentos" class="mt-3">
							<ul class="pagination pagination-sm mb-0">
								<li class="page-item <?= $paginaActual <= 1 ? 'disabled' : '' ?>">
									<?php $prevQuery = http_build_query(array_merge($queryBase, ['pagina' => max(1, $paginaActual - 1)])); ?>
									<a class="page-link" href="archivos_descargar.php?<?= $prevQuery ?>">Anterior</a>
								</li>
								<?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
									<?php if ($i === 1 || $i === $totalPaginas || abs($i - $paginaActual) <= 2): ?>
										<?php $pageQuery = http_build_query(array_merge($queryBase, ['pagina' => $i])); ?>
										<li class="page-item <?= $i === $paginaActual ? 'active' : '' ?>"><a class="page-link" href="archivos_descargar.php?<?= $pageQuery ?>"><?= $i ?></a></li>
									<?php elseif ($i === 2 && $paginaActual > 4): ?>
										<li class="page-item disabled"><span class="page-link">...</span></li>
									<?php elseif ($i === $totalPaginas - 1 && $paginaActual < $totalPaginas - 3): ?>
										<li class="page-item disabled"><span class="page-link">...</span></li>
									<?php endif; ?>
								<?php endfor; ?>
								<li class="page-item <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>">
									<?php $nextQuery = http_build_query(array_merge($queryBase, ['pagina' => min($totalPaginas, $paginaActual + 1)])); ?>
									<a class="page-link" href="archivos_descargar.php?<?= $nextQuery ?>">Siguiente</a>
								</li>
							</ul>
						</nav>
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

	<script>
		document.addEventListener('DOMContentLoaded', function () {
			var modalPdf = document.getElementById('modalPreviewPdfDescarga');
			if (!modalPdf) return;

			modalPdf.addEventListener('show.bs.modal', function (event) {
				var button = event.relatedTarget;
				if (!button) return;

				var url = button.getAttribute('data-pdf-url') || '';
				var titulo = button.getAttribute('data-pdf-titulo') || 'Vista previa PDF';

				var iframe = document.getElementById('previewPdfFrameDescarga');
				var title = document.getElementById('previewPdfTituloDescarga');
				if (iframe) iframe.setAttribute('src', url);
				if (title) title.textContent = titulo;
			});

			modalPdf.addEventListener('hidden.bs.modal', function () {
				var iframe = document.getElementById('previewPdfFrameDescarga');
				if (iframe) iframe.setAttribute('src', '');
			});
		});
	</script>

</body>
</html>
