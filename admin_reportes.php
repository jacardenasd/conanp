<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';
checkLogin(2);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');
$user_id = $_SESSION['user_id']; 

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);
$periodo = $_SESSION['periodo'];

if (isset($_GET['periodo'])) {$lperiodo = $_GET['periodo']; } else {$lperiodo = '';}
if (isset($_GET['user_id'])) {$luser_id = $_GET['user_id']; } else {$luser_id = '';}
if (isset($_GET['unidad_id'])) {$lunidad_id = $_GET['unidad_id']; } else {$lunidad_id = '';}
if (isset($_GET['trimestre'])) {$ltrimestre = $_GET['trimestre']; } else {$ltrimestre = '';}

function mensaje_filtros_faltantes(array $faltantes): string {
	if (empty($faltantes)) {
		return '';
	}
	return 'Falta seleccionar: ' . implode(', ', $faltantes);
}

$query_periodo = http_build_query(['periodo' => $lperiodo]);
$query_colectivas = http_build_query(['periodo' => $lperiodo, 'unidad_id' => $lunidad_id]);
$query_individual = http_build_query(['periodo' => $lperiodo, 'user_id' => $luser_id]);
$query_capacitacion = http_build_query([
	'periodo' => $lperiodo,
	'empleado' => $luser_id,
	'unidad_id' => $lunidad_id,
	'trimestre' => $ltrimestre
]);

$query_evaluaciones = http_build_query(['periodo' => $lperiodo, 'unidad_id' => $lunidad_id]);

$disabled_periodo = empty($lperiodo) ? ' disabled' : '';
$disabled_unidad = (empty($lperiodo) || empty($lunidad_id)) ? ' disabled' : '';
$disabled_usuario = (empty($lperiodo) || empty($luser_id)) ? ' disabled' : '';

$total_reportes = 6;
$reportes_habilitados = 1;
if (!empty($lperiodo)) {
	$reportes_habilitados += 2;
}
if (!empty($lperiodo) && !empty($lunidad_id)) {
	$reportes_habilitados += 1;
}
if (!empty($lperiodo) && !empty($luser_id)) {
	$reportes_habilitados += 2;
}

$msg_metas_individuales = mensaje_filtros_faltantes([
	...(empty($lperiodo) ? ['Periodo'] : [])
]);

$msg_metas_colectivas = mensaje_filtros_faltantes([
	...(empty($lperiodo) ? ['Periodo'] : []),
	...(empty($lunidad_id) ? ['Unidad'] : [])
]);

$msg_capacitacion = mensaje_filtros_faltantes([
	...(empty($lperiodo) ? ['Periodo'] : [])
]);

$msg_metas_usuario = mensaje_filtros_faltantes([
	...(empty($lperiodo) ? ['Periodo'] : []),
	...(empty($luser_id) ? ['Usuario'] : [])
]);

// Obtener catálogos
$periodos_ = $pdo->query("SELECT anio FROM periodos ORDER BY anio DESC")->fetchAll(PDO::FETCH_COLUMN);
$unidades = $pdo->query("SELECT id, nombre FROM unidades ORDER BY nombre")->fetchAll(PDO::FETCH_KEY_PAIR);
$usuarios = $pdo->query("SELECT user_id, CONCAT(nombre, ' ', apellido_paterno, ' ', apellido_materno) AS nombre FROM usuarios ORDER BY nombre")->fetchAll(PDO::FETCH_KEY_PAIR);
$trimestres = [1 => '1er trimestre', 2 => '2do trimestre', 3 => '3er trimestre', 4 => '4to trimestre'];

