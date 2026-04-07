<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin(2);

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');

// Obtener catálogos
$escolaridades = $pdo->query("SELECT * FROM escolaridades ORDER BY id")->fetchAll();
$puestos = $pdo->query("SELECT id, puesto, codigo_puesto FROM puestos ORDER BY puesto")->fetchAll();
$unidades = $pdo->query("SELECT id, nombre FROM unidades ORDER BY nombre")->fetchAll();
$adscripciones = $pdo->query("SELECT id, nombre FROM adscripciones ORDER BY nombre")->fetchAll();
$tipos_usuario = $pdo->query("SELECT id, nombre FROM tipos_puesto ORDER BY nombre DESC")->fetchAll();
$jefes = $pdo->query("SELECT user_id, CONCAT(nombre, ' ', apellido_paterno, ' ', apellido_materno) AS nombre_completo FROM usuarios ORDER BY nombre")->fetchAll();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['RFC'];
    $correo = $_POST['correo'];
    $RFC = $_POST['RFC'];
    $CURP = $_POST['CURP'];
    $IDRUSP = $_POST['IDRUSP'];

    // Verificación de duplicados
    $ver = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE username = ? OR RFC = ? OR CURP = ? OR IDRUSP = ? OR puesto_codigo = ?");
    $ver->execute([$username, $RFC, $CURP, $IDRUSP, $puesto_codigo]);

    if ($ver->fetchColumn() > 0) {
        $error = "Ya existe un usuario con el mismo nombre de usuario, RFC o CURP.";
    } else {
        $foto = null;
        if (!empty($_FILES['foto']['name'])) {
            $foto = uniqid() . "_" . basename($_FILES['foto']['name']);
            move_uploaded_file($_FILES['foto']['tmp_name'], "fotos/" . $foto);
        }

        $stmt = $pdo->prepare("INSERT INTO usuarios (
            username, password, role, nombre, tipo_usuario, apellido_paterno, apellido_materno, RFC, homoclave, CURP, IDRUSP,
            sexo, correo, nivel_estudios, fecha_alta, temporal, jefe_id, unidad_id, adscripcion_id,
            puesto_nombre, puesto_codigo, puesto_nivel, permite_metas_colectivas, requiere_cambio_password, foto
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $username,
            password_hash($_POST['RFC'], PASSWORD_DEFAULT),
            $_POST['role'],
            $_POST['nombre'],
            $_POST['tipo_usuario'],
            $_POST['apellido_paterno'],
            $_POST['apellido_materno'],
            $RFC,
            $_POST['homoclave'],
            $CURP,
            $IDRUSP,
            $_POST['sexo'],
            $correo,
            $_POST['nivel_estudios'],
            $_POST['fecha_alta'],
            $_POST['temporal'],
            $_POST['jefe_id'],
            $_POST['unidad_id'],
            $_POST['adscripcion_id'],
            $_POST['puesto_nombre'],
            $_POST['puesto_codigo'],
            $_POST['puesto_nivel'],
            $_POST['permite_metas_colectivas'],
            $_POST['requiere_cambio_password'],
            $foto
        ]);

        header("Location: admin_usuarios.php");
        exit;
    }
}

$niveles = $pdo->query("SELECT id, nombre FROM niveles ORDER BY nombre")->fetchAll();

?>

<!DOCTYPE html>
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
    <script>
