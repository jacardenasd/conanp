<?php
require 'config/db.php';
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

$tiposArchivo = [
	1 => 'Metas Individuales',
	2 => 'Metas Colectivas',
	3 => 'Capacitación',
	4 => 'Mi evaluación',
	5 => 'Mis colaboradores',
	6 => 'Inicio'
];

$mensaje = '';
$error = '';

// Guardar archivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
	$titulo = trim($_POST['titulo'] ?? '');
	$descripcion = trim($_POST['descripcion'] ?? '');
	$tipo = isset($_POST['tipo']) ? (int) $_POST['tipo'] : 0;
	$archivo = $_FILES['archivo'];

	if ($titulo === '' || !isset($tiposArchivo[$tipo])) {
		$error = 'Captura título y ubicación válidos.';
	} elseif (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
		$error = 'Debes seleccionar un archivo válido.';
	} else {
		$nombreOriginal = basename($archivo['name']);
		$extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
		$tamanoMax = 10 * 1024 * 1024;

		if ($extension !== 'pdf') {
			$error = 'Solo se permiten archivos en formato PDF.';
		} elseif ((int) $archivo['size'] > $tamanoMax) {
			$error = 'El archivo excede el tamaño máximo de 10 MB.';
		} else {
			$mime = '';
			if (function_exists('finfo_open')) {
				$finfo = finfo_open(FILEINFO_MIME_TYPE);
				if ($finfo) {
					$mime = finfo_file($finfo, $archivo['tmp_name']);
					finfo_close($finfo);
				}
			} elseif (function_exists('mime_content_type')) {
				$mime = mime_content_type($archivo['tmp_name']);
			}

			$esPdfPorMime = in_array($mime, ['application/pdf', 'application/x-pdf'], true);
			$esPdfPorFirma = false;
			$handle = @fopen($archivo['tmp_name'], 'rb');
			if ($handle) {
				$firma = fread($handle, 4);
				fclose($handle);
				$esPdfPorFirma = ($firma === '%PDF');
			}

			if (!$esPdfPorMime && !$esPdfPorFirma) {
				$error = 'El archivo no parece ser un PDF válido.';
			} else {
				$nombreSeguro = uniqid('doc_', true) . '.pdf';
				$ruta = 'documentos/' . $nombreSeguro;

				if (move_uploaded_file($archivo['tmp_name'], $ruta)) {
					$stmt = $pdo->prepare("INSERT INTO archivos (titulo, descripcion, nombre_archivo, tipo) VALUES (?, ?, ?, ?)");
					$stmt->execute([$titulo, $descripcion, $nombreSeguro, $tipo]);
					header('Location: admin_archivos.php?ok=1');
					exit;
				} else {
					$error = 'Error al subir el archivo.';
				}
			}
		}
	}
}

// Eliminar archivo
if (isset($_GET['eliminar'])) {
  $id = (int) $_GET['eliminar'];
  if ($id > 0) {
	  $stmt = $pdo->prepare("SELECT nombre_archivo FROM archivos WHERE id = ?");
	  $stmt->execute([$id]);
	  $archivo = $stmt->fetchColumn();
	  if ($archivo && file_exists("documentos/$archivo")) {
		  unlink("documentos/$archivo");
	  }
	  $pdo->prepare("DELETE FROM archivos WHERE id = ?")->execute([$id]);
  }
  header("Location: admin_archivos.php?ok=2");
  exit;
}

if (isset($_GET['ok'])) {
	if ($_GET['ok'] === '1') {
		$mensaje = 'Archivo cargado correctamente.';
	}
	if ($_GET['ok'] === '2') {
		$mensaje = 'Archivo eliminado correctamente.';
	}
}

$filtroTipo = isset($_GET['tipo_filtro']) ? (int) $_GET['tipo_filtro'] : 0;
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

$stats = $pdo->query("SELECT COUNT(*) AS total, SUM(CASE WHEN tipo = 6 THEN 1 ELSE 0 END) AS inicio, SUM(CASE WHEN tipo = 3 THEN 1 ELSE 0 END) AS capacitacion FROM archivos")->fetch(PDO::FETCH_ASSOC);

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

