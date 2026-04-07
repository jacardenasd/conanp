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
$role = $_SESSION['role'];

// Obtener mensaje dinámico de datos generales
$mensaje_datos = obtener_variable('mensaje_datos_generales') ?? 'En caso de identificar alguna corrección en los datos generales, envía un correo a capacitacion@conanp.gob.mx';

// Obtener catálogos
$escolaridades = $pdo->query("SELECT * FROM escolaridades ORDER BY id")->fetchAll();
$puestos = $pdo->query("SELECT id, puesto, codigo_puesto FROM puestos ORDER BY puesto")->fetchAll();
$unidades = $pdo->query("SELECT id, nombre FROM unidades ORDER BY nombre")->fetchAll();
$adscripciones = $pdo->query("SELECT id, nombre FROM adscripciones ORDER BY nombre")->fetchAll();
$tipo_usuarios = $pdo->query("SELECT id, nombre FROM tipos_puesto ORDER BY nombre")->fetchAll();
$jefes = $pdo->query("SELECT user_id, CONCAT(nombre, ' ', apellido_paterno, ' ', apellido_materno) AS nombre_completo FROM usuarios ORDER BY nombre")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $homoclave = strtoupper(trim($_POST['homoclave'] ?? ''));
    $nivel_estudios = trim($_POST['nivel_estudios'] ?? '');

    $niveles_validos = array_column($escolaridades, 'nombre');

    $homoclave_valida = ($homoclave === '') || (preg_match('/^[A-Z0-9]{3}$/', $homoclave) === 1);
    $nivel_valido = in_array($nivel_estudios, $niveles_validos, true);

    if (!$homoclave_valida || !$nivel_valido) {
        header('Location: editar_datos_personales.php?info=7');
        exit;
    }

    $stmt_update = $pdo->prepare("UPDATE usuarios SET homoclave = ?, nivel_estudios = ? WHERE user_id = ?");
    $stmt_update->execute([$homoclave, $nivel_estudios, $user_id]);

    header('Location: editar_datos_personales.php?info=1');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE user_id = ?");
