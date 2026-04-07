<?php
require 'includes/conexion.php';
require 'includes/variables.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] != 1) {
    header("Location: index.php");
    exit;
} 

$id_usuario = $_GET['id'] ?? null;
if (!$id_usuario) {
    echo "Candidato no especificado.";
    exit;
}

// Datos del candidato
$stmt = $pdo->prepare("SELECT u.*, p.nombre_puesto FROM sis_usuarios u LEFT JOIN puestos p ON u.id_puesto = p.id WHERE u.id_usuario = ?");
$stmt->execute([$id_usuario]);
$usuario = $stmt->fetch();
$nombre_completo = "{$usuario['nombre']} {$usuario['apellido_paterno']}";

// Evaluaciones asignadas
$stmt = $pdo->prepare("
    SELECT sp.nombre 
    FROM can_evaluaciones ce 
    JOIN sis_pruebas sp ON ce.id_prueba = sp.id 
    WHERE ce.id_usuario = ?
");
$stmt->execute([$id_usuario]);
$evaluaciones = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Competencias a evaluar (por puesto)
$stmt = $pdo->prepare("
    SELECT c.nombre_competencia 
    FROM competencias_puestos pc 
    JOIN competencias c ON pc.id_competencia = c.id 
    WHERE pc.id_puesto = ?
");
$stmt->execute([$usuario['id_puesto']]);
$competencias = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Texto de invitación
$invitacion = "Hola {$nombre_completo},\n\nHas sido registrado en nuestro sistema de evaluación psicométrica para el puesto de {$usuario['nombre_puesto']}. 
Te invitamos a ingresar al portal con tu RFC como contraseña temporal y realizar las siguientes evaluaciones:\n\nIngresa en: https://gestionrh.mx/\nUsuario: {$usuario['correo']}\nContraseña: {$usuario['rfc']}\n\n¡Éxito en tu proceso.";

require_once 'includes/enviar_correo.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_correo'])) {
    $enviado = enviarCorreoInvitacion($usuario['correo'], $nombre_completo, $usuario['rfc'], $evaluaciones, $competencias);
}
?>
<!DOCTYPE html>
<html lang="es" dir="ltr">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title>Limitless - Responsive Web Application Kit by Eugene Kopyov</title>

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

	<script src="assets/js/app.js"></script>
	<script src="assets/demo/pages/datatables_basic.js"></script>
	<!-- /theme JS files -->

</head>
<body>

<?php include_once 'includes/navbar.php'; ?>

	<!-- Page content -->
	<div class="page-content">

    <?php include_once 'includes/sidebar.php'; ?>

		<!-- Main content -->
		<div class="content-wrapper">

			<!-- Inner content -->
			<div class="content-inner">

				<!-- Page header -->
				<div class="page-header page-header-light shadow">
					<div class="page-header-content d-lg-flex border-top">
						<div class="d-flex">
							<div class="breadcrumb py-2">
								<a href="index.html" class="breadcrumb-item"><i class="ph-house"></i></a>
								<a href="evaluaciones.php" class="breadcrumb-item">Inicio</a>
								<span class="breadcrumb-item active">Dashboard</span>
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

				<?php if (isset($_GET['asignadas'])): ?>
				<div class="alert alert-success">Pruebas asignadas correctamente.</div>
				<?php endif; ?>

				<?php if (isset($enviado)): ?>
					<p style="color: <?= $enviado ? 'green' : 'red' ?>;">
						<?= $enviado ? 'Correo enviado correctamente.' : 'Error al enviar el correo.' ?>
					</p>
				<?php endif; ?>


							<!-- Dashboard content -->
					<div class="row">
						<div class="col-xl-12">


							<!-- CARD -->
							<div class="card">
								<div class="card-header d-flex align-items-center">
									<h5 class="mb-0">Detalle Evaluación</h5>
								</div>

                                <div class="card-body">

                                <h2>Evaluaciones asignadas a <?= htmlspecialchars($nombre_completo) ?></h2>


                                <ul>
                                    <?php foreach ($evaluaciones as $ev): ?>
                                        <li><?= htmlspecialchars($ev) ?></li>
                                    <?php endforeach; ?>
                                </ul>


                                <h2>Competencias a evaluar</h2>
                                <ul>
                                    <?php foreach ($competencias as $comp): ?>
                                        <li><?= htmlspecialchars($comp) ?></li>
                                    <?php endforeach; ?>
                                </ul>

                                <h2>Texto de invitación</h2>
                                <textarea rows="10" cols="100"><?= htmlspecialchars($invitacion) ?></textarea>

								<form method="post">
									<input type="hidden" name="enviar_correo"  value="1">
									<button type="submit" class="btn btn-info">Enviar invitación por correo</button>
								</form>
							
								<p>&nbsp;</p>

                                <h2>Asignar pruebas adicionales</h2>
								<form method="post" action="asignar_pruebas_adicionales.php">
									<input type="hidden" name="id_usuario" value="<?= $id_usuario ?>">

									<select name="pruebas[]" id="pruebas" multiple class="form-control" required>
										<?php
										// Obtener pruebas disponibles (por ejemplo, todas menos las ya asignadas)
										$stmt = $pdo->prepare("SELECT id, nombre FROM sis_pruebas WHERE id NOT IN (
											SELECT id_prueba FROM can_evaluaciones WHERE id_usuario = ?
										)");
										$stmt->execute([$id_usuario]);
										while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
											echo "<option value='{$row['id']}'>{$row['nombre']}</option>";
										}
										?>
									</select>

									<button type="submit" class="btn btn-primary mt-2">Asignar Pruebas</button>
								</form>


							</div>

							</div>
							<!-- /CARD -->

                        </div>
                    </div>
					<!-- /dashboard content -->

				</div>
				<!-- /content area -->

                <?php include_once 'includes/footer.php'; ?>

			</div>
			<!-- /inner content -->

		</div>
		<!-- /main content -->

	</div>
	<!-- /page content -->

</body>
</html>