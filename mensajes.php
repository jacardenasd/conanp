
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

$filtro_estado = $_GET['estado'] ?? 'todos';
$busqueda = trim($_GET['q'] ?? '');

if (!in_array($filtro_estado, ['todos', 'leido', 'no_leido'], true)) {
	$filtro_estado = 'todos';
}

$where = ['m.destinatario_id = :user_id'];
$params = [':user_id' => $user_id];

if ($filtro_estado === 'leido') {
	$where[] = 'm.leido = 1';
} elseif ($filtro_estado === 'no_leido') {
	$where[] = 'm.leido = 0';
}

if ($busqueda !== '') {
	$where[] = '(m.asunto LIKE :q OR m.mensaje LIKE :q)';
	$params[':q'] = "%{$busqueda}%";
}

$whereSql = ' WHERE ' . implode(' AND ', $where);

$statsStmt = $pdo->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN leido = 0 THEN 1 ELSE 0 END) AS no_leidos FROM mensajes WHERE destinatario_id = ?");
$statsStmt->execute([$user_id]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'no_leidos' => 0];

$rowsPerPage = 25;
$countSql = "SELECT COUNT(*) FROM mensajes m {$whereSql}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalFiltrados = (int) $countStmt->fetchColumn();

$paginaActual = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
$totalPaginas = max(1, (int) ceil($totalFiltrados / $rowsPerPage));
if ($paginaActual > $totalPaginas) {
	$paginaActual = $totalPaginas;
}
$offset = ($paginaActual - 1) * $rowsPerPage;

