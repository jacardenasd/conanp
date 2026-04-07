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
$colaborador_id = (int)($_GET['colaborador_id'] ?? 0);
$es_colaborador = false;
if ($colaborador_id > 0) {
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE user_id = ? AND jefe_id = ?");
  $stmt->execute([$colaborador_id, $user_id]);
  $es_colaborador = ((int)$stmt->fetchColumn() > 0);
}
if ($colaborador_id <= 0 || !$es_colaborador) {
  header("Location: mis_colaboradores.php?info=8");
  exit;
}
$periodo = $_SESSION['periodo'];

// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();

// Obtener metas del usuario
$stmt = $pdo->prepare("SELECT * FROM metas WHERE user_id = ? AND periodo = ?");
$stmt->execute([$colaborador_id, $periodo]);
$metas = $stmt->fetchAll();
$totalMetas = count($metas); 

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE user_id = ?");
$stmt->execute([$colaborador_id]);
$colaborador = $stmt->fetch(PDO::FETCH_ASSOC);

// Validar suma de ponderación
$stmt = $pdo->prepare("SELECT SUM(ponderacion) FROM metas WHERE user_id = ? AND periodo = ?");
$stmt->execute([$colaborador_id, $periodo]);
$suma = (float)($stmt->fetchColumn() ?? 0);

$prerequisitos = obtener_estatus_prerequisitos_colaborador($pdo, $colaborador_id, (int)$periodo);
$metas_listas = (bool)$prerequisitos['metas_listas'];
$estatus_metas = (int)($prerequisitos['estatus_metas'] ?? 0);

