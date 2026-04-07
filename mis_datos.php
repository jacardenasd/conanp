
<?php
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

$stmt = $pdo->prepare("SELECT u.*, CONCAT(j.nombre, ' ', j.apellido_paterno, ' ', j.apellido_materno) AS jefe_nombre
                       FROM usuarios u
                       LEFT JOIN usuarios j ON u.jefe_id = j.user_id
                       WHERE u.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$usuario = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_foto']) && isset($_FILES['foto'])) {
    $user_id = $_SESSION['user_id']; 

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);
    $archivo = $_FILES['foto'];

    if ($archivo['error'] === UPLOAD_ERR_OK) {
        $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
        $nombre_archivo = 'foto_' . $user_id . '_' . time() . '.' . $extension;
        $ruta_destino = 'fotos/' . $nombre_archivo;

        if (!is_dir('fotos')) {
            mkdir('fotos', 0755, true);
        }

        if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
            $stmt = $pdo->prepare("UPDATE usuarios SET foto = ? WHERE user_id = ?");
            $stmt->execute([$nombre_archivo, $user_id]);
            echo '<div class="alert alert-success mt-3">✅ Fotografía subida correctamente.</div>';
        } else {
            echo '<div class="alert alert-danger mt-3">❌ No se pudo guardar el archivo.</div>';
        }
    } else {
        echo '<div class="alert alert-danger mt-3">❌ Error al subir el archivo.</div>';
    }
}

// nivel
$stmt = $pdo->prepare("SELECT nombre FROM niveles WHERE id = ?");
$stmt->execute([$usuario['puesto_nivel']]);
$nivel = $stmt->fetch(PDO::FETCH_ASSOC);

// Fallback si no se encuentra
$nombre_nivel = $nivel ? $nivel['nombre'] : 'Sin clasificar';
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title><?php echo $nombre_sistema; ?></title>

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
	<script src="assets/js/vendor/forms/selects/select2.min.js"></script>

	<script src="assets/js/app.js"></script>
	<script src="assets/demo/pages/form_select2.js"></script>
</head>
<body>

<?php require_once('assets/main_navbar.php'); ?>

	<!-- Page content -->
	<div class="page-content">

	<?php if (isset($_SESSION['user_id'])) { ?>

		<?php require_once('assets/main_navigation.php'); ?>

		<?php } ?>

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
								<span class="breadcrumb-item active">Inicio</span>
								<span class="breadcrumb-item active">Mis datos</span>
							</div>
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


          <!-- Icon and text -->
          <div class="card card-body">
            <div class="row mt-4">

            <p>Consulta tus datos personales</p>

            
  <div class="col-md-3">
    <div class="border p-3 text-center">
      <img src="fotos/<?= htmlspecialchars($usuario['foto'] ?? 'default.png') ?>" class="img-thumbnail mb-2" style="max-width: 150px;">
      <button type="button" class="btn btn-outline-primary btn-sm mb-2" data-bs-toggle="modal" data-bs-target="#modalSubirFoto">
        📷 Subir nueva fotografía
      </button>
      <small class="d-block mt-2 text-muted">
        Tamaño oficial 2.5 x 3 cm<br>
        Tomada completamente de frente, rostro serio<br>
        Color o blanco y negro, fondo blanco
      </small>
    </div>
  </div>

  <div class="col-md-9">
    <table class="table table-bordered">
      <tr><th>Nombre</th><td><?= htmlspecialchars($usuario['nombre'] ?? '') ?></td></tr>
      <tr><th>Apellido Paterno</th><td><?= htmlspecialchars($usuario['apellido_paterno'] ?? '') ?></td></tr>
      <tr><th>Apellido Materno</th><td><?= htmlspecialchars($usuario['apellido_materno'] ?? '') ?></td></tr>
      <tr><th>RFC</th><td><?= htmlspecialchars($usuario['RFC'] ?? '') ?>-<?= htmlspecialchars($usuario['homoclave'] ?? '') ?></td></tr>
      <tr><th>CURP</th><td><?= htmlspecialchars($usuario['CURP'] ?? '') ?></td></tr>
      <tr><th>ID RUSP</th><td><?= htmlspecialchars($usuario['IDRUSP'] ?? '') ?></td></tr>
      <tr><th>Código de Puesto</th><td><?= htmlspecialchars($usuario['puesto_codigo'] ?? '') ?></td></tr>
      <tr><th>Nivel</th><td><?= $nombre_nivel ?></td></tr>
      <tr><th>Nombre del Puesto</th><td><?= htmlspecialchars($usuario['puesto_nombre'] ?? '') ?></td></tr>
      <tr><th>Superior Jerárquico</th><td><?= htmlspecialchars($usuario['jefe_nombre'] ?? 'No asignado') ?></td></tr>
      <tr><th>Correo</th><td><?= htmlspecialchars($usuario['correo'] ?? '') ?></td></tr>
      <tr><th>Permite Metas Colectivas</th><td><?= (!empty($usuario['permite_metas_colectivas']) ? 'Sí' : 'No') ?></td></tr>
    </table>

    <div class="mt-3">
      <a href="editar_datos_personales.php" class="btn btn-primary btn-sm">Corregir datos</a>
      <a href="cambiar_contrasena.php" class="btn btn-dark btn-sm">Cambiar contraseña</a>
    </div>
  </div>
</div>

<!-- Modal para subir fotografía -->
<div class="modal fade" id="modalSubirFoto" tabindex="-1" aria-labelledby="modalSubirFotoLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" enctype="multipart/form-data" class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="modalSubirFotoLabel">Subir Fotografía</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <input type="file" name="foto" accept="image/*" class="form-control mb-2" required>
      </div>
      <div class="modal-footer">
        <button name="subir_foto" class="btn btn-primary">Subir Fotografía</button>
      </div>
    </form>
  </div>
</div>
<!-- Modal para subir fotografía -->



          </div>
          <!-- /icon and text -->

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
