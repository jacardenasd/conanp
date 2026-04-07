
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
$periodo = date('Y'); // Ajustar si usas un periodo distinto

if (!in_array($_SESSION['role'], [2, 3])) {
  echo "<div class='alert alert-danger'>Acceso restringido.</div>";
  exit;
}

// Insertar nuevo periodo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_periodo'])) {
  $anio = $_POST['anio'];
  $estatus = $_POST['estatus'];

  $stmt = $pdo->prepare("INSERT INTO periodos (anio, estatus) VALUES (?, ?)");
  $stmt->execute([$anio, $estatus]);
  header("Location: admin_periodos.php?mensaje=agregado");
  exit;
}

// Actualizar estatus
if (isset($_GET['actualizar']) && isset($_GET['estatus'])) {
  $id = $_GET['actualizar'];
  $nuevo = $_GET['estatus'];
  $pdo->prepare("UPDATE periodos SET estatus = ? WHERE id = ?")->execute([$nuevo, $id]);
  header("Location: admin_periodos.php?mensaje=actualizado");
  exit;
}

$periodos = $pdo->query("SELECT * FROM periodos ORDER BY anio DESC")->fetchAll();
$estatus_opciones = ['Captura', 'Evaluación', 'Cerrado'];
?>
<!DOCTYPE html>
<html lang="es" dir="ltr">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title><?php echo htmlspecialchars($puesto_sistema); ?></title>

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
								<a href="#" class="breadcrumb-item">Administracion</a>
								<span class="breadcrumb-item active">Adscripciónes</span>
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

<?php if (isset($_GET['mensaje'])): ?>
    <div class="alert alert-success">✅ Cambios guardados correctamente.</div>
<?php endif; ?>


  <!-- Basic table -->
					<div class="card">
						<div class="card-header">
							<h5 class="mb-0">Adscripciónes</h5>
						</div>

						<div class="card-body">
							Example of a  calendars and date pickers, we've opted to isolate our custom table styles.
						</div>

						<form method="post" class="row g-3 mb-4">
    <div class="col-md-4">
      <label class="form-label">Año:</label>
      <input type="number" name="anio" class="form-control" required min="2000" max="2100">
    </div>
    <div class="col-md-4">
      <label class="form-label">Estatus:</label>
      <select name="estatus" class="form-control" required>
        <?php foreach ($estatus_opciones as $op): ?>
          <option value="<?= $op ?>"><?= $op ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4 align-self-end">
      <button class="btn btn-primary w-100" name="nuevo_periodo">Agregar Periodo</button>
    </div>
  </form>

  <table class="table table-bordered table-sm align-middle">
    <thead class="table-light">
      <tr>
        <th>Año</th>
        <th>Estatus Actual</th>
        <th>Cambiar a...</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($periodos as $p): ?>
        <tr>
          <td><?= $p['anio'] ?></td>
          <td><strong><?= $p['estatus'] ?></strong></td>
          <td>
            <?php foreach ($estatus_opciones as $op):
              if ($op != $p['estatus']): ?>
              <a href="?actualizar=<?= $p['id'] ?>&estatus=<?= $op ?>" class="btn btn-outline-secondary btn-sm me-1"><?= $op ?></a>
            <?php endif; endforeach; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

                        
				</div>
				<!-- /content area -->

				<?php require_once('assets/footer.php'); ?>

			</div>
			<!-- /inner content -->

		</div>
		<!-- /main content -->

	</div>
	<!-- /page content -->
