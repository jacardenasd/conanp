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

if ($_SESSION['role'] != '3') {
  header("Location: index.php");
  exit;
}

// Guardar nuevo evento
if (isset($_POST['guardar'])) {
  $stmt = $pdo->prepare("INSERT INTO calendario_evaluacion (titulo, fecha_inicio_captura, fecha_fin_captura, fecha_inicio_evaluacion, fecha_fin_evaluacion, tipo, visible) VALUES (?, ?, ?, ?, ?, ?, ?)");
  $stmt->execute([$_POST['titulo'], $_POST['fecha_inicio_captura'], $_POST['fecha_fin_captura'], $_POST['fecha_inicio_evaluacion'], $_POST['fecha_fin_evaluacion'], $_POST['tipo'], $_POST['visible'],]);
  header("Location: admin_calendario.php?mensaje=guardado");
  exit;
}

// Actualizar evento
if (isset($_POST['actualizar'])) {
  $stmt = $pdo->prepare("UPDATE calendario_evaluacion SET titulo = ?, fecha_inicio_captura = ?, fecha_fin_captura = ?, fecha_inicio_evaluacion = ?, fecha_fin_evaluacion = ?, tipo = ?, visible = ? WHERE id = ?");
  $stmt->execute([$_POST['titulo'], $_POST['fecha_inicio_captura'], $_POST['fecha_fin_captura'], $_POST['fecha_inicio_evaluacion'], $_POST['fecha_fin_evaluacion'], $_POST['tipo'], $_POST['visible'], $_POST['id']]);
  header("Location: admin_calendario.php?mensaje=actualizado");
  exit;
}

// Eliminar evento
if (isset($_GET['eliminar'])) {
  $stmt = $pdo->prepare("DELETE FROM calendario_evaluacion WHERE id = ?");
  $stmt->execute([$_GET['eliminar']]);
  header("Location: admin_calendario.php?mensaje=eliminado");
  exit;
}

// Obtener eventos
$eventos = $pdo->query("SELECT * FROM calendario_evaluacion ORDER BY fecha_inicio_captura")->fetchAll();
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
								<span class="breadcrumb-item active">Unidades</span>
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
                        <h4>Calendario de Evaluación</h4>

                                    
            <?php if (isset($_GET['mensaje'])): ?>
                <div class="alert alert-success mt-3">
                <?php
                    if ($_GET['mensaje'] == 'guardado') echo '✅ Evento agregado correctamente.';
                    if ($_GET['mensaje'] == 'actualizado') echo '✏️ Evento actualizado.';
                    if ($_GET['mensaje'] == 'eliminado') echo '🗑️ Evento eliminado.';
                ?>
                </div>
            <?php endif; ?>

            <form method="post" class="form-inline mb-4">
                <input type="text" name="titulo" class="form-control mr-2" placeholder="Etapa..." required>
                <div class="d-flex mb-3">
                <div class="me-2">
                    <label>Inicio Captura</label>
                    <input type="date" name="fecha_inicio_captura" class="form-control" required>
                </div>
                <div>
                    <label>Fin Captura</label>
                    <input type="date" name="fecha_fin_captura" class="form-control" required>
                </div>
                <div class="me-2">
                    <label>Inicio Evaluacion</label>
                    <input type="date" name="fecha_inicio_evaluacion" class="form-control" required>
                </div>
                <div>
                    <label>Fin Evaluacion</label>
                    <input type="date" name="fecha_fin_evaluacion" class="form-control" required>
                </div>
                <div>
                <label>Tipo</label>
                <select name="tipo" class="form-select" required>
                    <option value="1">SPC</option>
                    <option value="2">No SPC</option>
                </select>
                </div>
                <div>
                <label>Visible</label>
                <select name="visible" class="form-select" required>
                    <option value="1">Si</option>
                    <option value="0">No</option>
                </select>
                </div>
                </div>
                <div class="me-2">
                <button name="guardar" class="btn btn-success">Agregar</button>
                </div>

            </form>

            <table class="table table-bordered table-sm">
                <thead class="thead-light">
                <tr>
                    <th>Etapa</th>
                    <th>Inicio Captura</th>
                    <th>Fin Captura</th>
                    <th>Inicio Evaluacion</th>
                    <th>Fin Evaluacion</th>
                    <th>Tipo</th>
                    <th>Visible</th>
                    <th>Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($eventos as $e): ?>
                    <tr>
                    <form method="post">
                        <td>
                        <input type="text" name="titulo" value="<?= htmlspecialchars($e['titulo']) ?>" class="form-control" required>
                        <input type="hidden" name="id" value="<?= $e['id'] ?>">
                        </td>
                        <td><input type="date" name="fecha_inicio_captura" value="<?= $e['fecha_inicio_captura'] ?>" class="form-control" required></td>
                        <td><input type="date" name="fecha_fin_captura" value="<?= $e['fecha_fin_captura'] ?>" class="form-control" required></td>
                        <td><input type="date" name="fecha_inicio_evaluacion" value="<?= $e['fecha_inicio_evaluacion'] ?>" class="form-control" required></td>
                        <td><input type="date" name="fecha_fin_evaluacion" value="<?= $e['fecha_fin_evaluacion'] ?>" class="form-control" required></td>
                        <td>
                        <select name="tipo" class="form-select" required>
                            <option value="1" <?= $e['tipo'] == 1 ? 'selected' : '' ?>>SPC</option>
                            <option value="2" <?= $e['tipo'] == 2 ? 'selected' : '' ?>>No SPC</option>
                        </select>
                        </td>
                        <td>
                        <select name="visible" class="form-select" required>
                            <option value="1" <?= $e['visible'] == 1 ? 'selected' : '' ?>>Si</option>
                            <option value="0" <?= $e['visible'] == 0 ? 'selected' : '' ?>>No</option>
                        </select>
                        </td>
                        <td>
                        <button name="actualizar" class="btn btn-primary btn-sm">Guardar</button>
                        <a href="admin_calendario.php?eliminar=<?= $e['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar este evento?')">Eliminar</a>
                        </td>
                    </form>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
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
