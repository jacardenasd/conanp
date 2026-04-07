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
$periodo = $_SESSION['periodo'];
$captura_metas_colectivas_bloqueada = modulo_bloqueado_por_periodo('metas_colectivas', (int)$periodo);

// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();

// Obtener metas del usuario
$stmt = $pdo->prepare("SELECT * FROM metas_colectivas WHERE unidad_id = ? AND periodo = ?");
$stmt->execute([$unidad_id, $periodo]);
$metas = $stmt->fetchAll();
$totalMetas = count($metas); 

  // Validar suma de ponderación
  $stmt = $pdo->prepare("SELECT SUM(ponderacion) FROM metas_colectivas WHERE unidad_id = ? AND periodo = ?");
  $stmt->execute([$unidad_id, $periodo]);
  $suma = $stmt->fetchColumn();

// Cambio Febrero 2026 - A3, B1: Verificar si están finalizadas (bloqueo de edición)
$stmt_finalizado = $pdo->prepare("SELECT finalizado_colectivas FROM calificaciones_colectivas WHERE unidad_id = ? AND periodo = ?");
$stmt_finalizado->execute([$unidad_id, $periodo]);
$finalizado_colectivas = $stmt_finalizado->fetchColumn() ?? 0;

// Procesar eliminación solo si NO están finalizadas
if (isset($_POST['eliminar_confirmado']) && isset($_POST['id_eliminar'])) {
if ($finalizado_colectivas == 1 || $captura_metas_colectivas_bloqueada) {
    header("Location: metas_colectivas.php?info=8"); // Error: ya finalizado
    exit;
}

$stmt = $pdo->prepare("DELETE FROM metas_colectivas WHERE id = ? AND unidad_id = ?");
$stmt->execute([$_POST['id_eliminar'], $unidad_id]);
header("Location: metas_colectivas.php?info=3");
exit;
}
 
$stmt = $pdo->prepare("SELECT estatus FROM calificaciones_colectivas WHERE unidad_id = ? AND periodo = ?");
$stmt->execute([$unidad_id, $periodo]);
$resultado = $stmt->fetch(PDO::FETCH_ASSOC);
if ($resultado) {$estatus_metas = $resultado['estatus'];} else {$estatus_metas = 0;}

// Cambio Febrero 2026 - B1: En evaluación, bloquear totalmente edición de campos (especialmente instrumento y unidad medida)
$bloquear_edicion_colectivas = ($finalizado_colectivas == 1) || ($estatus_periodo === 'Evaluación') || $captura_metas_colectivas_bloqueada;
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
								<span class="breadcrumb-item active">Metas Colectivas  <?= $periodo ?></span>
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
                            $texto = '✏️ Meta actualizada.';
                            $clase_alerta = 'alert-info';
                            break;
                        case 3:
                            $texto = '🗑️ Meta eliminada.';
                            $clase_alerta = 'alert-danger';
                            break;
                        case 8:
                          $texto = '🔒 No puedes modificar las metas. Carga finalizada, periodo en evaluación o captura cerrada por configuración administrativa.';
                            $clase_alerta = 'alert-warning';
                            break;
                        case 9:
                            $texto = '✅ Periodo cerrado correctamente';
                            $clase_alerta = 'alert-success';
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




    <?php if ($captura_metas_colectivas_bloqueada): ?>
    <div class="alert alert-warning border-0 alert-dismissible fade show">
      La captura de metas colectivas está cerrada para el periodo <span class="fw-semibold"><?= (int)$periodo ?></span>.
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
            En este apartado puedes capturar y actualizar tus metas colectivas.
          </div>
        </div>


