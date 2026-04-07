<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';

$ruta_log_accesos = __DIR__ . '/includes/log_accesos.php';
if (is_file($ruta_log_accesos)) {
    require_once $ruta_log_accesos;
}

if (!function_exists('inicializar_tabla_log_accesos')) {
    function inicializar_tabla_log_accesos(PDO $pdo)
    {
        return;
    }
}

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

inicializar_tabla_log_accesos($pdo);

$evento = $_GET['evento'] ?? '';
$resultado = $_GET['resultado'] ?? '';
$username = strtoupper(trim($_GET['username'] ?? ''));
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';

$where = [];
$params = [];

if ($evento !== '') {
    $where[] = 'l.evento = ?';
    $params[] = $evento;
}

if ($resultado !== '') {
    $where[] = 'l.resultado = ?';
    $params[] = $resultado;
}

if ($username !== '') {
    $where[] = 'l.username LIKE ?';
    $params[] = '%' . $username . '%';
}

if ($fecha_inicio !== '') {
    $where[] = 'DATE(l.fecha) >= ?';
    $params[] = $fecha_inicio;
}

if ($fecha_fin !== '') {
    $where[] = 'DATE(l.fecha) <= ?';
    $params[] = $fecha_fin;
}

$logs = [];

try {
    $sql = "SELECT
                l.id,
                l.user_id,
                l.username,
                l.evento,
                l.resultado,
                l.detalle,
                l.ip,
                l.fecha,
                CONCAT_WS(' ', u.nombre, u.apellido_paterno, u.apellido_materno) AS nombre_completo
            FROM log_accesos_usuarios l
            LEFT JOIN usuarios u ON u.user_id = l.user_id";

    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY l.fecha DESC LIMIT 1000';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $whereAud = ["a.tabla = 'log_accesos_usuarios'"];
    $paramsAud = [];

    if ($evento !== '') {
        $whereAud[] = 'a.accion = ?';
        $paramsAud[] = strtoupper($evento);
    }

    if ($username !== '') {
        $whereAud[] = 'a.descripcion LIKE ?';
        $paramsAud[] = '%usuario: ' . $username . '%';
    }

    if ($fecha_inicio !== '') {
        $whereAud[] = 'DATE(a.fecha) >= ?';
        $paramsAud[] = $fecha_inicio;
    }

    if ($fecha_fin !== '') {
        $whereAud[] = 'DATE(a.fecha) <= ?';
        $paramsAud[] = $fecha_fin;
    }

    $sqlAud = "SELECT
                    a.user_id,
                    '' AS username,
                    LOWER(a.accion) AS evento,
                    'OK' AS resultado,
                    a.descripcion AS detalle,
                    '' AS ip,
                    a.fecha,
                    CONCAT_WS(' ', u.nombre, u.apellido_paterno, u.apellido_materno) AS nombre_completo
                FROM auditorias a
                LEFT JOIN usuarios u ON u.user_id = a.user_id
                WHERE " . implode(' AND ', $whereAud) . "
                ORDER BY a.fecha DESC
                LIMIT 1000";

    $stmtAud = $pdo->prepare($sqlAud);
    $stmtAud->execute($paramsAud);
    $logs = $stmtAud->fetchAll(PDO::FETCH_ASSOC);
}

$eventos_catalogo = [
    'login_exitoso' => 'Login exitoso',
    'login_fallido' => 'Login fallido',
    'login_inactivo' => 'Cuenta inactiva',
    'logout' => 'Logout'
];

$resultados_catalogo = [
    'OK' => 'OK',
    'DENEGADO' => 'DENEGADO'
];
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
                            <span class="breadcrumb-item active">Log de accesos</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Log de accesos de usuarios</h5>
                    </div>

                    <div class="card-body">
                        <form method="get" class="row g-3 mb-3">
                            <div class="col-md-2">
                                <label class="form-label">Evento</label>
                                <select name="evento" class="form-select">
                                    <option value="">Todos</option>
                                    <?php foreach ($eventos_catalogo as $clave => $label): ?>
                                        <option value="<?= htmlspecialchars($clave) ?>" <?= $evento === $clave ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Resultado</label>
                                <select name="resultado" class="form-select">
                                    <option value="">Todos</option>
                                    <?php foreach ($resultados_catalogo as $clave => $label): ?>
                                        <option value="<?= htmlspecialchars($clave) ?>" <?= $resultado === $clave ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Usuario (RFC)</label>
                                <input type="text" name="username" class="form-control" maxlength="20" value="<?= htmlspecialchars($username) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Fecha inicio</label>
                                <input type="date" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($fecha_inicio) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Fecha fin</label>
                                <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($fecha_fin) ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary">Filtrar</button>
                                <a href="admin_log_accesos.php" class="btn btn-light">Limpiar</a>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-striped table-bordered datatable-basic">
                                <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Usuario</th>
                                    <th>Nombre</th>
                                    <th>Evento</th>
                                    <th>Resultado</th>
                                    <th>IP</th>
                                    <th>Detalle</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($log['fecha']) ?></td>
                                        <td><?= htmlspecialchars($log['username'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($log['nombre_completo'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($eventos_catalogo[$log['evento']] ?? $log['evento']) ?></td>
                                        <td><?= htmlspecialchars($log['resultado']) ?></td>
                                        <td><?= htmlspecialchars($log['ip'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($log['detalle'] ?? '') ?></td>
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
