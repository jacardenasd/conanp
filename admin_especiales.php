<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';
checkLogin(2);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');
$user_id = $_SESSION['user_id']; 

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);
$periodo = $_SESSION['periodo'];

// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();


// Filtros
$filtro_periodo = $_GET['periodo'] ?? '';
$filtro_user_id = $_GET['user_id'] ?? '';

$sql = "SELECT e.*, u.nombre, u.apellido_paterno, u.apellido_materno 
        FROM especiales e 
        LEFT JOIN usuarios u ON u.user_id = e.user_id 
        WHERE 1";
$params = [];

if ($filtro_periodo !== '') {
    $sql .= " AND e.periodo = ?";
    $params[] = $filtro_periodo;
}

$sql .= " ORDER BY e.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$especiales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Catálogos
$usuarios = $pdo->query("SELECT user_id, CONCAT(nombre, ' ', apellido_paterno, ' ', apellido_materno) AS nombre FROM usuarios ORDER BY nombre")->fetchAll(PDO::FETCH_KEY_PAIR);
$periodos = $pdo->query("SELECT DISTINCT anio FROM periodos ORDER BY anio DESC")->fetchAll();

// CRUD POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['accion'] === 'agregar') {
        $stmt = $pdo->prepare("INSERT INTO especiales (user_id, periodo, seccion) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['user_id'], $_POST['periodo'], $_POST['seccion']]);
        header("Location: admin_especiales.php");
        exit;
    }

    if ($_POST['accion'] === 'editar') {
        $stmt = $pdo->prepare("UPDATE especiales SET user_id = ?, periodo = ?, seccion = ? WHERE id = ?");
        $stmt->execute([$_POST['user_id'], $_POST['periodo'], $_POST['seccion'], $_POST['id']]);
        header("Location: admin_especiales.php");
        exit;
    }

    if ($_POST['accion'] === 'eliminar') {
        $stmt = $pdo->prepare("DELETE FROM especiales WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        header("Location: admin_especiales.php");
        exit;
    }
}
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
								<span class="breadcrumb-item active">Evaluaciones Especiales</span>
							</div>

							<a href="#breadcrumb_elements" class="btn btn-sm btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
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
							<h5 class="mb-0">Evaluaciones Especiales</h5>
						</div>

						<div class="card-body">
							Agrega usuarios para permitir el acceso a todas las secciones de la evaluación, independientemente del estatus del periodo.<br/><p>&nbsp;</p>

  <form class="row row-cols-lg-auto g-3 align-items-center mb-3" method="get">
    <div class="col-12">
      <select name="periodo" class="form-select">
        <option value="">Todos los periodos</option>
        <?php foreach ($periodos as $per): ?>
            <option value="<?= $per['anio'] ?>" <?= ($periodo == $per['anio']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($per['anio']) ?>
            </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12">
      <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
    </div>
  </form>

    <button class="btn btn-sm btn-success mb-3" data-bs-toggle="modal" data-bs-target="#modalAgregar">Agregar</button>

  <table class="table table-striped table-bordered">
    <thead>
      <tr>
        <th>#</th>
        <th>Usuario</th>
        <th>Periodo</th>
        <th>Sección</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($especiales as $e): ?>
        <tr>
          <td><?= $e['id'] ?></td>
          <td><?= $e['nombre'] . ' ' . $e['apellido_paterno'] . ' ' . $e['apellido_materno'] ?></td>
          <td><?= $e['periodo'] ?></td>
          <td>Todas</td>
          <td>
            <button class="btn btn-sm btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalEditar<?= $e['id'] ?>">Editar</button>
            <button class="btn btn-sm btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#modalEliminar<?= $e['id'] ?>">Eliminar</button>
          </td>
        </tr>

        <!-- Modal Editar -->
        <div class="modal fade" id="modalEditar<?= $e['id'] ?>" tabindex="-1">
          <div class="modal-dialog">
          <div class="modal-content">
             <form method="post">
              <input type="hidden" name="accion" value="editar">
              <input type="hidden" name="id" value="<?= $e['id'] ?>">
              <div class="modal-header">
                <h5 class="modal-title">Editar Evaluación Especial</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <label>Usuario:</label>
                <select name="user_id" class="form-select" required>
                  <?php foreach ($usuarios as $id => $nombre): ?>
                    <option value="<?= $id ?>" <?= $id == $e['user_id'] ? 'selected' : '' ?>><?= $nombre ?></option>
                  <?php endforeach; ?>
                </select>
                <label class="mt-2">Periodo:</label>
                <select name="periodo" class="form-select" required>
                <?php foreach ($periodos as $per): ?>
                <option value="<?= $per['anio'] ?>" <?= ($periodo == $per['anio']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($per['anio']) ?>
                </option>
                <?php endforeach; ?>
                </select>
                <label class="mt-2">Sección:</label>
                <select name="seccion" class="form-select" required>
                <option value="1">Todas</option>
                </select>
              </div>
              <div class="modal-footer">
                <button type="submit" class="btn btn-sm btn-primary">Guardar cambios</button>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              </div>
            </form>
            </div>
          </div>
        </div>

        <!-- Modal Eliminar -->
        <div class="modal fade" id="modalEliminar<?= $e['id'] ?>" tabindex="-1">
          <div class="modal-dialog">
          <div  class="modal-content">
          <form method="post">
              <input type="hidden" name="accion" value="eliminar">
              <input type="hidden" name="id" value="<?= $e['id'] ?>">
              <div class="modal-header">
                <h5 class="modal-title">Eliminar Evaluación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                ¿Deseas eliminar la evaluación especial de <strong><?= $e['nombre'] . ' ' . $e['apellido_paterno'] . ' ' . $e['apellido_materno']  ?></strong>?
              </div>
              <div class="modal-footer">
                <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              </div>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Modal Agregar -->
<div class="modal fade" id="modalAgregar" tabindex="-1">
  <div class="modal-dialog">
  <div  class="modal-content">
     <form method="post">
      <input type="hidden" name="accion" value="agregar">
      <div class="modal-header">
        <h5 class="modal-title">Agregar Evaluación Especial</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label>Usuario:</label>
        <select name="user_id" class="form-select" required>
          <?php foreach ($usuarios as $id => $nombre): ?>
            <option value="<?= $id ?>"><?= $nombre ?></option>
          <?php endforeach; ?>
        </select>
        <label class="mt-2">Periodo:</label>
        <select name="periodo" class="form-select" required>
        <?php foreach ($periodos as $per): ?>
        <option value="<?= $per['anio'] ?>" <?= ($periodo == $per['anio']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($per['anio']) ?>
        </option>
        <?php endforeach; ?>
        </select>
        <label class="mt-2">Sección:</label>
        <select name="seccion" class="form-select" required>
         <option value="1">Todas</option>
        </select>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-sm btn-success">Guardar</button>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </form>
  </div>
  </div>
  </div>

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

