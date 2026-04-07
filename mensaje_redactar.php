
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['destinatario_id'])) {
  $remitente_id = $_SESSION['user_id'];
  $destinatarios = $_POST['destinatario_id'];
  $asunto = $_POST['asunto'];
  $mensaje = $_POST['mensaje'];

  if (in_array('todos', $destinatarios)) {
    $stmt = $pdo->prepare("SELECT user_id FROM usuarios WHERE user_id != ?");
    $stmt->execute([$remitente_id]);
    $destinatarios = $stmt->fetchAll(PDO::FETCH_COLUMN);
  }

  $stmt = $pdo->prepare("INSERT INTO mensajes (remitente_id, destinatario_id, asunto, mensaje) VALUES (?, ?, ?, ?)");

  foreach ($destinatarios as $destinatario_id) {
    $stmt->execute([$remitente_id, $destinatario_id, $asunto, $mensaje]);
  }

  header("Location: mensajes.php?mensaje=enviado");
  exit;
}

$usuarios = $pdo->query("SELECT user_id, nombre, apellido_paterno, apellido_materno FROM usuarios ORDER BY apellido_paterno")->fetchAll(PDO::FETCH_ASSOC);
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

					<!-- Relax -->
					<div class="card">
						<div class="card-header">
							<h5 class="mb-0">Redactar nuevo mensaje</h5>
						</div>

                <form method="post">
                <div class="mb-3">
                    <label for="destinatario_id" class="form-label">Para:</label>
                    <select name="destinatario_id[]" id="destinatario_id" class="form-control" multiple required>
                    <option value="todos">-- ENVIAR A TODOS LOS USUARIOS --</option>
                    <?php foreach ($usuarios as $u): 
                        $nombre = $u['nombre'] . ' ' . $u['apellido_paterno'] . ' ' . $u['apellido_materno'];
                    ?>
                        <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($nombre) ?> (<?= $u['user_id'] ?>)</option>
                    <?php endforeach; ?>
                    </select>
                    <div class="form-text">Puedes seleccionar uno o más destinatarios, o usar la opción "Enviar a todos".</div>
                </div>

                <div class="mb-3">
                    <label for="asunto" class="form-label">Asunto:</label>
                    <input type="text" name="asunto" id="asunto" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label for="mensaje" class="form-label">Mensaje:</label>
                    <textarea name="mensaje" id="mensaje" class="form-control" rows="6" required></textarea>
                </div>

                <button class="btn btn-sm btn-success">Enviar Mensaje</button>
                <a href="mensajes.php" class="btn btn-sm btn-secondary ms-2">Volver</a>
                </form>

                <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
                <script>
                const selectElement = document.getElementById('destinatario_id');
                if (selectElement) {
                    new Choices(selectElement, {
                    removeItemButton: true,
                    searchEnabled: true,
                    itemSelectText: '',
                    shouldSort: false
                    });
                }
                </script>

                    </div>
					<!-- /Relax -->


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
