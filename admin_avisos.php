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

$tiposAviso = [
    1 => 'Todos',
    2 => 'Capacitación'
];

$msg = '';
$error = '';

// Subir nuevo aviso
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $mensaje = trim($_POST['mensaje'] ?? '');
    $tipo = isset($_POST['tipo']) ? (int) $_POST['tipo'] : 0;
    $usuario_id = (int) $_SESSION['user_id'];
    $nombre_imagen = null;

    if ($titulo === '' || $mensaje === '' || !isset($tiposAviso[$tipo])) {
        $error = 'Completa título, mensaje y tipo válido.';
    } else {
        if (!empty($_FILES['imagen']['name'])) {
            $archivo = $_FILES['imagen'];
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            $extPermitidas = ['jpg', 'jpeg', 'png', 'webp'];
            $tamanoMax = 2 * 1024 * 1024;

            if (!in_array($extension, $extPermitidas, true)) {
                $error = 'La imagen debe ser JPG, PNG o WEBP.';
            } elseif ((int)$archivo['size'] > $tamanoMax) {
                $error = 'La imagen excede el tamaño máximo de 2 MB.';
            } else {
                $nombre_imagen = uniqid('aviso_', true) . '.' . $extension;
                $rutaImagen = 'imagenes_avisos/' . $nombre_imagen;
                if (!move_uploaded_file($archivo['tmp_name'], $rutaImagen)) {
                    $error = 'No se pudo subir la imagen del aviso.';
                }
            }
        }

        if ($error === '') {
            $stmt = $pdo->prepare("INSERT INTO avisos (titulo, mensaje, imagen, usuario_id, tipo) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$titulo, $mensaje, $nombre_imagen, $usuario_id, $tipo]);
            header('Location: admin_avisos.php?ok=1');
            exit;
        }
    }
}

// Eliminar aviso
if (isset($_GET['eliminar'])) {
  $id = (int) $_GET['eliminar'];

  if ($id > 0) {
      $stmt = $pdo->prepare("SELECT imagen FROM avisos WHERE id = ?");
      $stmt->execute([$id]);
      $imagen = $stmt->fetchColumn();
      if ($imagen && file_exists("imagenes_avisos/$imagen")) {
          unlink("imagenes_avisos/$imagen");
      }

      $pdo->prepare("DELETE FROM avisos WHERE id = ?")->execute([$id]);
  }
  header("Location: admin_avisos.php?ok=2");
  exit;
}

if (isset($_GET['ok'])) {
    if ($_GET['ok'] === '1') {
        $msg = 'Aviso publicado correctamente.';
    }
    if ($_GET['ok'] === '2') {
        $msg = 'Aviso eliminado correctamente.';
    }
}

$filtroTipo = isset($_GET['tipo_filtro']) ? (int) $_GET['tipo_filtro'] : 0;
$busqueda = trim($_GET['q'] ?? '');

$where = [];
$params = [];

if (isset($tiposAviso[$filtroTipo])) {
    $where[] = 'a.tipo = :tipo';
    $params[':tipo'] = $filtroTipo;
}

if ($busqueda !== '') {
    $where[] = '(a.titulo LIKE :q OR a.mensaje LIKE :q)';
    $params[':q'] = "%{$busqueda}%";
}

$whereSql = count($where) > 0 ? (' WHERE ' . implode(' AND ', $where)) : '';

$stats = $pdo->query("SELECT COUNT(*) AS total, SUM(CASE WHEN tipo = 1 THEN 1 ELSE 0 END) AS todos, SUM(CASE WHEN tipo = 2 THEN 1 ELSE 0 END) AS capacitacion FROM avisos")->fetch(PDO::FETCH_ASSOC);

$rowsPerPage = 9;
$paginaActual = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;

$countSql = "SELECT COUNT(*) FROM avisos a {$whereSql}";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalAvisos = (int) $stmtCount->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalAvisos / $rowsPerPage));

if ($paginaActual > $totalPaginas) {
    $paginaActual = $totalPaginas;
}

$offset = ($paginaActual - 1) * $rowsPerPage;

$sqlAvisos = "
    SELECT a.*, CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS autor
    FROM avisos a
    JOIN usuarios u ON a.usuario_id = u.user_id
    {$whereSql}
    ORDER BY a.fecha DESC
    LIMIT {$rowsPerPage} OFFSET {$offset}
