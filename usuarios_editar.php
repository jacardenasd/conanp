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
$user_id = $_GET['user_id'];
// Obtener catálogos
$escolaridades = $pdo->query("SELECT * FROM escolaridades ORDER BY id")->fetchAll();
$puestos = $pdo->query("SELECT id, puesto, codigo_puesto FROM puestos ORDER BY puesto")->fetchAll();
$unidades = $pdo->query("SELECT id, nombre FROM unidades ORDER BY nombre")->fetchAll();
$adscripciones = $pdo->query("SELECT id, nombre FROM adscripciones ORDER BY nombre")->fetchAll();
$tipo_usuarios = $pdo->query("SELECT id, nombre FROM tipos_puesto ORDER BY nombre")->fetchAll();
$niveles = $pdo->query("SELECT id, nombre FROM niveles ORDER BY nombre")->fetchAll();
$jefes = $pdo->query("SELECT user_id, CONCAT(nombre, ' ', apellido_paterno, ' ', apellido_materno) AS nombre_completo FROM usuarios ORDER BY nombre")->fetchAll();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
    $correo = $_POST['correo'];
    $RFC = $_POST['RFC'];
    $CURP = $_POST['CURP'];
    $IDRUSP = $_POST['IDRUSP'];
    $fecha_alta = $_POST['fecha_alta'];

    // Verificación de duplicados
    $ver = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE user_id = ? OR RFC = ? OR CURP = ?");
    $ver->execute([$user_id, $RFC, $CURP]);

    if ($ver->fetchColumn() > 0) {
        $error = "Ya existe un usuario con el mismo nombre de usuario, RFC o CURP.";
        header("Location: admin_usuarios.php?error=repetido");
        exit;

    } else {
        $foto = null;
        if (!empty($_FILES['foto']['name'])) {
            $foto = uniqid() . "_" . basename($_FILES['foto']['name']);
            move_uploaded_file($_FILES['foto']['tmp_name'], "fotos/" . $foto);
        }

        $stmt = $pdo->prepare("INSERT INTO usuarios (user_id, password, role, nombre, apellido_paterno, apellido_materno, estatus, RFC, homoclave, username, CURP, IDRUSP, sexo, correo, nivel_estudios, fecha_alta, temporal, jefe_id, unidad_id, adscripcion_id, puesto_nombre, puesto_codigo, puesto_nivel, permite_metas_colectivas, requiere_cambio_password, foto ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $user_id,
            password_hash($_POST['password'], PASSWORD_DEFAULT),
            $_POST['role'],
            $_POST['nombre'],
            $_POST['apellido_paterno'],
            $_POST['apellido_materno'],
            $_POST['estatus'],
            $RFC,
            $_POST['homoclave'],
            $RFC,
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

        header("Location: usuarios_editar.php?user_id=$user_id");
        exit;
    }
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
                        case 9:
                            $texto = '✅ Periodo cerrado correctamente';
                            $clase_alerta = 'alert-success';
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
							<h5 class="mb-0">Editar Usuario</h5>
						</div>

						<div class="card-body">
					    Algunos campos son obligatorios.<br/>

    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" action="usuarios_actualizar.php">
        <input type="hidden" name="user_id" value="<?= $usuario['user_id'] ?>">
        <input type="hidden" name="username" value="<?= $usuario['RFC'] ?>">

        <div class="row">
            <div class="col-md-3 mb-3"><label class="col-form-label">Nombre</label><input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($usuario['nombre']) ?>" required></div>
            <div class="col-md-3 mb-3"><label class="col-form-label">Apellido Paterno</label><input type="text" name="apellido_paterno" class="form-control" value="<?= htmlspecialchars($usuario['apellido_paterno']) ?>" required></div>
            <div class="col-md-3 mb-3"><label class="col-form-label">Apellido Materno</label><input type="text" name="apellido_materno" class="form-control" value="<?= htmlspecialchars($usuario['apellido_materno']) ?>" required></div>
            <div class="col-md-3 mb-3"><label class="col-form-label">Activo</label>
                <select name="estatus" class="form-control" required>
                    <option value="1" <?= $usuario['estatus'] == '1' ? 'selected' : '' ?>>Si</option>
                    <option value="0" <?= $usuario['estatus'] == '0' ? 'selected' : '' ?>>No</option>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3 mb-3"><label class="col-form-label">user_id</label><input type="text" name="user_id" class="form-control" readonly value="<?= htmlspecialchars($usuario['user_id']) ?>" required></div>
            <div class="col-md-3 mb-3"><label class="col-form-label">Correo</label><input type="email" name="correo" class="form-control" value="<?= htmlspecialchars($usuario['correo']) ?>" required></div>
            <div class="col-md-3 mb-3"><label class="col-form-label">Nueva Contraseña (dejar vacío para no cambiar)</label><input type="password" name="password" class="form-control"></div>
            <div class="col-md-3 mb-3"><label class="col-form-label" required>Tipo</label>
                <select name="tipo_usuario" class="form-select" required>
                    <?php foreach ($tipo_usuarios as $ti): ?>
                        <option value="<?= $ti['id'] ?>" <?= $usuario['tipo_usuario'] == $ti['id'] ? 'selected' : '' ?>><?= $ti['nombre'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3 mb-3"><label class="col-form-label">RFC</label><input type="text" name="RFC" class="form-control" value="<?= $usuario['RFC'] ?>" required></div>
            <div class="col-md-3 mb-3"><label class="col-form-label">Homoclave</label><input type="text" name="homoclave" class="form-control" value="<?= $usuario['homoclave'] ?>" required></div>
            <div class="col-md-3 mb-3"><label class="col-form-label">CURP</label><input type="text" name="CURP" class="form-control" value="<?= $usuario['CURP'] ?>" required></div>
            <div class="col-md-3 mb-3"><label class="col-form-label">IDRUSP</label><input type="text" name="IDRUSP" class="form-control" value="<?= $usuario['IDRUSP'] ?>"></div>
        </div>

        <div class="row">
            <div class="col-md-3 mb-3"><label class="col-form-label">Puesto</label><input type="text" name="puesto_nombre" class="form-control" value="<?= $usuario['puesto_nombre'] ?>" required></div>
            <div class="col-md-3 mb-3"><label class="col-form-label">Código de Puesto</label><input type="text" name="puesto_codigo" class="form-control" value="<?= $usuario['puesto_codigo'] ?>" required></div>
            <div class="col-md-3 mb-3"><label class="col-form-label">Nivel de Puesto</label>
            <select name="puesto_nivel" class="form-select" required>
                    <?php foreach ($niveles as $niv): ?>
                        <option value="<?= $niv['id'] ?>" <?= $usuario['puesto_nivel'] == $niv['id'] ? 'selected' : '' ?>><?= $niv['nombre'] ?></option>
                    <?php endforeach; ?>
            </select>
            </div>
            <div class="col-md-3 mb-3"><label class="col-form-label">Sexo</label>
                <select name="sexo" class="form-control" required>
                    <option value="">--</option>
                    <option value="H" <?= $usuario['sexo'] == 'H' ? 'selected' : '' ?>>Hombre</option>
                    <option value="M" <?= $usuario['sexo'] == 'M' ? 'selected' : '' ?>>Mujer</option>
                    <option value="Otro" <?= $usuario['sexo'] == 'Otro' ? 'selected' : '' ?>>Otro</option>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3 mb-3"><label class="col-form-label">Nivel de Estudios</label>
                <select name="nivel_estudios" class="form-control">
                    <?php foreach ($escolaridades as $e): ?>
                        <option value="<?= $e['nombre'] ?>" <?= $usuario['nivel_estudios'] == $e['nombre'] ? 'selected' : '' ?>><?= $e['nombre'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 mb-3"><label class="col-form-label">Unidad</label>
            <select name="unidad_id" id="unidad_id" class="form-control" required>
                <option value="">-- Selecciona una unidad --</option>
                 <?php foreach ($unidades as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $usuario['unidad_id'] == $u['id'] ? 'selected' : '' ?>><?= $u['nombre'] ?></option>
                 <?php endforeach; ?>
            </select>
            </div>
            <div class="col-md-3 mb-3"><label class="col-form-label" >Adscripción</label>
            <select name="adscripcion_id" id="adscripcion_id" class="form-control" required>
            <option value="">-- Selecciona una adscripción --</option>
            <?php foreach ($adscripciones as $a): ?>
            <option value="<?= $a['id'] ?>" <?= $usuario['adscripcion_id'] == $a['id'] ? 'selected' : '' ?>>
                <?= $a['nombre'] ?>
            </option>
            <?php endforeach; ?>
            </select>
            </div>
            <div class="col-md-3 mb-3"><label class="col-form-label">Jefe Inmediato</label>
                <select name="jefe_id" class="form-control select" required>
                    <option value="">-- Ninguno --</option>
                    <?php foreach ($jefes as $j): ?>
                        <option value="<?= $j['user_id'] ?>" <?= $usuario['jefe_id'] == $j['user_id'] ? 'selected' : '' ?>><?= $j['nombre_completo'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3 mb-3"><label class="col-form-label">Rol</label>
                <select name="role" class="form-select" required>
                    <option value="1" <?= $usuario['role'] == 1 ? 'selected' : '' ?>>Usuario</option>
                    <option value="2" <?= $usuario['role'] == 2 ? 'selected' : '' ?>>Administrador</option>
                    <option value="3" <?= $usuario['role'] == 3 ? 'selected' : '' ?>>Superadmin</option>
                </select>
            </div>
            <div class="col-md-3 mb-3"><label class="col-form-label">Temporal</label>
                <select name="temporal" class="form-select" required>
                    <option value="0" <?= $usuario['temporal'] == 0 ? 'selected' : '' ?>>No</option>
                    <option value="1" <?= $usuario['temporal'] == 1 ? 'selected' : '' ?>>Sí</option>
                </select>
            </div>
            <div class="col-md-2 mb-3"><label class="col-form-label" required>Metas Colectivas</label>
                <select name="permite_metas_colectivas" class="form-select">
                    <option value="0" <?= $usuario['permite_metas_colectivas'] == 0 ? 'selected' : '' ?>>No</option>
                    <option value="1" <?= $usuario['permite_metas_colectivas'] == 1 ? 'selected' : '' ?>>Sí</option>
                </select>
            </div>
            <div class="col-md-2 mb-3"><label class="col-form-label">Forzar Cambio Password</label>
                <select name="requiere_cambio_password" class="form-select" required>
                    <option value="0" <?= $usuario['requiere_cambio_password'] == 0 ? 'selected' : '' ?>>No</option>
                    <option value="1" <?= $usuario['requiere_cambio_password'] == 1 ? 'selected' : '' ?>>Sí</option>
                </select>
            </div>
            <div class="col-md-2 mb-3"><label class="col-form-label">Fecha Alta</label>
            <input type="date" name="fecha_alta" class="form-control" value="<?= $usuario['fecha_alta'] ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label class="col-form-label">Foto actual:</label><br>
            <?php if ($usuario['foto']): ?>
                <img src="fotos/<?= $usuario['foto'] ?>" alt="Foto de perfil" style="max-width: 120px; border-radius: 10px;">
            <?php else: ?>
                <span>No hay foto</span>
            <?php endif; ?>
        </div>
        <div class="mb-3"><label>Cambiar Foto</label><input type="file" name="foto" class="form-control"></div>

        <button type="submit" class="btn btn-sm btn-primary">Guardar cambios</button>
        <a href="admin_usuarios.php" class="btn btn-sm btn-secondary">Cancelar</a>
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