if (!$metas_listas) {
  header("Location: mis_colaboradores.php?info=7");
  exit;
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
	<script>
  setTimeout(() => {
  const alerta = document.getElementById('alerta-auto');
  if (alerta) {
    alerta.classList.remove('show');
    alerta.classList.add('fade');
    setTimeout(() => alerta.remove(), 500); // Espera a que termine la animación
  }
}, 4000);
  </script>
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
								<span class="breadcrumb-item active">Metas Individuales</span>
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
                            $texto = '✅ Meta guardada correctamente.';
                            $clase_alerta = 'alert-success';
                            break;
                        case 2:
                            $texto = 'Se generó un error en el cierre del periodo, por favor verifica.';
                            $clase_alerta = 'alert-info';
                            break;
                        case 3:
                            $texto = '🗑️ Meta eliminada.';
                            $clase_alerta = 'alert-danger';
                            break;
                        case 9:
                            $texto = '✅ Periodo cerrado correctamente';
                            $clase_alerta = 'alert-success';
                            break;
                        case 7:
                          $texto = '⚠️ No se puede evaluar. El colaborador debe capturar metas y proponer resultado en todas sus metas individuales.';
                          $clase_alerta = 'alert-warning';
                          break;
                        case 22:
                          $texto = '⚠️ No se puede cerrar la evaluación. Aún hay metas sin resultado final por parte del jefe.';
                          $clase_alerta = 'alert-warning';
                          break;
                        default:
                            $texto = 'info no reconocido.';
                            $clase_alerta = 'alert-secondary';
                            break;
                    }
                    ?>

                    <div class="alert <?= $clase_alerta ?> border-0 alert-dismissible fade show" id="alerta-auto">
										<span class="fw-semibold"> <?= $texto ?>
										<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
								    </div>
                    <?php endif; ?>




        <div class="card-body">

        <?php if ( $suma > 0 AND  $suma < 100 ) { ?>
        <div class="alert alert-warning  border-0 alert-dismissible fade show">
                    La ponderación suma <span class="fw-semibold"><?= $suma ?>%</span>. Recuerda que debe sumar 100%.
										<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
								    </div>
        <?php } ?>

        <div class="d-flex align-items-start mb-4">
          <img src="assets/images/avatars/Guacamole-2.png" alt="Guía" class="rounded-circle mr-3" width="70" height="70">
          <div class="bg-light border p-3 rounded shadow-sm">
            En este apartado puedes capturar y actualizar tus metas individuales.
          </div>
        </div>

					<!-- /card titles and subtitles -->
          <div class="row">
						<div class="col-lg-10">
							<div class="card">
								<div class="card-header">
									<h6 class="mb-0">Metas Individuales</h6>
								</div>
								
								<div class="card-body">
                <p class="mb-3">Evalua las metas de tu colaborador, al terminar da clic en el botón de "terminar" para confirmar el término del proceso. Una vez que hayas terminado, ya no se podrá editar la información evaluada.<br/>
                </p>

                <div class="d-flex align-items-center">
											<a class="d-block me-3">
												<img src="fotos/<?= htmlspecialchars($colaborador['foto'] ?? 'default.png') ?>" width="40" height="40" class="rounded-circle" alt="">
											</a>

									<div class="flex-fill">
										<a class="fw-semibold"><?= $colaborador['apellido_paterno']." ".$colaborador['apellido_materno'] ." ".$colaborador['nombre']  ?></a><br/>
										<?= $colaborador['puesto_nombre'] ?>
								</div>
								</div>


              </div>
							</div>
            </div>

						<div class="col-lg-2">
							<div class="card">
              <div class="card-header">
									<h6 class="mb-0">Cierre de Captura</h6>
								</div>
                <div class="card-body text-center">
									<p>Da clic al terminar el proceso de evaluación de metas.</p>
                  <?php
                      $stmt = $pdo->prepare("SELECT COUNT(*) FROM metas WHERE user_id = ? AND periodo = ? AND (resultado_final IS NULL OR resultado_final = '' OR resultado_final = 0)");
                      $stmt->execute([$colaborador_id, $periodo]);
                      $metas_incompletas = $stmt->fetchColumn();
                      $todas_completas = ($metas_incompletas == 0);
                  if ($estatus_periodo === 'Evaluación' AND $todas_completas AND $estatus_metas != 3) { ?>
                    <button class="btn btn-indigo btn-sm" data-bs-toggle="modal" data-bs-target="#confirmarcierre"><i class="ph-lock-key me-2"></i>Terminar</button>
                  <?php } ?>
								</div>
							</div>
            </div>
					</div>
					<!-- /card titles and subtitles -->

				<div class="card">
        <table class="table table-bordered table-xl">
          <thead>
          <tr class="bg-info text-white">
              <th>Meta Individual</th>
              <th>Unidad de Medida</th>
              <th>Ponderación</th>
              <th>Resultado</th>
              <th style="width:15%">Acciones</th>
            </tr>
          </thead>  
          <tbody>
            <?php foreach ($metas as $m): ?>
              <tr>
                <td><?= htmlspecialchars($m['indicador']) ?></td>
                <td><?= htmlspecialchars($m['unidad']) ?></td>
                <td><?= $m['ponderacion'] ?>%</td>
                <td><?php  if ($m['resultado_final'] > 0) { echo $m['resultado_final']."%";} else {echo "<span class='fs-sm text-danger'>Sin evaluar</span>";} ?></td>
                <td>

                    <?php if ($estatus_periodo === 'Evaluación' AND $estatus_metas != 3) {
                      $meta_tiene_propuesta = trim((string)($m['resultado'] ?? '')) !== '';
                      if ($meta_tiene_propuesta) { ?>
                      <a href="meta_evaluar.php?id=<?= $m['id'] ?>&colaborador_id=<?= $colaborador_id ?>" class="btn btn-sm btn-primary">Evaluar</a>
                      <?php } else { ?>
                        <span class='fs-sm text-warning'>Sin propuesta del colaborador</span>
                      <?php } ?>
                      <?php } elseif ($estatus_periodo === 'Evaluación' AND $estatus_metas == 3) {?>
                        <span class='fs-sm text-muted'>Periodo Cerrado</span>
                      <?php } elseif ($estatus_periodo === 'Cerrado') {?>
                        <span class='fs-sm text-muted'>Periodo Cerrado</span>
                    <?php } ?>
                </td>
              </tr>
              <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th></th>
            <th></th>
            <th>Total <?= $suma ?>%  </th>
            <th></th>
            <th></th>
          </tr>
        </tfoot>
      </table>
      </div>

      <p>&nbsp;</p>

       <a href="mis_colaboradores.php" class="btn btn-secondary btn-sm">Regresar</a>

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
<!-- Modal  Eliminación -->
<div class="modal fade" id="confirmarcierre" tabindex="-1">
<div class="modal-dialog">
	<div class="modal-content">
  <form method="post" action="cierre_periodo_evaluar.php">
    <div class="modal-header bg-danger text-white border-0">
        <h5 class="modal-title">Confirmación de Cierre</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        ¿Estás seguro de que quieres cerrar la evaluación?
      </div>
      <div class="modal-footer">
        <button name="cierre_periodo" class="btn btn-sm btn-danger">Terminar</button>
		<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <input type="hidden" name="colaborador_id" value="<?= $colaborador_id ?>"></input>
    <input type="hidden" name="estatus_periodo" value="<?= $estatus_periodo ?>"></input>
    <input type="hidden" name="estatus_metas" value="<?= $estatus_metas ?>"></input>
      </div>
    </form>
  </div>
</div>
</div>
<!-- /Modal  Eliminación -->



</body>
</html>