$sql = "
	SELECT m.*, u.nombre, u.apellido_paterno, u.apellido_materno, u.foto
	FROM mensajes m
	LEFT JOIN usuarios u ON m.remitente_id = u.user_id
	{$whereSql}
	ORDER BY m.fecha DESC
	LIMIT {$rowsPerPage} OFFSET {$offset}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$mensajes_ = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
	<!-- /theme JS files -->

    <style>
    .no-leido { font-weight: bold; background-color: #f5f9ff; }
    .leido { font-weight: normal; }
  </style>

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
								<span class="breadcrumb-item active">Mensajes</span>
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

					<div class="row mb-3">
						<div class="col-md-6 col-lg-4">
							<div class="card card-body bg-primary text-white">
								<div class="d-flex justify-content-between align-items-center">
									<div>Total recibidos</div>
									<h5 class="mb-0"><?= (int) ($stats['total'] ?? 0) ?></h5>
								</div>
							</div>
						</div>
						<div class="col-md-6 col-lg-4">
							<div class="card card-body bg-warning text-white">
								<div class="d-flex justify-content-between align-items-center">
									<div>No leídos</div>
									<h5 class="mb-0"><?= (int) ($stats['no_leidos'] ?? 0) ?></h5>
								</div>
							</div>
						</div>
					</div>

					<div class="card mb-3">
						<div class="card-body">
							<form method="get" class="row g-3 align-items-end">
								<div class="col-md-3">
									<label for="estado" class="form-label">Estado</label>
									<select name="estado" id="estado" class="form-select">
										<option value="todos" <?= $filtro_estado === 'todos' ? 'selected' : '' ?>>Todos</option>
										<option value="leido" <?= $filtro_estado === 'leido' ? 'selected' : '' ?>>Leídos</option>
										<option value="no_leido" <?= $filtro_estado === 'no_leido' ? 'selected' : '' ?>>No leídos</option>
									</select>
								</div>
								<div class="col-md-6">
									<label for="q" class="form-label">Buscar</label>
									<input type="text" id="q" name="q" class="form-control" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Asunto o contenido del mensaje">
								</div>
								<div class="col-md-3 d-flex gap-2">
									<button type="submit" class="btn btn-primary w-100">Filtrar</button>
									<a href="mensajes.php" class="btn btn-light w-100">Limpiar</a>
								</div>
							</form>
						</div>
					</div>


							<!-- Messages widget -->
							<div class="card">
								<div class="card-header d-flex align-items-center">
									<h5 class="mb-0">📬 Bandeja de Entrada</h5>
								</div>

								<div class="card-body">
									<?php if (count($mensajes_) > 0): ?>
										<div class="table-responsive">
											<table class="table table-hover table-striped">
												<thead>
													<tr>
														<th>Remitente</th>
														<th>Asunto</th>
														<th>Mensaje</th>
														<th>Fecha</th>
														<th>Estado</th>
														<th>Acción</th>
													</tr>
												</thead>
												<tbody>
												<?php foreach ($mensajes_ as $m): ?>
													<?php
													$nombre = trim(($m['nombre'] ?? '') . ' ' . ($m['apellido_paterno'] ?? '') . ' ' . ($m['apellido_materno'] ?? ''));
													$mensajePlano = trim(preg_replace('/\s+/', ' ', (string) $m['mensaje']));
													$preview = mb_strlen($mensajePlano) > 85 ? (mb_substr($mensajePlano, 0, 85) . '...') : $mensajePlano;
													?>
													<tr class="<?= ((int)$m['leido'] === 0) ? 'no-leido' : 'leido' ?>">
														<td>
															<div class="d-flex align-items-center gap-2">
																<img src="fotos/<?= htmlspecialchars($m['foto'] ?? 'default.png') ?>" class="rounded-circle" width="32" height="32" alt="">
																<span><?= htmlspecialchars($nombre !== '' ? $nombre : 'Sin nombre') ?></span>
															</div>
														</td>
														<td><?= htmlspecialchars($m['asunto']) ?></td>
														<td><?= htmlspecialchars($preview) ?></td>
														<td><?= date('d/m/Y H:i', strtotime($m['fecha'])) ?></td>
														<td>
															<?php if ((int)$m['leido'] === 1): ?>
																<span class="badge bg-success">Leído</span>
															<?php else: ?>
																<span class="badge bg-warning">No leído</span>
															<?php endif; ?>
														</td>
														<td><a href="mensaje_detalle.php?id=<?= (int)$m['id'] ?>" class="btn btn-sm btn-primary">Abrir</a></td>
													</tr>
												<?php endforeach; ?>
												</tbody>
											</table>
										</div>

										<?php if ($totalPaginas > 1): ?>
											<?php $queryBase = ['estado' => $filtro_estado, 'q' => $busqueda]; ?>
											<nav aria-label="Paginación bandeja" class="mt-3">
												<ul class="pagination pagination-sm mb-0">
													<li class="page-item <?= $paginaActual <= 1 ? 'disabled' : '' ?>">
														<?php $prevQuery = http_build_query(array_merge($queryBase, ['pagina' => max(1, $paginaActual - 1)])); ?>
														<a class="page-link" href="mensajes.php?<?= $prevQuery ?>">Anterior</a>
													</li>
													<?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
														<?php if ($i === 1 || $i === $totalPaginas || abs($i - $paginaActual) <= 2): ?>
															<?php $pageQuery = http_build_query(array_merge($queryBase, ['pagina' => $i])); ?>
															<li class="page-item <?= $i === $paginaActual ? 'active' : '' ?>">
																<a class="page-link" href="mensajes.php?<?= $pageQuery ?>"><?= $i ?></a>
															</li>
														<?php elseif ($i === 2 && $paginaActual > 4): ?>
															<li class="page-item disabled"><span class="page-link">...</span></li>
														<?php elseif ($i === $totalPaginas - 1 && $paginaActual < $totalPaginas - 3): ?>
															<li class="page-item disabled"><span class="page-link">...</span></li>
														<?php endif; ?>
													<?php endfor; ?>
													<li class="page-item <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>">
														<?php $nextQuery = http_build_query(array_merge($queryBase, ['pagina' => min($totalPaginas, $paginaActual + 1)])); ?>
														<a class="page-link" href="mensajes.php?<?= $nextQuery ?>">Siguiente</a>
													</li>
												</ul>
											</nav>
										<?php endif; ?>
									<?php else: ?>
										<div class="alert alert-info mb-0">No tienes mensajes con los filtros actuales.</div>
									<?php endif; ?>
								</div>


							</div>
							<!-- /messages widget -->


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
