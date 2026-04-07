<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin(3); // Solo super administradores

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');

// Filtros
$filtro_unidad = $_GET['unidad_id'] ?? '';
$filtro_periodo = $_GET['periodo'] ?? $_SESSION['periodo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_archivo_firmado'])) {
	$unidad_archivo = (int)($_POST['unidad_id_archivo'] ?? 0);
	$periodo_archivo = (int)($_POST['periodo_archivo'] ?? 0);

	$redirect_params = ['periodo' => $filtro_periodo];
	if ($filtro_unidad !== '') {
		$redirect_params['unidad_id'] = $filtro_unidad;
	}
	$redirect_url = 'admin_evaluadores_directivos.php?' . http_build_query($redirect_params);

	if ($unidad_archivo > 0 && $periodo_archivo > 0) {
		$stmt_archivo = $pdo->prepare("SELECT archivo_colectivas FROM calificaciones_colectivas WHERE unidad_id = ? AND periodo = ? LIMIT 1");
		$stmt_archivo->execute([$unidad_archivo, $periodo_archivo]);
		$archivo = $stmt_archivo->fetch(PDO::FETCH_ASSOC);

		if ($archivo && !empty($archivo['archivo_colectivas'])) {
			$pdo->prepare("UPDATE calificaciones_colectivas SET archivo_colectivas = NULL, fecha_archivo_colectivas = NULL WHERE unidad_id = ? AND periodo = ?")
				->execute([$unidad_archivo, $periodo_archivo]);

			$ruta_archivo = __DIR__ . '/firmas_colectivas/' . $archivo['archivo_colectivas'];
			if (is_file($ruta_archivo)) {
				@unlink($ruta_archivo);
			}

			header('Location: ' . $redirect_url . '&info=archivo_eliminado');
			exit;
		}
	}

	header('Location: ' . $redirect_url . '&info=archivo_no_encontrado');
	exit;
}

$where = "WHERE u.permite_metas_colectivas = 1";
$params = [];

// Filtro por unidad
if ($filtro_unidad !== '') {
    $where .= " AND u.unidad_id = ?";
    $params[] = $filtro_unidad;
}

// Consulta SQL: Evaluadores Directivos con sus metas colectivas
$sql = "SELECT u.user_id, u.nombre, u.apellido_paterno, u.apellido_materno, 
        u.puesto_nombre, u.correo, u.estatus,
        un.nombre AS unidad_nombre, u.unidad_id,
        cc.estatus AS estatus_colectivas, cc.resultado AS resultado_colectivas,
        cc.archivo_colectivas,
        (SELECT COUNT(*) FROM metas_colectivas mc WHERE mc.unidad_id = u.unidad_id AND mc.periodo = ?) AS total_metas,
		(SELECT SUM(mc.ponderacion) FROM metas_colectivas mc WHERE mc.unidad_id = u.unidad_id AND mc.periodo = ?) AS suma_ponderacion
        FROM usuarios u 
        LEFT JOIN unidades un ON u.unidad_id = un.id
        LEFT JOIN calificaciones_colectivas cc ON cc.unidad_id = u.unidad_id AND cc.periodo = ?
        $where
        ORDER BY un.nombre ASC, u.apellido_paterno ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$filtro_periodo, $filtro_periodo, $filtro_periodo], $params));
$evaluadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($evaluadores as &$ev) {
	if ((int)($ev['total_metas'] ?? 0) > 0) {
		$ev['estatus_colectivas_efectivo'] = (int)($ev['estatus_colectivas'] ?? 0);
		$ev['resultado_colectivas_efectivo'] = $ev['resultado_colectivas'];
	} else {
		$ev['estatus_colectivas_efectivo'] = 0;
		$ev['resultado_colectivas_efectivo'] = null;
	}
}
unset($ev);

