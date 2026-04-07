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

$filtro_unidad = isset($_GET['unidad_id']) ? (int) $_GET['unidad_id'] : 0;
$filtro_tipo = isset($_GET['tipo_usuario']) ? (int) $_GET['tipo_usuario'] : 0;
$filtro_general = trim($_GET['filtro_general'] ?? '');

$where = [];
$params = [];

if ($filtro_unidad > 0) {
    $where[] = 'u.unidad_id = :unidad_id';
    $params[':unidad_id'] = $filtro_unidad;
}

if ($filtro_tipo > 0) {
    $where[] = 'u.tipo_usuario = :tipo_usuario';
    $params[':tipo_usuario'] = $filtro_tipo;
}

if ($filtro_general !== '') {
    $where[] = '(u.RFC LIKE :filtro OR u.CURP LIKE :filtro OR u.IDRUSP LIKE :filtro OR u.puesto_nombre LIKE :filtro OR u.puesto_codigo LIKE :filtro OR u.nombre LIKE :filtro OR u.apellido_paterno LIKE :filtro OR u.apellido_materno LIKE :filtro OR u.username LIKE :filtro)';
    $params[':filtro'] = "%{$filtro_general}%";
}

$whereSql = count($where) > 0 ? (' WHERE ' . implode(' AND ', $where)) : '';