<div class="row g-4">
  <!-- Metas Colectivas -->
  <div class="col-lg-10">
    <div class="card 4">
      <div class="card-header">
        <h5 class="mb-0 fw-semibold text-primary">
          <i class="ph-users-four me-2 text-secondary"></i>Metas Colectivas
        </h5>
      </div>
      <div class="card-body">
        <p class="mb-4">
          Las metas colectivas deben ser <strong>observables, medibles y realistas</strong>, reflejando resultados específicos del desempeño. Para definirlas correctamente, considera lo siguiente:
        </p>

        <ol class="mb-4 ps-4">
          <li>Utiliza un <strong>verbo activo</strong> orientado a resultados: implantación, mejora, mantenimiento, coordinación, etc.</li>
          <li>Describe un <strong>indicador claro</strong> con su unidad de medida.</li>
          <li>Asigna una <strong>ponderación específica</strong> a cada meta, de acuerdo con su impacto.</li>
          <li>Establece una unidad de medida, con base en:</li>
          <ol type="i" class="ps-4">
            <li><strong>Cantidad:</strong> unidades, inventarios, estimaciones.</li>
            <li><strong>Calidad:</strong> especificaciones de producto o servicio.</li>
            <li><strong>Tiempo:</strong> fechas y plazos programados.</li>
            <li><strong>Costo:</strong> recursos económicos aplicados.</li>
          </ol>
        </ol>

        <p class="mb-0">
          ⚠️ <strong>Importante:</strong> Una vez que confirmes el término, ya no podrás editar esta información.
        </p>
      </div>
    </div>
  </div>

  <!-- Cierre de Captura -->
  <div class="col-lg-2">
    <div class="card">
      <div class="card-header">
        <h6 class="mb-0 fw-semibold text-danger">
          <i class="ph-lock-key me-1 text-danger"></i>Cierre de Captura
        </h6>
      </div>
      <div class="card-body text-center d-flex flex-column justify-content-center">
        <p class="mb-3">
          Al concluir el proceso de captura o evaluación, da clic en <strong>"Terminar"</strong> para confirmar.
        </p>

        <?php if (!$captura_metas_colectivas_bloqueada && (($estatus_periodo === 'Captura' && $estatus_metas == 0 && $suma == 100) OR ($estatus_periodo === "Evaluación" && ($estatus_metas == 1 OR $estatus_metas == 0 && $suma == 100)))) { ?>
          <button class="btn btn-primary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#confirmarcierre">
            <i class="ph-check-circle me-2"></i>Terminar
          </button>
        <?php } ?>
      </div>
    </div>
  </div>
</div>

        
        <?php if (count($metas) === 0): ?>
          <p class="text-muted">No has capturado metas aún.</p>
          <?php if (!$captura_metas_colectivas_bloqueada): ?>
          <a href="meta_nueva_colectivas.php" class="btn btn-sm btn-success">Agregar Meta</a>
          <?php endif; ?>
        <?php else: ?>

				<div class="card">
        <table class="table table-bordered table-xl">
          <thead>
          <tr class="bg-teal text-white">
              <th>Meta Individual</th>
              <th>Unidad de Medida</th>
              <th>Ponderación</th>
              <th>Resultado Esperado</th>
              <?php if (($estatus_periodo === 'Evaluación')) { ?>
              <th>Resultado</th>
              <?php } ?>
              <th style="width:15%">Acciones</th>
            </tr>
          </thead>  
          <tbody>
            <?php foreach ($metas as $m): ?>
              <tr>
                <td><?= htmlspecialchars($m['indicador']) ?></td>
                <td><?= htmlspecialchars($m['unidad']) ?></td>
                <td><?= $m['ponderacion'] ?>%</td>
                <td><?= htmlspecialchars($m['satisfactorio']) ?></td>
              <?php if (($estatus_periodo === 'Evaluación')) { ?>
                <td><?= $m['resultado'] ?>%</td>
              <?php } ?>
                <td>

                    <?php if ($captura_metas_colectivas_bloqueada) { ?>
                      <span class='fs-sm text-muted'>Captura cerrada por administración</span>
                    <?php } elseif ($estatus_periodo === 'Captura') {?>

                      <?php if ($estatus_metas == 0) {?>
                      <a href="meta_editar_colectivas.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-primary">Editar</a>
                      <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#confirmarEliminar<?= $m['id'] ?>">Eliminar</button>
                      <?php } else if ($estatus_metas == 1 OR $estatus_metas == 2 OR $estatus_metas == 3){ ?>
                        <span class='fs-sm text-muted'>Periodo Cerrado</span>
                      <?php } ?>

                      <?php } elseif ($estatus_periodo === 'Evaluación') {?>

                      <?php if ($estatus_metas == 0) {?>
                      <a href="meta_editar_colectivas.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-primary">Editar</a>
                      <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#confirmarEliminar<?= $m['id'] ?>">Eliminar</button>
                      <?php } else if ($estatus_metas == 1){ ?>
                        <a href="meta_editar_colectivas.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-primary">Proponer</a>
                      <?php } else if ($estatus_metas == 2 OR $estatus_metas == 3){ ?>
                        <span class='fs-sm text-muted'>Periodo Cerrado</span>
                      <?php } ?>
                        
                    <?php } elseif ($estatus_periodo === 'Cerrado') {?>
                      <span class='fs-sm text-muted'>Periodo Cerrado</span>
                    <?php } ?>

                </td>
              </tr>

