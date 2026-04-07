<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';
require 'includes/instrumentos_pnd.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin(2);

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');

asegurar_tabla_instrumentos_pnd($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guardar'])) {
        $nombre = trim($_POST['nombre'] ?? '');
        $orden = (int)($_POST['orden'] ?? 0);
        $estatus = isset($_POST['estatus']) ? 1 : 0;

        if ($nombre !== '') {
            $stmt = $pdo->prepare("INSERT INTO instrumentos_pnd (nombre, orden, estatus) VALUES (?, ?, ?)");
            $stmt->execute([$nombre, $orden, $estatus]);
            header('Location: admin_instrumentos_pnd.php?mensaje=guardado');
            exit;
        }
    }

    if (isset($_POST['actualizar'])) {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $orden = (int)($_POST['orden'] ?? 0);
        $estatus = isset($_POST['estatus']) ? 1 : 0;

        if ($id > 0 && $nombre !== '') {
            $stmt = $pdo->prepare("UPDATE instrumentos_pnd SET nombre = ?, orden = ?, estatus = ? WHERE id = ?");
            $stmt->execute([$nombre, $orden, $estatus, $id]);
            header('Location: admin_instrumentos_pnd.php?mensaje=actualizado');
            exit;
        }
    }

    if (isset($_POST['eliminar'])) {
        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            $stmtUso = $pdo->prepare("SELECT COUNT(*) FROM metas_colectivas WHERE instrumento = ?");
            $stmtUso->execute([$id]);
            $enUso = (int)$stmtUso->fetchColumn();

            if ($enUso > 0) {
                header('Location: admin_instrumentos_pnd.php?mensaje=en_uso');
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM instrumentos_pnd WHERE id = ?");
            $stmt->execute([$id]);
            header('Location: admin_instrumentos_pnd.php?mensaje=borrado');
            exit;
        }
    }
}

$busqueda = trim($_GET['q'] ?? '');
$whereSql = '';
$params = [];

if ($busqueda !== '') {
    $whereSql = ' WHERE i.nombre LIKE ?';
    $params[] = "%{$busqueda}%";
}

$rowsPerPage = 25;
$paginaActual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM instrumentos_pnd i{$whereSql}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPaginas = max(1, (int)ceil($total / $rowsPerPage));

if ($paginaActual > $totalPaginas) {
    $paginaActual = $totalPaginas;
}

$offset = ($paginaActual - 1) * $rowsPerPage;

$listSql = "SELECT i.*,
                   (SELECT COUNT(*) FROM metas_colectivas mc WHERE mc.instrumento = i.id) AS usos
            FROM instrumentos_pnd i{$whereSql}
            ORDER BY i.orden ASC, i.nombre ASC
            LIMIT {$rowsPerPage} OFFSET {$offset}";