// Obtener unidades con evaluadores directivos
$unidades = $pdo->query("SELECT DISTINCT u.id, u.nombre 
                         FROM unidades u 
                         INNER JOIN usuarios us ON us.unidad_id = u.id 
                         WHERE us.permite_metas_colectivas = 1 
                         ORDER BY u.nombre ASC")->fetchAll();

// Obtener periodos disponibles
$periodos = $pdo->query("SELECT anio FROM periodos ORDER BY anio DESC")->fetchAll();

// Estadísticas generales
$stats = [
    'total_evaluadores' => count($evaluadores),
    'con_metas' => count(array_filter($evaluadores, fn($e) => $e['total_metas'] > 0)),
	'evaluadas' => count(array_filter($evaluadores, fn($e) => ($e['estatus_colectivas_efectivo'] ?? 0) >= 2)),
	'pendientes' => count(array_filter($evaluadores, fn($e) => ($e['estatus_colectivas_efectivo'] ?? 0) < 2))
];

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title><?php echo htmlspecialchars($nombre_sistema); ?> - Evaluadores Directivos</title>

	<!-- Global stylesheets -->
	<link href="assets/fonts/inter/inter.css" rel="stylesheet" type="text/css">
	<link href="assets/icons/phosphor/styles.min.css" rel="stylesheet" type="text/css">
	<link href="assets/css/ltr/all.min.css" id="stylesheet" rel="stylesheet" type="text/css">
	<link href="assets/icons/icomoon/styles.min.css" rel="stylesheet" type="text/css">

	<!-- Core JS files -->
	<script src="assets/demo/demo_configurator.js"></script>
	<script src="assets/js/bootstrap/bootstrap.bundle.min.js"></script>

	<!-- Theme JS files -->
	<script src="assets/js/jquery/jquery.min.js"></script>
	<script src="assets/js/vendor/tables/datatables/datatables.min.js"></script>
	<script src="assets/js/app.js"></script>
	<script src="assets/demo/pages/datatables_basic.js"></script>
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
								<span class="breadcrumb-item active">Evaluadores Directivos</span>
							</div>
						</div>
					</div>
				</div>
				<!-- /page header -->

				<!-- Content area -->
				<div class="content">

					<?php if (isset($_GET['info']) && $_GET['info'] === 'archivo_eliminado'): ?>
						<div class="alert alert-success">El archivo firmado fue eliminado correctamente.</div>
					<?php elseif (isset($_GET['info']) && $_GET['info'] === 'archivo_no_encontrado'): ?>
						<div class="alert alert-warning">No se encontró un archivo firmado para eliminar.</div>
					<?php endif; ?>

					<!-- Estadísticas -->
					<div class="row mb-3">
						<div class="col-lg-3">
							<div class="card bg-primary text-white">
								<div class="card-body">
									<div class="d-flex align-items-center">
										<i class="ph-users-four ph-2x me-3"></i>
										<div>
											<h3 class="mb-0"><?= $stats['total_evaluadores'] ?></h3>
											<span class="fs-sm">Total Evaluadores</span>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="col-lg-3">
							<div class="card bg-success text-white">
								<div class="card-body">
									<div class="d-flex align-items-center">
										<i class="ph-check-circle ph-2x me-3"></i>
										<div>
											<h3 class="mb-0"><?= $stats['con_metas'] ?></h3>
											<span class="fs-sm">Con Metas Capturadas</span>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="col-lg-3">
							<div class="card bg-teal text-white">
								<div class="card-body">
									<div class="d-flex align-items-center">
										<i class="ph-clipboard-text ph-2x me-3"></i>
										<div>
											<h3 class="mb-0"><?= $stats['evaluadas'] ?></h3>
											<span class="fs-sm">Evaluaciones Finalizadas</span>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="col-lg-3">
							<div class="card bg-warning text-white">
								<div class="card-body">
									<div class="d-flex align-items-center">
										<i class="ph-hourglass ph-2x me-3"></i>
										<div>
											<h3 class="mb-0"><?= $stats['pendientes'] ?></h3>
											<span class="fs-sm">Pendientes</span>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Filtros -->
					<div class="card">
						<div class="card-header">
							<h5 class="mb-0"><i class="ph-funnel me-2"></i>Filtros</h5>
						</div>
						<div class="card-body">
							<form method="get" class="row g-3">
								<div class="col-md-5">
									<label class="form-label">Unidad Administrativa</label>
									<select name="unidad_id" class="form-select">
										<option value="">Todas las unidades</option>
										<?php foreach ($unidades as $u): ?>
											<option value="<?= $u['id'] ?>" <?= $filtro_unidad == $u['id'] ? 'selected' : '' ?>>
												<?= htmlspecialchars($u['nombre']) ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="col-md-3">
									<label class="form-label">Periodo</label>
									<select name="periodo" class="form-select">
										<?php foreach ($periodos as $p): ?>
											<option value="<?= $p['anio'] ?>" <?= $filtro_periodo == $p['anio'] ? 'selected' : '' ?>>
												<?= $p['anio'] ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="col-md-4 d-flex align-items-end">
									<button type="submit" class="btn btn-primary me-2">
										<i class="ph-magnifying-glass me-2"></i>Buscar
									</button>
									<a href="admin_evaluadores_directivos.php" class="btn btn-secondary">
										<i class="ph-x me-2"></i>Limpiar
									</a>
								</div>
							</form>
						</div>
					</div>

					<!-- Tabla de Evaluadores -->
					<div class="card mt-3">
						<div class="card-header">
							<h5 class="mb-0">
								<i class="ph-list-checks me-2"></i>
								Evaluadores Directivos - Periodo <?= $filtro_periodo ?>
							</h5>
						</div>

						<div class="table-responsive">
							<table class="table table-hover datatable-basic">
								<thead class="table-light">
									<tr>
										<th>Evaluador</th>
										<th>Puesto</th>
										<th>Unidad Administrativa</th>
										<th>Metas</th>
										<th>Ponderación</th>
										<th>Estatus</th>
										<th>Resultado</th>
										<th>Archivo</th>
										<th class="text-center">Acciones</th>
									</tr>
								</thead>
								<tbody>
									<?php 
									$unidad_anterior = '';
									foreach ($evaluadores as $ev): 
										// Separador visual por unidad
										if ($ev['unidad_nombre'] != $unidad_anterior && $filtro_unidad === '') {
											$unidad_anterior = $ev['unidad_nombre'];
											echo '<tr class="table-secondary"><td colspan="9" class="fw-bold"><i class="ph-building me-2"></i>' . htmlspecialchars($ev['unidad_nombre']) . '</td></tr>';
										}
										
										// Determinar color de estatus
										$estatus_col = $ev['estatus_colectivas_efectivo'] ?? 0;
										$badge_class = 'bg-secondary';
										$badge_text = 'Sin captura';
										if ($estatus_col == 1) {
											$badge_class = 'bg-info';
											$badge_text = 'Capturadas';
										} elseif ($estatus_col == 2) {
											$badge_class = 'bg-success';
											$badge_text = 'Evaluadas';
										} elseif ($estatus_col >= 3) {
											$badge_class = 'bg-primary';
											$badge_text = 'Finalizadas';
										}
										
										// Color de ponderación
										$suma = $ev['suma_ponderacion'] ?? 0;
										$color_ponderacion = ($suma == 100) ? 'text-success fw-bold' : (($suma > 0) ? 'text-warning' : 'text-muted');
									?>
									<tr>
										<td>
											<?= htmlspecialchars($ev['nombre'] . ' ' . $ev['apellido_paterno'] . ' ' . $ev['apellido_materno']) ?>
											<br><small class="text-muted"><?= htmlspecialchars($ev['correo']) ?></small>
										</td>
										<td><small><?= htmlspecialchars($ev['puesto_nombre']) ?></small></td>
										<td>
											<small><?= htmlspecialchars($ev['unidad_nombre']) ?></small>
										</td>
										<td class="text-center">
											<?php if ($ev['total_metas'] > 0): ?>
												<span class="badge bg-teal"><?= $ev['total_metas'] ?> metas</span>
											<?php else: ?>
												<span class="text-muted">-</span>
											<?php endif; ?>
										</td>
										<td class="text-center">
											<span class="<?= $color_ponderacion ?>">
												<?= $suma ?>%
											</span>
										</td>
										<td>
											<span class="badge <?= $badge_class ?>"><?= $badge_text ?></span>
										</td>
										<td class="text-center">
											<?php if (isset($ev['resultado_colectivas_efectivo']) && $ev['resultado_colectivas_efectivo'] > 0): ?>
												<strong><?= number_format($ev['resultado_colectivas_efectivo'], 2) ?></strong>
											<?php else: ?>
												<span class="text-muted">-</span>
											<?php endif; ?>
										</td>
										<td class="text-center">
											<?php if (!empty($ev['archivo_colectivas'])): ?>
												<a href="firmas_colectivas/<?= rawurlencode($ev['archivo_colectivas']) ?>" target="_blank" class="btn btn-sm btn-success" title="Ver archivo firmado">
													<i class="ph-file-pdf"></i>
												</a>
												<button type="button" class="btn btn-sm btn-danger" title="Eliminar archivo firmado" data-bs-toggle="modal" data-bs-target="#modalEliminarArchivo<?= (int)$ev['user_id'] ?>">
													<i class="ph-trash"></i>
												</button>
											<?php else: ?>
												<span class="text-muted">-</span>
											<?php endif; ?>
										</td>
										<td class="text-center">
											<a href="usuarios_editar.php?user_id=<?= $ev['user_id'] ?>" class="btn btn-sm btn-primary" title="Editar usuario">
												<i class="ph-pencil"></i>
											</a>
											<?php if ($ev['total_metas'] > 0): ?>
												<a href="generar_excel_colectivas.php?unidad_id=<?= $ev['unidad_id'] ?>&periodo=<?= $filtro_periodo ?>" class="btn btn-sm btn-danger" title="Descargar Excel">
													<i class="ph-file-xls"></i>
												</a>
											<?php endif; ?>
										</td>
									</tr>
									<?php if (!empty($ev['archivo_colectivas'])): ?>
									<div class="modal fade" id="modalEliminarArchivo<?= (int)$ev['user_id'] ?>" tabindex="-1" aria-hidden="true">
										<div class="modal-dialog modal-dialog-centered">
											<div class="modal-content">
												<div class="modal-header bg-danger text-white">
													<h5 class="modal-title">Confirmar eliminación</h5>
													<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
												</div>
												<div class="modal-body">
													¿Deseas eliminar el archivo firmado de <strong><?= htmlspecialchars($ev['nombre'] . ' ' . $ev['apellido_paterno'] . ' ' . $ev['apellido_materno']) ?></strong>?
												</div>
												<div class="modal-footer">
													<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
													<form method="post" class="d-inline">
														<input type="hidden" name="unidad_id_archivo" value="<?= (int)$ev['unidad_id'] ?>">
														<input type="hidden" name="periodo_archivo" value="<?= (int)$filtro_periodo ?>">
														<button type="submit" name="eliminar_archivo_firmado" value="1" class="btn btn-danger">Eliminar</button>
													</form>
												</div>
											</div>
										</div>
									</div>
									<?php endif; ?>
									<?php endforeach; ?>
									
									<?php if (empty($evaluadores)): ?>
									<tr>
										<td colspan="9" class="text-center text-muted">
											<i class="ph-magnifying-glass ph-2x d-block mb-2"></i>
											No se encontraron evaluadores directivos
										</td>
									</tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>

					<!-- Leyenda -->
					<div class="alert alert-info mt-3">
						<h6 class="alert-heading mb-2"><i class="ph-info me-2"></i>Información</h6>
						<ul class="mb-0">
							<li><strong>Evaluador Directivo:</strong> Usuario autorizado para capturar y evaluar Metas Colectivas de su Unidad Administrativa.</li>
							<li><strong>Estatus:</strong> 
								<span class="badge bg-secondary">Sin captura</span> = No ha iniciado | 
								<span class="badge bg-info">Capturadas</span> = Metas registradas | 
								<span class="badge bg-success">Evaluadas</span> = Evaluación finalizada
							</li>
							<li><strong>Ponderación:</strong> Debe sumar <strong class="text-success">100%</strong> para poder cerrar el período.</li>
							<li><strong>Acciones:</strong> <i class="ph-pencil"></i>Editar usuario | <i class="ph-file-xls"></i>Descargar Excel de metas</li>
						</ul>
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