$stmt->execute([$user_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC); 

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


                <?php if (isset($_GET['info'])): ?>
                    <?php
                    $clase_alerta = 'alert-success'; // valor por defecto

                    switch ($_GET['info']) {
                        case 1:
                            $texto = '✅ Datos guardados correctamente.';
                            $clase_alerta = 'alert-success';
                            break;
                        case 2:
                            $texto = '✏️ Meta actualizada.';
                            $clase_alerta = 'alert-info';
                            break;
                        case 3:
                            $texto = '🗑️ Meta eliminada.';
                            $clase_alerta = 'alert-danger';
                            break;
                        case 6:
                            $texto = '✏️ Para que puedas acceder a las diferentes secciones del Sistema, debes completar tus datos personales.';
                            $clase_alerta = 'alert-warning';
                            break;
                        case 7:
                            $texto = '⚠️ Verifica los datos capturados. La homoclave debe tener 3 caracteres alfanuméricos y el nivel de estudios debe ser válido.';
                            $clase_alerta = 'alert-warning';
                            break;
                        default:
                            $texto = 'info no reconocido.';
                            $clase_alerta = 'alert-secondary';
                            break;
                    }
                    ?>

                    <div class="alert <?= $clase_alerta ?> border-0 alert-dismissible fade show" id="alerta-auto">
										<span class="fw-semibold"> <?= $texto ?>
										<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
								    </div>
                    <?php endif; ?>


					<!-- Basic table -->
					<div class="card">
						<div class="card-header">
							<h5 class="mb-0">Datos Personales</h5>
						</div>

						<div class="card-body">
                        <div class="alert alert-info mb-3">
                            <i class="ph-info me-2"></i>
                            <strong>Información:</strong> Solo puedes editar Homoclave y Nivel de Estudios. Para cualquier otro dato, contacta a Recursos Humanos.
                        </div>


        <form method="post" action="editar_datos_personales.php">
        <input type="hidden" name="user_id" value="<?= $usuario['user_id'] ?>">
        <input type="hidden" name="username" value="<?= $usuario['RFC'] ?>">

        <div class="row">
            <div class="col-md-3 mb-3"><label class="col-form-label">Nombre</label><input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($usuario['nombre']) ?>" disabled></div>
            <div class="col-md-3 mb-3"><label class="col-form-label">Apellido Paterno</label><input type="text" name="apellido_paterno" class="form-control" value="<?= htmlspecialchars($usuario['apellido_paterno']) ?>" disabled></div>
            <div class="col-md-3 mb-3"><label class="col-form-label">Apellido Materno</label><input type="text" name="apellido_materno" class="form-control" value="<?= htmlspecialchars($usuario['apellido_materno']) ?>" disabled></div>
            <div class="col-md-3 mb-3"><label class="col-form-label">Correo</label><input type="email" name="correo" class="form-control" value="<?= htmlspecialchars($usuario['correo']) ?>" disabled></div>
        </div>

        <div class="row">
        <div class="col-md-3 mb-3"><label class="col-form-label">RFC</label><input type="text" name="RFC" class="form-control" value="<?= $usuario['RFC'] ?>" maxlength="10" disabled></div>
        <div class="col-md-3 mb-3"><label class="col-form-label">Homoclave</label><input type="text" name="homoclave" class="form-control" value="<?= htmlspecialchars($usuario['homoclave']) ?>" maxlength="3"></div>
            <div class="col-md-3 mb-3"><label class="col-form-label">CURP</label><input type="text" name="CURP" class="form-control" value="<?= $usuario['CURP'] ?>" maxlength="20" disabled></div>
            <div class="col-md-3 mb-3"><label class="col-form-label">IDRUSP</label><input type="text" name="IDRUSP" class="form-control" value="<?= $usuario['IDRUSP'] ?>" maxlength="25" disabled></div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3"><label class="col-form-label">Sexo</label>
                <select name="sexo" class="form-control" disabled>
                    <option value="">--</option>
                    <option value="H" <?= $usuario['sexo'] == 'H' ? 'selected' : '' ?>>Hombre</option>
                    <option value="M" <?= $usuario['sexo'] == 'M' ? 'selected' : '' ?>>Mujer</option>
                    <option value="Otro" <?= $usuario['sexo'] == 'Otro' ? 'selected' : '' ?>>Otro</option>
                </select>
            </div>
            <div class="col-md-4 mb-3"><label class="col-form-label">Nivel de Estudios</label>
                <select name="nivel_estudios" class="form-control">
                    <?php foreach ($escolaridades as $e): ?>
                        <option value="<?= $e['nombre'] ?>" <?= $usuario['nivel_estudios'] == $e['nombre'] ? 'selected' : '' ?>><?= $e['nombre'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 mb-3"><label class="col-form-label">Jefe Inmediato</label>
                <select name="jefe_id" class="form-control select" disabled>
                    <option value="">-- Ninguno --</option>
                    <?php foreach ($jefes as $j): ?>
                        <option value="<?= $j['user_id'] ?>" <?= $usuario['jefe_id'] == $j['user_id'] ? 'selected' : '' ?>><?= $j['nombre_completo'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3"><label class="col-form-label">Unidad</label>
            <select name="unidad_id" id="unidad_id" class="form-control" disabled>
                <option value="">-- Selecciona una unidad --</option>
                 <?php foreach ($unidades as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $usuario['unidad_id'] == $u['id'] ? 'selected' : '' ?>><?= $u['nombre'] ?></option>
                 <?php endforeach; ?>
            </select>
            </div>
            <div class="col-md-6 mb-3"><label class="col-form-label" >Adscripción</label>
            <select name="adscripcion_id" id="adscripcion_id" class="form-control" disabled>
            <option value="">-- Selecciona una adscripción --</option>
            <?php foreach ($adscripciones as $a): ?>
            <option value="<?= $a['id'] ?>" <?= $usuario['adscripcion_id'] == $a['id'] ? 'selected' : '' ?>>
                <?= $a['nombre'] ?>
            </option>
            <?php endforeach; ?>
            </select>
            </div>
        </div>

        <div class="row">
        <div class="col-md-6 mb-3"><label class="col-form-label">Denominación de Puesto</label>
            <input type="text" name="puesto_nombre" class="form-control" value="<?= $usuario['puesto_nombre'] ?>" disabled>
            </div>
            <div class="col-md-6 mb-3"><label class="col-form-label">Código de Puesto</label>
            <input type="text" name="puesto_codigo" class="form-control" value="<?= $usuario['puesto_codigo'] ?>" disabled>
            </div>
        </div>

        <div class="row">
        <div class="col-md-12 mb-3">
            <div class="alert alert-warning">
                <i class="ph-warning me-2"></i>
                <strong>Correcciones:</strong> <?php echo htmlspecialchars($mensaje_datos); ?>
            </div>
            </div>
        </div>

        <button type="submit" class="btn btn-sm btn-success">Guardar cambios</button>
        <span class="mx-1"></span>
        <a href="mis_datos.php" class="btn btn-sm btn-primary">Volver</a>
    </form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const unidadSelect = document.querySelector('[name=unidad_id]');
    const adscripcionSelect = document.querySelector('[name=adscripcion_id]');

    unidadSelect.addEventListener('change', function () {
        const unidadId = this.value;
        adscripcionSelect.innerHTML = '<option value="">Cargando...</option>';

        fetch('cargar_adscripciones.php?unidad_id=' + unidadId)
            .then(response => response.json())
            .then(data => {
                adscripcionSelect.innerHTML = '<option value="">-- Selecciona --</option>';
                data.forEach(ad => {
                    const option = document.createElement('option');
                    option.value = ad.id;
                    option.textContent = ad.nombre;
                    adscripcionSelect.appendChild(option);
                });
            });
    });
});
</script>

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
