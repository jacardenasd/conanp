<?php
require 'config/db.php';
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

if ((int)($_SESSION['role'] ?? 0) !== 3) {
    echo "<div class='alert alert-danger'>Acceso restringido.</div>";
    exit;
}

$estatus_opciones = ['Captura', 'Evaluación', 'Cerrado'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['nuevo_periodo'])) {
        $anio = isset($_POST['anio']) ? (int) $_POST['anio'] : 0;
        $estatus = $_POST['estatus'] ?? '';

        if ($anio >= 2000 && $anio <= 2100 && in_array($estatus, $estatus_opciones, true)) {
            $stmt = $pdo->prepare("INSERT INTO periodos (anio, estatus) VALUES (?, ?)");
            $stmt->execute([$anio, $estatus]);
            header('Location: admin_periodos.php?mensaje=agregado');
            exit;
        }
    }

    if (isset($_POST['actualizar_estatus'])) {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $estatus = $_POST['estatus'] ?? '';

        if ($id > 0 && in_array($estatus, $estatus_opciones, true)) {
            $stmt = $pdo->prepare("UPDATE periodos SET estatus = ? WHERE id = ?");
            $stmt->execute([$estatus, $id]);
            header('Location: admin_periodos.php?mensaje=actualizado');
            exit;
        }
    }

    if (isset($_POST['eliminar_periodo'])) {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM periodos WHERE id = ?");
            $stmt->execute([$id]);
            header('Location: admin_periodos.php?mensaje=eliminado');
            exit;
        }
    }
}

$filtro_estatus = $_GET['estatus'] ?? '';
$busqueda = trim($_GET['q'] ?? '');

$where = [];
$params = [];

if ($filtro_estatus !== '' && in_array($filtro_estatus, $estatus_opciones, true)) {
    $where[] = 'estatus = :estatus';
    $params[':estatus'] = $filtro_estatus;
}

if ($busqueda !== '') {
    $where[] = 'CAST(anio AS CHAR) LIKE :q';
    $params[':q'] = "%{$busqueda}%";
}

$whereSql = count($where) > 0 ? (' WHERE ' . implode(' AND ', $where)) : '';

$stats = $pdo->query("SELECT COUNT(*) AS total, SUM(CASE WHEN estatus = 'Captura' THEN 1 ELSE 0 END) AS captura, SUM(CASE WHEN estatus = 'Evaluación' THEN 1 ELSE 0 END) AS evaluacion, SUM(CASE WHEN estatus = 'Cerrado' THEN 1 ELSE 0 END) AS cerrado FROM periodos")->fetch(PDO::FETCH_ASSOC);

$rowsPerPage = 25;
$paginaActual = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM periodos{$whereSql}");
$countStmt->execute($params);
$totalPeriodos = (int) $countStmt->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalPeriodos / $rowsPerPage));

if ($paginaActual > $totalPaginas) {
    $paginaActual = $totalPaginas;
}

$offset = ($paginaActual - 1) * $rowsPerPage;

