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
$user_id = $_SESSION['user_id']; 

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);

$unidad_id = $_GET['unidad_id'];
$periodo = $_GET['periodo'];
$es_periodo_legacy_colectivas = ((int)$periodo <= 2025);
$periodo_activo = (int)($_SESSION['periodo'] ?? 0);

function resetear_estatus_colectivas_periodo_activo(PDO $pdo, int $unidad_id, int $periodo_objetivo, int $periodo_activo): void {
  if ($periodo_activo <= 0 || $periodo_objetivo !== $periodo_activo) {
    return;
  }

  $stmt = $pdo->prepare("SELECT COUNT(*) FROM metas_colectivas WHERE unidad_id = ? AND periodo = ?");
  $stmt->execute([$unidad_id, $periodo_objetivo]);
  $total_metas = (int)$stmt->fetchColumn();

  $nuevo_estatus = ($total_metas > 0) ? 1 : 0;

  $stmt = $pdo->prepare("SELECT COUNT(*) FROM calificaciones_colectivas WHERE unidad_id = ? AND periodo = ?");
  $stmt->execute([$unidad_id, $periodo_objetivo]);
  $existe_calificacion = (int)$stmt->fetchColumn();

  if ($existe_calificacion > 0) {
    $stmt = $pdo->prepare("UPDATE calificaciones_colectivas SET estatus = ?, resultado = 0 WHERE unidad_id = ? AND periodo = ?");
    $stmt->execute([$nuevo_estatus, $unidad_id, $periodo_objetivo]);
  } else {
    $stmt = $pdo->prepare("INSERT INTO calificaciones_colectivas (unidad_id, periodo, estatus, resultado) VALUES (?, ?, ?, 0)");
    $stmt->execute([$unidad_id, $periodo_objetivo, $nuevo_estatus]);
  }
}

// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();


$id = $_GET['id'] ?? null;
if (!$id) {
  header("Location: admin_metas_colectivas_detalle.php");
  exit;
}

// Cargar unidades de medida
$unidades_medida_rows = $pdo->query("SELECT id, nombre FROM unidades_medida ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$unidades_medida = array_map(static function ($row) {
  return (string)$row['nombre'];
}, $unidades_medida_rows);
$unidades = $pdo->query("SELECT id, nombre FROM unidades ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$periodos = $pdo->query("SELECT anio FROM periodos ORDER BY anio")->fetchAll(PDO::FETCH_ASSOC);
asegurar_columna_eje_pnd_metas_colectivas($pdo);
$instrumentos_pnd = obtener_instrumentos_pnd($pdo, true);
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
  header("Location: admin_metas_colectivas_detalle.php");
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
foreach ($unidades_medida as $u) {
  if (mb_strtolower(trim($u), 'UTF-8') === mb_strtolower($unidad_actual, 'UTF-8')) {
    $unidad_actual_en_catalogo = true;
    break;
  }
}
if ($unidad_actual !== '' && !$unidad_actual_en_catalogo) {
  $unidades_medida[] = $unidad_actual;
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
    if ($bloquear_campos_estructura) {
      $instrumento = $meta['instrumento'];
      $eje_pnd_id = $meta['eje_pnd_id'];
      $unidad = $meta['unidad'];
    }

    $stmt = $pdo->prepare("UPDATE metas_colectivas SET instrumento = ?, eje_pnd_id = ?, indicador = ?, unidad = ?, ponderacion = ?, satisfactorio = ?, estatus = ?, resultado = ? WHERE id = ?");

    $stmt->execute([
      $instrumento,
      $eje_pnd_id,
      $_POST['indicador'],
      $unidad,
      $_POST['ponderacion'],
      $_POST['satisfactorio'],
      $_POST['estatus'],
      $_POST['resultado'],
      $id
    ]);

    resetear_estatus_colectivas_periodo_activo($pdo, (int)$unidad_id, (int)$periodo, $periodo_activo);

    header("Location: admin_metas_colectivas_detalle.php?info=2&unidad_id=$unidad_id&periodo=$periodo");
    exit;
  }
}
$resultado = $meta['resultado'] ?? 80; 

$stmt = $pdo->prepare("SELECT * FROM calificaciones_colectivas WHERE unidad_id = ? AND periodo = ?");
$stmt->execute([$unidad_id, $periodo]);
$calificacion = $stmt->fetch(PDO::FETCH_ASSOC); 

$estatus_metas = '';
if ($calificacion) {
    $estatus_metas = $calificacion['estatus'];
} else {
    // Puedes manejar la situación como mejor te convenga
    $estatus_metas = 'no evaluado';
    // O dejarlo en blanco, o mostrar un mensaje de error, etc.
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
								<a href="#" class="breadcrumb-item">Mi evaluación</a>
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
						Captura la información solicitada; algunos campos son obligatorios.
            <p>&nbsp;</p>
              

            <form method="post">
                <div class="form-group"><label class="col-lg-3 col-form-label">Descripción de la Meta Colectiva:</label>
                <textarea name="indicador" class="form-control" rows="3" required><?= htmlspecialchars($meta['indicador']) ?></textarea>
                </div>
                
                <div class="row">


                <div class="col-lg-4">
                <div class="mb-6">
                <label class="col-lg-3 col-form-label">Instrumento:</label>
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
                    <?php foreach ($unidades_medida as $u): ?>
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
                <label class="col-lg-3 col-form-label">Eje del P.N.D.:</label>
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
                  <input name="ponderacion" type="number" min="1" max="100" class="form-control" required value="<?= $meta['ponderacion'] ?>">
               		</div>
               	</div>
               	</div>

                 <div class="row">
		            <div class="col-lg-4">
					<div class="mb-6">
					<label class="col-lg-5 col-form-label">Resultado Esperado:</label>
					<input name="satisfactorio" type="text" class="form-control" value="<?= $meta['satisfactorio'] ?>" required>
					</div>
               		</div>

                   <div class="col-lg-4">
					<div class="mb-6">
					<label class="col-lg-5 col-form-label">Unidad:</label>
                  <select name="unidad_id" class="form-control" required>
                  <option value="">Seleccione</option>
                  <?php foreach ($unidades as $un): ?>
                    <option value="<?= $un['id'] ?>" <?php if ($un['id'] === $meta['unidad_id']) {echo 'selected';} ?>><?= $un['nombre'] ?></option>
                  <?php endforeach; ?>
                </select>
              		</div>
               		</div>

                  <div class="col-lg-4">
					<div class="mb-6">
					<label class="col-lg-3 col-form-label">Periodo:</label>
					<select name="periodo" class="form-control" required>
                  <option value="">Seleccione</option>
                  <?php foreach ($periodos as $pe): ?>
                    <option value="<?= $pe['anio'] ?>" <?php if ($pe['anio'] === $meta['periodo']) {echo 'selected';} ?>><?= $pe['anio'] ?></option>
                  <?php endforeach; ?>
                </select>
                  </div>
               	</div>
               	</div>


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

                <p>&nbsp;</p>
                <button class="btn btn-sm btn-primary">Guardar Meta</button>
                <a href="admin_metas_colectivas_detalle.php" class="btn btn-sm btn-secondary">Regresar</a>
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