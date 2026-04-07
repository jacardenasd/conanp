<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin();

if (!function_exists('obtener_estatus_prerequisitos_colaborador')) {
  function obtener_estatus_prerequisitos_colaborador($pdo, $colaborador_id, $periodo) {
    $stmt = $pdo->prepare("SELECT estatus_metas, estatus_gerenciales FROM calificaciones WHERE user_id = ? AND periodo = ?");
    $stmt->execute([$colaborador_id, $periodo]);
    $estatus = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $estatus_metas = (int)($estatus['estatus_metas'] ?? 0);
    $estatus_gerenciales = (int)($estatus['estatus_gerenciales'] ?? 0);

    $stmt = $pdo->prepare("SELECT COUNT(*) AS total_metas,
                                  SUM(CASE WHEN resultado IS NOT NULL AND resultado <> '' AND resultado <> 0 THEN 1 ELSE 0 END) AS metas_con_propuesta
                           FROM metas
                           WHERE user_id = ? AND periodo = ?");
    $stmt->execute([$colaborador_id, $periodo]);
    $metas = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $total_metas = (int)($metas['total_metas'] ?? 0);
    $metas_con_propuesta = (int)($metas['metas_con_propuesta'] ?? 0);
    $metas_listas = ($total_metas > 0 && $metas_con_propuesta >= $total_metas);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM competencias_evaluacion WHERE user_id = ? AND periodo = ? AND tipo = 'auto'");
    $stmt->execute([$colaborador_id, $periodo]);
    $autoeval_registros = (int)($stmt->fetchColumn() ?? 0);
    $gerenciales_listas = ($autoeval_registros > 0);

    $estatus_metas_efectivo = max($estatus_metas, $metas_listas ? 2 : 0);
    $estatus_gerenciales_efectivo = max($estatus_gerenciales, $gerenciales_listas ? 2 : 0);

    return [
      'estatus_metas' => $estatus_metas,
      'estatus_gerenciales' => $estatus_gerenciales,
      'estatus_metas_efectivo' => $estatus_metas_efectivo,
      'estatus_gerenciales_efectivo' => $estatus_gerenciales_efectivo,
      'metas_listas' => $metas_listas,
      'gerenciales_listas' => $gerenciales_listas,
      'puede_evaluar_jefe' => ($estatus_metas_efectivo >= 2 && $estatus_gerenciales_efectivo >= 2),
    ];
  }
}

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');
$unidad_id = $_SESSION['unidad_id'];
$user_id = $_SESSION['user_id']; 

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);
$periodo = $_SESSION['periodo'];
$colaborador_id = (int)($_GET['colaborador_id'] ?? 0);
if ($colaborador_id <= 0) {
  header("Location: mis_colaboradores.php?info=8");
  exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE user_id = ? AND jefe_id = ?");
$stmt->execute([$colaborador_id, $user_id]);
if ((int)$stmt->fetchColumn() <= 0) {
  header("Location: mis_colaboradores.php?info=8");
  exit;
}

// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
  header("Location: mis_colaboradores.php");
  exit;
}

