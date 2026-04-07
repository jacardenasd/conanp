<?php
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


// Guardar usuario
if ((isset($_POST["MM_insert"])) && ($_POST["MM_insert"] == "form1")) {

  $user_id = $_POST['user_id'];
  $RFC = $_POST['RFC'];
  $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE user_id = ? OR RFC = ?");
  $stmt->execute([$user_id, $RFC]);
  $usuario = $stmt->fetch();
  
  if ($usuario) {
    header("Location: usuarios.php?info=5");
    exit();
  }

  $stmt = $pdo->prepare("INSERT INTO usuarios (user_id, username, role, nombre, apellido_paterno, apellido_materno, estatus, RFC, IDRUSP, sexo, correo, nivel_estudios, fecha_alta, temporal, jefe_id, unidad_id, adscripcion_id, puesto_id, permite_metas_colectivas, requiere_cambio_password, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
  $stmt->execute([
    $_POST['user_id'],
    $_POST['RFC'],
    $_POST['role'],
    $_POST['nombre'],
    $_POST['apellido_paterno'],
    $_POST['apellido_materno'],
    $_POST['estatus'],
    $_POST['RFC'],
    $_POST['IDRUSP'],
    $_POST['sexo'],
    $_POST['correo'],
    $_POST['nivel_estudios'],
    $_POST['fecha_alta'],
    $_POST['temporal'],
    $_POST['jefe_id'],
    $_POST['unidad_id'],
    $_POST['adscripcion_id'],
    $_POST['puesto_id'],
    $_POST['permite_metas_colectivas'],
    $_POST['requiere_cambio_password'],
    password_hash($_POST['RFC'], PASSWORD_DEFAULT)
  ]);
  header("Location: usuarios.php?info=1");
  exit();
}

// Editar usuario
if ((isset($_POST["MM_update"])) && ($_POST["MM_update"] == "form1")) {
  $stmt = $pdo->prepare("UPDATE usuarios SET username = ?, role = ?, nombre = ?, apellido_paterno = ?, apellido_materno = ?, estatus = ?, RFC = ?, IDRUSP = ?, sexo = ?, correo = ?, nivel_estudios = ?, fecha_alta = ?, temporal = ?,  jefe_id = ?, unidad_id = ?, adscripcion_id = ?, puesto_id = ?, permite_metas_colectivas = ?, requiere_cambio_password = ? WHERE user_id = ?");
  $stmt->execute([
    $_POST['RFC'],
    $_POST['role'],
    $_POST['nombre'],
    $_POST['apellido_paterno'],
    $_POST['apellido_materno'],
    $_POST['estatus'],
    $_POST['RFC'],
    $_POST['IDRUSP'],
    $_POST['sexo'],
    $_POST['correo'],
    $_POST['nivel_estudios'],
    $_POST['fecha_alta'],
    $_POST['temporal'],
    $_POST['jefe_id'],
    $_POST['unidad_id'],
    $_POST['adscripcion_id'],
    $_POST['puesto_id'],
    $_POST['permite_metas_colectivas'],
    $_POST['requiere_cambio_password'],
    $_POST['user_id'],
  ]);
  header("Location: usuarios.php?info=2");
  exit();
}

// Eliminar usuario
if (isset($_GET['eliminar'])) {
  $stmt = $pdo->prepare("DELETE FROM usuarios WHERE user_id = ?");
  $stmt->execute([$_GET['eliminar']]);
  header("Location: usuarios.php?info=3");
  exit();
}

// Restablecer contraseña a RFC
if (isset($_GET['resetpass'])) {
    $stmt = $pdo->prepare("SELECT RFC FROM usuarios WHERE user_id = ?");
    $stmt->execute([$_GET['resetpass']]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($usuario) {
        $nueva_clave = password_hash($usuario['RFC'], PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE usuarios SET password = ? WHERE user_id = ?");
        $update->execute([$nueva_clave, $_GET['resetpass']]);
    }
    header("Location: usuarios.php?info=4");
    exit();
}

// Obtener usuarios
$stmt = $pdo->query("SELECT * FROM usuarios");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es" dir="ltr">
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
								<a href="#" class="breadcrumb-item">Administracion</a>
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


        <?php if (isset($_GET['info'])): ?>
                    <?php
                    $clase_alerta = 'alert-success'; // valor por defecto

                    switch ($_GET['info']) {
                        case 1:
                            $texto = '✅ Usuario guardado correctamente.';
                            $clase_alerta = 'alert-success';
                            break;
                        case 2:
                            $texto = '✏️ Usuario actualizado correctamente.';
                            $clase_alerta = 'alert-info';
                            break;
                        case 3:
                            $texto = '🗑️ Usuario eliminado correctamente.';
                            $clase_alerta = 'alert-danger';
                            break;
                            case 4:
                              $texto = '⚠️ Se ha restablecido la contraseña';
                              $clase_alerta = 'alert-warning';
                              break;
                            case 5:
                              $texto = '⚠️ El usuario ya existe, favor de verificar';
                              $clase_alerta = 'alert-warning';
                              break;
                          default:
                            $texto = 'Mensaje no reconocido.';
                            $clase_alerta = 'alert-secondary';
                            break;
                    }
                    ?>

                    <div class="alert <?= $clase_alerta ?> border-0 alert-dismissible fade show">
										<span class="fw-semibold"> <?= $texto ?>
										<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
								    </div>

                    <?php endif; ?>


					<!-- Basic table -->
					<div class="card">
						<div class="card-header">
							<h5 class="mb-0">Usuarios</h5>
						</div>

						<div class="card-body">
							Example of a  calendars and date pickers, we've opted to isolate our custom table styles.
						</div>

						<div class="table-responsive">
						<table class="table datatable-pagination">
							<thead class="thead-light">
								<tr>
									<th>user_id</th>
									<th>Usuario</th>
									<th>Nombre completo</th>
									<th>RFC</th>
									<th>Correo</th>
									<th>Rol</th>
									<th>Acciones</th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ($usuarios as $u): ?>
								<tr>
									<td><?= $u['user_id'] ?></td>
									<td><?= htmlspecialchars($u['username']) ?></td>
									<td><?= htmlspecialchars("{$u['nombre']} {$u['apellido_paterno']} {$u['apellido_materno']}") ?></td>
									<td><?= htmlspecialchars($u['RFC']) ?></td>
									<td><?= htmlspecialchars($u['correo']) ?></td>
									<td><?= $u['role'] ?></td>
									<td>
									    <a href="usuarios_editar.php?user_id=<?= $u['user_id'] ?>" class="btn btn-primary btn-sm">Editar</a>

										<button class='btn btn-sm btn-warning btn-resetpass' data-id="<?= $u['user_id'] ?>" data-nombre="<?= $u['nombre'] ?> <?= $u['apellido_paterno'] ?>" data-bs-toggle="modal" data-bs-target="#modalResetPass">Restablecer</button>

                    <button class="btn btn-danger btn-sm btn-eliminar" data-id="<?= $u['user_id'] ?>" data-nombre="<?= $u['nombre'] ?>" data-bs-toggle="modal" data-bs-target="#modalEliminar"> Eliminar </button>
                    <a href="generar_reporte_pdf_plantilla.php?user_id=<?= $u['user_id'] ?>&periodo=1" class="btn btn-sm btn-outline-danger">PDF</a>
                    <a href="generar_reporte_individual.php?user_id=<?= $u['user_id'] ?>&periodo=1" class="btn btn-sm btn-outline-success">XLSX</a>

									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
						</div>
					</div>
					<!-- /basic table -->

          <a href="usuarios_agregar.php" class="btn btn-success btn-sm">Agregar</a>

				</div>
				<!-- /content area -->

				<?php require_once('assets/footer.php'); ?>

			</div>
			<!-- /inner content -->

		</div>
		<!-- /main content -->

	</div>
	<!-- /page content -->

<!-- Modal Eliminar -->
<div class="modal fade" id="modalEliminar" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <form method="get" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Eliminar Usuario</h5></div>
      <div class="modal-body">
        ¿Deseas eliminar al usuario <strong id="nombreEliminar"></strong>?
        <input type="hidden" name="eliminar" id="idEliminar">
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-danger">Eliminar</button>
        <button class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.btn-eliminar').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('idEliminar').value = btn.dataset.id;
      document.getElementById('nombreEliminar').textContent = btn.dataset.nombre;
    });
  });
});
</script>

<!-- Modal Restablecer Password -->
<div class="modal fade" id="modalResetPass" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <form method="get" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Restablecer Pasasword</h5></div>
      <div class="modal-body">
        ¿Deseas restablecer el password de <strong id="nombreResetPass"></strong>?
        <input type="hidden" name="resetpass" id="idResetPass">
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-warning">Restablecer</button>
        <button class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.btn-resetpass').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('idResetPass').value = btn.dataset.id;
      document.getElementById('nombreResetPass').textContent = btn.dataset.nombre;
    });
  });
});
</script>


</body>
</html>