$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$items = $listStmt->fetchAll(PDO::FETCH_ASSOC);
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
                            <span class="breadcrumb-item active">Instrumentos</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <h2 class="mb-0">Administrar Instrumentos</h2>
                </div>

                <?php if (isset($_GET['mensaje'])): ?>
                    <div class="alert <?= $_GET['mensaje'] === 'en_uso' ? 'alert-warning' : 'alert-success' ?>">
                        <?php
                        if ($_GET['mensaje'] === 'guardado') echo 'Instrumento guardado.';
                        if ($_GET['mensaje'] === 'actualizado') echo 'Instrumento actualizado.';
                        if ($_GET['mensaje'] === 'borrado') echo 'Instrumento eliminado.';
                        if ($_GET['mensaje'] === 'en_uso') echo 'No se puede eliminar: el instrumento está en uso en metas colectivas.';
                        ?>
                    </div>
                <?php endif; ?>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="card card-body bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>Total instrumentos</div>
                                <h4 class="mb-0"><?= $total ?></h4>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">Nuevo instrumento</h5></div>
                    <div class="card-body">
                        <form method="post" class="row g-3 align-items-end">
                            <div class="col-md-7">
                                <label for="nombre_nuevo" class="form-label">Nombre</label>
                                <input type="text" id="nombre_nuevo" name="nombre" class="form-control" placeholder="Ejemplo: Desarrollo sustentable." required>
                            </div>
                            <div class="col-md-2">
                                <label for="orden_nuevo" class="form-label">Orden</label>
                                <input type="number" id="orden_nuevo" name="orden" class="form-control" value="0" min="0" required>
                            </div>
                            <div class="col-md-1">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="estatus_nuevo" name="estatus" checked>
                                    <label class="form-check-label" for="estatus_nuevo">Activo</label>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <button name="guardar" class="btn btn-success w-100">Agregar</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <form method="get" class="row g-3 align-items-end">
                            <div class="col-md-10">
                                <label for="q" class="form-label">Buscar</label>
                                <input type="text" id="q" name="q" class="form-control" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Texto del instrumento">
                            </div>
                            <div class="col-md-2 d-flex gap-2">
                                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                                <a href="admin_instrumentos_pnd.php" class="btn btn-light w-100">Limpiar</a>
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
                                        <th>#</th>
                                        <th>Instrumento</th>
                                        <th>Orden</th>
                                        <th>Estatus</th>
                                        <th>Usos</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $index => $item): ?>
                                        <tr>
                                            <td><?= ($offset + $index + 1) ?></td>
                                            <td><?= htmlspecialchars($item['nombre']) ?></td>
                                            <td><?= (int)$item['orden'] ?></td>
                                            <td>
                                                <?php if ((int)$item['estatus'] === 1): ?>
                                                    <span class="badge bg-success">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= (int)$item['usos'] ?></td>
                                            <td class="d-flex gap-2">
                                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalEditar<?= (int)$item['id'] ?>">Editar</button>
                                                <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalEliminar<?= (int)$item['id'] ?>">Eliminar</button>
                                            </td>
                                        </tr>

                                        <div class="modal fade" id="modalEditar<?= (int)$item['id'] ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <form method="post">
                                                        <div class="modal-header bg-primary text-white">
                                                            <h6 class="modal-title">Editar instrumento</h6>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label">Nombre</label>
                                                                <input type="text" name="nombre" value="<?= htmlspecialchars($item['nombre']) ?>" class="form-control" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Orden</label>
                                                                <input type="number" name="orden" value="<?= (int)$item['orden'] ?>" class="form-control" min="0" required>
                                                            </div>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" id="estatus_<?= (int)$item['id'] ?>" name="estatus" <?= (int)$item['estatus'] === 1 ? 'checked' : '' ?>>
                                                                <label class="form-check-label" for="estatus_<?= (int)$item['id'] ?>">Activo</label>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button name="actualizar" class="btn btn-primary">Guardar</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="modal fade" id="modalEliminar<?= (int)$item['id'] ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <form method="post">
                                                        <div class="modal-header bg-danger text-white">
                                                            <h6 class="modal-title">Confirmar eliminación</h6>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            ¿Eliminar el instrumento <strong><?= htmlspecialchars($item['nombre']) ?></strong>?
                                                            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button name="eliminar" class="btn btn-danger">Eliminar</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <?php if (count($items) === 0): ?>
                                        <tr><td colspan="6" class="text-center text-muted">No se encontraron instrumentos.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalPaginas > 1): ?>
                            <?php $queryBase = ['q' => $busqueda]; ?>
                            <nav aria-label="Paginación instrumentos" class="mt-3">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?= $paginaActual <= 1 ? 'disabled' : '' ?>">
                                        <?php $prevQuery = http_build_query(array_merge($queryBase, ['pagina' => max(1, $paginaActual - 1)])); ?>
                                        <a class="page-link" href="admin_instrumentos_pnd.php?<?= $prevQuery ?>">Anterior</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                        <?php if ($i === 1 || $i === $totalPaginas || abs($i - $paginaActual) <= 2): ?>
                                            <?php $pageQuery = http_build_query(array_merge($queryBase, ['pagina' => $i])); ?>
                                            <li class="page-item <?= $i === $paginaActual ? 'active' : '' ?>"><a class="page-link" href="admin_instrumentos_pnd.php?<?= $pageQuery ?>"><?= $i ?></a></li>
                                        <?php elseif ($i === 2 && $paginaActual > 4): ?>
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php elseif ($i === $totalPaginas - 1 && $paginaActual < $totalPaginas - 3): ?>
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    <li class="page-item <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>">
                                        <?php $nextQuery = http_build_query(array_merge($queryBase, ['pagina' => min($totalPaginas, $paginaActual + 1)])); ?>
                                        <a class="page-link" href="admin_instrumentos_pnd.php?<?= $nextQuery ?>">Siguiente</a>
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
