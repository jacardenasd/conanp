<?php
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



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nueva = $_POST['nueva'];
  $confirmar = $_POST['confirmar'];

  if ($nueva !== $confirmar) {
    $error = "Las contraseñas no coinciden.";
  } elseif (strlen($nueva) < 6) {
    $error = "La nueva contraseña debe tener al menos 6 caracteres.";
  } else {
    $stmt = $pdo->prepare("UPDATE usuarios SET password = ?, requiere_cambio_password = 0 WHERE user_id = ?");
    $stmt->execute([password_hash($nueva, PASSWORD_DEFAULT), $_SESSION['user_id']]);
    unset($_SESSION['requiere_cambio']);
    header("Location: mis_datos.php?info=1");
    exit();
  }
}
?>


<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title>CONANP - Sistema de Evaluación del Desempeño</title>

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
	<script src="assets/js/app.js"></script>
	<!-- /theme JS files -->

</head>

<body>

	<!-- Main navbar -->
	<div class="navbar navbar-dark navbar-static py-2">
		<div class="container-fluid">
			<div class="navbar-brand">
				<a href="index.php" class="d-inline-flex align-items-center">
					<img src="assets/images/logo_icon.png" alt="">
					<img src="assets/images/logo_text_light.png" class="d-none d-sm-inline-block h-16px ms-3" alt="">
				</a>
			</div>

			<div class="d-flex justify-content-end align-items-center ms-auto">
				<ul class="navbar-nav flex-row">
				</ul>
			</div>
		</div>
	</div>
	<!-- /main navbar -->


	<!-- Page content -->
	<div class="page-content">

		<!-- Main content -->
		<div class="content-wrapper">

			<!-- Inner content -->
			<div class="content-inner">

				<!-- Content area -->
				<div class="content d-flex justify-content-center align-items-center">

				<!-- Login form -->
				<body class="bg-light">


				<form class="login-form" method="post">

				<?php if (isset($error)): ?>
    			  <div class="alert alert-danger"><?= $error ?></div>
    			<?php endif; ?>

						<div class="card mb-0">
							<div class="card-body">
								<div class="text-center mb-3">
									<div class="d-inline-flex align-items-center justify-content-center mb-4 mt-2">
										<img src="assets/images/logo_icon.png" class="h-48px" alt="">
									</div>
									<h5 class="mb-0">Cambiar Contraseña</h5>
									<span class="d-block text-muted">Ingresa tu nueva contraseña</span>
								</div>

								<div class="mb-3">
									<label class="form-label">Nueva Contraseña</label>
									<div class="form-control-feedback form-control-feedback-start">
										<input type="password" class="form-control" name="nueva" id="nueva" required>
										<div class="form-control-feedback-icon">
											<i class="ph-lock text-muted"></i>
										</div>
									</div>
								</div>

								<div class="mb-3">
									<label class="form-label">Confirmar nueva contraseña</label>
									<div class="form-control-feedback form-control-feedback-start">
										<input type="password" class="form-control" name="confirmar" id="confirmar" required>
										<div class="form-control-feedback-icon">
											<i class="ph-lock text-muted"></i>
										</div>
									</div>
								</div>

								<div class="mb-3">
									<button type="submit" class="btn btn-primary w-100">Guardar</button>
									<input type="hidden" name="login" value="1">
								</div>

							</div>
						</div>
					</form>
					<!-- /login form -->

				</div>
				<!-- /content area -->


			</div>
			<!-- /inner content -->

		</div>
		<!-- /main content -->

	</div>
	<!-- /page content -->
	
</body>
</html>
