
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
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$error = '';

if ($id <= 0) {
	header('Location: mensajes.php');
	exit;
}

$esAdmin = (int) ($_SESSION['role'] ?? 1) >= 2;

if ($esAdmin) {
	$stmt = $pdo->prepare("SELECT m.*, u.nombre, u.apellido_paterno, u.apellido_materno, u.foto FROM mensajes m JOIN usuarios u ON m.remitente_id = u.user_id WHERE m.id = ?");
	$stmt->execute([$id]);
} else {
	$stmt = $pdo->prepare("SELECT m.*, u.nombre, u.apellido_paterno, u.apellido_materno, u.foto FROM mensajes m JOIN usuarios u ON m.remitente_id = u.user_id WHERE m.id = ? AND (m.destinatario_id = ? OR m.remitente_id = ?)");
	$stmt->execute([$id, $user_id, $user_id]);
}

$mensaje = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mensaje) {
	header('Location: mensajes.php');
	exit;
}

$puedeResponder = $esAdmin || (in_array($user_id, [(int)$mensaje['remitente_id'], (int)$mensaje['destinatario_id']], true));

// Marcar mensaje como leído solo al destinatario real
if ((int)$mensaje['destinatario_id'] === $user_id && (int)$mensaje['leido'] === 0) {
	$stmtLeido = $pdo->prepare("UPDATE mensajes SET leido = 1 WHERE id = ? AND destinatario_id = ?");
	$stmtLeido->execute([$id, $user_id]);
}

// Enviar respuesta
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$respuesta = trim($_POST['respuesta'] ?? '');

	if (!$puedeResponder) {
		$error = 'No tienes permisos para responder este mensaje.';
	} elseif ($respuesta === '') {
		$error = 'La respuesta no puede ir vacía.';
	} else {
		$stmtInsert = $pdo->prepare("INSERT INTO mensajes_respuestas (mensaje_id, remitente_id, respuesta) VALUES (?, ?, ?)");
		$stmtInsert->execute([$id, $user_id, $respuesta]);
		header("Location: mensaje_detalle.php?id={$id}");
		exit;
	}
}

// Obtener respuestas al mensaje
$stmt = $pdo->prepare("SELECT r.*, u.nombre, u.apellido_paterno, u.apellido_materno, u.foto FROM mensajes_respuestas r JOIN usuarios u ON r.remitente_id = u.user_id WHERE r.mensaje_id = ? ORDER BY r.fecha");
$stmt->execute([$id]);
$respuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
								<a href="#" class="breadcrumb-item">Mensajes</a>
								<span class="breadcrumb-item active">Leer mensaje</span>
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

					<?php if ($error !== ''): ?>
						<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
					<?php endif; ?>


							<!-- Chat widget -->
							<div class="card">
								<div class="card-header">
									<h6 class="mb-0"><?= htmlspecialchars($mensaje['asunto']) ?></h6>
								</div>

								<div class="card-body">
									<div class="media-chat-scrollable mb-3">
										<div class="media-chat vstack gap-3">
											<div class="media-chat-item hstack align-items-start gap-3">
												<a href="#" class="d-block status-indicator-container">
													<img src="fotos/<?= htmlspecialchars($mensaje['foto'] ?? 'default.png') ?>" class="w-40px h-40px rounded-pill" alt="">
													<span class="status-indicator bg-success"></span>
												</a>

												<div>
													<div class="media-chat-message">De: <b><?= htmlspecialchars($mensaje['nombre'] . ' ' . $mensaje['apellido_paterno'] . ' ' . $mensaje['apellido_materno']) ?></b><br/>
													<?= nl2br(htmlspecialchars($mensaje['mensaje'])) ?></div>
													<div class="fs-sm text-muted mt-2"><?= date('d/m/Y H:i', strtotime($mensaje['fecha'])) ?></div>
												</div>
											</div>

						<?php if (count($respuestas) > 0): ?>
                        <?php foreach ($respuestas as $r): ?>
											<?php $esMio = ((int)$r['remitente_id'] === $user_id); ?>

											<div class="media-chat-item <?= $esMio ? 'media-chat-item-reverse' : '' ?> hstack align-items-start gap-3">
												<a href="#" class="d-block status-indicator-container">
													<img src="fotos/<?= htmlspecialchars($r['foto'] ?? 'default.png') ?>" class="w-40px h-40px rounded-pill" alt="">
													<span class="status-indicator bg-success"></span>
												</a>
												<div>

													<div class="media-chat-message">De: <b><?= htmlspecialchars($r['nombre'] . ' ' . $r['apellido_paterno'] . ' ' . $r['apellido_materno']) ?></b><br/>
													<?= nl2br(htmlspecialchars($r['respuesta'])) ?></div>
													<div class="fs-sm text-muted mt-2">
														<?= date('d/m/Y H:i', strtotime($r['fecha'])) ?>
													</div>
												</div>
											</div>

                        <?php endforeach; ?>
                    	<?php else: ?>
                        <p class="text-muted">No hay respuestas aún.</p>
                    	<?php endif; ?>

										</div>
										
									</div>



									<?php if ($puedeResponder): ?>
									<form method="post">
										<div class="mb-3">
											<textarea name="respuesta" id="respuesta" class="form-control form-control-content mb-3" cols="2" rows="3" required placeholder="Ingresa tu respuesta..."><?= htmlspecialchars($_POST['respuesta'] ?? '') ?></textarea>
										</div>
										<button class="btn btn-primary btn-sm">Enviar <i class="ph-paper-plane-tilt ms-2"></i></button>
										<a href="<?= $esAdmin ? 'admin_mensajes.php' : 'mensajes.php' ?>" class="btn btn-sm btn-secondary ms-2">Volver</a>
									</form>
									<?php else: ?>
										<a href="mensajes.php" class="btn btn-sm btn-secondary">Volver</a>
									<?php endif; ?>
								</div>
							</div>
							<!-- /chat widget -->




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