$sqlArchivos = "SELECT * FROM archivos {$whereSql} ORDER BY fecha_subida DESC LIMIT {$rowsPerPage} OFFSET {$offset}";
$stmtArchivos = $pdo->prepare($sqlArchivos);
$stmtArchivos->execute($params);
$archivos = $stmtArchivos->fetchAll(PDO::FETCH_ASSOC);
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
								<a href="#" class="breadcrumb-item">Administración</a>
								<span class="breadcrumb-item active">Archivos</span>
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

					<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
						<h2 class="mb-0">Administrar Archivos</h2>
					</div>

					<?php if ($mensaje !== ''): ?>
						<div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
					<?php endif; ?>
					<?php if ($error !== ''): ?>
						<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
					<?php endif; ?>

					<div class="row mb-3">
						<div class="col-md-4">
							<div class="card card-body bg-primary text-white">
								<div class="d-flex justify-content-between align-items-center">
									<div>Total archivos</div>
									<h4 class="mb-0"><?= (int) ($stats['total'] ?? 0) ?></h4>
								</div>
							</div>
						</div>
						<div class="col-md-4">
							<div class="card card-body bg-success text-white">
								<div class="d-flex justify-content-between align-items-center">
									<div>Capacitación</div>
									<h4 class="mb-0"><?= (int) ($stats['capacitacion'] ?? 0) ?></h4>
								</div>
							</div>
						</div>
						<div class="col-md-4">
							<div class="card card-body bg-info text-white">
								<div class="d-flex justify-content-between align-items-center">
									<div>Inicio</div>
									<h4 class="mb-0"><?= (int) ($stats['inicio'] ?? 0) ?></h4>
								</div>
							</div>
						</div>
					</div>

					<div class="card mb-3">
						<div class="card-header"><h5 class="mb-0">Subir PDF</h5></div>
						<div class="card-body">
							<p class="mb-3">Carga documentos de apoyo para las secciones del sistema. Solo se permiten archivos PDF (máx. 10 MB).</p>
							<form method="post" enctype="multipart/form-data" class="row g-3">
								<div class="col-md-6">
									<label class="form-label">Título</label>
									<input type="text" name="titulo" class="form-control" maxlength="255" required value="<?= htmlspecialchars($_POST['titulo'] ?? '') ?>">
								</div>
								<div class="col-md-3">
									<label class="form-label">Ubicación</label>
									<select name="tipo" id="tipo" class="form-select" required>
										<option value="">Selecciona ubicación</option>
										<?php foreach ($tiposArchivo as $idTipo => $textoTipo): ?>
											<option value="<?= $idTipo ?>" <?= (isset($_POST['tipo']) && (int)$_POST['tipo'] === $idTipo) ? 'selected' : '' ?>><?= htmlspecialchars($textoTipo) ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="col-md-3">
									<label class="form-label">Archivo PDF</label>
									<input type="file" name="archivo" class="form-control" accept="application/pdf,.pdf" required>
								</div>
								<div class="col-12">
									<label class="form-label">Descripción</label>
									<textarea name="descripcion" class="form-control" rows="3"><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
								</div>
								<div class="col-12">
									<button type="submit" class="btn btn-success">Cargar archivo</button>
								</div>
							</form>
						</div>
					</div>

					<div class="card mb-3">
						<div class="card-body">
							<form method="get" class="row g-3 align-items-end">
								<div class="col-md-3">
									<label for="tipo_filtro" class="form-label">Ubicación</label>
									<select id="tipo_filtro" name="tipo_filtro" class="form-select">
										<option value="0">Todas</option>
										<?php foreach ($tiposArchivo as $idTipo => $textoTipo): ?>
											<option value="<?= $idTipo ?>" <?= $filtroTipo === $idTipo ? 'selected' : '' ?>><?= htmlspecialchars($textoTipo) ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="col-md-7">
									<label for="q" class="form-label">Buscar</label>
									<input type="text" id="q" name="q" class="form-control" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Título o descripción">
								</div>
								<div class="col-md-2 d-flex gap-2">
									<button type="submit" class="btn btn-primary w-100">Filtrar</button>
									<a href="admin_archivos.php" class="btn btn-light w-100">Limpiar</a>
								</div>
							</form>
						</div>
					</div>

					<div class="card">
						<div class="card-body">
							<div class="table-responsive">
								<table class="table table-hover table-striped">
									<thead>
										<tr>
											<th>Título</th>
											<th>Descripción</th>
											<th>Archivo</th>
											<th>Fecha</th>
											<th>Ubicación</th>
											<th>Acciones</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($archivos as $a): ?>
											<?php
											$descPlano = trim(preg_replace('/\s+/', ' ', (string)($a['descripcion'] ?? '')));
											$descPreview = mb_strlen($descPlano) > 100 ? (mb_substr($descPlano, 0, 100) . '...') : $descPlano;
											?>
											<tr>
												<td><?= htmlspecialchars($a['titulo']) ?></td>
												<td><?= htmlspecialchars($descPreview) ?></td>
												<td class="d-flex gap-2">
													<a href="documentos/<?= rawurlencode($a['nombre_archivo']) ?>" class="btn btn-primary btn-sm" target="_blank">Ver PDF</a>
													<button
														type="button"
														class="btn btn-outline-primary btn-sm btn-preview-pdf"
														data-bs-toggle="modal"
														data-bs-target="#modalPreviewPdf"
														data-pdf-url="documentos/<?= rawurlencode($a['nombre_archivo']) ?>"
														data-pdf-titulo="<?= htmlspecialchars($a['titulo'], ENT_QUOTES, 'UTF-8') ?>"
													>Vista previa</button>
												</td>
												<td><?= date('d/m/Y H:i', strtotime($a['fecha_subida'])) ?></td>
												<td><span class="badge bg-secondary"><?= htmlspecialchars($tiposArchivo[(int)$a['tipo']] ?? 'No definido') ?></span></td>
												<td><a href="admin_archivos.php?eliminar=<?= (int)$a['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar este archivo?')">Eliminar</a></td>
											</tr>
										<?php endforeach; ?>
										<?php if (count($archivos) === 0): ?>
											<tr><td colspan="6" class="text-center text-muted">No hay archivos para los filtros seleccionados.</td></tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>

							<?php if ($totalPaginas > 1): ?>
								<?php $queryBase = ['tipo_filtro' => $filtroTipo, 'q' => $busqueda]; ?>
								<nav aria-label="Paginación archivos" class="mt-3">
									<ul class="pagination pagination-sm mb-0">
										<li class="page-item <?= $paginaActual <= 1 ? 'disabled' : '' ?>">
											<?php $prevQuery = http_build_query(array_merge($queryBase, ['pagina' => max(1, $paginaActual - 1)])); ?>
											<a class="page-link" href="admin_archivos.php?<?= $prevQuery ?>">Anterior</a>
										</li>
										<?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
											<?php if ($i === 1 || $i === $totalPaginas || abs($i - $paginaActual) <= 2): ?>
												<?php $pageQuery = http_build_query(array_merge($queryBase, ['pagina' => $i])); ?>
												<li class="page-item <?= $i === $paginaActual ? 'active' : '' ?>"><a class="page-link" href="admin_archivos.php?<?= $pageQuery ?>"><?= $i ?></a></li>
											<?php elseif ($i === 2 && $paginaActual > 4): ?>
												<li class="page-item disabled"><span class="page-link">...</span></li>
											<?php elseif ($i === $totalPaginas - 1 && $paginaActual < $totalPaginas - 3): ?>
												<li class="page-item disabled"><span class="page-link">...</span></li>
											<?php endif; ?>
										<?php endfor; ?>
										<li class="page-item <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>">
											<?php $nextQuery = http_build_query(array_merge($queryBase, ['pagina' => min($totalPaginas, $paginaActual + 1)])); ?>
											<a class="page-link" href="admin_archivos.php?<?= $nextQuery ?>">Siguiente</a>
										</li>
									</ul>
								</nav>
							<?php endif; ?>
						</div>
					</div>

					<div class="modal fade" id="modalPreviewPdf" tabindex="-1" aria-hidden="true">
						<div class="modal-dialog modal-xl modal-dialog-scrollable">
							<div class="modal-content">
								<div class="modal-header">
									<h5 class="modal-title" id="previewPdfTitulo">Vista previa PDF</h5>
									<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
								</div>
								<div class="modal-body p-0">
									<iframe id="previewPdfFrame" src="" style="width:100%; height:75vh; border:0;" title="Vista previa PDF"></iframe>
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

	<script>
		document.addEventListener('DOMContentLoaded', function () {
			var modalPdf = document.getElementById('modalPreviewPdf');
			if (!modalPdf) return;

			modalPdf.addEventListener('show.bs.modal', function (event) {
				var button = event.relatedTarget;
				if (!button) return;

				var url = button.getAttribute('data-pdf-url') || '';
				var titulo = button.getAttribute('data-pdf-titulo') || 'Vista previa PDF';

				var iframe = document.getElementById('previewPdfFrame');
				var title = document.getElementById('previewPdfTitulo');
				if (iframe) iframe.setAttribute('src', url);
				if (title) title.textContent = titulo;
			});

			modalPdf.addEventListener('hidden.bs.modal', function () {
				var iframe = document.getElementById('previewPdfFrame');
				if (iframe) iframe.setAttribute('src', '');
			});
		});
	</script>

</body>
</html>
