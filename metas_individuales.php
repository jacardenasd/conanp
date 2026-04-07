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
$captura_metas_individuales_bloqueada = modulo_bloqueado_por_periodo('metas_individuales', (int)$periodo);

// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();

// Obtener metas del usuario
$stmt = $pdo->prepare("SELECT * FROM metas WHERE user_id = ? AND periodo = ?");
$stmt->execute([$user_id, $periodo]);
$metas = $stmt->fetchAll();
$totalMetas = count($metas); 

// Validar suma de ponderación
$stmt = $pdo->prepare("SELECT SUM(ponderacion) FROM metas WHERE user_id = ? AND periodo = ?");
$stmt->execute([$user_id, $periodo]);
$suma = $stmt->fetchColumn();

if (isset($_POST['eliminar_confirmado']) && isset($_POST['id_eliminar'])) {
if ($captura_metas_individuales_bloqueada) {
header("Location: metas_individuales.php?info=11");
exit;
}

$stmt = $pdo->prepare("DELETE FROM metas WHERE id = ? AND user_id = ?");
$stmt->execute([$_POST['id_eliminar'], $user_id]);
header("Location: metas_individuales.php?info=3");
exit;
}
 
//evaluacion especial
$stmt = $pdo->prepare("SELECT COUNT(*) FROM especiales WHERE user_id = ? AND periodo = ?");
$stmt->execute([$user_id, $periodo]);
$especial = $stmt->fetchColumn() > 0;

$periodo_flexible_2025 = ((int)$periodo === 2025);
$permite_captura_flexible = ($estatus_periodo === 'Captura' || $estatus_periodo === 'Evaluación' || $especial || $periodo_flexible_2025);
$permite_propuesta_flexible = ($estatus_periodo === 'Evaluación' || $especial || $periodo_flexible_2025);


// valor de metas individuales
$stmt = $pdo->prepare("SELECT individuales, estatus_metas FROM calificaciones WHERE user_id = ? AND periodo = ?");
$stmt->execute([$user_id, $periodo]);
$datos = $stmt->fetch(PDO::FETCH_ASSOC);

if ($datos) {
  $individuales = $datos['individuales'];
  $estatus_metas = $datos['estatus_metas'];
} else {
  $individuales = 0;
  $estatus_metas = 0;
}

// Validar que todas las metas tengan propuesta de resultado, osea nunguna con 0
$stmt = $pdo->prepare("SELECT COUNT(*) FROM metas WHERE user_id = ? AND periodo = ? AND resultado = ?");
$stmt->execute([$user_id, $periodo, 0]);
$metas_con_evaluacion = $stmt->fetchColumn();

$en_captura_metas = ($permite_captura_flexible && !$captura_metas_individuales_bloqueada && $estatus_metas <= 1);
$en_propuesta_resultados = ($permite_propuesta_flexible && $estatus_metas == 1 && (float)$suma == 100.0);
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
								<span class="breadcrumb-item active">Metas Individuales <?= $periodo ?></span>
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
                        case 9:
                          $texto = '✅ Periodo cerrado correctamente';
                          $clase_alerta = 'alert-success';
                          break;
                        case 10:
                          $texto = '🔒 No puedes editar las metas. Ya se encuentra finalizado el proceso o evaluado por tu superior.';
                          $clase_alerta = 'alert-warning';
                          break;
                        case 11:
                          $texto = '🔒 La captura de metas individuales está cerrada para este periodo por configuración administrativa.';
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



    <?php if ($captura_metas_individuales_bloqueada): ?>
    <div class="alert alert-warning border-0 alert-dismissible fade show">
      La captura de metas individuales está cerrada para el periodo <span class="fw-semibold"><?= (int)$periodo ?></span>.
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>


        <?php if ($estatus_periodo == 'Evaluación' AND $totalMetas == 0) { ?>
        <div class="alert alert-info border-0 alert-dismissible fade show">
               El periodo está en <span class="fw-semibold">Evaluación</span>. Puedes capturar tus metas y después proponer resultados en esta misma sección.
										<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
								    </div>
        <?php } ?>


        <?php if ( $suma > 0 AND  $suma < 100 ) { ?>
        <div class="alert alert-warning  border-0 alert-dismissible fade show">
                    La ponderación suma <span class="fw-semibold"><?= $suma ?>%</span>. Recuerda que debe sumar 100%.
										<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
								    </div>
        <?php } ?>

        <div class="card-body">

        <div class="d-flex align-items-start mb-4">
          <img src="assets/images/avatars/Guacamole-2.png" alt="Guía" class="rounded-circle mr-3" width="70" height="70">
          <div class="bg-light border p-3 rounded shadow-sm">
           En este apartado puedes capturar y actualizar tus metas individuales.
          </div>
        </div>

        <div class="row g-4">
  <!-- Metas Individuales -->
  <div class="col-lg-10">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0 fw-semibold text-primary">
          <i class="ph-target me-2 text-secondary"></i>Metas Individuales
        </h5>
      </div>
      <div class="card-body">
        <p class="mb-4">
          Tus metas individuales deben ser <strong>observables, medibles y realistas</strong>, reflejando resultados específicos del desempeño. Para redactarlas correctamente, considera lo siguiente:
        </p>

        <ol class="mb-4 ps-4">
          <li>Utiliza un <strong>verbo activo orientado a resultados</strong>: implantación, operación, mejora, mantenimiento, coordinación, etc.</li>
          <li>Describe un <strong>indicador de desempeño claro</strong>, incluyendo unidad de medida.</li>
          <li>Asigna una <strong>ponderación específica</strong> a cada meta, con base en su impacto.</li>
          <li>Establece una unidad de medida basada en:</li>
          <ol type="i" class="ps-4">
            <li><strong>Cantidad</strong>: unidades, inventarios, pronósticos.</li>
            <li><strong>Calidad</strong>: especificaciones del producto/servicio.</li>
            <li><strong>Tiempo</strong>: fechas programadas, plazos límite.</li>
            <li><strong>Costo</strong>: variaciones en recursos económicos.</li>
          </ol>
        </ol>

        <p class="mb-0">
          ⚠️ <strong>Importante:</strong> Una vez que confirmes el término de la captura, ya no podrás editar la información.
        </p>
      </div>
    </div>
  </div>

  <!-- Cierre de Captura -->
  <div class="col-lg-2">
    <div class="card">
      <div class="card-header">
        <h6 class="mb-0 fw-semibold text-danger">
          <i class="ph-lock-key me-1 text-danger"></i>Cierre de <?php if (($estatus_periodo === 'Evaluación')) { echo 'Evaluación'; } else { echo 'Captura';} ?>

        </h6>
      </div>
      <div class="card-body text-center d-flex flex-column justify-content-center">
        <p class="mb-0">
          Al completar la captura o evaluación, aparecerá el botón <strong>"Terminar"</strong> para confirmar el cierre del proceso.<br/>
            <?php if (
              ($permite_captura_flexible AND $suma == 100 AND $estatus_metas <= 1)
            OR ($permite_propuesta_flexible AND $metas_con_evaluacion == 0 AND $estatus_metas == 1)
            OR ($especial AND $estatus_metas < 2)
            ) { ?>
                    <button class="btn btn-indigo btn-sm" data-bs-toggle="modal" data-bs-target="#confirmarcierre"><i class="ph-lock-key me-2"></i>Terminar</button>
          <?php } elseif ($estatus_metas >= 2) { ?>
                    <span class="badge bg-success">✓ Evaluación Finalizada</span>
          <?php } ?>
        </p>
      </div>
    </div>
  </div>
