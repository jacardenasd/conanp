
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
$user_id = $_SESSION['user_id']; 

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);
$colaborador = $_GET['colaborador'] ?? null;
$nivel = null;
$nombre_completo = '';
$competencias = [];
$evaluacion_auto = [];
$evaluacion_jefe = [];

$periodo = $_SESSION['periodo'];

// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();


if ($colaborador) {
    $stmt = $pdo->prepare("SELECT puesto_nivel, nombre, apellido_paterno, apellido_materno FROM usuarios WHERE user_id = ?");
    $stmt->execute([$colaborador]);
  $datos_colaborador = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($datos_colaborador) {
    $nivel = $datos_colaborador['puesto_nivel'];
    $nombre_completo = "{$datos_colaborador['nombre']} {$datos_colaborador['apellido_paterno']} {$datos_colaborador['apellido_materno']}";
  }

    $competencias = $pdo->query("SELECT * FROM competencias")->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT competencia_id, valor FROM competencias_valores WHERE nivel = ?");
    $stmt->execute([$nivel]);
    $valores = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $stmt = $pdo->prepare("SELECT descripcion_id, evaluacion FROM competencias_evaluacion 
                           WHERE user_id = ? AND periodo = ? AND tipo = 'auto'");
    $stmt->execute([$colaborador, $periodo]);
    $evaluacion_auto = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $stmt = $pdo->prepare("SELECT descripcion_id, evaluacion FROM competencias_evaluacion 
                           WHERE user_id = ? AND periodo = ? AND tipo = 'jefe'");
    $stmt->execute([$colaborador, $periodo]);
    $evaluacion_jefe = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

  }

$opciones = ['Muy Característico', 'Característico', 'Poco Característico', 'No es Característico', 'No Aplica'];

// si ya fue finalizada por el jefe, bloquear edicion
$stmt = $pdo->prepare("SELECT estatus_gerenciales FROM calificaciones WHERE user_id = ? AND periodo = ?");
$stmt->execute([$colaborador, $periodo]);
$estatus_gerenciales = (int)($stmt->fetchColumn() ?? 0);
$autoeval_finalizada = ($estatus_gerenciales >= 3);

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
								<a href="#" class="breadcrumb-item">Evaluación Gerencial</a>
								<span class="breadcrumb-item active">Evaluación Gerencial Colaborador</span>
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


<?php if ($colaborador && $nivel): ?>

  <!-- Title with left icon -->
  <div class="card">  
    <div class="card-body">
    <h5>Evaluado: <?= htmlspecialchars($nombre_completo) ?></h5>
    Instrucciones: Para el caso de la autoevaluación gerencial, la persona Evaluador(a)a asignada, validará las valoraciones emitidas por la persona evaluada. El primer paso será que la persona evaluada, realice su autoevaluación gerencial, luego de ello, la persona evaluadora validará o modificara (en su caso) el resultado para cada capacidad. La calificación final de la Autoevaluación Gerencial será la que valide el Evaluador.<br/>
    <b>Una vez guardada la evaluación, ya no se podrá modificar.</b>
    </div>
  </div>
  <!-- /title with left icon -->


<form action="admin_guardar_competencias_colaborador.php" method="post">
  <input type="hidden" name="nivel" value="<?= $nivel ?>">
  <input type="hidden" name="colaborador" value="<?= $colaborador ?>">
  <input type="hidden" name="tipo" value="jefe">
    
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
          foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $desc):
            $auto = $evaluacion_auto[$desc['id']] ?? 'Sin respuesta';
            $jefe = $evaluacion_jefe[$desc['id']] ?? 'Sin respuesta';
        ?>
          <div class="mb-3">
            <h6 class="card-title"><?= htmlspecialchars($desc['descripcion']) ?></h6>
            <div class="mb-1 text-success small">Autoevaluación: <strong><?= htmlspecialchars($auto) ?></strong></div>
            <?php foreach ($opciones as $opt): ?>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio"
                name="evaluacion[<?= $desc['id'] ?>]" value="<?= $opt ?>" <?= ($jefe === $opt) ? "checked" : "" ?> <?= $autoeval_finalizada ? 'disabled' : '' ?> required>
                <label class="form-check-label"><?= $opt ?></label>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <?php 
  if (!$autoeval_finalizada) { ?>
  <button class="btn btn-sm btn-success">Guardar</button>
  <?php } ?>
  <a href="admin_calificaciones.php" class="btn btn-secondary btn-sm">Regresar</a>



  
</form>
<?php endif; ?>
						

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
