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

// Consultar el periodo actual desde la tabla variables
$stmt = $pdo->prepare("SELECT valor FROM variables WHERE nombre = 'periodo_actual'");
$stmt->execute();
$periodo = $stmt->fetchColumn();

// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();




$id = $_GET['id'] ?? null;
if (!$id) {
  header("Location: metas_colectivas.php");
  exit;
}

// Cargar unidades de medida
$unidades = $pdo->query("SELECT nombre FROM unidades_medida ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);


// Obtener la meta actual
$stmt = $pdo->prepare("SELECT * FROM metas_colectivas WHERE id = ? AND unidad_id = ?");
$stmt->execute([$id, $unidad_id]);
$meta = $stmt->fetch();

if (!$meta) {
  header("Location: metas_colectivas.php");
  exit;
}

$errores = [];
$bloquear_campos_estructura = ($estatus_periodo === 'Evaluación');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validar que la ponderación total no exceda 100 (sin incluir esta misma)
  $stmt = $pdo->prepare("SELECT SUM(ponderacion) FROM metas_colectivas WHERE unidad_id = ? AND periodo = ? AND id != ?");
  $stmt->execute([$unidad_id, $periodo, $id]);
  $suma = $stmt->fetchColumn();

  if ($suma + $_POST['ponderacion'] > 100) {
    $errores[] = 'La ponderación total excede el 100%.';
  }

  if (strlen(trim($_POST['indicador'])) < 5 || is_numeric($_POST['indicador'])) {
    $errores[] = 'La descripción de la meta debe tener al menos 10 caracteres y no ser solo números.';
  }

  if (empty($errores)) {
    $unidad = $_POST['unidad'] ?? $meta['unidad'];
    if ($bloquear_campos_estructura) {
      $unidad = $meta['unidad'];
    }

    $stmt = $pdo->prepare("UPDATE metas_colectivas SET indicador = ?, unidad = ?, ponderacion = ?, sobresaliente = ?, satisfactorio = ?, no_satisfactorio = ?, no_aprobatorio = ?, deficiente = ?, estatus = ?, resultado = ? WHERE id = ? AND unidad_id = ?");

    $stmt->execute([
      $_POST['indicador'],
      $unidad,
      $_POST['ponderacion'],
      $_POST['sobresaliente'],
      $_POST['satisfactorio'],
      $_POST['no_satisfactorio'],
      $_POST['no_aprobatorio'],
      $_POST['deficiente'],
      $_POST['estatus'],
      $_POST['resultado'],
      $id,
      $unidad_id
    ]);

    header("Location: metas_colectivas.php?mensaje=actualizado");
    exit;
  }
}
$resultado = $meta['resultado'] ?? 80; 
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
								<a href="#" class="breadcrumb-item">Mi evaluación</a>
								<a href="#" class="breadcrumb-item">metas_colectivas colectivas</a>
								<span class="breadcrumb-item active">Editar Meta</span>
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

        
  <?php if (!empty($errores)): ?>
    <div class="alert alert-danger"><ul><?php foreach ($errores as $e) echo "<li>$e</li>"; ?></ul></div>
  <?php endif; ?>


        <div class="card">
						<div class="card-header">
            <h3>Editar Meta</h3>
						</div>

						<div class="card-body">
						Captura la información solicitada; algunos campos son obligatorios.
            <p>&nbsp;</p>
              

            <form method="post">
                <div class="form-group"><label class="col-lg-3 col-form-label">Descripción de la Meta Individual:</label>
                <textarea name="indicador" class="form-control" rows="3" required><?= htmlspecialchars($meta['indicador']) ?></textarea>
                </div>

                <div class="row">
		              <div class="col-lg-6">
									<div class="mb-6">
									<label class="col-lg-3 col-form-label">Unidad de Medida:</label>
                  <select name="unidad" class="form-control" required <?php if ($bloquear_campos_estructura) { echo 'disabled'; } ?>>
                    <option value="">Seleccione</option>
                    <?php foreach ($unidades as $u): ?>
                      <option value="<?= $u ?>" <?= $meta['unidad'] === $u ? 'selected' : '' ?>><?= $u ?></option>
                    <?php endforeach; ?>
                  </select>
                  </div>
                  </div>

		              <div class="col-lg-6">
									<div class="mb-6">
									<label class="col-lg-3 col-form-label">Ponderación (%):</label>
                  <input name="ponderacion" type="number" min="1" max="100" class="form-control" required value="<?= $meta['ponderacion'] ?>">
               		</div>
               	</div>
               	</div>

                <div class="form-group"><label class="col-lg-3 col-form-label">Resultado Sobresaliente:</label>
                  <textarea name="sobresaliente" class="form-control" rows="2" required><?= htmlspecialchars($meta['sobresaliente']) ?></textarea>
                </div>
                <div class="form-group"><label class="col-lg-3 col-form-label">Resultado Satisfactorio:</label>
                  <textarea name="satisfactorio" class="form-control" rows="2" required><?= htmlspecialchars($meta['satisfactorio']) ?></textarea>
                </div>
                <div class="form-group"><label class="col-lg-3 col-form-label">Resultado No Satisfactorio:</label>
                  <textarea name="no_satisfactorio" class="form-control" rows="2" required><?= htmlspecialchars($meta['no_satisfactorio']) ?></textarea>
                </div>
                <div class="form-group"><label class="col-lg-3 col-form-label">Resultado No Aprobatorio:</label>
                  <textarea name="no_aprobatorio" class="form-control" rows="2" required><?= htmlspecialchars($meta['no_aprobatorio']) ?></textarea>
                </div>
                <div class="form-group"><label class="col-lg-3 col-form-label">Resultado Deficiente:</label>
                  <textarea name="deficiente" class="form-control" rows="2" required><?= htmlspecialchars($meta['deficiente']) ?></textarea>
                </div>

                    <?php if ($estatus_periodo === 'Captura') {?>
                    <input type="hidden" name="estatus" value="no evaluado">
                    <?php } elseif ($estatus_periodo === 'Evaluación') {?>

                      <div class="form-group">
                      <label class="form-label">
                        Resultado de Evaluación: <strong><span id="valorResultado">80%</span></strong>
                        <span class="badge bg-warning text-dark ms-2" id="etiquetaResultado">No aprobatorio</span>
                      </label>
                      <input type="range" class="form-range" name="resultado" id="resultado" min="0" max="100" step="0.1" value="<?= $resultado ?>" oninput="actualizarEvaluacion(this.value)">
                      <div class="progress" style="height: 15px;">
                        <div id="barraResultado" class="progress-bar bg-warning text-dark" style="width: 80%;">80%</div>
                      </div>
                    </div>
                    <input type="hidden" name="estatus" value="evaluado">
                    <?php } ?>

                <p>&nbsp;</p>
                <button class="btn btn-sm btn-primary">Guardar Meta</button>
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
<script>
  function actualizarEvaluacion(valor) {
    valor = parseFloat(valor);
    const barra = document.getElementById('barraResultado');
    const span = document.getElementById('valorResultado');
    const etiqueta = document.getElementById('etiquetaResultado');

    span.textContent = valor.toFixed(1) + '%';
    barra.style.width = valor + '%';
    barra.textContent = valor.toFixed(1) + '%';

    if (valor >= 90) {
      barra.className = "progress-bar bg-success";
      etiqueta.textContent = "Sobresaliente";
      etiqueta.className = "badge bg-success";
    } else if (valor >= 70) {
      barra.className = "progress-bar bg-primary";
      etiqueta.textContent = "Satisfactorio";
      etiqueta.className = "badge bg-primary";
    } else if (valor >= 60) {
      barra.className = "progress-bar bg-info text-dark";
      etiqueta.textContent = "No satisfactorio";
      etiqueta.className = "badge bg-info text-dark";
    } else if (valor >= 50) {
      barra.className = "progress-bar bg-warning text-dark";
      etiqueta.textContent = "No aprobatorio";
      etiqueta.className = "badge bg-warning text-dark";
    } else {
      barra.className = "progress-bar bg-danger";
      etiqueta.textContent = "Deficiente";
      etiqueta.className = "badge bg-danger";
    }
  }

  // Inicializa al cargar con el valor desde el input
  document.addEventListener("DOMContentLoaded", function () {
    const val = document.getElementById('resultado').value;
    actualizarEvaluacion(val);
  });
</script>