</div>

        
				<div class="card">
        <table class="table table-bordered table-xl">
          <thead>
          <tr class="bg-info text-white">
              <th>Meta Individual</th>
              <th>Unidad de Medida</th>
              <th>Ponderación</th>
              <?php if (($estatus_periodo === 'Evaluación' OR $estatus_periodo === 'Cerrado')) { ?>
              <th>Resultado (Evaluado)</th>
              <th>Resultado por el Superior Jerárquico</th>
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
              <?php if (($estatus_periodo === 'Evaluación' OR $estatus_periodo === 'Cerrado')) { ?>
                <!-- Cambio Febrero 2026 D3: Columna de resultado propuesto por el evaluado -->
                <td class="text-center">
                  <?php 
                  if ($m['resultado'] != 0) {
                    echo '<span class="badge bg-primary">'.$m['resultado'].'%</span>';
                  } else {
                    echo '<span class="text-muted">Pendiente</span>';
                  }
                  ?>
                </td>
                <!-- Cambio Febrero 2026 D3: Nueva columna de resultado dado por el superior -->
                <td class="text-center">
                  <?php 
                  if ($m['resultado_final'] != 0) {
                    echo '<span class="badge bg-success">'.$m['resultado_final'].'%</span>';
                  } else {
                    echo '<span class="text-muted">Pendiente</span>';
                  }
                  ?>
                </td>
              <?php } ?>
                <td>

                    <?php if ($en_propuesta_resultados) { ?>
                      <a href="meta_editar.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-primary">Proponer</a>
                    <?php } elseif ($en_captura_metas) { ?>
                      <a href="meta_editar.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-primary">Editar</a>
                      <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#confirmarEliminar<?= $m['id'] ?>">Eliminar</button>
                    <?php } else { ?>
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
          <th>Total <?= $suma ?>%  </th>
        <?php if (($estatus_periodo === 'Evaluación')) { ?>
          <th></th>
        <?php } ?>
          <th></th>
          </tr>
        </tfoot>
      </table>
      </div>

      <p>&nbsp;</p>

      <?php if ($en_captura_metas) { ?>

      <?php  if ($totalMetas >= 7 ) { ?>

        <div class="alert alert-warning">⚠️ Ya has capturado las 7 metas permitidas.</div>

        <?php } else { ?>

          <a href="meta_nueva.php" class="btn btn-sm btn-success">Agregar Meta</a>

        <?php } ?>

      <?php } ?>
       <a href="mis_metas_individuales.php" class="btn btn-secondary btn-sm">Regresar</a>

      </div>
</div>


<!-- Modal  Eliminación -->
<div class="modal fade" id="confirmarcierre" tabindex="-1">
<div class="modal-dialog modal-lg">
	<div class="modal-content">
  <form method="post" action="cierre_periodo.php">
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
    <input type="hidden" name="user_id" value="<?= $user_id ?>"></input>
    <input type="hidden" name="estatus_periodo" value="<?= $estatus_periodo ?>"></input>
    <input type="hidden" name="estatus_metas" value="<?= $estatus_metas ?>"></input>
      </div>
    </form>
  </div>
</div>
</div>
<!-- /Modal  Eliminación -->


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
