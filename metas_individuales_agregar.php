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
$periodo = $_SESSION['periodo'];

// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();

$user_id = $_SESSION['user_id']; 

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);
$errores = [];

// Cargar unidades de medida
$unidades = $pdo->query("SELECT nombre FROM unidades_medida ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
$periodos = $pdo->query("SELECT anio FROM periodos ORDER BY anio")->fetchAll(PDO::FETCH_ASSOC);
$usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY user_id")->fetchAll(PDO::FETCH_ASSOC);

function resetear_estatus_individuales_admin(PDO $pdo, int $user_id_objetivo, int $periodo_objetivo): void {
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM metas WHERE user_id = ? AND periodo = ?");
  $stmt->execute([$user_id_objetivo, $periodo_objetivo]);
  $total_metas = (int)$stmt->fetchColumn();

  $nuevo_estatus = ($total_metas > 0) ? 1 : 0;

  $stmt = $pdo->prepare("SELECT COUNT(*) FROM calificaciones WHERE user_id = ? AND periodo = ?");
  $stmt->execute([$user_id_objetivo, $periodo_objetivo]);
  $existe_calificacion = (int)$stmt->fetchColumn();

  if ($existe_calificacion > 0) {
    $stmt = $pdo->prepare("UPDATE calificaciones SET estatus_metas = ?, individuales = 0 WHERE user_id = ? AND periodo = ?");
    $stmt->execute([$nuevo_estatus, $user_id_objetivo, $periodo_objetivo]);
  } else {
    $stmt = $pdo->prepare("INSERT INTO calificaciones (user_id, periodo, estatus_metas, individuales) VALUES (?, ?, ?, 0)");
    $stmt->execute([$user_id_objetivo, $periodo_objetivo, $nuevo_estatus]);
  }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$user_id =  $_POST['user_id'];
$periodo = $_POST['periodo'];

  // Contar cuántas metas hay ya
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM metas WHERE user_id = ? AND periodo = ?");
  $stmt->execute([$user_id, $periodo]);
  $totalMetas = $stmt->fetchColumn();

  if ($totalMetas >= 7) {
    $errores[] = 'Ya se han capturado el máximo de 7 metas.';
  }

  // Validar suma de ponderación
  $stmt = $pdo->prepare("SELECT SUM(ponderacion) FROM metas WHERE user_id = ? AND periodo = ?");
  $stmt->execute([$user_id, $periodo]);
  $suma = $stmt->fetchColumn();

  if ($suma + $_POST['ponderacion'] > 100) {
    $errores[] = 'La ponderación total excede el 100%.';
  }

  // Validar contenido de la meta
  if (strlen(trim($_POST['indicador'])) < 10 || is_numeric($_POST['indicador'])) {
    $errores[] = 'La descripción de la meta debe tener al menos 10 caracteres y no ser solo números.';
  }

  if (empty($errores)) {
    $stmt = $pdo->prepare("INSERT INTO metas (user_id, periodo, indicador, unidad, ponderacion, sobresaliente, satisfactorio, no_satisfactorio, no_aprobatorio, deficiente, estatus)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->execute([
      $_POST['user_id'],
      $_POST['periodo'],
      $_POST['indicador'],
      $_POST['unidad'],
      $_POST['ponderacion'],
      $_POST['sobresaliente'],
      $_POST['satisfactorio'],
      $_POST['no_satisfactorio'],
      $_POST['no_aprobatorio'],
      $_POST['deficiente'],
      0
    ]);

    resetear_estatus_individuales_admin($pdo, (int)$_POST['user_id'], (int)$_POST['periodo']);

    header("Location: admin_metas_individuales.php?info=1");
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
								<a href="#" class="breadcrumb-item">Metas Individuales</a>
								<span class="breadcrumb-item active">Agregar Meta</span>
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
            <h3>Agregar Meta</h3>
						</div>

						<div class="card-body">
						Captura la información solicitada; algunos campos son obligatorios.
            <p>&nbsp;</p>


           <?php if (!empty($errores)): ?>
              <div class="alert alert-danger"><ul><?php foreach ($errores as $e) echo "<li>$e</li>"; ?></ul></div>
            <?php endif; ?>

            <form method="post">
            <div class="row">
		              <div class="col-lg-6">
									<div class="mb-6">
									<label class="col-lg-3 col-form-label">Usuario:</label>
                  <select name="user_id" class="form-control" required>
                  <option value="">Seleccione</option>
                  <?php foreach ($usuarios as $un): ?>
                    <option value="<?= $un['user_id'] ?>" <?php if (isset($_POST['user_id']) AND $_POST['user_id'] == $un['user_id']) {echo 'selected';} ?>><?= $un['nombre']." ".$un['apellido_paterno']." ".$un['apellido_materno'] ?></option>
                  <?php endforeach; ?>
                </select>
              		</div>
               		</div>

		              <div class="col-lg-6">
									<div class="mb-6">
									<label class="col-lg-3 col-form-label">Periodo:</label>
                  <select name="periodo" class="form-control" required>
                  <option value="">Seleccione</option>
                  <?php foreach ($periodos as $pe): ?>
                    <option value="<?= $pe['anio'] ?>" <?php if (isset($_POST['periodo']) AND $_POST['periodo'] == $pe['anio']) {echo 'selected';} ?>><?= $pe['anio'] ?></option>
                  <?php endforeach; ?>
                </select>
               		</div>
               	</div>


              <div class="form-group"><label class="col-lg-3 col-form-label">Descripción de la Meta Individual:</label>
              <textarea name="indicador" class="form-control" rows="3" required><?php if  (isset($_POST['indicador'])) {echo $_POST['indicador'];} ?></textarea>
              </div>

              <div class="row">
		              <div class="col-lg-6">
									<div class="mb-6">
									<label class="col-lg-3 col-form-label">Unidad de Medida:</label>
                  <select name="unidad" class="form-control" required>
                  <option value="">Seleccione</option>
                  <?php foreach ($unidades as $u): ?>
                    <option value="<?= $u ?>" <?php if (isset($_POST['unidad']) AND $_POST['unidad'] == $u) {echo 'selected';} ?>><?= $u ?></option>
                  <?php endforeach; ?>
                </select>
              		</div>
               		</div>

		              <div class="col-lg-6">
									<div class="mb-6">
									<label class="col-lg-3 col-form-label">Ponderación (%):</label>
                  <input name="ponderacion" type="number" min="1" max="100" class="form-control" value="<?php if  (isset($_POST['ponderacion'])) {echo $_POST['ponderacion'];} ?>" required>									</div>
               		</div>
               	</div>

              <div class="form-group"><label class="col-lg-3 col-form-label">Resultado Sobresaliente:</label>
                <textarea name="sobresaliente" class="form-control" rows="2" required><?php if  (isset($_POST['sobresaliente'])) {echo $_POST['sobresaliente'];} ?></textarea>
              </div>
              <div class="form-group"><label class="col-lg-3 col-form-label">Resultado Satisfactorio:</label>
                <textarea name="satisfactorio" class="form-control" rows="2" required><?php if  (isset($_POST['satisfactorio'])) {echo $_POST['satisfactorio'];} ?></textarea>
              </div>
              <div class="form-group"><label class="col-lg-3 col-form-label">Resultado No Satisfactorio:</label>
                <textarea name="no_satisfactorio" class="form-control" rows="2" required><?php if  (isset($_POST['no_satisfactorio'])) {echo $_POST['no_satisfactorio'];} ?></textarea>
              </div>
              <div class="form-group"><label class="col-lg-3 col-form-label">Resultado No Aprobatorio:</label>
                <textarea name="no_aprobatorio" class="form-control" rows="2" required><?php if  (isset($_POST['no_aprobatorio'])) {echo $_POST['no_aprobatorio'];} ?></textarea>
              </div>
              <div class="form-group"><label class="col-lg-3 col-form-label">Resultado Deficiente:</label>
                <textarea name="deficiente" class="form-control" rows="2" required><?php if  (isset($_POST['deficiente'])) {echo $_POST['deficiente'];} ?></textarea>
              </div>
              <p>&nbsp;</p>
              </div>

              <button class="btn btn-sm btn-success">Guardar Meta</button>
              <a href="metas_individuales.php" class="btn btn-sm btn-secondary">Regresar</a>
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