<!-- Modal  Eliminación -->
<div class="modal fade" id="confirmarEliminar<?= $m['id'] ?>" tabindex="-1">
<div class="modal-dialog modal-lg">
	<div class="modal-content">
    <form method="post">
    <div class="modal-header bg-danger text-white border-0">
        <h5 class="modal-title">Confirmación de Borrado</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        ¿Estás seguro de que deseas eliminar la Meta:<br/> <strong><?= $m['indicador'] ?></strong>?
      </div>
      <div class="modal-footer">
        <button name="eliminar_confirmado" class="btn btn-sm btn-danger">Eliminar</button>
		<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
		<input type="hidden" name="id_eliminar" value="<?= $m['id'] ?>"></input>
      </div>
    </form>
  </div>
</div>
</div>
<!-- /Modal  Eliminación -->

              <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
          <th></th>
          <th></th>
        <?php if (($estatus_periodo === 'Evaluación')) { ?>
          <th></th>
        <?php } ?>
          <th>Total <?= $suma ?>%  </th>
          <th></th>
          <th></th>
          </tr>
        </tfoot>
      </table>
      </div>
      <?php endif; ?>

      <p>&nbsp;</p> 

      <?php  if (!$captura_metas_colectivas_bloqueada && $estatus_periodo === 'Captura' AND $estatus_metas == 0 AND $suma != 100) {  ?>
        <?php if ($totalMetas < 7): ?>
        <a href="meta_nueva_colectivas.php" class="btn btn-sm btn-success">Agregar Meta</a>
        <?php else: ?>
        <div class="alert alert-warning">⚠️ Ya has capturado las 7 metas permitidas.</div>
        <?php endif; ?>
      <?php } ?>


      <?php  if (!$captura_metas_colectivas_bloqueada && $estatus_periodo === 'Evaluación' AND $estatus_metas == 0 AND $suma != 100) {  ?>
        <?php if ($totalMetas < 7): ?>
        <a href="meta_nueva_colectivas.php" class="btn btn-sm btn-success">Agregar Meta</a>
        <?php else: ?>
        <div class="alert alert-warning">⚠️ Ya has capturado las 7 metas permitidas.</div>
        <?php endif; ?>
      <?php } ?>



      <a href="mis_metas_colectivas.php" class="btn btn-secondary btn-sm">Regresar</a>

      </div>
</div>


<!-- Modal  cierre -->
<div class="modal fade" id="confirmarcierre" tabindex="-1">
<div class="modal-dialog modal-lg">
	<div class="modal-content">
  <form method="post" action="cierre_periodo_colectivas.php">
    <div class="modal-header bg-danger text-white border-0">
        <h5 class="modal-title">Confirmación de Cierre</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        ¿Estás seguro de que quieres cerrar el periodo actual?
      </div>
      <div class="modal-footer">
        <button name="cierre_periodo" class="btn btn-sm btn-danger">Terminar</button>
		<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <input type="hidden" name="unidad_id" value="<?= $unidad_id ?>"></input>
    <input type="hidden" name="estatus_periodo" value="<?= $estatus_periodo ?>"></input>
    <input type="hidden" name="estatus_metas" value="<?= $estatus_metas ?>"></input>
    <input type="hidden" name="periodo" value="<?= $periodo ?>"></input>
      </div>
    </form>
  </div>
</div>
</div>
<!-- /Modal  cierre -->


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
