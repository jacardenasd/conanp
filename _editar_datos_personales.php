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
$unidad_id = $_SESSION['unidad_id'];
$user_id = $_SESSION['user_id']; 

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);
$periodo = $_SESSION['periodo'];

// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();


$user_id = $_SESSION['user_id']; 

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, apellido_paterno = ?, apellido_materno = ?, RFC = ?, CURP = ?, IDRUSP = ?, correo = ?, temporal = ?, jefe_id = ? WHERE user_id = ?");
    $stmt->execute([
        $_POST['nombre'],
        $_POST['apellido_paterno'],
        $_POST['apellido_materno'],
        $_POST['RFC'],
        $_POST['CURP'],
        $_POST['IDRUSP'],
        $_POST['correo'],
        $_POST['temporal'],
        $_POST['jefe_id'],
        $user_id
    ]);
    header("Location: mis_datos.php?mensaje=actualizado");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE user_id = ?");
$stmt->execute([$user_id]);
$usuario = $stmt->fetch();

// Lista de posibles jefes
$jefes = $pdo->query("SELECT user_id, nombre, apellido_paterno, apellido_materno FROM usuarios ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
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
								<a href="#" class="breadcrumb-item">Mis datos</a>
								<span class="breadcrumb-item active">Editar datos personales</span>
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

        <div class="card">
						<div class="card-header">
            <h3>Editar Datos Personales</h3>
						</div>

						<div class="card-body">
						Captura la información solicitada; algunos campos son obligatorios.
            <p>&nbsp;</p>

<h4></h4>
<form method="post" class="row g-3">
  <div class="col-md-4"><label class="form-label">Nombre</label><input name="nombre" value="<?= htmlspecialchars($usuario['nombre']) ?>" class="form-control"></div>
  <div class="col-md-4"><label class="form-label">Apellido Paterno</label><input name="apellido_paterno" value="<?= htmlspecialchars($usuario['apellido_paterno']) ?>" class="form-control"></div>
  <div class="col-md-4"><label class="form-label">Apellido Materno</label><input name="apellido_materno" value="<?= htmlspecialchars($usuario['apellido_materno']) ?>" class="form-control"></div>
  <div class="col-md-4"><label class="form-label">RFC</label><input name="RFC" value="<?= htmlspecialchars($usuario['RFC']) ?>" class="form-control"></div>
  <div class="col-md-4"><label class="form-label">CURP</label><input name="CURP" value="<?= htmlspecialchars($usuario['CURP']) ?>" class="form-control"></div>
  <div class="col-md-4"><label class="form-label">ID RUSP</label><input name="IDRUSP" value="<?= htmlspecialchars($usuario['IDRUSP']) ?>" class="form-control"></div>
  <div class="col-md-6"><label class="form-label">Correo</label><input name="correo" type="email" value="<?= htmlspecialchars($usuario['correo']) ?>" class="form-control"></div>
  <div class="col-md-6">
    <label class="form-label">Tipo de Empleado</label>
    <select name="temporal" class="form-control">
      <option value="1" <?= $usuario['temporal'] === '1' ? 'selected' : '' ?>>SPC</option>
      <option value="2" <?= $usuario['temporal'] === '2' ? 'selected' : '' ?>>Primer Nivel de Ingreso</option>
      <option value="3" <?= $usuario['temporal'] === '3' ? 'selected' : '' ?>>Eventual</option>
      <option value="4" <?= $usuario['temporal'] === '4' ? 'selected' : '' ?>>Operativo</option>
      <option value="5" <?= $usuario['temporal'] === '5' ? 'selected' : '' ?>>Otro</option>
    </select>
  </div>
  <div class="col-md-6">
  <label for="jefe_id" class="form-label">Seleccione al nuevo superior:</label>
  <select name="jefe_id" id="jefe_id" class="form-control select" required>
    <option value="">Seleccione...</option>
    <?php foreach ($jefes as $j): ?>
      <option value="<?= $j['user_id'] ?>" <?= $usuario['jefe_id'] == $j['user_id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($j['nombre'] . ' ' . $j['apellido_paterno'] . ' ' . $j['apellido_materno']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  </div>
  <div class="col-12"><button class="btn btn-sm btn-success">Guardar cambios</button>
  <a href="mis_datos.php" class="btn btn-sm btn-secondary">Cancelar</a></div>
</form>

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