// Cargar unidades de medida
$unidades = $pdo->query("SELECT nombre FROM unidades_medida ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);

// Obtener la meta actual
$stmt = $pdo->prepare("SELECT * FROM metas WHERE id = ? AND user_id = ? AND periodo = ?");
$stmt->execute([$id, $colaborador_id, $periodo]);
$meta = $stmt->fetch();

if (!$meta) {
  header("Location: mis_colaboradores.php");
  exit;
}

//si no hay calificacion, pues 80
if ((float)($meta['resultado_final'] ?? 0) == 0) {
  $resultado_final = 80;
} else {
  $resultado_final = $meta['resultado_final'];
}

$prerequisitos = obtener_estatus_prerequisitos_colaborador($pdo, $colaborador_id, (int)$periodo);
$metas_listas = (bool)$prerequisitos['metas_listas'];

if (!$metas_listas || trim((string)($meta['resultado'] ?? '')) === '') {
  header("Location: mis_colaboradores.php?info=7");
  exit;
}

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $prerequisitos_post = obtener_estatus_prerequisitos_colaborador($pdo, $colaborador_id, (int)$periodo);
  $metas_listas_post = (bool)$prerequisitos_post['metas_listas'];
  if (!$metas_listas_post || trim((string)($meta['resultado'] ?? '')) === '') {
    header("Location: mis_colaboradores.php?info=7");
    exit;
  }

  // Validar que la ponderación total no exceda 100 (sin incluir esta misma)
  $stmt = $pdo->prepare("SELECT SUM(ponderacion) FROM metas WHERE user_id = ? AND periodo = ? AND id != ?");
  $stmt->execute([$colaborador_id, $periodo, $id]);
  $suma = $stmt->fetchColumn();

  if (empty($errores)) {
    $unidad_inmutable = $meta['unidad'];
    $stmt = $pdo->prepare("UPDATE metas SET indicador = ?, unidad = ?, ponderacion = ?, sobresaliente = ?, satisfactorio = ?, no_satisfactorio = ?, no_aprobatorio = ?, deficiente = ?, estatus = ?, resultado_final = ? WHERE id = ? AND user_id = ? AND periodo = ?");

    $stmt->execute([
      $_POST['indicador'],
      $unidad_inmutable,
      $_POST['ponderacion'],
      $_POST['sobresaliente'],
      $_POST['satisfactorio'],
      $_POST['no_satisfactorio'],
      $_POST['no_aprobatorio'],
      $_POST['deficiente'],
      $_POST['estatus'],
      $_POST['resultado_final'],
      $id,
      $colaborador_id,
      $periodo
    ]);

    header("Location: evaluar_individuales.php?mensaje=actualizado&colaborador_id=$colaborador_id");
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
								<a href="#" class="breadcrumb-item">Mis colaboradores</a>
								<a href="#" class="breadcrumb-item">Metas Individuales</a>
								<span class="breadcrumb-item active">Evaluar Meta</span>
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
						Captura la información solicitada; algunos campos son obligatorios.<br/>
            El resultado mostrado es el propuesto por el usuario evaluado.
            <p>&nbsp;</p>
              

            <form method="post">
                <div class="form-group"><label class="col-lg-3 col-form-label">Descripción de la Meta Individual:</label>
                <textarea name="indicador" class="form-control" rows="3" readonly><?= htmlspecialchars($meta['indicador']) ?></textarea>
                </div>
                
                <div class="row">
		              <div class="col-lg-6">
									<div class="mb-6">
									<label class="col-lg-3 col-form-label">Unidad de Medida:</label>
                  <select name="unidad" class="form-control" disabled>
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
                  <input name="ponderacion" type="number" min="1" max="100" class="form-control"  readonly value="<?= $meta['ponderacion'] ?>">
               		</div>
               	</div>
               	</div>

                <div class="form-group"><label class="col-lg-3 col-form-label">Resultado Sobresaliente:</label>
                  <textarea name="sobresaliente" class="form-control" rows="2"  readonly><?= htmlspecialchars($meta['sobresaliente']) ?></textarea>
                </div>
                <div class="form-group"><label class="col-lg-3 col-form-label">Resultado Satisfactorio:</label>
                  <textarea name="satisfactorio" class="form-control" rows="2"  readonly><?= htmlspecialchars($meta['satisfactorio']) ?></textarea>
                </div>
                <div class="form-group"><label class="col-lg-3 col-form-label">Resultado No Satisfactorio:</label>
                  <textarea name="no_satisfactorio" class="form-control" rows="2"  readonly><?= htmlspecialchars($meta['no_satisfactorio']) ?></textarea>
                </div>
                <div class="form-group"><label class="col-lg-3 col-form-label">Resultado No Aprobatorio:</label>
                  <textarea name="no_aprobatorio" class="form-control" rows="2"  readonly><?= htmlspecialchars($meta['no_aprobatorio']) ?></textarea>
                </div>
                <div class="form-group"><label class="col-lg-3 col-form-label">Resultado Deficiente:</label>
                  <textarea name="deficiente" class="form-control" rows="2"  readonly><?= htmlspecialchars($meta['deficiente']) ?></textarea>
                </div>


                      <div class="form-group">
                      <label class="col-lg-6 col-form-label">
                      Resultado de la Meta: <strong><span id="valorresultado_final">80%</span></strong>
                        <span class="badge bg-warning text-dark ms-2" id="etiquetaresultado_final">No aprobatorio</span>
                      </label>
                      <input type="range" class="form-range" name="resultado_final" id="resultado_final" min="1" max="100" step="1" value="<?= $resultado_final ?>" oninput="actualizarEvaluacion(this.value)">
                      <div class="progress" style="height: 15px;">
                        <div id="barraresultado_final" class="progress-bar bg-warning text-dark" style="width: 80%;">80%</div>
                      </div>
                      <div class="form-text">Recorre el punto azul para seleccionar el Resultado.</div>
                    </div>
                    <input type="hidden" name="estatus" value="evaluado">
                    <?php if ($meta['resultado'] != '') {echo "Calificación propuesta por el Evaluado: ".$meta['resultado']."%"; } else {echo "Para esta meta, el usuario no ha propuesto resultado.";} ?>

                <p>&nbsp;</p>
                <button class="btn btn-sm btn-primary">Evaluar Meta</button>
                <a href="evaluar_individuales.php?colaborador_id=<?= $colaborador_id ?>" class="btn btn-sm btn-secondary">Regresar</a>
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
    const barra = document.getElementById('barraresultado_final');
    const span = document.getElementById('valorresultado_final');
    const etiqueta = document.getElementById('etiquetaresultado_final');

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
    actualizarEvaluacion(val);
  });
</script>