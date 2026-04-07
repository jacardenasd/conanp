<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';
require 'includes/cedulas_firmadas.php';
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

$datosUsuario = verificarDatosUsuario($pdo, $user_id);

asegurar_tabla_cedulas_firmadas($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar'])) {
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT nombre_archivo FROM cedulas_firmadas WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $archivo = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($archivo) {
            $pdo->prepare("DELETE FROM cedulas_firmadas WHERE id = ?")->execute([$id]);

            $rutaArchivo = __DIR__ . '/cedulas_firmadas/' . $archivo['nombre_archivo'];
            if (is_file($rutaArchivo)) {
                @unlink($rutaArchivo);
            }

            header('Location: admin_cedulas_firmadas.php?info=eliminado');
            exit;
        }
    }

    header('Location: admin_cedulas_firmadas.php?info=no_encontrado');
    exit;
}

$periodosCedulas = $pdo->query("SELECT anio
                                FROM periodos
                                ORDER BY anio DESC")->fetchAll(PDO::FETCH_COLUMN);

$filtroPeriodo = isset($_GET['periodo']) && $_GET['periodo'] !== '' ? (int)$_GET['periodo'] : 0;
$filtroUnidad = isset($_GET['unidad_id']) && $_GET['unidad_id'] !== '' ? (int)$_GET['unidad_id'] : 0;
$filtroUsuario = isset($_GET['usuario_id']) && $_GET['usuario_id'] !== '' ? (int)$_GET['usuario_id'] : 0;

if ($filtroPeriodo > 0 && !in_array($filtroPeriodo, array_map('intval', $periodosCedulas), true)) {
    $filtroPeriodo = 0;
}

$sqlUnidades = "SELECT DISTINCT un.id, un.nombre
                FROM cedulas_firmadas cf
                INNER JOIN usuarios u ON u.user_id = cf.user_id
                INNER JOIN unidades un ON un.id = u.unidad_id
                WHERE 1=1";
$paramsUnidades = [];

if ($filtroPeriodo > 0) {
    $sqlUnidades .= " AND cf.periodo = ?";
    $paramsUnidades[] = $filtroPeriodo;
}

$sqlUnidades .= " ORDER BY un.nombre";
$stmtUnidades = $pdo->prepare($sqlUnidades);
$stmtUnidades->execute($paramsUnidades);
$unidades = $stmtUnidades->fetchAll(PDO::FETCH_ASSOC);

if ($filtroUnidad > 0 && !in_array($filtroUnidad, array_map('intval', array_column($unidades, 'id')), true)) {
    $filtroUnidad = 0;
}

$sqlUsuarios = "SELECT DISTINCT u.user_id,
                       CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS nombre
                FROM cedulas_firmadas cf
                INNER JOIN usuarios u ON u.user_id = cf.user_id
                WHERE 1=1";
$paramsUsuarios = [];

if ($filtroPeriodo > 0) {
    $sqlUsuarios .= " AND cf.periodo = ?";
    $paramsUsuarios[] = $filtroPeriodo;
}
if ($filtroUnidad > 0) {
    $sqlUsuarios .= " AND u.unidad_id = ?";
    $paramsUsuarios[] = $filtroUnidad;
}

$sqlUsuarios .= " ORDER BY nombre";
$stmtUsuarios = $pdo->prepare($sqlUsuarios);
$stmtUsuarios->execute($paramsUsuarios);
$usuarios = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);

if ($filtroUsuario > 0 && !in_array($filtroUsuario, array_map('intval', array_column($usuarios, 'user_id')), true)) {
    $filtroUsuario = 0;
}

$sql = "SELECT cf.id, cf.user_id, cf.periodo, cf.nombre_archivo, cf.fecha_subida,
               u.username,
               CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS nombre_completo,
               un.nombre AS unidad
        FROM cedulas_firmadas cf
        INNER JOIN usuarios u ON u.user_id = cf.user_id
        LEFT JOIN unidades un ON un.id = u.unidad_id
        WHERE 1=1";
$params = [];

if ($filtroPeriodo > 0) {
    $sql .= " AND cf.periodo = ?";
    $params[] = $filtroPeriodo;
}

if ($filtroUnidad > 0) {
    $sql .= " AND u.unidad_id = ?";
    $params[] = $filtroUnidad;
}

if ($filtroUsuario > 0) {
    $sql .= " AND cf.user_id = ?";
    $params[] = $filtroUsuario;
}

