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

$niveles = $pdo->query("SELECT * FROM niveles ORDER BY id")->fetchAll();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $puesto = $_POST['puesto'];
    $nivel = $_POST['nivel'];
    $codigo = $_POST['codigo_puesto'];
    $ggn = $_POST['ggn'];

    $verifica = $pdo->prepare("SELECT COUNT(*) FROM puestos WHERE codigo_puesto = ?");
    $verifica->execute([$codigo]);

    if ($verifica->fetchColumn() > 0) {
        $error = "Ya existe un puesto con el mismo código.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO puestos (puesto, nivel, codigo_puesto, ggn) VALUES (?, ?, ?, ?)");
        $stmt->execute([$puesto, $nivel, $codigo, $ggn]);
        header("Location: admin_puestos.php?info=1");
        exit;
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
								<a href="#" class="breadcrumb-item">Administración</a>
								<span class="breadcrumb-item active">Agregar Puesto</span>
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
						<h5 class="mb-0">Agregar Nuevo Puesto</h5>
					</div>

					<div class="card-body">
						Algunos campos son obligatorios.


    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="mb-3">
            <label>Puesto</label>
            <input type="text" name="puesto" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Nivel</label>
            <select name="nivel" class="form-select" required>
                <?php foreach ($niveles as $n): ?>
                <option value="<?= $n['id'] ?>"><?= $n['nombre'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label>Código de Puesto</label>
            <input type="text" name="codigo_puesto" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>GGN</label>
            <input type="text" name="ggn" class="form-control">
        </div>
        <button type="submit" class="btn btn-success">Guardar</button>
        <a href="admin_puestos.php" class="btn btn-secondary">Cancelar</a>
    </form>
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
