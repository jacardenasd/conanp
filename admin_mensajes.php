
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

// Obtener lista de usuarios para filtro
$usuarios = $pdo->query("
    SELECT user_id, CONCAT(nombre, ' ', apellido_paterno, ' ', apellido_materno) AS nombre_completo 
    FROM usuarios 
    ORDER BY nombre ASC
")->fetchAll();

$filtro_usuario = isset($_GET['usuario']) ? (int) $_GET['usuario'] : 0;
$filtro_estado = $_GET['estado'] ?? 'todos';
$busqueda = trim($_GET['q'] ?? '');

if (!in_array($filtro_estado, ['todos', 'leido', 'no_leido'], true)) {
	$filtro_estado = 'todos';
}

$fromSql = "
	FROM mensajes m
	JOIN usuarios r ON m.remitente_id = r.user_id
	JOIN usuarios d ON m.destinatario_id = d.user_id
	LEFT JOIN (
		SELECT mensaje_id, COUNT(*) AS total_respuestas
		FROM mensajes_respuestas
		GROUP BY mensaje_id
	) mr ON mr.mensaje_id = m.id
";

$where = [];
$params = [];

if ($filtro_usuario > 0) {
	$where[] = "(m.remitente_id = :usuario OR m.destinatario_id = :usuario)";
	$params[':usuario'] = $filtro_usuario;
}

if ($filtro_estado === 'leido') {
	$where[] = "m.leido = 1";
} elseif ($filtro_estado === 'no_leido') {
	$where[] = "m.leido = 0";
}

if ($busqueda !== '') {
	$where[] = "(m.asunto LIKE :q OR m.mensaje LIKE :q)";
	$params[':q'] = "%{$busqueda}%";
}

$whereSql = count($where) > 0 ? (' WHERE ' . implode(' AND ', $where)) : '';

$statsSql = "
	SELECT
		COUNT(*) AS total,
		SUM(CASE WHEN m.leido = 0 THEN 1 ELSE 0 END) AS no_leidos,
		SUM(CASE WHEN COALESCE(mr.total_respuestas, 0) > 0 THEN 1 ELSE 0 END) AS con_respuestas
	{$fromSql}
	{$whereSql}
";

$stmtStats = $pdo->prepare($statsSql);
$stmtStats->execute($params);
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'no_leidos' => 0, 'con_respuestas' => 0];

$rowsPerPage = 25;
$paginaActual = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
$totalMensajes = (int) ($stats['total'] ?? 0);
$totalPaginas = max(1, (int) ceil($totalMensajes / $rowsPerPage));

if ($paginaActual > $totalPaginas) {
	$paginaActual = $totalPaginas;
}

$offset = ($paginaActual - 1) * $rowsPerPage;

$sqlMensajes = "
	SELECT
		m.*,
		CONCAT(r.nombre, ' ', r.apellido_paterno, ' ', r.apellido_materno) AS remitente,
		CONCAT(d.nombre, ' ', d.apellido_paterno, ' ', d.apellido_materno) AS destinatario,
		COALESCE(mr.total_respuestas, 0) AS total_respuestas
	{$fromSql}
	{$whereSql}
	ORDER BY m.fecha DESC
	LIMIT {$rowsPerPage} OFFSET {$offset}
";

