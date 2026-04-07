<?php
require 'config/db.php';
require 'includes/variables.php';

$ruta_log_accesos = __DIR__ . '/includes/log_accesos.php';
if (is_file($ruta_log_accesos)) {
	require_once $ruta_log_accesos;
}

if (!function_exists('inicializar_tabla_log_accesos')) {
	function inicializar_tabla_log_accesos(PDO $pdo)
	{
		return;
	}
}

if (!function_exists('registrar_log_acceso')) {
	function registrar_log_acceso(PDO $pdo, $evento, $userId = null, $username = null, $resultado = 'OK', $detalle = null)
	{
		$descripcion = trim(($detalle ?? 'Evento de acceso') . ($username ? ' | usuario: ' . $username : ''));
		try {
			$stmtAuditoria = $pdo->prepare("INSERT INTO auditorias (user_id, tabla, accion, descripcion, fecha, usuario_id)
				VALUES (?, 'log_accesos_usuarios', ?, ?, NOW(), ?)");
			$stmtAuditoria->execute([
				$userId,
				strtoupper((string)$evento),
				substr($descripcion, 0, 255),
				$userId
			]);
		} catch (Throwable $e) {
			return;
		}
	}
}

session_start();

inicializar_tabla_log_accesos($pdo);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');

// Consultar el periodo actual desde la tabla variables
$stmt = $pdo->prepare("SELECT valor FROM variables WHERE nombre = 'periodo_actual'");
$stmt->execute();
$periodo_actual = $stmt->fetchColumn(); 

$mensaje = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
	$username = strtoupper(trim($_POST['username'] ?? ''));
    $password = $_POST['password'];

    // 1) Busca al usuario por username
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2) Verifica que exista y la contraseña
    if ($user && password_verify($password, $user['password'])) {

        // 3) Comprueba estatus = 1
        if ((int)$user['estatus'] !== 1) {
            $error = "Tu cuenta está inactiva. Contacta al administrador.";
			registrar_log_acceso(
				$pdo,
				'login_inactivo',
				$user['user_id'] ?? null,
				$user['username'] ?? $username,
				'DENEGADO',
				'Intento de acceso con cuenta inactiva'
			);
        } else {
            // 4) Todo OK: crea la sesión y redirige
            $_SESSION['periodo']        = $periodo_actual;
            $_SESSION['user_id']        = $user['user_id'];
            $_SESSION['tipo_usuario']   = $user['tipo_usuario'];
            $_SESSION['username']       = $user['username'];
            $_SESSION['nombre']         = $user['nombre'];
            $_SESSION['role']           = $user['role'];
            $_SESSION['unidad_id']      = $user['unidad_id'];
            $_SESSION['adscripcion_id'] = $user['adscripcion_id'];
            $_SESSION['puesto_id']      = $user['puesto_id'];
            $_SESSION['sexo']           = $user['sexo'];
			$_SESSION['permite_metas_colectivas'] = $user['permite_metas_colectivas'];
			$_SESSION['sidebar_resized'] = 0;

			registrar_log_acceso(
				$pdo,
				'login_exitoso',
				$user['user_id'],
				$user['username'],
				'OK',
				'Inicio de sesión exitoso'
			);
	
			// Forzamos cambio de constraseña
			if ($user['requiere_cambio_password']) {
				$_SESSION['requiere_cambio'] = true;
				header("Location: cambiar_password.php");
				exit();
			  }

			// Redirigir a la URL original si existe
			if (isset($_SESSION['redirect_after_login']) AND $_SESSION['redirect_after_login'] != 'index.php') {
				$redirect = $_SESSION['redirect_after_login'];
				unset($_SESSION['redirect_after_login']);
				header("Location: $redirect");
				exit();
			} 
			
// si no es SPC ni PRIMER_NIVEL_INGRESO, solo capacitación
		if ($user['tipo_usuario'] > 2) {
				header("Location: mi_capacitacion.php");
				exit();
			}
			
				header("Location: index.php"); 
				exit();
        }

    } else {
        // Usuario no existe o contraseña inválida
        $error = "Usuario o contraseña incorrectos";
		registrar_log_acceso(
			$pdo,
			'login_fallido',
			null,
			$username,
			'DENEGADO',
			'Usuario o contraseña incorrectos'
		);
    }
}
// Restaurar contraseña
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['restore'])) {
    $usuario = $_POST['usuario'];
    $stmt = $pdo->prepare("SELECT user_id, RFC FROM usuarios WHERE username = ? OR correo = ?");
    $stmt->execute([$usuario, $usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $newpass = password_hash($user['RFC'], PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE usuarios SET password = ?, requiere_cambio_password = 1 WHERE user_id = ?");
        $update->execute([$newpass, $user['user_id']]);
        $mensaje = "Contraseña restablecida. Puedes ingresar con tu RFC como contraseña.";
    } else {
        $error = "Usuario no encontrado.";
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title><?php echo $nombre_sistema; ?></title>

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
				<a href="#" class="d-inline-flex align-items-center">
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

					<!-- Registration form -->
					<form action="" method="post" class="flex-fill">
						<div class="row">
							<div class="col-lg-6 offset-lg-3">
								<div class="card mb-0">
									<div class="card-body">
										<div class="text-center mb-3">
											<div class="d-inline-flex align-items-center justify-content-center mb-4 mt-2">
											<img src="assets/images/3logo_icon.png?<?= time() ?>" class="h-100px" alt="Logo login">
											</div>
											<h5 class="mb-0"><?php echo htmlspecialchars($nombre_sistema); ?></h5>
											<span class="d-block text-muted">Acceso</span>
										</div>

										<div>

										<div class="d-flex align-items-start mb-4">
											<img src="assets/images/avatars/Guacamole-2.png" alt="Guía" class="rounded-circle mr-3" width="70" height="70">
											<div class="bg-light border p-3 rounded shadow-sm">
												Te damos la bienvenida a nuestro <?php echo htmlspecialchars($nombre_sistema); ?> en la CONANP.
											</div>
											</div>
										<p>Te damos la bienvenida al Sistema de Evaluación del Desempeño (SED) de la CONANP.</p>
										<p>El Sistema reúne la información que permite evaluar, en forma individual y colectiva el cumplimiento de las funciones y metas asignadas a las personas servidoras públicas, en función de sus habilidades, capacidades y del perfil determinado para el puesto que ocupan.</p>
										<p>El Sistema de Evaluación de Desempeño, juega un papel fundamental, ya que es aquí donde la información del ejercicio fiscal expresa el impacto de los programas y acciones de la CONANP para beneficio de las Áreas Naturales Protegidas.</p>
										<p>Además, podrás realizar el registro y carga de la capacitación realizada.</p>
										<p>Cualquier duda o aclaración, estamos a tus órdenes en el número <i>55 5449 7000</i> extensiones <i>17005</i>, <i>17097</i> y <i>17218</i> o al correo electrónico <a href="mailto:capacitacion@conanp.gob.mx"><i>capacitacion@conanp.gob.mx</i></a>.</p>
										</div>

									</div>

									<div class="card-body border-top">
									<?php if ($error) echo "<div class='alert alert-danger'>$error</div>"; ?>
    								<?php if ($mensaje) echo "<div class='alert alert-success'>$mensaje</div>"; ?>


									<div class="row">
											<div class="col-lg-6">
												<div class="mb-3">
													<label class="form-label">Usuario:</label>
													<div class="form-control-feedback form-control-feedback-start">
														<input type="text" class="form-control" name="username" id="username" maxlength="10" oninput="this.value = this.value.toUpperCase();" placeholder="Ingresa RFC a 10 posiciones" required>
														<div class="form-control-feedback-icon">
															<i class="ph-user-circle text-muted"></i>
														</div>
													</div>
												</div>
											</div>

											<div class="col-lg-6">
												<div class="mb-3">
													<label class="form-label">Constraseña:</label>
													<div class="form-control-feedback form-control-feedback-start">
														<input type="password" class="form-control" placeholder="•••••••••••" name="password" id="password" required>
														<button type="button" onclick="togglePassword()" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); border: none; background: none; cursor: pointer;"><i class="ph-eye"></i></button>
														<div class="form-control-feedback-icon">
															<i class="ph-lock text-muted"></i>
														</div>
													</div>
												</div>
											</div>
										</div>

									<div class="d-flex align-items-center mb-3">
									<button type="submit"  class="btn btn-primary">Acceder</button>
									<input type="hidden" name="login" value="1">
									<a href="#" data-bs-toggle="modal" data-bs-target="#modalRecuperar" class="ms-auto">¿Olvidaste tu contraseña?</a>
									</div>
									
									</div>
								</div>
							</div>
						</div>
					</form>
					<!-- /registration form -->

				</div>
				<!-- /content area -->


				<?php require_once('assets/footer.php'); ?>

			</div>
			<!-- /inner content -->

		</div>
		<!-- /main content -->

	</div>
	<!-- /page content -->

	<!-- Modal para recuperación -->
	<div id="modalRecuperar" class="modal fade" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content">
		<form method="post">
		<div class="modal-header">
					<h5 class="modal-title">Restaurar Contraseña</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
			<div class="modal-body">
				<input type="hidden" name="restore" value="1">
				<p>Ingresa tu <strong>RFC</strong> a 10 posiciones.</p>
				<input type="text" name="usuario" class="form-control" required>
			</div>
			<div class="modal-footer">
				<button type="submit" class="btn btn-sm btn-warning">Restaurar</button>
				<button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
			</div>
		</form>
      </div>
    </div>

</body>
<script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const currentType = passwordInput.getAttribute('type');

    if (currentType === 'password') {
        passwordInput.setAttribute('type', 'text');
    } else {
        passwordInput.setAttribute('type', 'password');
    }
}
</script>
</html>
