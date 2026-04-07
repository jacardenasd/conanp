
<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin(3);

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');

// Obtener lista de años desde la tabla periodos
$anios = $pdo->query("SELECT anio FROM periodos ORDER BY anio DESC")->fetchAll(PDO::FETCH_COLUMN);

$msg = '';

// Procesar cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$accion = $_POST['accion'] ?? 'guardar_variables';

	if ($accion === 'guardar_variables') {


	// Guardar logo principal
	if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
		$ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
		$tamano = $_FILES['logo']['size'];
		if ($ext === 'png' && $tamano <= 512000) {
			move_uploaded_file($_FILES['logo']['tmp_name'], 'assets/images/logo_icon.png');
			$msg .= "<br>✅ Logo principal actualizado.";
		} else {
			$msg .= "<br>⚠️ El logo principal debe ser PNG y menor a 500 KB.";
		}
	}

	// Guardar logo login
	if (!empty($_FILES['logo_login']['name']) && $_FILES['logo_login']['error'] === UPLOAD_ERR_OK) {
		$ext = strtolower(pathinfo($_FILES['logo_login']['name'], PATHINFO_EXTENSION));
		$tamano = $_FILES['logo_login']['size'];
		if ($ext === 'png' && $tamano <= 512000) {
			move_uploaded_file($_FILES['logo_login']['tmp_name'], 'assets/images/3logo_icon.png');
			$msg .= "<br>✅ Logo de login actualizado.";
		} else {
			$msg .= "<br>⚠️ El logo de login debe ser PNG y menor a 500 KB.";
		}
	}

		if (isset($_POST['nombre'], $_POST['valor']) && is_array($_POST['nombre']) && is_array($_POST['valor'])) {
			foreach ($_POST['nombre'] as $i => $nombre) {
				$valor = $_POST['valor'][$i] ?? '';

				// Validar que periodo_actual sea un año válido
				if ($nombre === 'periodo_actual' && !in_array((int)$valor, $anios)) {
					continue;
				}

				$stmt = $pdo->prepare("UPDATE variables SET valor = ? WHERE nombre = ?");
				$stmt->execute([$valor, $nombre]);
			}
			$msg = "Variables actualizadas correctamente.";
		}
    }
}

// Obtener todas las variables
$variables = $pdo->query("SELECT * FROM variables ORDER BY nombre ASC")->fetchAll();
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
								<a href="#" class="breadcrumb-item">Administraicón</a>
								<span class="breadcrumb-item active">Variables del Sistema</span>
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
					<h2>Variables del sistema</h2>

					<?php if ($msg !== ''): ?>
						<div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
					<?php endif; ?>

					<div class="card">
						<div class="card-header">
							<h5 class="mb-0">Variables generales</h5>
						</div>
						<div class="card-body">
							<form method="post" enctype="multipart/form-data">
								<input type="hidden" name="accion" value="guardar_variables">
								<table class="table table-bordered">
									<thead class="table-light">
										<tr>
											<th>Nombre</th>
											<th>Valor</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($variables as $v): ?>
										<tr>
											<td>
												<input type="hidden" name="nombre[]" value="<?= htmlspecialchars($v['nombre']) ?>">
												<strong><?= htmlspecialchars($v['nombre']) ?></strong>
											</td>
											<td>
												<?php if ($v['nombre'] === 'periodo_actual'): ?>
													<select name="valor[]" class="form-select">
														<?php foreach ($anios as $anio): ?>
															<option value="<?= $anio ?>" <?= $anio == $v['valor'] ? 'selected' : '' ?>><?= $anio ?></option>
														<?php endforeach; ?>
													</select>
												<?php else: ?>
													<input type="text" name="valor[]" class="form-control" value="<?= htmlspecialchars($v['valor']) ?>">
												<?php endif; ?>
											</td>
										</tr>
										<?php endforeach; ?>
										<tr>
											<td><strong>Logo principal</strong></td>
											<td>
												<img src="assets/images/logo_icon.png?<?= time() ?>" alt="Logo principal" height="80" class="border p-1">
											</td>
										</tr>
										<tr>
											<td><label for="logo">Nuevo logo principal (PNG)</label></td>
											<td>
												<input type="file" name="logo" accept="image/png" class="form-control">
											</td>
										</tr>

										<tr>
											<td><strong>Logo de login</strong></td>
											<td>
												<img src="assets/images/3logo_icon.png?<?= time() ?>" alt="Logo login" height="80" class="border p-1">
											</td>
										</tr>
										<tr>
											<td><label for="logo_login">Nuevo logo login (PNG)</label></td>
											<td>
												<input type="file" name="logo_login" accept="image/png" class="form-control">
											</td>
										</tr>
									</tbody>
								</table>
								<button type="submit" class="btn btn-outline-primary">Guardar variables generales</button>
							</form>
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