";

$stmtAvisos = $pdo->prepare($sqlAvisos);
$stmtAvisos->execute($params);
$avisos = $stmtAvisos->fetchAll(PDO::FETCH_ASSOC);
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
                                <span class="breadcrumb-item active">Avisos</span>
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
                        <h2 class="mb-0">Administrar Avisos</h2>
                    </div>

                    <?php if ($msg !== ''): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
                    <?php endif; ?>
                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="card card-body bg-primary text-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>Total avisos</div>
                                    <h4 class="mb-0"><?= (int) ($stats['total'] ?? 0) ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card card-body bg-info text-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>Tipo todos</div>
                                    <h4 class="mb-0"><?= (int) ($stats['todos'] ?? 0) ?></h4>
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
                    </div>

                    <div class="card mb-3">
                        <div class="card-header"><h5 class="mb-0">Nuevo aviso</h5></div>
                        <div class="card-body">
                            <form method="post" enctype="multipart/form-data" class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Título</label>
                                    <input type="text" name="titulo" class="form-control" maxlength="255" required value="<?= htmlspecialchars($_POST['titulo'] ?? '') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Tipo</label>
                                    <select name="tipo" class="form-select" required>
                                        <option value="">Seleccione</option>
                                        <?php foreach ($tiposAviso as $idTipo => $labelTipo): ?>
                                            <option value="<?= $idTipo ?>" <?= (isset($_POST['tipo']) && (int)$_POST['tipo'] === $idTipo) ? 'selected' : '' ?>><?= htmlspecialchars($labelTipo) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Imagen (opcional)</label>
                                    <input type="file" name="imagen" class="form-control" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Mensaje</label>
                                    <textarea name="mensaje" class="form-control" rows="4" required><?= htmlspecialchars($_POST['mensaje'] ?? '') ?></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">Publicar aviso</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-body">
                            <form method="get" class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label" for="tipo_filtro">Tipo</label>
                                    <select id="tipo_filtro" name="tipo_filtro" class="form-select">
                                        <option value="0">Todos</option>
                                        <?php foreach ($tiposAviso as $idTipo => $labelTipo): ?>
                                            <option value="<?= $idTipo ?>" <?= $filtroTipo === $idTipo ? 'selected' : '' ?>><?= htmlspecialchars($labelTipo) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label" for="q">Buscar</label>
                                    <input type="text" id="q" name="q" class="form-control" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Título o mensaje">
                                </div>
                                <div class="col-md-2 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                                    <a href="admin_avisos.php" class="btn btn-light w-100">Limpiar</a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="row">
                        <?php foreach ($avisos as $a): ?>
                            <div class="col-md-6 col-xl-4 mb-3">
                                <div class="card h-100 shadow-sm">
                                    <?php if (!empty($a['imagen'])): ?>
                                        <img src="imagenes_avisos/<?= htmlspecialchars($a['imagen']) ?>" class="card-img-top" style="height:180px; object-fit:cover;" alt="Imagen aviso">
                                    <?php else: ?>
                                        <img src="imagenes_avisos/cover2.jpg" class="card-img-top" style="height:180px; object-fit:cover;" alt="Imagen aviso">
                                    <?php endif; ?>
                                    <div class="card-body d-flex flex-column">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="card-title mb-0"><?= htmlspecialchars($a['titulo']) ?></h5>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($tiposAviso[(int)$a['tipo']] ?? 'N/A') ?></span>
                                        </div>
                                        <p class="text-muted small mb-2">Publicado por <strong><?= htmlspecialchars($a['autor']) ?></strong> · <?= date('d/m/Y H:i', strtotime($a['fecha'])) ?></p>
                                        <?php
                                        $mensajePlano = trim(preg_replace('/\s+/', ' ', (string) $a['mensaje']));
                                        $preview = mb_strlen($mensajePlano) > 130 ? (mb_substr($mensajePlano, 0, 130) . '...') : $mensajePlano;
                                        $imagenAviso = !empty($a['imagen']) ? ('imagenes_avisos/' . $a['imagen']) : 'imagenes_avisos/cover2.jpg';
                                        ?>
                                        <p class="card-text mb-3"><?= htmlspecialchars($preview) ?></p>
                                        <div class="mt-auto d-flex gap-2">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary btn-preview-aviso"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalPreviewAviso"
                                                data-aviso-titulo="<?= htmlspecialchars($a['titulo'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-aviso-mensaje="<?= htmlspecialchars($a['mensaje'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-aviso-imagen="<?= htmlspecialchars($imagenAviso, ENT_QUOTES, 'UTF-8') ?>"
                                                data-aviso-fecha="<?= htmlspecialchars(date('d/m/Y H:i', strtotime($a['fecha'])), ENT_QUOTES, 'UTF-8') ?>"
                                            >Vista previa</button>
                                            <a href="admin_avisos.php?eliminar=<?= (int)$a['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar este aviso?')">Eliminar</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($avisos) === 0): ?>
                            <div class="col-12"><div class="alert alert-info">No hay avisos para los filtros seleccionados.</div></div>
                        <?php endif; ?>
                    </div>

                    <?php if ($totalPaginas > 1): ?>
                        <?php $queryBase = ['tipo_filtro' => $filtroTipo, 'q' => $busqueda]; ?>
                        <nav aria-label="Paginación avisos" class="mt-2">
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?= $paginaActual <= 1 ? 'disabled' : '' ?>">
                                    <?php $prevQuery = http_build_query(array_merge($queryBase, ['pagina' => max(1, $paginaActual - 1)])); ?>
                                    <a class="page-link" href="admin_avisos.php?<?= $prevQuery ?>">Anterior</a>
                                </li>
                                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                    <?php if ($i === 1 || $i === $totalPaginas || abs($i - $paginaActual) <= 2): ?>
                                        <?php $pageQuery = http_build_query(array_merge($queryBase, ['pagina' => $i])); ?>
                                        <li class="page-item <?= $i === $paginaActual ? 'active' : '' ?>">
                                            <a class="page-link" href="admin_avisos.php?<?= $pageQuery ?>"><?= $i ?></a>
                                        </li>
                                    <?php elseif ($i === 2 && $paginaActual > 4): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php elseif ($i === $totalPaginas - 1 && $paginaActual < $totalPaginas - 3): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <li class="page-item <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>">
                                    <?php $nextQuery = http_build_query(array_merge($queryBase, ['pagina' => min($totalPaginas, $paginaActual + 1)])); ?>
                                    <a class="page-link" href="admin_avisos.php?<?= $nextQuery ?>">Siguiente</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>

                    <div class="modal fade" id="modalPreviewAviso" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Vista previa de aviso</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <img id="previewAvisoImagen" src="imagenes_avisos/cover2.jpg" class="img-fluid rounded mb-3" alt="Imagen aviso">
                                    <h5 id="previewAvisoTitulo" class="mb-2"></h5>
                                    <p class="text-muted mb-3" id="previewAvisoFecha"></p>
                                    <div id="previewAvisoMensaje" class="border rounded p-3 bg-light"></div>
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
            var modalAviso = document.getElementById('modalPreviewAviso');
            if (!modalAviso) return;

            modalAviso.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                if (!button) return;

                var titulo = button.getAttribute('data-aviso-titulo') || '';
                var mensaje = button.getAttribute('data-aviso-mensaje') || '';
                var imagen = button.getAttribute('data-aviso-imagen') || 'imagenes_avisos/cover2.jpg';
                var fecha = button.getAttribute('data-aviso-fecha') || '';

                var tituloEl = document.getElementById('previewAvisoTitulo');
                var mensajeEl = document.getElementById('previewAvisoMensaje');
                var imagenEl = document.getElementById('previewAvisoImagen');
                var fechaEl = document.getElementById('previewAvisoFecha');

                if (tituloEl) tituloEl.textContent = titulo;
                if (fechaEl) fechaEl.textContent = fecha;
                if (imagenEl) imagenEl.setAttribute('src', imagen);
                if (mensajeEl) {
                    mensajeEl.innerHTML = '';
                    mensaje.split(/\r\n|\n|\r/).forEach(function (linea, idx) {
                        if (idx > 0) mensajeEl.appendChild(document.createElement('br'));
                        mensajeEl.appendChild(document.createTextNode(linea));
                    });
                }
            });
        });
    </script>

</body>
</html>
