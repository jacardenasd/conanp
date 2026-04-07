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


$user_id = $_GET['user_id'];
$periodo = $_GET['periodo'];

// Buscar registro existente
$stmt = $pdo->prepare("SELECT * FROM calificaciones WHERE user_id = ? AND periodo = ?");
$stmt->execute([$user_id, $periodo]);
$registro = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        ':individuales' => $_POST['individuales'],
        ':capacitacion' => $_POST['capacitacion'],
        ':gerenciales' => $_POST['gerenciales'],
        ':user_id' => $user_id,
        ':periodo' => $periodo
    ];

    if ($registro) {
        $update = $pdo->prepare("UPDATE calificaciones SET individuales = :individuales, capacitacion = :capacitacion, gerenciales = :gerenciales WHERE user_id = :user_id AND periodo = :periodo");
        $update->execute($data);
    } else {
        $insert = $pdo->prepare("INSERT INTO calificaciones (individuales, capacitacion, gerenciales, user_id, periodo) VALUES (:individuales, :capacitacion, :gerenciales, :user_id, :periodo)");
        $insert->execute($data);
    }

    header("Location: admin_calificaciones.php?periodo=" . $periodo);
    exit;
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

	<script src="assets/js/app.js"></script>
	<script src="assets/demo/pages/components_modals.js"></script>
    <script src="assets/demo/pages/components_buttons.js"></script>
	<script src="assets/demo/pages/datatables_basic.js"></script>
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
								<span class="breadcrumb-item active">Puestos</span>
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

					<!-- Basic table -->
					<div class="card">
						<div class="card-header">
							<h5 class="mb-0">Editar Usuario</h5>
						</div>

						<div class="card-body">
							Example of a  calendars and date pickers, we've opted to isolate our custom table styles.<br/>


                            <div class="container mt-4">
    <h2>✏️ Editar Calificaciones</h2>
    <form method="post">
        <div class="mb-3">
            <label>Metas Individuales (0 a 100)</label>
            <input type="number" name="individuales" class="form-control" value="<?= $registro['individuales'] ?? '' ?>" max="100" min="0">
        </div>
        <div class="mb-3">
            <label>Capacitación (0 a 100)</label>
            <input type="number" name="capacitacion" class="form-control" value="<?= $registro['capacitacion'] ?? '' ?>" max="100" min="0">
        </div>
        <div class="mb-3">
            <label>Gerenciales (0 a 100)</label>
            <input type="number" name="gerenciales" class="form-control" value="<?= $registro['gerenciales'] ?? '' ?>" max="100" min="0">
        </div>
        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="admin_calificaciones.php?periodo=<?= $periodo ?>" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

</div>
</div>
</div>
<?php require_once('assets/footer.php'); ?>

</div>
<!-- /inner content -->

</div>
<!-- /main content -->

</div>
<!-- /page content -->


</body>
</html>
