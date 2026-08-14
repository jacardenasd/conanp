<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin(3);

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');
$user_id = $_SESSION['user_id'];
$periodo_default = (int)($_SESSION['periodo'] ?? date('Y'));

verificarDatosUsuario($pdo, $user_id);

$periodos = $pdo->query("SELECT anio FROM periodos ORDER BY anio DESC")->fetchAll(PDO::FETCH_ASSOC);
$unidades = $pdo->query("SELECT id, nombre FROM unidades ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

$periodo = (int)($_GET['periodo'] ?? $periodo_default);
$unidad_id = trim((string)($_GET['unidad_id'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina = 30;
$offset = ($pagina - 1) * $por_pagina;

$sort = trim((string)($_GET['sort'] ?? 'persona'));
$dir = strtolower(trim((string)($_GET['dir'] ?? 'asc')));

$sort_map = [
    'persona' => "u.nombre",
    'unidad' => "un.nombre",
    'metas_individuales' => "metas_individuales",
    'metas_colectivas' => "metas_colectivas",
    'autogerenciales' => "autogerenciales",
    'capacitacion' => "capacitacion",
    'aportaciones_destacadas' => "aportaciones_destacadas",
    'actividades_extraordinarias' => "actividades_extraordinarias",
];

if (!array_key_exists($sort, $sort_map)) {
    $sort = 'persona';
}

if ($dir !== 'asc' && $dir !== 'desc') {
    $dir = 'asc';
}

$order_by = $sort_map[$sort] . ' ' . strtoupper($dir);
if ($sort === 'persona') {
    $order_by .= ", u.apellido_paterno " . strtoupper($dir) . ", u.apellido_materno " . strtoupper($dir);
}

$where = " WHERE u.estatus = 1 ";
if ($unidad_id !== '') {
    $where .= " AND u.unidad_id = :unidad_id ";
}
if ($q !== '') {
    $where .= " AND (u.nombre LIKE :q OR u.apellido_paterno LIKE :q OR u.apellido_materno LIKE :q OR u.RFC LIKE :q) ";
}

$sql = "
SELECT
    u.user_id,
    u.RFC,
    u.puesto_nombre,
    u.unidad_id,
    CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS nombre_completo,
    un.nombre AS unidad,
    COALESCE(c.individuales, 0) AS metas_individuales,
    COALESCE(cc.resultado, 0) AS metas_colectivas,
    COALESCE(c.gerenciales, 0) AS autogerenciales,
    COALESCE(c.capacitacion, 0) AS capacitacion,
    COALESCE(c.aportaciones_destacadas, 0) AS aportaciones_destacadas,
    COALESCE(c.actividades_extraordinarias, 0) AS actividades_extraordinarias
FROM usuarios u
LEFT JOIN unidades un ON un.id = u.unidad_id
LEFT JOIN calificaciones c ON c.user_id = u.user_id AND c.periodo = :periodo
LEFT JOIN calificaciones_colectivas cc ON cc.unidad_id = u.unidad_id AND cc.periodo = :periodo
{$where}
ORDER BY {$order_by}
LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':periodo', $periodo, PDO::PARAM_INT);
if ($unidad_id !== '') {
    $stmt->bindValue(':unidad_id', (int)$unidad_id, PDO::PARAM_INT);
}
if ($q !== '') {
    $stmt->bindValue(':q', "%{$q}%", PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sql_total = "SELECT COUNT(*) FROM usuarios u {$where}";
$stmt_total = $pdo->prepare($sql_total);
if ($unidad_id !== '') {
    $stmt_total->bindValue(':unidad_id', (int)$unidad_id, PDO::PARAM_INT);
}
if ($q !== '') {
    $stmt_total->bindValue(':q', "%{$q}%", PDO::PARAM_STR);
}
$stmt_total->execute();
$total_registros = (int)$stmt_total->fetchColumn();
$total_paginas = max(1, (int)ceil($total_registros / $por_pagina));

$query_base = $_GET;
unset($query_base['pagina']);
$query_base = http_build_query($query_base);
$query_base = $query_base !== '' ? $query_base . '&' : '';

function sort_link(array $currentQuery, string $column, string $currentSort, string $currentDir): string {
    $nextDir = ($currentSort === $column && $currentDir === 'asc') ? 'desc' : 'asc';
    $query = $currentQuery;
    $query['sort'] = $column;
    $query['dir'] = $nextDir;
    $query['pagina'] = 1;
    return 'admin_resumen_calificaciones.php?' . http_build_query($query);
}

function sort_icon(string $column, string $currentSort, string $currentDir): string {
    if ($currentSort !== $column) {
        return '⇅';
    }
    return $currentDir === 'asc' ? '↑' : '↓';
}
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
    <script src="assets/js/app.js"></script>
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
                            <span class="breadcrumb-item active">Resumen de Calificaciones</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Resumen de Calificaciones por Persona</h5>
                    </div>

                    <div class="card-body">
                        <form method="get" class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label for="periodo" class="form-label">Periodo</label>
                                <select name="periodo" id="periodo" class="form-select">
                                    <?php foreach ($periodos as $p): ?>
                                        <option value="<?= (int)$p['anio'] ?>" <?= ((int)$p['anio'] === $periodo) ? 'selected' : '' ?>><?= (int)$p['anio'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="unidad_id" class="form-label">Unidad Administrativa</label>
                                <select name="unidad_id" id="unidad_id" class="form-select">
                                    <option value="">-- Todas --</option>
                                    <?php foreach ($unidades as $u): ?>
                                        <option value="<?= (int)$u['id'] ?>" <?= ((string)$u['id'] === $unidad_id) ? 'selected' : '' ?>><?= htmlspecialchars($u['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="q" class="form-label">Buscar persona</label>
                                <input type="text" id="q" name="q" class="form-control" value="<?= htmlspecialchars($q) ?>" placeholder="Nombre o RFC">
                            </div>
                            <div class="col-md-1 align-self-end">
                                <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                            </div>
                        </form>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted">Mostrando <?= count($registros) ?> de <?= $total_registros ?> personas</span>
                            <div class="btn-group">
                                <a href="generar_excel_resumen_calificaciones.php?<?= htmlspecialchars(http_build_query(['periodo'=>$periodo,'unidad_id'=>$unidad_id,'q'=>$q])) ?>" class="btn btn-danger btn-sm">Descargar Excel</a>
                                <?php if ($q !== '' || $unidad_id !== '' || $periodo !== $periodo_default): ?>
                                    <a href="admin_resumen_calificaciones.php" class="btn btn-light btn-sm">Limpiar filtros</a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr class="bg-primary text-white">
                                        <th><a class="text-white text-decoration-none" href="<?= htmlspecialchars(sort_link($_GET, 'persona', $sort, $dir)) ?>">Persona <?= sort_icon('persona', $sort, $dir) ?></a></th>
                                        <th><a class="text-white text-decoration-none" href="<?= htmlspecialchars(sort_link($_GET, 'unidad', $sort, $dir)) ?>">Unidad <?= sort_icon('unidad', $sort, $dir) ?></a></th>
                                        <th><a class="text-white text-decoration-none" href="<?= htmlspecialchars(sort_link($_GET, 'metas_individuales', $sort, $dir)) ?>">Metas Individuales <?= sort_icon('metas_individuales', $sort, $dir) ?></a></th>
                                        <th><a class="text-white text-decoration-none" href="<?= htmlspecialchars(sort_link($_GET, 'metas_colectivas', $sort, $dir)) ?>">Metas Colectivas <?= sort_icon('metas_colectivas', $sort, $dir) ?></a></th>
                                        <th><a class="text-white text-decoration-none" href="<?= htmlspecialchars(sort_link($_GET, 'autogerenciales', $sort, $dir)) ?>">Autogerenciales <?= sort_icon('autogerenciales', $sort, $dir) ?></a></th>
                                        <th><a class="text-white text-decoration-none" href="<?= htmlspecialchars(sort_link($_GET, 'capacitacion', $sort, $dir)) ?>">Capacitacion <?= sort_icon('capacitacion', $sort, $dir) ?></a></th>
                                        <th><a class="text-white text-decoration-none" href="<?= htmlspecialchars(sort_link($_GET, 'aportaciones_destacadas', $sort, $dir)) ?>">Aportaciones <?= sort_icon('aportaciones_destacadas', $sort, $dir) ?></a></th>
                                        <th><a class="text-white text-decoration-none" href="<?= htmlspecialchars(sort_link($_GET, 'actividades_extraordinarias', $sort, $dir)) ?>">Actividades <?= sort_icon('actividades_extraordinarias', $sort, $dir) ?></a></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($registros) === 0): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">Sin resultados para los filtros seleccionados.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($registros as $r): ?>
                                            <?php
                                            $link_metas_ind = 'admin_metas_individuales.php?' . http_build_query([
                                                'user_id' => $r['user_id'],
                                                'periodo' => $periodo,
                                            ]);
                                            $link_metas_col = 'admin_metas_colectivas.php?' . http_build_query([
                                                'unidad_id' => $r['unidad_id'],
                                                'periodo' => $periodo,
                                            ]);
                                            $link_autogerenciales = 'admin_evaluar_competencias_colaborador.php?' . http_build_query([
                                                'colaborador' => $r['user_id'],
                                            ]);
                                            $link_capacitacion = 'admin_capacitacion.php?' . http_build_query([
                                                'empleado' => $r['user_id'],
                                                'periodo' => $periodo,
                                            ]);
                                            $link_aportaciones = 'admin_aportaciones_destacadas.php?' . http_build_query([
                                                'colaborador_id' => $r['user_id'],
                                                'periodo' => $periodo,
                                            ]);
                                            $link_actividades = 'admin_actividades_extraordinarias.php?' . http_build_query([
                                                'colaborador_id' => $r['user_id'],
                                                'periodo' => $periodo,
                                            ]);
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold"><?= htmlspecialchars($r['nombre_completo']) ?></div>
                                                    <div class="text-muted fs-sm"><?= htmlspecialchars($r['RFC']) ?> | <?= htmlspecialchars($r['puesto_nombre'] ?? '-') ?></div>
                                                </td>
                                                <td><?= htmlspecialchars($r['unidad'] ?? '-') ?></td>
                                                <td>
                                                    <div><?= number_format((float)$r['metas_individuales'], 2) ?></div>
                                                    <a class="btn btn-sm btn-outline-primary mt-1" href="<?= htmlspecialchars($link_metas_ind) ?>">Ver detalle</a>
                                                </td>
                                                <td>
                                                    <div><?= number_format((float)$r['metas_colectivas'], 2) ?></div>
                                                    <a class="btn btn-sm btn-outline-primary mt-1" href="<?= htmlspecialchars($link_metas_col) ?>">Ver detalle</a>
                                                </td>
                                                <td>
                                                    <div><?= number_format((float)$r['autogerenciales'], 2) ?></div>
                                                    <a class="btn btn-sm btn-outline-primary mt-1" href="<?= htmlspecialchars($link_autogerenciales) ?>">Ver detalle</a>
                                                </td>
                                                <td>
                                                    <div><?= number_format((float)$r['capacitacion'], 2) ?></div>
                                                    <a class="btn btn-sm btn-outline-primary mt-1" href="<?= htmlspecialchars($link_capacitacion) ?>">Ver detalle</a>
                                                </td>
                                                <td>
                                                    <div><?= number_format((float)$r['aportaciones_destacadas'], 2) ?></div>
                                                    <a class="btn btn-sm btn-outline-primary mt-1" href="<?= htmlspecialchars($link_aportaciones) ?>">Ver detalle</a>
                                                </td>
                                                <td>
                                                    <div><?= number_format((float)$r['actividades_extraordinarias'], 2) ?></div>
                                                    <a class="btn btn-sm btn-outline-primary mt-1" href="<?= htmlspecialchars($link_actividades) ?>">Ver detalle</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($total_paginas > 1): ?>
                            <nav aria-label="Paginación" class="mt-3">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="admin_resumen_calificaciones.php?<?= $query_base ?>pagina=<?= max(1, $pagina - 1) ?>">Anterior</a>
                                    </li>
                                    <?php for ($i = max(1, $pagina - 2); $i <= min($total_paginas, $pagina + 2); $i++): ?>
                                        <li class="page-item <?= ($i === $pagina) ? 'active' : '' ?>">
                                            <a class="page-link" href="admin_resumen_calificaciones.php?<?= $query_base ?>pagina=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= ($pagina >= $total_paginas) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="admin_resumen_calificaciones.php?<?= $query_base ?>pagina=<?= min($total_paginas, $pagina + 1) ?>">Siguiente</a>
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