$sql .= " ORDER BY cf.fecha_subida DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cedulas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo htmlspecialchars($nombre_sistema); ?></title>

    <link href="assets/fonts/inter/inter.css" rel="stylesheet" type="text/css">
    <link href="assets/icons/phosphor/styles.min.css" rel="stylesheet" type="text/css">
    <link href="assets/css/ltr/all.min.css" id="stylesheet" rel="stylesheet" type="text/css">

    <script src="assets/demo/demo_configurator.js"></script>
    <script src="assets/js/bootstrap/bootstrap.bundle.min.js"></script>
    <link href="assets/icons/icomoon/styles.min.css" rel="stylesheet" type="text/css">

    <script src="assets/js/jquery/jquery.min.js"></script>
    <script src="assets/js/vendor/tables/datatables/datatables.min.js"></script>
    <script src="assets/js/vendor/forms/selects/select2.min.js"></script>

    <script src="assets/js/app.js"></script>
    <script src="assets/demo/pages/datatables_basic.js"></script>
    <script src="assets/demo/pages/form_select2.js"></script>
</head>
<body>
<?php require_once('assets/main_navbar.php'); ?>
<div class="page-content">
    <?php require_once('assets/main_navigation.php'); ?>
    <div class="content-wrapper">
        <div class="content-inner">
            <div class="page-header page-header-light shadow">
                <div class="page-header-content d-lg-flex border-top">
                    <div class="d-flex">
                        <div class="breadcrumb py-2">
                            <a href="index.php" class="breadcrumb-item"><i class="ph-house"></i></a>
                            <a href="#" class="breadcrumb-item">Administración</a>
                            <span class="breadcrumb-item active">Cédulas Finales</span>
                        </div>
                        <a href="#breadcrumb_elements" class="btn btn-sm btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
                            <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="content">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Revisión de cédulas de evaluación final firmadas</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_GET['info']) && $_GET['info'] === 'eliminado'): ?>
                            <div class="alert alert-success">La cédula fue eliminada correctamente.</div>
                        <?php elseif (isset($_GET['info']) && $_GET['info'] === 'no_encontrado'): ?>
                            <div class="alert alert-warning">No se encontró el registro solicitado.</div>
                        <?php endif; ?>

                        <form method="get" class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Periodo</label>
                                <select name="periodo" class="form-select">
                                    <option value="">Todos</option>
                                    <?php foreach ($periodosCedulas as $anio): ?>
                                        <option value="<?= (int)$anio ?>" <?= ($filtroPeriodo === (int)$anio) ? 'selected' : '' ?>><?= (int)$anio ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Unidad Administrativa</label>
                                <select name="unidad_id" class="form-select select">
                                    <option value="">Todas</option>
                                    <?php foreach ($unidades as $unidad): ?>
                                        <option value="<?= (int)$unidad['id'] ?>" <?= ($filtroUnidad === (int)$unidad['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($unidad['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Usuario</label>
                                <select name="usuario_id" class="form-select select">
                                    <option value="">Todos</option>
                                    <?php foreach ($usuarios as $usuario): ?>
                                        <option value="<?= (int)$usuario['user_id'] ?>" <?= ($filtroUsuario === (int)$usuario['user_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($usuario['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-striped table-bordered datatable-basic">
                                <thead>
                                    <tr>
                                        <th>Periodo</th>
                                        <th>Usuario</th>
                                        <th>Username</th>
                                        <th>Unidad</th>
                                        <th>Fecha de carga</th>
                                        <th>Archivo</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cedulas as $cedula): ?>
                                        <tr>
                                            <td><?= (int)$cedula['periodo'] ?></td>
                                            <td><?= htmlspecialchars($cedula['nombre_completo']) ?></td>
                                            <td><?= htmlspecialchars($cedula['username']) ?></td>
                                            <td><?= htmlspecialchars($cedula['unidad'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($cedula['fecha_subida']))) ?></td>
                                            <td>
                                                <?php
                                                    $rutaArchivo = __DIR__ . '/cedulas_firmadas/' . $cedula['nombre_archivo'];
                                                    $archivoDisponible = is_file($rutaArchivo);
                                                ?>
                                                <?php if ($archivoDisponible): ?>
                                                    <a class="btn btn-sm btn-warning" href="cedulas_firmadas/<?= rawurlencode($cedula['nombre_archivo']) ?>" target="_blank">
                                                        <i class="ph-eye me-1"></i>Ver PDF
                                                    </a>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Archivo no disponible</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="post" onsubmit="return confirm('¿Deseas eliminar esta cédula final?');">
                                                    <input type="hidden" name="id" value="<?= (int)$cedula['id'] ?>">
                                                    <button type="submit" name="eliminar" value="1" class="btn btn-sm btn-danger">
                                                        <i class="ph-trash me-1"></i>Eliminar
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <?php require_once('assets/footer.php'); ?>
        </div>
    </div>
</div>
</body>
</html>
