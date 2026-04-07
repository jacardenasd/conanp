<?php
// ============================================
// PANEL SUPER ADMIN: EVALUACIÓN DE COMPETENCIAS
// Cambio #7: Panel para consultar y validar competencias de usuarios
// ============================================

require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';
require 'includes/finalizaciones.php';

checkLogin(3); // Solo Super Admin

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');

$filtro_periodo = (int)($_GET['periodo'] ?? 0);
$q = trim($_GET['q'] ?? '');
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina = 25;
$offset = ($pagina - 1) * $por_pagina;

$periodos = $pdo->query("SELECT anio FROM periodos ORDER BY anio DESC")->fetchAll(PDO::FETCH_COLUMN);

// Procesar validación del jefe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    $user_id = intval($_POST['user_id'] ?? 0);
    $periodo = intval($_POST['periodo'] ?? 0);
    
    if ($accion === 'bloquear_competencias') {
        // Bloquear competencias de usuario (jefe valida)
        if (bloquear_competencias_evaluacion($pdo, $user_id, $periodo, $_SESSION['user_id'])) {
            $_SESSION['mensaje'] = '✅ Competencias bloqueadas correctamente';
        }
    } elseif ($accion === 'desbloquear_competencias') {
        // Desbloquear (solo super admin puede revertir)
        $stmt = $pdo->prepare("
            UPDATE competencias_evaluacion 
            SET bloqueado_edicion = 0, usuario_validacion_jefe_id = NULL, fecha_validacion_jefe = NULL
            WHERE user_id = ? AND periodo = ? AND tipo = 'auto'
        ");
        if ($stmt->execute([$user_id, $periodo])) {
            $_SESSION['mensaje'] = '🔄 Bloqueo de competencias revertido';
        }
    }
    
    header('Location: admin_evaluar_competencias.php');
    exit;
}

// Obtener lista de usuarios con sus competencias evaluadas
$where = " WHERE u.role = 1 ";
$params = [];
if ($filtro_periodo > 0) {
	$where .= " AND ce.periodo = :periodo ";
	$params['periodo'] = $filtro_periodo;
}
if ($q !== '') {
	$where .= " AND (u.nombre LIKE :q OR u.apellido_paterno LIKE :q OR u.apellido_materno LIKE :q) ";
	$params['q'] = "%{$q}%";
}

$sql_base = "
    SELECT DISTINCT 
        u.user_id, u.nombre, u.apellido_paterno, u.apellido_materno,
        u.puesto_nivel, p.puesto,
        ce.periodo,
        MAX(CASE WHEN ce.tipo = 'auto' THEN ce.bloqueado_edicion ELSE NULL END) AS bloqueado_auto,
        MAX(CASE WHEN ce.tipo = 'jefe' THEN 1 ELSE 0 END) AS tiene_evaluacion_jefe,
        COUNT(CASE WHEN ce.tipo = 'jefe' AND ce.valor > 0 THEN 1 END) AS comp_evaluadas_jefe
    FROM usuarios u
    LEFT JOIN puestos p ON u.puesto_id = p.id
    LEFT JOIN competencias_evaluacion ce ON u.user_id = ce.user_id
	{$where}
    GROUP BY u.user_id, ce.periodo
";

