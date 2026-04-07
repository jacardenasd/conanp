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
$user_id = $_SESSION['user_id']; 

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);
$periodo = $_SESSION['periodo'];
$captura_metas_colectivas_bloqueada = modulo_bloqueado_por_periodo('metas_colectivas', (int)$periodo);
$es_periodo_legacy_colectivas = ((int)$periodo <= 2025);

// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();
$es_evaluacion = in_array($estatus_periodo, ['Evaluación', 'Evaluacion'], true);


$id = $_GET['id'] ?? null;
if (!$id) {
  header("Location: metas_colectivas.php");
  exit;
}

// Cargar unidades de medida
$unidades_medida_rows = $pdo->query("SELECT id, nombre FROM unidades_medida ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$unidades = array_map(static function ($row) {
  return (string)$row['nombre'];
}, $unidades_medida_rows);

$instrumentos_pnd = obtener_instrumentos_pnd($pdo, true);
asegurar_columna_eje_pnd_metas_colectivas($pdo);
$ejes_pnd = obtener_ejes_pnd($pdo, true);
$instrumentos_formulario = $es_periodo_legacy_colectivas
  ? array_map(static function ($row) {
      return [
        'id' => (int)$row['id'],
        'nombre' => (string)$row['nombre'],
        'estatus' => 1,
      ];
    }, $unidades_medida_rows)
  : $instrumentos_pnd;

$ids_instrumentos_pnd = array_map(static function ($item) {
  return (int)$item['id'];
}, $instrumentos_formulario);
$ids_ejes_pnd = array_map(static function ($item) {
  return (int)$item['id'];
}, $ejes_pnd);


// Obtener la meta actual
$stmt = $pdo->prepare("SELECT * FROM metas_colectivas WHERE id = ? AND unidad_id = ?");
$stmt->execute([$id, $unidad_id]);
$meta = $stmt->fetch();

if (!$meta) {
  header("Location: metas_colectivas.php");
  exit;
}

if (!$es_periodo_legacy_colectivas && !in_array((int)$meta['instrumento'], $ids_instrumentos_pnd, true)) {
  $stmtInstrumentoActual = $pdo->prepare("SELECT id, nombre, orden, estatus FROM instrumentos_pnd WHERE id = ? LIMIT 1");
  $stmtInstrumentoActual->execute([(int)$meta['instrumento']]);
  $instrumentoActual = $stmtInstrumentoActual->fetch(PDO::FETCH_ASSOC);
  if ($instrumentoActual) {
    $instrumentos_pnd[] = $instrumentoActual;
    $ids_instrumentos_pnd[] = (int)$instrumentoActual['id'];
  }
}

$unidad_actual = trim((string)($meta['unidad'] ?? ''));
$unidad_actual_en_catalogo = false;
foreach ($unidades as $u) {
  if (mb_strtolower(trim($u), 'UTF-8') === mb_strtolower($unidad_actual, 'UTF-8')) {
    $unidad_actual_en_catalogo = true;
    break;
  }
}
if ($unidad_actual !== '' && !$unidad_actual_en_catalogo) {
  $unidades[] = $unidad_actual;
}

if (!in_array((int)($meta['eje_pnd_id'] ?? 0), $ids_ejes_pnd, true) && !empty($meta['eje_pnd_id'])) {
  $stmtEjeActual = $pdo->prepare("SELECT id, nombre, orden, estatus FROM ejes_pnd WHERE id = ? LIMIT 1");
  $stmtEjeActual->execute([(int)$meta['eje_pnd_id']]);
  $ejeActual = $stmtEjeActual->fetch(PDO::FETCH_ASSOC);
  if ($ejeActual) {
    $ejes_pnd[] = $ejeActual;
    $ids_ejes_pnd[] = (int)$ejeActual['id'];
  }
}

$errores = [];

// VALIDACIÓN SERVER-SIDE: Verificar si el periodo permite edición
$stmt = $pdo->prepare("SELECT estatus FROM calificaciones_colectivas WHERE unidad_id = ? AND periodo = ?");
$stmt->execute([$unidad_id, $periodo]);
$cal_resultado = $stmt->fetch(PDO::FETCH_ASSOC);
$cal_estatus = $cal_resultado ? $cal_resultado['estatus'] : 0;