$stmt = $pdo->prepare($sqlMensajes);
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
	<script src="assets/js/vendor/forms/selects/select2.min.js"></script>

	<script src="assets/js/app.js"></script>
	<script src="assets/demo/pages/components_modals.js"></script>
    <script src="assets/demo/pages/components_buttons.js"></script>
	<script src="assets/demo/pages/datatables_basic.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
	<script src="assets/demo/pages/form_select2.js"></script>

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
								<a href="#" class="breadcrumb-item">Metas Individuales</a>
								<span class="breadcrumb-item active">Agregar Meta</span>
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

				<div class="container mt-4">
					<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
						<h2 class="mb-0">Mensajes del Sistema</h2>
						<a class="btn btn-success" href="mensajes_redactar.php"><i class="ph-paper-plane-tilt me-1"></i> Redactar nuevo</a>
					</div>

					<div class="row mb-4">
						<div class="col-md-4">
							<div class="card card-body bg-primary text-white">
								<div class="d-flex justify-content-between align-items-center">
									<div>Total</div>
									<h4 class="mb-0"><?= (int) ($stats['total'] ?? 0) ?></h4>
								</div>
							</div>
						</div>
						<div class="col-md-4">
							<div class="card card-body bg-warning text-white">
								<div class="d-flex justify-content-between align-items-center">
									<div>No leídos</div>
									<h4 class="mb-0"><?= (int) ($stats['no_leidos'] ?? 0) ?></h4>
								</div>
							</div>
						</div>
						<div class="col-md-4">
							<div class="card card-body bg-success text-white">
								<div class="d-flex justify-content-between align-items-center">
									<div>Con respuestas</div>
									<h4 class="mb-0"><?= (int) ($stats['con_respuestas'] ?? 0) ?></h4>
								</div>
							</div>
						</div>
					</div>

					<div class="card mb-3">
						<div class="card-body">
							<form method="get" class="row g-3 align-items-end">
								<div class="col-md-4">
									<label for="usuario" class="form-label">Usuario</label>
									<select name="usuario" id="usuario" class="form-select select">
										<option value="0">Todos los usuarios</option>
										<?php foreach ($usuarios as $u): ?>
											<option value="<?= (int) $u['user_id'] ?>" <?= $filtro_usuario === (int) $u['user_id'] ? 'selected' : '' ?>>
												<?= htmlspecialchars($u['nombre_completo']) ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="col-md-3">
									<label for="estado" class="form-label">Estado</label>
									<select name="estado" id="estado" class="form-select">
										<option value="todos" <?= $filtro_estado === 'todos' ? 'selected' : '' ?>>Todos</option>
										<option value="leido" <?= $filtro_estado === 'leido' ? 'selected' : '' ?>>Leídos</option>
										<option value="no_leido" <?= $filtro_estado === 'no_leido' ? 'selected' : '' ?>>No leídos</option>
									</select>
								</div>
								<div class="col-md-3">
									<label for="q" class="form-label">Buscar</label>
									<input type="text" name="q" id="q" class="form-control" placeholder="Asunto o mensaje" value="<?= htmlspecialchars($busqueda) ?>">
								</div>
								<div class="col-md-2 d-flex gap-2">
									<button type="submit" class="btn btn-primary w-100">Filtrar</button>
									<a href="admin_mensajes.php" class="btn btn-light w-100">Limpiar</a>
								</div>
							</form>
						</div>
					</div>

					<div class="card">
						<div class="card-body">
							<?php if ($totalMensajes > $rowsPerPage): ?>
								<div class="alert alert-info mb-3">Mostrando <?= $rowsPerPage ?> mensajes por página (página <?= $paginaActual ?> de <?= $totalPaginas ?>).</div>
							<?php endif; ?>

							<div class="table-responsive">
								<table class="table table-striped table-hover">
									<thead>
										<tr>
											<th>Fecha</th>
											<th>Remitente</th>
											<th>Destinatario</th>
											<th>Asunto</th>
											<th>Mensaje</th>
											<th>Estado</th>
											<th>Respuestas</th>
											<th>Acciones</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($mensajes_ as $m): ?>
											<?php
											$mensajePlano = trim(preg_replace('/\s+/', ' ', (string) $m['mensaje']));
											$preview = mb_strlen($mensajePlano) > 90 ? (mb_substr($mensajePlano, 0, 90) . '...') : $mensajePlano;
											?>
											<tr>
												<td><?= date('d/m/Y H:i', strtotime($m['fecha'])) ?></td>
												<td><?= htmlspecialchars($m['remitente']) ?></td>
												<td><?= htmlspecialchars($m['destinatario']) ?></td>
												<td><?= htmlspecialchars($m['asunto']) ?></td>
												<td><?= htmlspecialchars($preview) ?></td>
												<td>
													<?php if ((int)$m['leido'] === 1): ?>
														<span class="badge bg-success">Leído</span>
													<?php else: ?>
														<span class="badge bg-warning">No leído</span>
													<?php endif; ?>
												</td>
												<td><span class="badge bg-primary"><?= (int)$m['total_respuestas'] ?></span></td>
												<td><a class="btn btn-sm btn-primary" href="mensaje_detalle.php?id=<?= (int)$m['id'] ?>">Ver</a></td>
											</tr>
										<?php endforeach; ?>
										<?php if (count($mensajes_) === 0): ?>
											<tr>
												<td colspan="8" class="text-center text-muted">No se encontraron mensajes con los filtros aplicados.</td>
											</tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>

							<?php if ($totalPaginas > 1): ?>
								<?php
								$queryBase = [
									'usuario' => $filtro_usuario,
									'estado' => $filtro_estado,
									'q' => $busqueda,
								];
								?>
								<nav aria-label="Paginación mensajes" class="mt-3">
									<ul class="pagination pagination-sm mb-0">
										<li class="page-item <?= $paginaActual <= 1 ? 'disabled' : '' ?>">
											<?php $prevQuery = http_build_query(array_merge($queryBase, ['pagina' => max(1, $paginaActual - 1)])); ?>
											<a class="page-link" href="admin_mensajes.php?<?= $prevQuery ?>">Anterior</a>
										</li>
										<?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
											<?php if ($i === 1 || $i === $totalPaginas || abs($i - $paginaActual) <= 2): ?>
												<?php $pageQuery = http_build_query(array_merge($queryBase, ['pagina' => $i])); ?>
												<li class="page-item <?= $i === $paginaActual ? 'active' : '' ?>">
													<a class="page-link" href="admin_mensajes.php?<?= $pageQuery ?>"><?= $i ?></a>
												</li>
											<?php elseif ($i === 2 && $paginaActual > 4): ?>
												<li class="page-item disabled"><span class="page-link">...</span></li>
											<?php elseif ($i === $totalPaginas - 1 && $paginaActual < $totalPaginas - 3): ?>
												<li class="page-item disabled"><span class="page-link">...</span></li>
											<?php endif; ?>
										<?php endfor; ?>
										<li class="page-item <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>">
											<?php $nextQuery = http_build_query(array_merge($queryBase, ['pagina' => min($totalPaginas, $paginaActual + 1)])); ?>
											<a class="page-link" href="admin_mensajes.php?<?= $nextQuery ?>">Siguiente</a>
										</li>
									</ul>
								</nav>
							<?php endif; ?>
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
