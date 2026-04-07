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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['agregar'])) {
        $stmt = $pdo->prepare("INSERT INTO adscripciones (nombre, ponderacion, no_satisfactorio, adscripcion) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['nombre'], $_POST['ponderacion'], $_POST['no_satisfactorio'], $_POST['adscripcion']]);
    } elseif (isset($_POST['editar'])) {
        $stmt = $pdo->prepare("UPDATE adscripciones SET nombre = ?, ponderacion = ?, no_satisfactorio = ?, adscripcion = ?  WHERE id = ?");
        $stmt->execute([$_POST['nombre'], $_POST['ponderacion'], $_POST['no_satisfactorio'], $_POST['adscripcion'], $_POST['id']]);
    }
    header("Location: adscripciones.php");
    exit();
}

if (isset($_GET['eliminar'])) {
    $stmt = $pdo->prepare("DELETE FROM adscripciones WHERE id = ?");
    $stmt->execute([$_GET['eliminar']]);
    header("Location: adscripciones.php");
    exit();
}

$stmt = $pdo->query("SELECT
adscripciones.id,
adscripciones.nombre,
adscripciones.unidad_id,
unidades.id,
unidades.nombre As Unidad
FROM
adscripciones
LEFT JOIN unidades ON adscripciones.unidad_id = unidades.id ORDER BY adscripciones.unidad_id");
$adscripciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
								<a href="#" class="breadcrumb-item">Administracion</a>
								<span class="breadcrumb-item active">Adscripciónes</span>
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

					<!-- Basic table -->
					<div class="card">
						<div class="card-header">
							<h5 class="mb-0">Adscripciónes</h5>
						</div>

						<div class="card-body">
							Example of a  calendars and date pickers, we've opted to isolate our custom table styles.
						</div>

						<div class="table-responsive">
						<table class="table datatable-pagination">
							<thead class="thead-light">
								<tr>
									<th>ID</th>
									<th>Adscripción</th>
									<th>Unidad</th>
									<th>Acciones</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($adscripciones as $u): ?>
								<tr>
									<td><?= $u['id'] ?></td>
									<td><?= htmlspecialchars($u['nombre']) ?></td>
									<td><?= htmlspecialchars($u['Unidad']) ?></td>
									<td>
										<button class="btn btn-info btn-sm btn-editar" 
										data-id="<?= $u['id'] ?>" 
										data-nombre="<?= htmlspecialchars($u['nombre']) ?>" 
										data-ponderacion="<?= htmlspecialchars($u['Unidad']) ?>" 
										data-bs-toggle="modal" data-bs-target="#modalEditar">Editar</button>

										<button class="btn btn-danger btn-sm btn-eliminar" 
										data-id="<?= $u['id'] ?>" 
										data-nombre="<?= htmlspecialchars($u['nombre']) ?>" 
										data-ponderacion="<?= htmlspecialchars($u['Unidad']) ?>" 
										data-bs-toggle="modal" data-bs-target="#modalEliminar">Eliminar</button>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						</div>
					</div>
					<!-- /basic table -->

					<button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalAgregar">Agregar</button>
					<a href="dashboard.php" class="btn btn-secondary btn-sm">Regresar</a>

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
<div class="modal fade" id="modalAgregar" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="post" class="modal-content">
      <div class="modal-header">
		<h5 class="modal-title">Agregar Adscripción</h5>
		<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
	  </div>
      <div class="modal-body">

	  	<div class="form-group">
            <label>Nombre</label>
            <input type="text" name="nombre" class="form-control" required placeholder="Nombre de adscripcion"></input>
        </div>

		<div class="form-group">
            <label>Ponderacion</label>
            <input type="number" name="ponderacion" class="form-control" required placeholder="ponderacion"></input>
        </div>

        <div class="form-group">
            <label>Resultado No Satisfactorio</label>
            <textarea name="no_satisfactorio" class="form-control" required placeholder="Resultado No Satisfactorio"></textarea>
        </div>

        <div class="form-group">
            <label>Adscripción de Medida</label>
            <select name="adscripcion" class="form-control" required>
				<option value="">Seleccione...</option>
				<option value="cantidad">Cantidad</option>
                <option value="calidad">Calidad</option>
                <option value="tiempo">Tiempo</option>
                <option value="costo">Costo</option>
            </select>
        </div>

	</div>
      <div class="modal-footer">
        <button name="agregar" class="btn btn-sm btn-success">Agregar</button>
		<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
	  </div>
    </form>
  </div>
</div>
<!-- /Modal Agregar -->


<!-- Modal Editar -->
<div class="modal fade" id="modalEditar" tabindex="-1">
	<div class="modal-dialog modal-lg">
    <form method="post" class="modal-content">
      <input type="hidden" name="id" id="idEditar">
      <div class="modal-header"><h5 class="modal-title">Editar Adscripción</h5>
	  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
	  </div>
      <div class="modal-body">

	    <div class="form-group">
            <label>Nombre</label>
            <input type="text" name="nombre" id="nombreEditar" class="form-control" required></input>
        </div>

		<div class="form-group">
            <label>Ponderacion</label>
            <input type="number" name="ponderacion" id="ponderacionEditar" class="form-control" required></input>
        </div>

        <div class="form-group">
            <label>Resultado No Satisfactorio</label>
            <textarea name="no_satisfactorio" id="no_satisfactorioEditar" class="form-control" required></textarea>
        </div>

        <div class="form-group">
            <label>Adscripción de Medida</label>
            <select  name="adscripcion" id="adscripcionEditar" class="form-control" required>
				<option value="">Seleccione...</option>
				<option value="cantidad">Cantidad</option>
                <option value="calidad">Calidad</option>
                <option value="tiempo">Tiempo</option>
                <option value="costo">Costo</option>
            </select>
        </div>

	</div>
      <div class="modal-footer">
        <button name="editar" class="btn btn-sm btn-info">Editar</button>
		<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
	      </div>
    </form>
  </div>
</div>

<script>
$('.btn-editar').click(function() {
    $('#idEditar').val($(this).data('id'));
    $('#nombreEditar').val($(this).data('nombre'));
    $('#ponderacionEditar').val($(this).data('ponderacion'));
    $('#no_satisfactorioEditar').val($(this).data('no_satisfactorio'));
    $('#adscripcionEditar').val($(this).data('adscripcion'));
});
</script>
<!-- Modal Editar -->


<!-- Modal  Eliminación -->
<!-- Modal Editar -->
<div class="modal fade" id="modalEliminar" tabindex="-1" aria-labelledby="modalEliminarLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
    <form method="get" action="adscripciones.php" class="modal-content">
    <form method="post" class="modal-content">
      <input type="hidden" name="id" id="idEditar">
      <div class="modal-header"><h5 class="modal-title">Editar Adscripción</h5>
	  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
	  </div>
      <div class="modal-body">
        ¿Estás seguro de que deseas eliminar <strong id="nombreEliminar"></strong>?
        <input type="hidden" name="eliminar" id="idEliminar">
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
		<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('.btn-eliminar').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('idEliminar').value = this.dataset.id;
            document.getElementById('nombreEliminar').innerText = this.dataset.nombre;
            document.getElementById('no_satisfactorioEliminar').innerText = this.dataset.no_satisfactorio;
            document.getElementById('adscripcionEliminar').innerText = this.dataset.adscripcion;
            var modal = new bootstrap.Modal(document.getElementById('modalEliminar'));
            modal.show();
        });
    });
});
</script>
<!-- Modal  Eliminación -->

</body>
</html>