$filtros_activos = [];
if (!empty($lperiodo)) {
	$filtros_activos[] = 'Periodo: ' . $lperiodo;
}
if (!empty($lunidad_id) && isset($unidades[$lunidad_id])) {
	$filtros_activos[] = 'Unidad: ' . $unidades[$lunidad_id];
}
if (!empty($luser_id) && isset($usuarios[$luser_id])) {
	$filtros_activos[] = 'Usuario: ' . $usuarios[$luser_id];
}
if (!empty($ltrimestre) && isset($trimestres[(int)$ltrimestre])) {
	$filtros_activos[] = 'Trimestre: ' . $trimestres[(int)$ltrimestre];
}

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
	<script src="assets/js/vendor/forms/selects/select2.min.js"></script>

	<script src="assets/js/app.js"></script>
	<script src="assets/demo/pages/components_modals.js"></script>
    <script src="assets/demo/pages/components_buttons.js"></script>
	<script src="assets/demo/pages/datatables_basic.js"></script>
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
								<a href="#" class="breadcrumb-item">Administracion</a>
								<span class="breadcrumb-item active">Descarga de Reportes en Excel</span>
							</div>

							<a href="#breadcrumb_elements" class="btn btn-sm btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
								<i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
							</a>
						</div>

					</div>
				</div>
				<!-- /page header -->

				<!-- Content area -->
				<div class="content">

					<!-- Basic table -->
					<div class="card">
						<div class="card-header">
							<h5 class="mb-0">Descarga de Reportes en Excel</h5>
						</div>

						<div class="card-body">
                        Selecciona el periodo (y otros filtros si aplica) y descarga el reporte deseado. <br/>
                        El filtro de trimestre solo aplica para Capacitación.<p>&nbsp;</p>

						<div class="row g-3 mb-3">
							<div class="col-md-3">
								<div class="border rounded p-3 h-100">
									<div class="fs-sm text-muted">Reportes disponibles</div>
									<div class="fs-5 fw-semibold"><?= (int)$reportes_habilitados ?> / <?= (int)$total_reportes ?></div>
								</div>
							</div>
							<div class="col-md-3">
								<div class="border rounded p-3 h-100">
									<div class="fs-sm text-muted">Periodo actual</div>
									<div class="fs-5 fw-semibold"><?= !empty($lperiodo) ? htmlspecialchars($lperiodo) : 'No seleccionado' ?></div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="border rounded p-3 h-100">
									<div class="fs-sm text-muted mb-2">Filtros activos</div>
									<?php if (!empty($filtros_activos)): ?>
										<?php foreach ($filtros_activos as $filtro): ?>
											<span class="badge bg-light text-dark border me-1 mb-1"><?= htmlspecialchars($filtro) ?></span>
										<?php endforeach; ?>
									<?php else: ?>
										<span class="text-muted">Sin filtros activos</span>
									<?php endif; ?>
								</div>
							</div>
						</div>

						<?php if (empty($lperiodo)): ?>
							<div class="alert alert-warning border-0 alert-dismissible fade show">
								Selecciona un periodo para habilitar la mayoría de reportes.
								<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
							</div>
						<?php endif; ?>




  <form method="get" action="" class="row g-3 mb-4">
    <div class="col-md-3">
      <label>Periodo:</label>
      <select name="periodo" class="form-select" required>
        <option value="">Selecciona</option>
        <?php foreach ($periodos_ as $anio): ?>
					<option value="<?= $anio ?>" <?php if($anio == $lperiodo) {echo "selected";} ?>><?= $anio ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label>Unidad:</label>
      <select name="unidad_id" class="form-select">
        <option value="">Todas</option>
        <?php foreach ($unidades as $id => $nombre): ?>
					<option value="<?= $id ?>" <?php if($id == $lunidad_id) {echo "selected";} ?>><?= $nombre ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label>Usuario:</label>
      <select name="user_id" class="form-select select">
        <option value="">Todos</option>
        <?php foreach ($usuarios as $ids => $nombre): ?>
					<option value="<?= $ids ?>" <?php if($ids == $luser_id) {echo "selected";} ?>><?= $nombre ?></option>
        <?php endforeach; ?>
      </select>
    </div>
		<div class="col-md-3">
			<label>Trimestre (Capacitación):</label>
			<select name="trimestre" class="form-select">
				<option value="">Todos</option>
				<?php foreach ($trimestres as $t_id => $t_nombre): ?>
					<option value="<?= $t_id ?>" <?php if($t_id == $ltrimestre) {echo "selected";} ?>><?= $t_nombre ?></option>
				<?php endforeach; ?>
			</select>
		</div>
    <div class="col-md-3 align-self-end">
      <button type="submit" class="btn btn-sm btn-primary w-100">Aplicar filtros</button>
    </div>
		<div class="col-md-3 align-self-end">
			<a href="admin_reportes.php" class="btn btn-sm btn-primary w-100">Borrar filtros</a>
		</div>
  </form>

  <p>&nbsp;</p>

  <div class="row g-3">
    <div class="col-md-3">
			<a href="reporte_metas_individuales.php?<?= htmlspecialchars($query_periodo) ?>" class="btn btn-outline-success w-100<?= $disabled_periodo ?>"<?= empty($lperiodo) ? ' aria-disabled="true" tabindex="-1"' : '' ?>>📄 Metas Individuales</a>
			<?php if ($msg_metas_individuales !== ''): ?>
				<div class="fs-sm text-muted mt-1"><?= htmlspecialchars($msg_metas_individuales) ?></div>
			<?php endif; ?>
    </div>
    <div class="col-md-3">
			<a href="generar_excel_colectivas.php?<?= htmlspecialchars($query_colectivas) ?>" class="btn btn-outline-success w-100<?= $disabled_unidad ?>"<?= (empty($lperiodo) || empty($lunidad_id)) ? ' aria-disabled="true" tabindex="-1"' : '' ?>>📄 Metas colectivas</a>
			<?php if ($msg_metas_colectivas !== ''): ?>
				<div class="fs-sm text-muted mt-1"><?= htmlspecialchars($msg_metas_colectivas) ?></div>
			<?php endif; ?>
    </div>
    <div class="col-md-3">
			<a href="capacitacion_excel.php?<?= htmlspecialchars($query_capacitacion) ?>" class="btn btn-outline-success w-100<?= $disabled_periodo ?>"<?= empty($lperiodo) ? ' aria-disabled="true" tabindex="-1"' : '' ?>>📄 Capacitación</a>
			<?php if ($msg_capacitacion !== ''): ?>
				<div class="fs-sm text-muted mt-1"><?= htmlspecialchars($msg_capacitacion) ?></div>
			<?php endif; ?>
    </div>
    <div class="col-md-3">
			<a href="admin_usuarios.php" class="btn btn-outline-success w-100">📄 Usuarios</a>
    </div>
    <div class="col-md-3">
			<a href="generar_excel_individual.php?<?= htmlspecialchars($query_individual) ?>" class="btn btn-outline-success w-100<?= $disabled_usuario ?>"<?= (empty($lperiodo) || empty($luser_id)) ? ' aria-disabled="true" tabindex="-1"' : '' ?>>📄 Metas individuales Usuario</a>
			<?php if ($msg_metas_usuario !== ''): ?>
				<div class="fs-sm text-muted mt-1"><?= htmlspecialchars($msg_metas_usuario) ?></div>
			<?php endif; ?>
    </div>
	<div class="col-md-3">
			<a href="generar_excel_evaluaciones_periodo.php?<?= htmlspecialchars($query_evaluaciones) ?>" class="btn btn-outline-success w-100<?= $disabled_periodo ?>"<?= empty($lperiodo) ? ' aria-disabled="true" tabindex="-1"' : '' ?>>📄 Evaluaciones por periodo</a>
			<?php if ($disabled_periodo !== ''): ?>
				<div class="fs-sm text-muted mt-1"><?= htmlspecialchars($msg_metas_individuales) ?></div>
			<?php endif; ?>
	</div>
    <div class="col-md-3">
			<a href="reporte_cedula_resultados.php?<?= htmlspecialchars($query_individual) ?>" class="btn btn-outline-success w-100<?= $disabled_usuario ?>"<?= (empty($lperiodo) || empty($luser_id)) ? ' aria-disabled="true" tabindex="-1"' : '' ?>>📄 Cédula de resultados</a>
			<?php if ($msg_metas_usuario !== ''): ?>
				<div class="fs-sm text-muted mt-1"><?= htmlspecialchars($msg_metas_usuario) ?></div>
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



