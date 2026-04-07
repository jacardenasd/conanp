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
$user_id = $_SESSION['user_id']; 

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);
$puesto_id = $_SESSION['puesto_id'];
$periodo = $_SESSION['periodo'];

// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();

// Bloqueo persistente de autoevaluacion
$stmt = $pdo->prepare("SELECT estatus_gerenciales FROM calificaciones WHERE user_id = ? AND periodo = ?");
$stmt->execute([$user_id, $periodo]);
$estatus_gerenciales = (int)($stmt->fetchColumn() ?? 0);

$stmt = $pdo->prepare("SELECT bloqueado_edicion FROM competencias_evaluacion WHERE user_id = ? AND periodo = ? AND tipo = 'auto' LIMIT 1");
$stmt->execute([$user_id, $periodo]);
$bloqueo_auto = (int)($stmt->fetchColumn() ?? 0);

$autoeval_bloqueada = ($estatus_gerenciales >= 2) || ($bloqueo_auto === 1);


// Obtener el nivel del puesto del usuario
$stmt = $pdo->prepare("SELECT puesto_nivel FROM usuarios WHERE user_id = ?");
$stmt->execute([$user_id]);
$nivel = $stmt->fetchColumn();

// Obtener competencias y comportamientos
$competencias = $pdo->query("SELECT * FROM competencias")->fetchAll(PDO::FETCH_ASSOC);

// Obtener las evaluaciones previas
$stmt = $pdo->prepare("SELECT * FROM competencias_evaluacion WHERE user_id = ? AND periodo = ? AND tipo = 'auto'");
$stmt->execute([$user_id, $periodo]);
$evaluaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
$evaluado = [];
foreach ($evaluaciones as $e) {
    $evaluado[$e['descripcion_id']] = $e['evaluacion'];
}

// Reapertura puntual de autoevaluacion gerencial por estatus administrativo
$permiso_reapertura_gerencial = ($estatus_periodo !== 'Evaluación' && $estatus_gerenciales <= 1);

// Opciones de evaluación
$opciones = ['Muy Característico', 'Característico', 'Poco Característico', 'No es Característico', 'No Aplica'];

//evaluacion especial
$stmt = $pdo->prepare("SELECT COUNT(*) FROM especiales WHERE user_id = ? AND periodo = ?");
$stmt->execute([$user_id, $periodo]);
$especial = $stmt->fetchColumn() > 0;
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
								<a href="#" class="breadcrumb-item">Mi evaluación</a>
								<span class="breadcrumb-item active">Autoevaluación Gerencial</span>
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
                            $texto = '✅ Registro guardado correctamente.';
                            $clase_alerta = 'alert-success';
                            break;
                        case 2:
                            $texto = '✏️ Registro actualizado correctamente.';
                            $clase_alerta = 'alert-info';
                            break;
                        case 3:
                            $texto = '🗑️ Registro eliminado correctamente.';
                            $clase_alerta = 'alert-danger';
                            break;
                            case 4:
                              $texto = '⚠️ El archivo es muy pesado';
                              $clase_alerta = 'alert-warning';
                              break;
                            case 6:
                              $texto = '🔒 La autoevaluación ya fue guardada o finalizada. No se permiten cambios.';
                              $clase_alerta = 'alert-warning';
                              break;
                          default:
                            $texto = 'info no reconocido.';
                            $clase_alerta = 'alert-secondary';
                            break;
                    }
                    ?>

                    <div class="alert <?= $clase_alerta ?> border-0 alert-dismissible fade show">
										<span class="fw-semibold"> <?= $texto ?>
										<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
								    </div>

                    <?php endif; ?>


				<div class="d-flex mb-3">
				<img src="assets/images/avatars/E-commerce-2.png" class="rounded-circle me-3" width="50" height="50">
				<div class="bg-white border border-primary p-3 rounded-start rounded-pill shadow-sm">
					<strong>📝 Tip:</strong> Selecciona la opción que mejor describa tu comportamiento para cada uno de los siguientes aspectos.
				</div>
				</div>

  <!-- Title with left icon -->
  <div class="card">  
    <div class="card-body">
    Instrucciones: Se evalúan los estándares de actuación profesional en materia gerencial. Este rubro también es Evaluado(a) como Capacidades de Desarrollo Administrativo y Calidad (Visión Estratégica, Liderazgo, Orientación a Resultados, Negociación y Trabajo en Equipo).
    </div>
  </div>
  <!-- /title with left icon -->


<form action="guardar_competencias.php" method="post">
  <input type="hidden" name="nivel" value="<?= $nivel ?>">
  <input type="hidden" name="actualiza" value="1">
  <?php foreach ($competencias as $index => $comp): ?>
    <div class="card">
      <div class="card-header">
	  <h4><?= htmlspecialchars($comp['competencia']) ?></h4>
        <p><?= htmlspecialchars($comp['descripcion']) ?></em></p>
      </div>
      <div class="card-body">
        <?php
          $stmt = $pdo->prepare("SELECT * FROM competencias_descripcion WHERE competencia_id = ? AND nivel = ?");
          $stmt->execute([$comp['competencia_id'], $nivel]);
          $descripciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
          foreach ($descripciones as $desc):
            $seleccionada = $evaluado[$desc['id']] ?? '';
        ?>
          <div class="mb-2">
		  <h6 class="card-title"><?= htmlspecialchars($desc['descripcion']) ?></h6>
            <?php foreach ($opciones as $opt): ?>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio"
                  name="evaluacion[<?= $desc['id'] ?>]" value="<?= $opt ?>"
                  <?= ($seleccionada === $opt) ? 'checked' : '' ?>
                  <?= $autoeval_bloqueada ? 'disabled' : '' ?> required>
                <label class="form-check-label"><?= $opt ?></label>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (!$autoeval_bloqueada && ($estatus_periodo == 'Evaluación' OR ($especial) OR $permiso_reapertura_gerencial)) { ?>
  <button class="btn btn-sm btn-primary">Guardar</button>
  <?php } elseif ($autoeval_bloqueada) { echo "<span class='fs-sm text-muted'>Autoevaluación bloqueada</span>"; } else { echo "<span class='fs-sm text-muted'>Periodo Cerrado</span>";}?>
  <a href="mi_evaluacion.php" class="btn btn-secondary btn-sm">Regresar</a>
</form>
						

				</div>
				<!-- /content area -->

				<?php require_once('assets/footer.php'); ?>

			</div>
			<!-- /inner content -->

		</div>
		<!-- /main content -->

	</div>
	<!-- /page content -->

<!-- Modal Agregar -->

</body>
</html>