$stmt = $pdo->prepare("SELECT id, anio, estatus FROM periodos{$whereSql} ORDER BY anio DESC LIMIT {$rowsPerPage} OFFSET {$offset}");
$stmt->execute($params);
$periodos = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <script src="assets/js/vendor/notifications/bootbox.min.js"></script>

    <script src="assets/js/app.js"></script>
    <script src="assets/demo/pages/components_modals.js"></script>
    <script src="assets/demo/pages/components_buttons.js"></script>
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
                            <span class="breadcrumb-item active">Periodos</span>
                        </div>
                        <a href="#breadcrumb_elements" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
                            <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="content">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <h2 class="mb-0">Administrar Periodos</h2>
                </div>

                <?php if (isset($_GET['mensaje'])): ?>
                    <div class="alert alert-success">Cambios guardados correctamente.</div>
                <?php endif; ?>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="card card-body bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>Total</div>
                                <h4 class="mb-0"><?= (int) ($stats['total'] ?? 0) ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-body bg-info text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>Captura</div>
                                <h4 class="mb-0"><?= (int) ($stats['captura'] ?? 0) ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-body bg-warning text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>Evaluación</div>
                                <h4 class="mb-0"><?= (int) ($stats['evaluacion'] ?? 0) ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-body bg-dark text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>Cerrado</div>
                                <h4 class="mb-0"><?= (int) ($stats['cerrado'] ?? 0) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">Nuevo periodo</h5></div>
                    <div class="card-body">
                        <form method="post" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label" for="anio">Año</label>
                                <input type="number" id="anio" name="anio" class="form-control" required min="2000" max="2100">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="estatus_nuevo">Estatus</label>
                                <select id="estatus_nuevo" name="estatus" class="form-select" required>
                                    <?php foreach ($estatus_opciones as $op): ?>
                                    <option value="<?= htmlspecialchars($op) ?>"><?= htmlspecialchars($op) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button class="btn btn-primary w-100" name="nuevo_periodo">Agregar periodo</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <form method="get" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label for="estatus" class="form-label">Estatus</label>
                                <select id="estatus" name="estatus" class="form-select">
                                    <option value="">Todos</option>
                                    <?php foreach ($estatus_opciones as $op): ?>
                                    <option value="<?= htmlspecialchars($op) ?>" <?= $filtro_estatus === $op ? 'selected' : '' ?>><?= htmlspecialchars($op) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="q" class="form-label">Buscar año</label>
                                <input type="text" id="q" name="q" class="form-control" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Ejemplo: 2026">
                            </div>
                            <div class="col-md-2 d-flex gap-2">
                                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                                <a href="admin_periodos.php" class="btn btn-light w-100">Limpiar</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Año</th>
                                        <th>Estatus actual</th>
                                        <th>Cambiar estatus</th>
                                        <th>Eliminar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($periodos as $p): ?>
                                    <?php $estatusActual = (string)($p['estatus'] ?? ($p['Estado'] ?? 'Captura')); ?>
                                    <tr>
                                        <td><?= (int)$p['anio'] ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($estatusActual) ?></span></td>
                                        <td>
                                            <form method="post" class="d-flex gap-2">
                                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                                <select name="estatus" class="form-select form-select-sm" style="max-width: 180px;">
                                                    <?php foreach ($estatus_opciones as $op): ?>
                                                    <option value="<?= htmlspecialchars($op) ?>" <?= $op === $estatusActual ? 'selected' : '' ?>><?= htmlspecialchars($op) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" name="actualizar_estatus" class="btn btn-sm btn-outline-primary">Actualizar</button>
                                            </form>
                                        </td>
                                        <td>
                                            <form method="post" onsubmit="return confirm('¿Seguro que deseas eliminar el periodo <?= (int)$p['anio'] ?>?');">
                                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                                <button type="submit" name="eliminar_periodo" class="btn btn-danger btn-sm">Eliminar</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>

                                    <?php if (count($periodos) === 0): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No se encontraron periodos.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalPaginas > 1): ?>
                        <?php $queryBase = ['estatus' => $filtro_estatus, 'q' => $busqueda]; ?>
                        <nav aria-label="Paginación periodos" class="mt-3">
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?= $paginaActual <= 1 ? 'disabled' : '' ?>">
                                    <?php $prevQuery = http_build_query(array_merge($queryBase, ['pagina' => max(1, $paginaActual - 1)])); ?>
                                    <a class="page-link" href="admin_periodos.php?<?= $prevQuery ?>">Anterior</a>
                                </li>
                                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                    <?php if ($i === 1 || $i === $totalPaginas || abs($i - $paginaActual) <= 2): ?>
                                        <?php $pageQuery = http_build_query(array_merge($queryBase, ['pagina' => $i])); ?>
                                        <li class="page-item <?= $i === $paginaActual ? 'active' : '' ?>"><a class="page-link" href="admin_periodos.php?<?= $pageQuery ?>"><?= $i ?></a></li>
                                    <?php elseif ($i === 2 && $paginaActual > 4): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php elseif ($i === $totalPaginas - 1 && $paginaActual < $totalPaginas - 3): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <li class="page-item <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>">
                                    <?php $nextQuery = http_build_query(array_merge($queryBase, ['pagina' => min($totalPaginas, $paginaActual + 1)])); ?>
                                    <a class="page-link" href="admin_periodos.php?<?= $nextQuery ?>">Siguiente</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php require_once('assets/footer.php'); ?>
        </div>
    </div>
</div>

</body>
</html>