$sql_total = "SELECT COUNT(*) FROM ({$sql_base}) t";
$stmt_total = $pdo->prepare($sql_total);
foreach ($params as $key => $value) {
	$stmt_total->bindValue(':' . $key, $value, $key === 'periodo' ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt_total->execute();
$total_registros = (int)$stmt_total->fetchColumn();
$total_paginas = max(1, (int)ceil($total_registros / $por_pagina));

$sql = $sql_base . " ORDER BY u.apellido_paterno, u.apellido_materno LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
	$stmt->bindValue(':' . $key, $value, $key === 'periodo' ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$usuarios_competencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query_base = $_GET;
unset($query_base['pagina']);
$query_base = http_build_query($query_base);
$query_base = $query_base !== '' ? $query_base . '&' : '';

?>
<!DOCTYPE html>
<html lang="es" dir="ltr">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title><?php echo htmlspecialchars($nombre_sistema); ?> - Evaluación de Competencias</title>

	<link href="assets/fonts/inter/inter.css" rel="stylesheet" type="text/css">
	<link href="assets/icons/phosphor/styles.min.css" rel="stylesheet" type="text/css">
	<link href="assets/css/ltr/all.min.css" id="stylesheet" rel="stylesheet" type="text/css">
	<link href="assets/icons/icomoon/styles.min.css" rel="stylesheet" type="text/css">

	<script src="assets/demo/demo_configurator.js"></script>
	<script src="assets/js/bootstrap/bootstrap.bundle.min.js"></script>
	<script src="assets/js/jquery/jquery.min.js"></script>
	<script src="assets/js/vendor/tables/datatables/datatables.min.js"></script>
	<script src="assets/js/vendor/notifications/bootbox.min.js"></script>
	<script src="assets/js/app.js"></script>
	<script src="assets/demo/pages/datatables_basic.js"></script>
</head>

<body>

<?php require_once('assets/main_navbar.php'); ?>

	<div class="page-content">

	<?php require_once('assets/main_navigation.php'); ?>

		<div class="content-wrapper">

			<div class="content-inner">

				<div class="page-header page-header-light shadow">

					<div class="page-header-content d-lg-flex border-top">
						<div class="d-flex">
							<div class="breadcrumb py-2">
								<a href="index.php" class="breadcrumb-item"><i class="ph-house"></i></a>
								<span class="breadcrumb-item active">Evaluación de Competencias</span>
							</div>
						</div>

						<div class="ms-lg-auto">
							<a href="admin_reportes.php" class="btn btn-light"><i class="ph-arrow-left"></i> Volver</a>
						</div>
					</div>
				</div>

				<div class="content">

					<?php if (isset($_SESSION['mensaje'])): ?>
						<div class="alert alert-success alert-dismissible fade show" role="alert">
							<?php echo htmlspecialchars($_SESSION['mensaje']); unset($_SESSION['mensaje']); ?>
							<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
						</div>
					<?php endif; ?>

					<div class="card">
						<div class="card-header">
							<h5 class="mb-0">👥 Panel de Evaluación de Competencias</h5>
							<small class="text-muted">Consulta y gestiona la evaluación de competencias de todos los usuarios</small>
						</div>

						<div class="card-body pb-0">
							<form method="get" class="row g-3 mb-3">
								<div class="col-md-3">
									<label class="form-label">Periodo</label>
									<select name="periodo" class="form-select">
										<option value="">Todos</option>
										<?php foreach ($periodos as $p): ?>
											<option value="<?= (int)$p ?>" <?= ($filtro_periodo === (int)$p) ? 'selected' : '' ?>><?= (int)$p ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="col-md-4">
									<label class="form-label">Buscar usuario</label>
									<input type="text" name="q" class="form-control" value="<?= htmlspecialchars($q) ?>" placeholder="Nombre o apellido">
								</div>
								<div class="col-md-5 d-flex align-items-end gap-2">
									<button type="submit" class="btn btn-primary">Filtrar</button>
									<a href="admin_evaluar_competencias.php" class="btn btn-light">Limpiar</a>
									<span class="text-muted">Mostrando <?= count($usuarios_competencias) ?> de <?= $total_registros ?></span>
								</div>
							</form>
						</div>

						<div class="table-responsive">
							<table class="table table-striped table-hover" id="competencias_table">
								<thead>
									<tr>
										<th>Usuario</th>
										<th>Puesto</th>
										<th>Nivel</th>
										<th>Período</th>
										<th style="text-align: center;">Auto-Evaluación</th>
										<th style="text-align: center;">Evaluación Jefe</th>
										<th style="text-align: center;">Estado Bloqueo</th>
										<th>Acciones</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($usuarios_competencias as $uc): ?>
										<tr>
											<td>
												<strong><?php echo htmlspecialchars($uc['nombre'] . ' ' . $uc['apellido_paterno']); ?></strong>
											</td>
											<td><?php echo htmlspecialchars($uc['puesto'] ?? 'N/A'); ?></td>
											<td><?php echo intval($uc['puesto_nivel']); ?></td>
											<td><?php echo intval($uc['periodo'] ?? 0); ?></td>
											<td style="text-align: center;">
												<?php if ($uc['periodo']): ?>
													<span class="badge bg-success">✓ Capturada</span>
												<?php else: ?>
													<span class="badge bg-secondary">-</span>
												<?php endif; ?>
											</td>
											<td style="text-align: center;">
												<?php if ($uc['tiene_evaluacion_jefe']): ?>
													<span class="badge bg-info"><?php echo $uc['comp_evaluadas_jefe']; ?>/5</span>
												<?php else: ?>
													<span class="badge bg-secondary">-</span>
												<?php endif; ?>
											</td>
											<td style="text-align: center;">
												<?php if ($uc['bloqueado_auto']): ?>
													<span class="badge bg-danger">🔒 BLOQUEADO</span>
												<?php else: ?>
													<span class="badge bg-warning">🔓 ABIERTO</span>
												<?php endif; ?>
											</td>
											<td>
												<?php if ($uc['periodo']): ?>
													<?php if (!$uc['bloqueado_auto']): ?>
														<form method="POST" style="display: inline;">
															<input type="hidden" name="user_id" value="<?php echo $uc['user_id']; ?>">
															<input type="hidden" name="periodo" value="<?php echo $uc['periodo']; ?>">
															<input type="hidden" name="accion" value="bloquear_competencias">
															<button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Bloquear evaluación de competencias de este usuario?')">
																🔒 BLOQUEAR
															</button>
														</form>
													<?php else: ?>
														<form method="POST" style="display: inline;">
															<input type="hidden" name="user_id" value="<?php echo $uc['user_id']; ?>">
															<input type="hidden" name="periodo" value="<?php echo $uc['periodo']; ?>">
															<input type="hidden" name="accion" value="desbloquear_competencias">
															<button type="submit" class="btn btn-sm btn-success" onclick="return confirm('¿Desbloquear evaluación de competencias de este usuario?')">
																🔓 DESBLOQUEAR
															</button>
														</form>
													<?php endif; ?>
												<?php endif; ?>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>

						<?php if (empty($usuarios_competencias)): ?>
							<div class="alert alert-info m-3">
								<p>No hay registros de evaluación de competencias.</p>
							</div>
						<?php endif; ?>

						<?php if ($total_paginas > 1): ?>
							<div class="card-body pt-0">
								<nav aria-label="Paginación de competencias">
									<ul class="pagination pagination-sm mb-0">
										<li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
											<a class="page-link" href="admin_evaluar_competencias.php?<?= $query_base ?>pagina=<?= max(1, $pagina - 1) ?>">Anterior</a>
										</li>
										<?php for ($i = max(1, $pagina - 2); $i <= min($total_paginas, $pagina + 2); $i++): ?>
											<li class="page-item <?= ($i === $pagina) ? 'active' : '' ?>">
												<a class="page-link" href="admin_evaluar_competencias.php?<?= $query_base ?>pagina=<?= $i ?>"><?= $i ?></a>
											</li>
										<?php endfor; ?>
										<li class="page-item <?= ($pagina >= $total_paginas) ? 'disabled' : '' ?>">
											<a class="page-link" href="admin_evaluar_competencias.php?<?= $query_base ?>pagina=<?= min($total_paginas, $pagina + 1) ?>">Siguiente</a>
										</li>
									</ul>
								</nav>
							</div>
						<?php endif; ?>

					</div>

				</div>

			</div>

		</div>

	</div>

<?php require_once('assets/footer.php'); ?>

</body>
</html>
