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

$user_id = $_SESSION['NoEmp'];
$periodo = date('Y'); // Ajustar si usas un periodo distinto

// Obtener metas del usuario
$stmt = $pdo->prepare("SELECT * FROM metas WHERE user_id = ? AND periodo = ?");
$stmt->execute([$user_id, $periodo]);
$metas = $stmt->fetchAll();
$totalMetas = count($metas); 

  // Validar suma de ponderación
  $stmt = $pdo->prepare("SELECT SUM(ponderacion) FROM metas WHERE user_id = ? AND periodo = ?");
  $stmt->execute([$user_id, $periodo]);
  $suma = $stmt->fetchColumn();

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
								<span class="breadcrumb-item active">Metas Individuales</span>
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

  <?php if (isset($_GET['mensaje'])): ?>
    <div class="alert alert-<?= $_GET['mensaje'] == 'eliminado' ? 'danger' : 'success' ?>">
      <?php
        switch ($_GET['mensaje']) {
          case 'guardado': echo '✅ Meta guardada correctamente.'; break;
          case 'actualizado': echo '✏️ Meta actualizada.'; break;
          case 'eliminado': echo '🗑️ Meta eliminada.'; break;
        }
      ?>
    </div>
  <?php endif; ?>

        <div class="card">
						<div class="card-header">
            <h3>Metas Individuales — Periodo <?= $periodo ?></h3>
						</div>

						<div class="card-body">
							Example of a  calendars and date pickers, we've opted to isolate our custom table styles.



        <?php if (count($metas) === 0): ?>
          <p class="text-muted">No has capturado metas aún.</p>
          <a href="meta_nueva.php" class="btn btn-sm btn-success">Agregar Meta</a>
        <?php else: ?>


        <table class="table table-bordered table-striped table-xl">
          <thead>
          <tr class="bg-dark text-white">
              <th>Indicador</th>
              <th>Unidad</th>
              <th>Ponderación</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($metas as $m): ?>
              <tr>
                <td><?= htmlspecialchars($m['indicador']) ?></td>
                <td><?= htmlspecialchars($m['unidad']) ?></td>
                <td><?= $m['ponderacion'] ?>%</td>
                <td>
                  <a href="meta_editar.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-primary">Editar</a>
                  <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#confirmarEliminar<?= $m['id'] ?>">Eliminar</button>
                </td>
              </tr>

              <!-- Modal de confirmación -->
              <div class="modal fade" id="confirmarEliminar<?= $m['id'] ?>" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                <form method="post">
                  <div class="modal-content">
                    <input type="hidden" name="id_eliminar" value="<?= $m['id'] ?>">
                    <div class="modal-header bg-danger text-white">
                      <h5 class="modal-title">¿Eliminar esta meta?</h5>
                      <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                      </button>
                    </div>
                    <div class="modal-body">
                      <p><strong><?= htmlspecialchars($m['indicador']) ?></strong></p>
                      <p>Esta acción no se puede deshacer.</p>
                    </div>
                    <div class="modal-footer">
                      <button type="submit" name="eliminar_confirmado" class="btn btn-danger">Eliminar</button>
                      <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    </div>
                  </di>
                </div>
              </div>

            </div>
            <!-- /content area -->

            <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th></th>
            <th></th>
            <th>Total <?= $suma ?>%  </th>
            <th></th>
          </tr>
        </tfoot>
      </table>

      <p>&nbsp;</p>
<?php if ($totalMetas < 7): ?>
  <a href="meta_nueva.php" class="btn btn-sm btn-success">Agregar Meta</a>
<?php else: ?>
  <div class="alert alert-warning">⚠️ Ya has capturado las 7 metas permitidas.</div>
<?php endif; ?>



      <?php endif; ?>
      <a href="mi_evaluacion.php" class="btn btn-secondary btn-sm">Regresar</a>
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

<?php
// Eliminar meta si se confirma vía POST
if (isset($_POST['eliminar_confirmado']) && isset($_POST['id_eliminar'])) {
  $stmt = $pdo->prepare("DELETE FROM metas WHERE id = ? AND user_id = ?");
  $stmt->execute([$_POST['id_eliminar'], $user_id]);
  header("Location: metas.php?mensaje=eliminado");
  exit;
}
?>