document.addEventListener('DOMContentLoaded', function () {
    const unidadSelect = document.getElementById('unidad_id');
    const adscripcionSelect = document.getElementById('adscripcion_id');

    unidadSelect.addEventListener('change', function () {
        const unidadId = this.value;
        fetch('get_adscripciones.php?unidad_id=' + unidadId)
            .then(response => response.text())
            .then(data => {
                adscripcionSelect.innerHTML = data;
            })
            .catch(error => {
                adscripcionSelect.innerHTML = '<option value="">Error al cargar adscripciones</option>';
            });
    });
});
</script>
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
								<span class="breadcrumb-item active">Usuarios</span>
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
							<h5 class="mb-0"> Agregar Usuario</h5>
						</div>

						<div class="card-body">
							Algunos campos son obligatorios.<br/>


    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <div class="row">
            <div class="col-md-3 mb-3"><label class="col-form-label">Nombre</label><input type="text" name="nombre" class="form-control" required></div>
            <div class="col-md-3 mb-3"><label class="col-form-label">Apellido Paterno</label><input type="text" name="apellido_paterno" class="form-control" required></div>
            <div class="col-md-3 mb-3"><label class="col-form-label">Apellido Materno</label><input type="text" name="apellido_materno" class="form-control"></div>
            <div class="col-md-3 mb-3"><label class="col-form-label">Tipo</label>
                <select name="tipo_usuario" class="form-control" required>
                    <?php foreach ($tipos_usuario as $ti): ?>
                        <option value="<?= $ti['id'] ?>"><?= $ti['nombre'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-md-5 mb-3"><label class="col-form-label">Unidad</label>
            <select name="unidad_id" id="unidad_id" class="form-control">
                <option value="">-- Selecciona una unidad --</option>
                 <?php foreach ($unidades as $u): ?>
                 <option value="<?= $u['id'] ?>"><?= $u['nombre'] ?></option>
                 <?php endforeach; ?>
            </select>
            </div>
            <div class="col-md-5 mb-3"><label class="col-form-label">Adscripción</label>
            <select name="adscripcion_id" id="adscripcion_id" class="form-control select">
                <option value="">-- Selecciona una unidad primero --</option>
            </select>
            </div>
            <div class="col-md-2 mb-3"><label class="col-form-label">Sexo</label>
                <select name="sexo" class="form-select" required>
                    <option value="">--</option>
                    <option value="H">Hombre</option>
                    <option value="M">Mujer</option>
                    <option value="Otro">Otro</option>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-md-5 mb-3"><label class="col-form-label">Correo</label><input type="email" name="correo" class="form-control" required></div>
            <div class="col-md-3 mb-3"><label class="col-form-label">Fecha Alta</label><input type="date" name="fecha_alta" class="form-control" required></div>
            <div class="col-md-2 mb-3"><label class="col-form-label">Temporal</label>
                <select name="temporal" class="form-select" required>
                    <option value="0">No</option>
                    <option value="1">Sí</option>
                </select>
            </div>
            <div class="col-md-2 mb-3"><label class="col-form-label">Metas Colectivas</label>
                <select name="permite_metas_colectivas" class="form-select" required>
                    <option value="0">No</option>
                    <option value="1">Sí</option>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-md-3 mb-3"><label class="col-form-label">RFC</label><input type="text" name="RFC" class="form-control" maxlength="10" required></div>
            <div class="col-md-3 mb-3"><label class="col-form-label">Homoclave</label><input type="text" name="homoclave" class="form-control" maxlength="5" required></div>
            <div class="col-md-3 mb-3"><label class="col-form-label">CURP</label><input type="text" name="CURP" class="form-control" maxlength="20" required></div>
            <div class="col-md-3 mb-3"><label class="col-form-label">IDRUSP</label><input type="text" name="IDRUSP" class="form-control" maxlength="20"></div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3"><label class="col-form-label">Jefe Inmediato</label>
                <select name="jefe_id" class="form-control select" required>
                    <option value="">-- Ninguno --</option>
                    <?php foreach ($jefes as $j): ?>
                        <option value="<?= $j['user_id'] ?>"><?= $j['nombre_completo'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 mb-3"><label class="col-form-label">Rol</label>
                <select name="role" class="form-select" required>
                    <option value="1">Usuario</option>
                    <option value="2">Administrador</option>
                    <option value="3">Superadmin</option>
                </select>
            </div>
            <div class="col-md-2 mb-3"><label class="col-form-label"bel>Forzar Cambio Password</label>
                <select name="requiere_cambio_password" class="form-select" required>
                    <option value="0">No</option>
                    <option value="1">Sí</option>
                </select>
            </div>
            <div class="col-md-2 mb-3"><label class="col-form-label">Nivel de Estudios</label>
                <select name="nivel_estudios" class="form-select" required>
                    <?php foreach ($escolaridades as $e): ?>
                        <option value="<?= $e['nombre'] ?>"><?= $e['nombre'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>


        <div class="row">
            <div class="col-md-3 mb-3"><label class="col-form-label"bel>Puesto</label>
            <input type="text" name="puesto_nombre" class="form-control">
            </div>
            <div class="col-md-3 mb-3"><label class="col-form-label">Codigo Puesto</label>
            <input type="text" name="puesto_codigo" class="form-control">
            </div>
            <div class="col-md-3 mb-3"><label class="col-form-label">Nivel de Puesto</label>
            <select name="puesto_nivel" class="form-select" required>
                    <?php foreach ($niveles as $niv): ?>
                        <option value="<?= $niv['id'] ?>"><?= $niv['nombre'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 mb-3"><label class="col-form-label">Foto</label>
            <input type="file" name="foto" class="form-control"></div>
        </div>

        <button type="submit" class="btn btn-success btn-sm">Guardar Usuario</button>
        <a href="admin_usuarios.php" class="btn btn-secondary btn-sm">Cancelar</a>
    </form>

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
</script>

</body>
</html>

