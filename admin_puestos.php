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

$filtro_nivel = isset($_GET['nivel']) ? (int) $_GET['nivel'] : 0;
$busqueda = trim($_GET['q'] ?? '');

$niveles = $pdo->query("SELECT * FROM niveles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$where = [];
$params = [];

if ($filtro_nivel > 0) {
    $where[] = 'p.nivel = :nivel';
    $params[':nivel'] = $filtro_nivel;
}

if ($busqueda !== '') {
    $where[] = '(p.puesto LIKE :q OR p.codigo_puesto LIKE :q OR p.ggn LIKE :q)';
    $params[':q'] = "%{$busqueda}%";
}

$whereSql = count($where) > 0 ? (' WHERE ' . implode(' AND ', $where)) : '';

$stats = $pdo->query("SELECT COUNT(*) AS total, COUNT(DISTINCT nivel) AS niveles_activos FROM puestos")->fetch(PDO::FETCH_ASSOC);

$rowsPerPage = 25;
$paginaActual = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM puestos p{$whereSql}");
$countStmt->execute($params);
$totalPuestos = (int) $countStmt->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalPuestos / $rowsPerPage));

if ($paginaActual > $totalPaginas) {
    $paginaActual = $totalPaginas;
}

$offset = ($paginaActual - 1) * $rowsPerPage;

$sql = "
    SELECT
        p.*,
        n.nombre AS nivel_nombre
    FROM puestos p
    LEFT JOIN niveles n ON p.nivel = n.id
    {$whereSql}
    ORDER BY p.nivel ASC, p.puesto ASC
    LIMIT {$rowsPerPage} OFFSET {$offset}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$puestos = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                            <span class="breadcrumb-item active">Puestos</span>
                        </div>
                        <a href="#breadcrumb_elements" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
                            <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="content">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <h2 class="mb-0">Administrar Puestos</h2>
                    <a href="puestos_agregar.php" class="btn btn-success">Agregar puesto</a>
                </div>

                <?php if (isset($_GET['info'])): ?>
                    <div class="alert alert-info alert-dismissible fade show">
                        <?php
                        switch ($_GET['info']) {
                            case '1': echo 'Puesto guardado correctamente.'; break;
                            case '2': echo 'Puesto actualizado correctamente.'; break;
                            case '3': echo 'Puesto eliminado correctamente.'; break;
                            default: echo 'Acción procesada.'; break;
                        }
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                <?php endif; ?>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="card card-body bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>Total puestos</div>
                                <h4 class="mb-0"><?= (int) ($stats['total'] ?? 0) ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-body bg-info text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>Niveles activos</div>
                                <h4 class="mb-0"><?= (int) ($stats['niveles_activos'] ?? 0) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <form method="get" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="nivel" class="form-label">Nivel</label>
                                <select id="nivel" name="nivel" class="form-select">
                                    <option value="0">Todos</option>
                                    <?php foreach ($niveles as $nivel): ?>
                                    <option value="<?= (int)$nivel['id'] ?>" <?= $filtro_nivel === (int)$nivel['id'] ? 'selected' : '' ?>><?= htmlspecialchars($nivel['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label for="q" class="form-label">Buscar</label>
                                <input type="text" id="q" name="q" class="form-control" placeholder="Puesto, código o grado" value="<?= htmlspecialchars($busqueda) ?>">
                            </div>
                            <div class="col-md-2 d-flex gap-2">
                                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                                <a href="admin_puestos.php" class="btn btn-light w-100">Limpiar</a>
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
                                        <th>Puesto</th>
                                        <th>Código</th>
                                        <th>Nivel</th>
                                        <th>Grado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($puestos as $p): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($p['puesto']) ?></td>
                                        <td><?= htmlspecialchars($p['codigo_puesto']) ?></td>
                                        <td><?= htmlspecialchars($p['nivel_nombre'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($p['ggn']) ?></td>
                                        <td class="d-flex gap-2">
                                            <a href="puestos_editar.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-primary">Editar</a>
                                            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#confirmarEliminar<?= (int)$p['id'] ?>">Eliminar</button>

                                            <div class="modal fade" id="confirmarEliminar<?= (int)$p['id'] ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-danger text-white">
                                                            <h5 class="modal-title">Confirmar eliminación</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            ¿Eliminar el puesto <strong><?= htmlspecialchars($p['puesto']) ?></strong>?
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                            <a href="puestos_eliminar.php?id=<?= (int)$p['id'] ?>" class="btn btn-danger">Eliminar</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>

                                    <?php if (count($puestos) === 0): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No se encontraron puestos.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalPaginas > 1): ?>
                        <?php $queryBase = ['nivel' => $filtro_nivel, 'q' => $busqueda]; ?>
                        <nav aria-label="Paginación puestos" class="mt-3">
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?= $paginaActual <= 1 ? 'disabled' : '' ?>">
                                    <?php $prevQuery = http_build_query(array_merge($queryBase, ['pagina' => max(1, $paginaActual - 1)])); ?>
                                    <a class="page-link" href="admin_puestos.php?<?= $prevQuery ?>">Anterior</a>
                                </li>
                                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                    <?php if ($i === 1 || $i === $totalPaginas || abs($i - $paginaActual) <= 2): ?>
                                        <?php $pageQuery = http_build_query(array_merge($queryBase, ['pagina' => $i])); ?>
                                        <li class="page-item <?= $i === $paginaActual ? 'active' : '' ?>"><a class="page-link" href="admin_puestos.php?<?= $pageQuery ?>"><?= $i ?></a></li>
                                    <?php elseif ($i === 2 && $paginaActual > 4): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php elseif ($i === $totalPaginas - 1 && $paginaActual < $totalPaginas - 3): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <li class="page-item <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>">
                                    <?php $nextQuery = http_build_query(array_merge($queryBase, ['pagina' => min($totalPaginas, $paginaActual + 1)])); ?>
                                    <a class="page-link" href="admin_puestos.php?<?= $nextQuery ?>">Siguiente</a>
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
