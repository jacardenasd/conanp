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

$unidad_id = $_SESSION['unidad_id'];
$user_id = $_SESSION['user_id']; 
$return_user_id = $_GET['return_user_id'] ?? ($_POST['return_user_id'] ?? '');
$return_periodo = $_GET['return_periodo'] ?? ($_POST['return_periodo'] ?? '');
$return_modo = $_GET['return_modo'] ?? ($_POST['return_modo'] ?? '');

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

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);
$periodo = $_SESSION['periodo'];

// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();


$id = $_GET['id'] ?? null;
if (!$id) {
  header("Location: admin_metas_individuales.php");
  exit;
}

// Cargar unidades de medida
$unidades = $pdo->query("SELECT nombre FROM unidades_medida ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);


// Obtener la meta actual
$stmt = $pdo->prepare("SELECT m.*, p.nombre, p.apellido_paterno, p.apellido_materno, p.RFC, pu.puesto, periodo AS puesto
        FROM metas m
        LEFT JOIN usuarios p ON m.user_id = p.user_id
    	  LEFT JOIN puestos AS pu ON p.puesto_id = pu.id
        WHERE m.id = ?");
$stmt->execute([$id]);
$meta = $stmt->fetch();

if (!$meta) {
  header("Location: admin_metas_individuales.php");
  exit;
}

$meta_user_id = (int)$meta['user_id'];
$meta_periodo = (int)$meta['periodo'];

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


  
  // Validar que la ponderación total no exceda 100 (sin incluir esta misma)
  $stmt = $pdo->prepare("SELECT SUM(ponderacion) FROM metas WHERE user_id = ? AND periodo = ? AND id != ?");
  $stmt->execute([$meta_user_id, $meta_periodo, $id]);
  $suma = (float)$stmt->fetchColumn();

  if (($suma + (float)$_POST['ponderacion']) > 100) {
    $errores[] = 'La ponderación total excede el 100%.';
  }

  if (empty($errores)) {
    $omitir_resultado_final = (isset($_POST['omitir_resultado_final']) && $_POST['omitir_resultado_final'] === '1')
      || ((float)($_POST['resultado_final'] ?? 0) === 1.0);

    if ($omitir_resultado_final) {
      $stmt = $pdo->prepare("UPDATE metas SET indicador = ?, unidad = ?, ponderacion = ?, sobresaliente = ?, satisfactorio = ?, no_satisfactorio = ?, no_aprobatorio = ?, deficiente = ?, estatus = ? WHERE id = ?");
      $stmt->execute([
        $_POST['indicador'],
        $_POST['unidad'],
        $_POST['ponderacion'],
        $_POST['sobresaliente'],
        $_POST['satisfactorio'],
        $_POST['no_satisfactorio'],
        $_POST['no_aprobatorio'],
        $_POST['deficiente'],
        $_POST['estatus'],
        $id
      ]);
    } else {
      $stmt = $pdo->prepare("UPDATE metas SET indicador = ?, unidad = ?, ponderacion = ?, sobresaliente = ?, satisfactorio = ?, no_satisfactorio = ?, no_aprobatorio = ?, deficiente = ?, estatus = ?, resultado_final = ? WHERE id = ?");

      $stmt->execute([
        $_POST['indicador'],
        $_POST['unidad'],
        $_POST['ponderacion'],
        $_POST['sobresaliente'],
        $_POST['satisfactorio'],
        $_POST['no_satisfactorio'],
        $_POST['no_aprobatorio'],
        $_POST['deficiente'],
        $_POST['estatus'],
        $_POST['resultado_final'],
        $id
      ]);
    }

    resetear_estatus_individuales_admin($pdo, $meta_user_id, $meta_periodo);

    $destino = "admin_metas_individuales.php?info=2";
    if ($return_user_id !== '') {
      $destino .= "&user_id=" . urlencode((string)$return_user_id);
    }
    if ($return_periodo !== '') {
      $destino .= "&periodo=" . urlencode((string)$return_periodo);
    }
    if ($return_modo !== '') {
      $destino .= "&modo=" . urlencode((string)$return_modo);
    }

    header("Location: " . $destino);
    exit;
  }
}

$resultado_final = $meta['resultado_final'] ?? 80; 

$stmt = $pdo->prepare("SELECT * FROM calificaciones WHERE user_id = ? AND periodo = ?");
$stmt->execute([$meta_user_id, $meta_periodo]);
$calificacion = $stmt->fetch(PDO::FETCH_ASSOC); 
if ($calificacion){$estatus_metas = $calificacion['estatus_metas'];} else {$estatus_metas = 0;}
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
            Captura la información solicitada.
            <p>&nbsp;</p>
              

            <form method="post">
              <input type="hidden" name="return_user_id" value="<?= htmlspecialchars((string)$return_user_id) ?>">
              <input type="hidden" name="return_periodo" value="<?= htmlspecialchars((string)$return_periodo) ?>">
              <input type="hidden" name="return_modo" value="<?= htmlspecialchars((string)$return_modo) ?>">

                <div class="form-group"><label class="col-lg-3 col-form-label">Usuario:</label>
                <?= $meta['nombre'] ." ".$meta['apellido_paterno']." ".$meta['apellido_materno'] ?>
                </div>

                <div class="form-group"><label class="col-lg-3 col-form-label">Periodo:</label>
                <?= $meta['periodo'] ?>
                </div>


            <div class="form-group"><label class="col-lg-3 col-form-label">Descripción de la Meta Individual:</label>
                <textarea name="indicador" class="form-control" rows="3"><?= htmlspecialchars($meta['indicador'] ?? '') ?></textarea>
                </div>
                
                <div class="row">
                <div class="col-lg-6">
									<div class="mb-6">
									<label class="col-lg-3 col-form-label">Unidad de Medida:</label>
                  <select name="unidad" class="form-control">
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
                  <input name="ponderacion" type="number" min="1" max="100" class="form-control" value="<?= $meta['ponderacion'] ?>">
               		</div>
               	</div>
               	</div>

                 <div class="row">
                 <div class="col-lg-6">
									<div class="mb-6">
									<label class="col-lg-3 col-form-label">Estatus:</label>
                  <select name="estatus" class="form-control">
                    <option value="">Seleccione</option>
                    <option value="evaluado" <?= $meta['estatus'] === "evaluado" ? 'selected' : '' ?>>Evaluado</option>
                    <option value="no evaluado" <?= $meta['estatus'] === "no evaluado" ? 'selected' : '' ?>>No evaluado</option>
                    </select>
                  </div>
                  </div>

		              <div class="col-lg-6">
									<div class="mb-6">
									<label class="col-lg-3 col-form-label">Ponderación (%):</label>
                  <input name="ponderacion" type="number" min="1" max="100" class="form-control" value="<?= $meta['ponderacion'] ?>">
               		</div>
               	</div>
               	</div>


                <div class="form-group"><label class="col-lg-3 col-form-label">Resultado Sobresaliente:</label>
                  <textarea name="sobresaliente" class="form-control" rows="2"><?= htmlspecialchars($meta['sobresaliente'] ?? '') ?></textarea>
                </div>
                <div class="form-group"><label class="col-lg-3 col-form-label">Resultado Satisfactorio:</label>
                  <textarea name="satisfactorio" class="form-control" rows="2"><?= htmlspecialchars($meta['satisfactorio'] ?? '') ?></textarea>
                </div>
                <div class="form-group"><label class="col-lg-3 col-form-label">Resultado No Satisfactorio:</label>
                  <textarea name="no_satisfactorio" class="form-control" rows="2"><?= htmlspecialchars($meta['no_satisfactorio'] ?? '') ?></textarea>
                </div>
                <div class="form-group"><label class="col-lg-3 col-form-label">Resultado No Aprobatorio:</label>
                  <textarea name="no_aprobatorio" class="form-control" rows="2"><?= htmlspecialchars($meta['no_aprobatorio'] ?? '') ?></textarea>
                </div>
                <div class="form-group"><label class="col-lg-3 col-form-label">Resultado Deficiente:</label>
                  <textarea name="deficiente" class="form-control" rows="2"><?= htmlspecialchars($meta['deficiente'] ?? '') ?></textarea>
                </div>


                      <div class="form-group">
                      <label class="col-lg-6 col-form-label">
                        Resultado de la Meta: <strong><span id="valorResultado">80%</span></strong>
                        <span class="badge bg-warning text-dark ms-2" id="etiquetaResultado">No aprobatorio</span>
                      </label>
                      <input type="range" class="form-range" name="resultado_final" id="resultado_final" min="1" max="100" step="1" value="<?= $resultado_final ?>" oninput="actualizarEvaluacion(this.value)">
                      <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="omitir_resultado_final" id="omitir_resultado_final" value="1">
                        <label class="form-check-label" for="omitir_resultado_final">No actualizar resultado final (también se omite si está en 1.0%)</label>
                      </div>
                      <div class="progress" style="height: 15px;">
                        <div id="barraResultado" class="progress-bar bg-warning text-dark" style="width: 80%;">80%</div>
                      </div>
                      <div class="form-text">Recorre el punto azul para seleccionar el resultado. Se evalúa el resultado final.</div>
                    </div>

                <p>&nbsp;</p>
                <button class="btn btn-sm btn-primary">Guardar Meta</button>
                <a href="admin_metas_individuales.php<?= ($return_user_id !== '' ? '?user_id=' . urlencode((string)$return_user_id) . ($return_periodo !== '' ? '&periodo=' . urlencode((string)$return_periodo) : '') . ($return_modo !== '' ? '&modo=' . urlencode((string)$return_modo) : '') : '') ?>" class="btn btn-sm btn-secondary">Regresar</a>
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
      barra.className = "progress-bar bg-info";
      etiqueta.textContent = "No satisfactorio";
      etiqueta.className = "badge bg-info text-dark";
    } else if (valor >= 50) {
      barra.className = "progress-bar bg-warning";
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
    const val = document.getElementById('resultado_final').value;
    const checkOmitir = document.getElementById('omitir_resultado_final');
    const slider = document.getElementById('resultado_final');

    checkOmitir.addEventListener('change', function () {
      slider.disabled = this.checked;
    });

    actualizarEvaluacion(val);
  });
</script>