// Si está en evaluación Y ya fue cerrado (estatus >= 2), bloquear edición
if ($estatus_periodo === 'Evaluación' && $cal_estatus >= 2) {
    header("Location: metas_colectivas.php?info=bloqueado");
    exit;
}

if ($estatus_periodo === 'Captura' && $captura_metas_colectivas_bloqueada) {
  header("Location: metas_colectivas.php?info=8");
  exit;
}

$bloquear_campos_estructura = $es_evaluacion;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if ($estatus_periodo === 'Captura' && $captura_metas_colectivas_bloqueada) {
    header("Location: metas_colectivas.php?info=8");
    exit;
  }

  // Validar que la ponderación total no exceda 100 (sin incluir esta misma)
  $stmt = $pdo->prepare("SELECT SUM(ponderacion) FROM metas_colectivas WHERE unidad_id = ? AND periodo = ? AND id != ?");
  $stmt->execute([$unidad_id, $periodo, $id]);
  $suma = $stmt->fetchColumn();

  $indicador_input = $_POST['indicador'] ?? $meta['indicador'];
  $ponderacion_input = $_POST['ponderacion'] ?? $meta['ponderacion'];

  if ($suma + $ponderacion_input > 100) {
    $errores[] = 'La ponderación total excede el 100%.';
  }

  if (strlen(trim($indicador_input)) < 5 || is_numeric($indicador_input)) {
    $errores[] = 'La descripción de la meta debe tener al menos 10 caracteres y no ser solo números.';
  }

  if (!$bloquear_campos_estructura) {
    $instrumento_id = (int)($_POST['instrumento'] ?? 0);
    if (!in_array($instrumento_id, $ids_instrumentos_pnd, true)) {
      $errores[] = 'Selecciona un instrumento válido.';
    }

    if (!$es_periodo_legacy_colectivas) {
      $eje_pnd_id = (int)($_POST['eje_pnd_id'] ?? 0);
      if (!in_array($eje_pnd_id, $ids_ejes_pnd, true)) {
        $errores[] = 'Selecciona un eje del P.N.D. válido.';
      }
    }
  }

  if (empty($errores)) {
    $instrumento = (int)($_POST['instrumento'] ?? $meta['instrumento']);
    $eje_pnd_id = $es_periodo_legacy_colectivas
      ? (int)($meta['eje_pnd_id'] ?? 0)
      : (int)($_POST['eje_pnd_id'] ?? ($meta['eje_pnd_id'] ?? 0));
    $unidad = $_POST['unidad'] ?? $meta['unidad'];
    $indicador = $_POST['indicador'] ?? $meta['indicador'];
    $ponderacion = $_POST['ponderacion'] ?? $meta['ponderacion'];
    $satisfactorio = $_POST['satisfactorio'] ?? $meta['satisfactorio'];
    if ($bloquear_campos_estructura) {
      $instrumento = $meta['instrumento'];
      $eje_pnd_id = $meta['eje_pnd_id'];
      $unidad = $meta['unidad'];
      $indicador = $meta['indicador'];
      $ponderacion = $meta['ponderacion'];
      $satisfactorio = $meta['satisfactorio'];
    }

    $stmt = $pdo->prepare("UPDATE metas_colectivas SET instrumento = ?, eje_pnd_id = ?, indicador = ?, unidad = ?, ponderacion = ?, satisfactorio = ?, estatus = ?, resultado = ? WHERE id = ? AND unidad_id = ?");

    $stmt->execute([
      $instrumento,
      $eje_pnd_id,
      $indicador,
      $unidad,
      $ponderacion,
      $satisfactorio,
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

$stmt = $pdo->prepare("SELECT estatus FROM calificaciones_colectivas WHERE unidad_id = ? AND periodo = ?");
$stmt->execute([$unidad_id, $periodo]);
$resultado = $stmt->fetch(PDO::FETCH_ASSOC);
if ($resultado) {$estatus_metas = $resultado['estatus'];} else {$estatus_metas = 0;}
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
								<span class="breadcrumb-item active">Editar Meta  <?= $periodo ?></span>
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
                <div class="form-group"><label class="col-lg-3 col-form-label">Descripción de la Meta Colectiva:</label>
                <textarea name="indicador" class="form-control" rows="3" <?php if ($bloquear_campos_estructura) { echo 'disabled'; } ?> required><?= htmlspecialchars($meta['indicador']) ?></textarea>
                </div>
                
                <div class="row">


                <div class="col-lg-4">
                <div class="mb-6">
                <label class="col-form-label text-nowrap">Instrumento:</label>
                  <select name="instrumento" class="form-control" <?php if ($bloquear_campos_estructura) { echo 'disabled'; } ?> required>
                  <option value="">Seleccione</option>
                  <?php foreach ($instrumentos_formulario as $instrumento): ?>
                    <option value="<?= (int)$instrumento['id'] ?>" <?php if ((string)$meta['instrumento'] === (string)$instrumento['id']) { echo 'selected'; } ?>>
                      <?= htmlspecialchars($instrumento['nombre']) ?><?= (int)$instrumento['estatus'] === 0 ? ' (Inactivo)' : '' ?>
                    </option>
                  <?php endforeach; ?>
                  </select>
              		</div>
               		</div>


		              <div class="col-lg-4">
									<div class="mb-6">
									<label class="col-lg-5 col-form-label">Unidad de Medida:</label>
                  <select name="unidad" class="form-control" required <?php if ($bloquear_campos_estructura) { echo 'disabled'; } ?>>
                    <option value="">Seleccione</option>
                    <?php foreach ($unidades as $u): ?>
                      <?php
                        $u_val = trim((string)$u);
                        $unidad_sel = (mb_strtolower(trim((string)$meta['unidad']), 'UTF-8') === mb_strtolower($u_val, 'UTF-8'));
                      ?>
                      <option value="<?= htmlspecialchars($u_val) ?>" <?= $unidad_sel ? 'selected' : '' ?>><?= htmlspecialchars($u_val) ?></option>
                    <?php endforeach; ?>
                  </select>
                  
                  </div>
                  </div>

                <div class="col-lg-4">
                <div class="mb-6">
                <label class="col-form-label text-nowrap">Eje del P.N.D.:</label>
                  <select name="eje_pnd_id" class="form-control" <?php if ($bloquear_campos_estructura) { echo 'disabled'; } ?> required>
                  <option value="">Seleccione</option>
                  <?php foreach ($ejes_pnd as $eje): ?>
                    <option value="<?= (int)$eje['id'] ?>" <?php if ((string)($meta['eje_pnd_id'] ?? '') === (string)$eje['id']) { echo 'selected'; } ?>>
                      <?= htmlspecialchars($eje['nombre']) ?><?= (int)$eje['estatus'] === 0 ? ' (Inactivo)' : '' ?>
                    </option>
                  <?php endforeach; ?>
                  </select>
                		</div>
                		</div>

		              <div class="col-lg-4">
									<div class="mb-6">
									<label class="col-lg-3 col-form-label">Ponderación (%):</label>
                  <input name="ponderacion" type="number" min="1" max="100" class="form-control" <?php if ($bloquear_campos_estructura) { echo 'disabled'; } ?> required value="<?= $meta['ponderacion'] ?>">
               		</div>
               	</div>
               	</div>

                <div class="form-group"><label class="col-lg-3 col-form-label">Resultado Esperado:</label>
                  <input name="satisfactorio" type="text" class="form-control" <?php if ($bloquear_campos_estructura) { echo 'disabled'; } ?> value="<?php echo $meta['satisfactorio']; ?>" required>

                </div>

                    <?php if ($estatus_periodo === 'Captura') {?>
                    <input type="hidden" name="estatus" value="no evaluado">
                    <?php } elseif ($estatus_periodo === 'Evaluación') {?>

                      <div class="form-group">
                      <label class="col-lg-6 col-form-label">
                        Resultado de la Meta: <strong><span id="valorResultado">80%</span></strong>
                        <span class="badge bg-warning text-dark ms-2" id="etiquetaResultado">No aprobatorio</span>
                      </label>
                      <input type="range" class="form-range" name="resultado" id="resultado" min="1" max="100" step="1" value="<?= $resultado ?>" oninput="actualizarEvaluacion(this.value)">
                      <div class="progress" style="height: 15px;">
                        <div id="barraResultado" class="progress-bar bg-warning text-dark" style="width: 80%;">80%</div>
                      </div>
                      <div class="form-text">Recorre el punto azul para seleccionar el resultado</div>
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
    const val = document.getElementById('resultado').value;
    actualizarEvaluacion(val);
  });
</script>