$unidades = $pdo->query("SELECT id, nombre FROM unidades ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$tipos = $pdo->query("SELECT id, nombre FROM tipos_puesto ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

$stats = $pdo->query("SELECT COUNT(*) AS total, SUM(CASE WHEN role = 3 THEN 1 ELSE 0 END) AS superadmins, SUM(CASE WHEN role = 2 THEN 1 ELSE 0 END) AS admins FROM usuarios")->fetch(PDO::FETCH_ASSOC);

$rowsPerPage = 25;
$paginaActual = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;

$countSql = "SELECT COUNT(*) FROM usuarios u {$whereSql}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalUsuariosFiltrados = (int) $countStmt->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalUsuariosFiltrados / $rowsPerPage));

if ($paginaActual > $totalPaginas) {
    $paginaActual = $totalPaginas;
}

$offset = ($paginaActual - 1) * $rowsPerPage;

$sql = "
    SELECT
        u.*,
        tp.nombre AS tipo_usuario_nombre,
        un.nombre AS unidad_nombre,
        CONCAT(j.nombre, ' ', j.apellido_paterno, ' ', j.apellido_materno) AS jefe_nombre,
        a.nombre AS adscripcion_nombre
    FROM usuarios u
    LEFT JOIN tipos_puesto tp ON u.tipo_usuario = tp.id
    LEFT JOIN usuarios j ON u.jefe_id = j.user_id
    LEFT JOIN adscripciones a ON u.adscripcion_id = a.id
    LEFT JOIN unidades un ON u.unidad_id = un.id
    {$whereSql}
    ORDER BY u.nombre ASC, u.apellido_paterno ASC
    LIMIT {$rowsPerPage} OFFSET {$offset}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tieneValor = static function ($valor): bool {
    return isset($valor) && trim((string) $valor) !== '';
};
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
                            <span class="breadcrumb-item active">Usuarios</span>
                        </div>
                        <a href="#breadcrumb_elements" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
                            <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="content">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <h2 class="mb-0">Administrar Usuarios</h2>
                    <div class="d-flex gap-2">
                        <a href="usuarios_agregar.php" class="btn btn-success">Agregar usuario</a>
                        <a href="usuarios_importar.php" class="btn btn-danger">Importar usuarios</a>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="card card-body bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>Total usuarios</div>
                                <h4 class="mb-0"><?= (int) ($stats['total'] ?? 0) ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-body bg-info text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>Admins</div>
                                <h4 class="mb-0"><?= (int) ($stats['admins'] ?? 0) ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-body bg-dark text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>Superadmins</div>
                                <h4 class="mb-0"><?= (int) ($stats['superadmins'] ?? 0) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <form method="get" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="unidad_id" class="form-label">Unidad</label>
                                <select name="unidad_id" id="unidad_id" class="form-select">
                                    <option value="0">Todas</option>
                                    <?php foreach ($unidades as $u): ?>
                                        <option value="<?= (int)$u['id'] ?>" <?= $filtro_unidad === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="tipo_usuario" class="form-label">Tipo de usuario</label>
                                <select name="tipo_usuario" id="tipo_usuario" class="form-select">
                                    <option value="0">Todos</option>
                                    <?php foreach ($tipos as $t): ?>
                                        <option value="<?= (int)$t['id'] ?>" <?= $filtro_tipo === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="filtro_general" class="form-label">Búsqueda general</label>
                                <input type="text" id="filtro_general" name="filtro_general" class="form-control" placeholder="Nombre, RFC, CURP, IDRUSP, puesto o usuario" value="<?= htmlspecialchars($filtro_general) ?>">
                            </div>
                            <div class="col-md-2 d-flex gap-2">
                                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                                <a href="admin_usuarios.php" class="btn btn-light w-100">Limpiar</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th>Usuario</th>
                                        <th>Unidad</th>
                                        <th>Tipo</th>
                                        <th>Rol</th>
                                        <th>RFC</th>
                                        <th>IDRUSP</th>
                                        <th>Código puesto</th>
                                        <th>Candado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuarios as $u): ?>
                                    <?php
                                        $faltantes = [];
                                        if (!$tieneValor($u['RFC'] ?? null)) {
                                            $faltantes[] = 'RFC';
                                        }
                                        if (!$tieneValor($u['CURP'] ?? null)) {
                                            $faltantes[] = 'CURP';
                                        }
                                        if (!$tieneValor($u['IDRUSP'] ?? null)) {
                                            $faltantes[] = 'IDRUSP';
                                        }
                                        $datosIncompletos = count($faltantes) > 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <img src="fotos/<?= htmlspecialchars($u['foto'] ?? 'default.png') ?>" width="40" height="40" class="rounded-circle" alt="">
                                                </div>
                                                <div class="flex-fill">
                                                    <div class="fw-semibold"><?= htmlspecialchars(trim($u['nombre'] . ' ' . $u['apellido_paterno'] . ' ' . $u['apellido_materno'])) ?></div>
                                                    <div class="fs-sm text-muted"><?= htmlspecialchars($u['puesto_nombre'] ?? '') ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($u['unidad_nombre'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($u['tipo_usuario_nombre'] ?? '-') ?></td>
                                        <td>
                                            <?php if ((int)$u['role'] === 3): ?>
                                                <span class="badge bg-dark">Superadmin</span>
                                            <?php elseif ((int)$u['role'] === 2): ?>
                                                <span class="badge bg-info">Admin</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Usuario</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($u['RFC'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($u['IDRUSP'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($u['puesto_codigo'] ?? '') ?></td>
                                        <td>
                                            <?php if ($datosIncompletos): ?>
                                                <span class="badge bg-danger" title="Falta: <?= htmlspecialchars(implode(', ', $faltantes)) ?>">
                                                    <i class="ph-lock me-1"></i> Incompleto
                                                </span>
                                                <div class="mt-1">
                                                    <a href="usuarios_editar.php?user_id=<?= (int)$u['user_id'] ?>" class="link-danger fs-sm">Actualizar datos</a>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge bg-success">
                                                    <i class="ph-lock-open me-1"></i> Completo
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="d-flex gap-2">
                                            <a href="usuarios_editar.php?user_id=<?= (int)$u['user_id'] ?>" class="btn btn-sm btn-primary">Editar</a>
                                            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#confirmarEliminar<?= (int)$u['user_id'] ?>">Eliminar</button>

                                            <div class="modal fade" id="confirmarEliminar<?= (int)$u['user_id'] ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-danger text-white">
                                                            <h5 class="modal-title">Confirmar eliminación</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            ¿Deseas eliminar al usuario <strong><?= htmlspecialchars(trim($u['nombre'] . ' ' . $u['apellido_paterno'])) ?></strong>?
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                            <a href="usuarios_eliminar.php?user_id=<?= (int)$u['user_id'] ?>" class="btn btn-danger">Eliminar</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>

                                    <?php if (count($usuarios) === 0): ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">No se encontraron usuarios para los filtros seleccionados.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalPaginas > 1): ?>
                        <?php $queryBase = ['unidad_id' => $filtro_unidad, 'tipo_usuario' => $filtro_tipo, 'filtro_general' => $filtro_general]; ?>
                        <nav aria-label="Paginación usuarios" class="mt-3">
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?= $paginaActual <= 1 ? 'disabled' : '' ?>">
                                    <?php $prevQuery = http_build_query(array_merge($queryBase, ['pagina' => max(1, $paginaActual - 1)])); ?>
                                    <a class="page-link" href="admin_usuarios.php?<?= $prevQuery ?>">Anterior</a>
                                </li>
                                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                    <?php if ($i === 1 || $i === $totalPaginas || abs($i - $paginaActual) <= 2): ?>
                                        <?php $pageQuery = http_build_query(array_merge($queryBase, ['pagina' => $i])); ?>
                                        <li class="page-item <?= $i === $paginaActual ? 'active' : '' ?>"><a class="page-link" href="admin_usuarios.php?<?= $pageQuery ?>"><?= $i ?></a></li>
                                    <?php elseif ($i === 2 && $paginaActual > 4): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php elseif ($i === $totalPaginas - 1 && $paginaActual < $totalPaginas - 3): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <li class="page-item <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>">
                                    <?php $nextQuery = http_build_query(array_merge($queryBase, ['pagina' => min($totalPaginas, $paginaActual + 1)])); ?>
                                    <a class="page-link" href="admin_usuarios.php?<?= $nextQuery ?>">Siguiente</a>
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
