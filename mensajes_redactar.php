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

if ((int)($_SESSION['role'] ?? 0) < 2) {
		header('Location: mensajes.php');
		exit;
}

// Obtener usuarios destino
$usuarios = $pdo->query("SELECT user_id, CONCAT(nombre, ' ', apellido_paterno, ' ', apellido_materno) AS nombre_completo FROM usuarios WHERE estatus = 1 ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$error = '';

// Enviar mensaje a múltiples destinatarios
if (isset($_POST['enviar'])) {
	$asunto = trim($_POST['asunto'] ?? '');
	$mensaje = trim($_POST['mensaje'] ?? '');
	$para = $_POST['para'] ?? [];

	if (!is_array($para)) {
		$para = [];
	}

	if ($asunto === '' || $mensaje === '' || count($para) === 0) {
		$error = 'Debes seleccionar al menos un destinatario y capturar asunto/mensaje.';
	} else {
		if (in_array('TODOS', $para, true)) {
			$destinatarios = $pdo->query("SELECT user_id FROM usuarios WHERE estatus = 1")->fetchAll(PDO::FETCH_COLUMN);
		} else {
			$destinatarios = array_map('intval', $para);
		}

		$destinatarios = array_values(array_unique(array_filter($destinatarios, static function ($id) {
			return $id > 0;
		})));

		if (count($destinatarios) === 0) {
			$error = 'No se encontraron destinatarios válidos para enviar el mensaje.';
		} else {
			$stmt = $pdo->prepare("INSERT INTO mensajes (remitente_id, destinatario_id, asunto, mensaje) VALUES (?, ?, ?, ?)");
			foreach ($destinatarios as $destinatario) {
				$stmt->execute([$user_id, $destinatario, $asunto, $mensaje]);
			}

			header("Location: mensajes_redactar.php?mensaje=enviado");
			exit;
		}
	}
}
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
								<a href="#" class="breadcrumb-item">Administración</a>
								<a href="admin_mensajes.php" class="breadcrumb-item">Mensajes</a>
								<span class="breadcrumb-item active">Redactar</span>
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
					<div class="card">
						<div class="card-header d-flex justify-content-between align-items-center">
							<h5 class="mb-0">Redactar mensaje</h5>
							<a href="admin_mensajes.php" class="btn btn-light btn-sm">Volver a mensajes</a>
						</div>
						<div class="card-body">
							<?php if (isset($_GET['mensaje']) && $_GET['mensaje'] === 'enviado'): ?>
								<div class="alert alert-success">Mensaje enviado correctamente.</div>
							<?php endif; ?>

							<?php if ($error !== ''): ?>
								<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
							<?php endif; ?>

							<form method="post">
								<div class="mb-3">
									<label class="form-label" for="para">Destinatarios</label>
									<select id="para" name="para[]" class="form-select select" multiple required>
										<option value="TODOS">Todos los usuarios</option>
										<?php foreach ($usuarios as $u): ?>
											<option value="<?= (int)$u['user_id'] ?>">
												<?= htmlspecialchars($u['nombre_completo']) ?> (<?= (int)$u['user_id'] ?>)
											</option>
										<?php endforeach; ?>
									</select>
									<small class="text-muted">Tip: al elegir “Todos los usuarios” no necesitas seleccionar más opciones.</small>
								</div>

								<div class="mb-3">
									<label class="form-label" for="asunto">Asunto</label>
									<input id="asunto" name="asunto" class="form-control" maxlength="255" required value="<?= htmlspecialchars($_POST['asunto'] ?? '') ?>">
								</div>

								<div class="mb-3">
									<label class="form-label" for="mensaje">Mensaje</label>
									<textarea id="mensaje" name="mensaje" class="form-control" rows="6" required><?= htmlspecialchars($_POST['mensaje'] ?? '') ?></textarea>
								</div>

								<div class="d-flex gap-2">
									<button name="enviar" class="btn btn-primary">Enviar mensaje</button>
									<a href="admin_mensajes.php" class="btn btn-light">Cancelar</a>
								</div>
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
