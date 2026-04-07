<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';
require 'includes/instrumentos_pnd.php';
require 'includes/ejes_pnd.php';

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
$periodo = $_SESSION['periodo'];
$captura_metas_colectivas_bloqueada = modulo_bloqueado_por_periodo('metas_colectivas', (int)$periodo);


// Consultar el periodo actual desde la tabla variables

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
$instrumentos_pnd = obtener_instrumentos_pnd($pdo, true);
$ejes_pnd = obtener_ejes_pnd($pdo, true);
asegurar_columna_eje_pnd_metas_colectivas($pdo);
$ids_instrumentos_pnd = array_map(static function ($item) {
	return (int)$item['id'];
}, $instrumentos_pnd);
$ids_ejes_pnd = array_map(static function ($item) {
	return (int)$item['id'];
}, $ejes_pnd);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if ($captura_metas_colectivas_bloqueada) {
		header("Location: metas_colectivas.php?info=8");
		exit;
	}

  // Contar cuántas metas_colectivas hay ya
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM metas_colectivas WHERE unidad_id = ? AND periodo = ?");
  $stmt->execute([$unidad_id, $periodo]);
  $totalMetas = $stmt->fetchColumn();

  if ($totalMetas >= 7) {
    $errores[] = 'Ya has capturado el máximo de 7 metas.';
  }

  // Validar suma de ponderación
  $stmt = $pdo->prepare("SELECT SUM(ponderacion) FROM metas_colectivas WHERE unidad_id = ? AND periodo = ?");
  $stmt->execute([$unidad_id, $periodo]);
  $suma = $stmt->fetchColumn();

  if ($suma + $_POST['ponderacion'] > 100) {
    $errores[] = 'La ponderación total excede el 100%.';
  }

  // Validar contenido de la meta
  if (strlen(trim($_POST['indicador'])) < 10 || is_numeric($_POST['indicador'])) {
    $errores[] = 'La descripción de la meta debe tener al menos 10 caracteres y no ser solo números.';
  }

	$instrumento_id = (int)($_POST['instrumento'] ?? 0);
	if (!in_array($instrumento_id, $ids_instrumentos_pnd, true)) {
		$errores[] = 'Selecciona una alineación al P.N.D. válida.';
	}

	$eje_pnd_id = (int)($_POST['eje_pnd_id'] ?? 0);
	if (!in_array($eje_pnd_id, $ids_ejes_pnd, true)) {
		$errores[] = 'Selecciona un eje del P.N.D. válido.';
	}

  if (empty($errores)) {
		$stmt = $pdo->prepare("INSERT INTO metas_colectivas (unidad_id, periodo, indicador, instrumento, eje_pnd_id, unidad, ponderacion, satisfactorio)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->execute([
      $unidad_id,
      $periodo,
      $_POST['indicador'],
	$instrumento_id,
	$eje_pnd_id,
      $_POST['unidad'],
      $_POST['ponderacion'],
      $_POST['satisfactorio']
    ]);

    header("Location: metas_colectivas.php?mensaje=guardado");
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
								<a href="#" class="breadcrumb-item">Metas Colectivas</a>
								<span class="breadcrumb-item active">Agregar Meta Colectiva <?= $periodo ?></span>
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
              <div class="form-group"><label class="col-lg-3 col-form-label">Descripción de la Meta Colectiva:</label>
              <textarea name="indicador" class="form-control" rows="3" required><?php if  (isset($_POST['indicador'])) {echo $_POST['indicador'];} ?></textarea>
              </div>

              <div class="row">
		              <div class="col-lg-4">
					<div class="mb-6">
					<label class="col-lg-3 col-form-label">Instrumento:</label>
                  <select name="instrumento" class="form-control" required>
                  <option value="">Seleccione</option>
                  <?php foreach ($instrumentos_pnd as $instrumento): ?>
                    <option value="<?= (int)$instrumento['id'] ?>" <?php if ((string)($_POST['instrumento'] ?? '') === (string)$instrumento['id']) { echo 'selected'; } ?>>
                      <?= htmlspecialchars($instrumento['nombre']) ?>
                    </option>
                  <?php endforeach; ?>
                  </select>
              		</div>
               		</div>

                   <div class="col-lg-4">
									<div class="mb-6">
									<label class="col-lg-5 col-form-label">Unidad de Medida:</label>
                  <select name="unidad" class="form-control" required>
                  <option value="">Seleccione</option>
                  <?php foreach ($unidades as $u): ?>
                    <option value="<?= $u ?>" <?php if (isset($_POST['unidad']) AND $_POST['unidad'] === $u) {echo 'selected';} ?>><?= $u ?></option>
                  <?php endforeach; ?>
                </select>
              		</div>
               		</div>

									<div class="col-lg-4">
					<div class="mb-6">
					<label class="col-lg-3 col-form-label">Eje del P.N.D.:</label>
									<select name="eje_pnd_id" class="form-control" required>
									<option value="">Seleccione</option>
									<?php foreach ($ejes_pnd as $eje): ?>
										<option value="<?= (int)$eje['id'] ?>" <?php if ((string)($_POST['eje_pnd_id'] ?? '') === (string)$eje['id']) { echo 'selected'; } ?>>
											<?= htmlspecialchars($eje['nombre']) ?>
										</option>
									<?php endforeach; ?>
								</select>
					</div>
				</div>

                  <div class="col-lg-4">
									<div class="mb-6">
									<label class="col-lg-3 col-form-label">Ponderación (%):</label>
                  <input name="ponderacion" type="number" min="1" max="100" class="form-control" value="<?php if  (isset($_POST['ponderacion'])) {echo $_POST['ponderacion'];} ?>" required>
                  </div>
               		</div>
               	</div>

              <div class="form-group"><label class="col-lg-3 col-form-label">Resultado Esperado:</label>
              <input name="satisfactorio" type="text" class="form-control" value="<?php if  (isset($_POST['satisfactorio'])) {echo $_POST['satisfactorio'];} ?>" required>
              </div>
              <p>&nbsp;</p>
              <button class="btn btn-sm btn-success">Guardar Meta</button>
              <a href="metas_colectivas.php" class="btn btn-sm btn-secondary">Regresar</a>
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
