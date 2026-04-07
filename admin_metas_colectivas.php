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

$periodo = $_GET['periodo'] ?? ($_SESSION['periodo'] ?? obtener_variable('periodo_actual') ?? date('Y'));
$unidad_id = $_GET['unidad_id'] ?? '';
$q = trim($_GET['q'] ?? '');
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina = 25;
$offset = ($pagina - 1) * $por_pagina;

$periodos = $pdo->query("SELECT DISTINCT anio FROM periodos ORDER BY anio DESC")->fetchAll();
$unidades = $pdo->query("SELECT id, nombre FROM unidades ORDER BY nombre ASC")->fetchAll();

$sql = "SELECT 
            u.id, u.nombre,
            COUNT(mc.id) as total_metas,
            SUM(CASE WHEN mc.resultado > 0 THEN 1 ELSE 0 END) as metas_evaluadas,
            COALESCE(SUM(mc.ponderacion), 0) as suma_ponderacion,
            CASE WHEN COUNT(mc.id) > 0 THEN COALESCE(MAX(cc.estatus), 0) ELSE 0 END as estatus,
            CASE WHEN COUNT(mc.id) > 0 THEN MAX(cc.resultado) ELSE NULL END as resultado,
            ROUND(COALESCE(SUM(CASE WHEN mc.resultado > 0 THEN (mc.resultado * mc.ponderacion / 100) ELSE 0 END), 0), 2) as calificacion_calculada
        FROM unidades u
        LEFT JOIN metas_colectivas mc ON mc.unidad_id = u.id AND mc.periodo = ?
        LEFT JOIN calificaciones_colectivas cc ON cc.unidad_id = u.id AND cc.periodo = ?
        WHERE 1";

$params = [$periodo, $periodo];

if (!empty($unidad_id)) {
    $sql .= " AND u.id = ?";
    $params[] = $unidad_id;
}
if ($q !== '') {
    $sql .= " AND u.nombre LIKE ?";
    $params[] = "%{$q}%";
}

$sql .= " GROUP BY u.id ORDER BY u.nombre LIMIT ? OFFSET ?";
$params[] = $por_pagina;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
foreach ($params as $idx => $value) {
    $stmt->bindValue($idx + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$unidades_data = $stmt->fetchAll();

$sql_total = "SELECT COUNT(*) FROM unidades u WHERE 1";
$params_total = [];
if (!empty($unidad_id)) {
    $sql_total .= " AND u.id = ?";
    $params_total[] = $unidad_id;
}
if ($q !== '') {
    $sql_total .= " AND u.nombre LIKE ?";
    $params_total[] = "%{$q}%";
}
$stmt_total = $pdo->prepare($sql_total);
$stmt_total->execute($params_total);
$total_registros = (int)$stmt_total->fetchColumn();
$total_paginas = max(1, (int)ceil($total_registros / $por_pagina));

$query_base = $_GET;
unset($query_base['pagina']);
$query_base = http_build_query($query_base);
$query_base = $query_base !== '' ? $query_base . '&' : '';
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
    <script src="assets/demo/pages/datatables_basic.js"></script>
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
                                <span class="breadcrumb-item active">Metas Colectivas</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="content">

                    <?php if (isset($_GET['info'])): ?>
                        <?php
                        $clase_alerta = 'alert-success';
                        $texto = 'Acción realizada correctamente.';

                        switch ($_GET['info']) {
                            case '1':
                                $texto = '✅ Estatus actualizado correctamente.';
                                $clase_alerta = 'alert-success';
                                break;
                            case '2':
                                $texto = '✏️ Meta actualizada.';
                                $clase_alerta = 'alert-info';
                                break;
                            case '3':
                                $texto = '🗑️ Meta eliminada.';
                                $clase_alerta = 'alert-danger';
                                break;
                        }
                        ?>
                        <div class="alert <?= $clase_alerta ?> border-0 alert-dismissible fade show">
                            <?= $texto ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Metas Colectivas</h5>
                        </div>

                        <div class="card-body">
                            <p class="text-muted">Administra metas colectivas por unidad y periodo. Cambiar el estatus a cerrado actualizará el estatus para que los usuarios puedan descargar su cédula de evaluación.</p>

                            <form class="row g-3 mb-4" method="get">
                                <div class="col-md-4">
                                    <label for="periodo" class="form-label">Período</label>
                                    <select name="periodo" id="periodo" class="form-select">
                                        <?php foreach ($periodos as $p): ?>
                                            <option value="<?= $p['anio'] ?>" <?= ((int)$periodo === (int)$p['anio']) ? 'selected' : '' ?>>
                                                <?= $p['anio'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="unidad_id" class="form-label">Unidad</label>
                                    <select name="unidad_id" id="unidad_id" class="form-select">
                                        <option value="">Todas las unidades</option>
                                        <?php foreach ($unidades as $u): ?>
                                            <option value="<?= $u['id'] ?>" <?= ((string)$unidad_id === (string)$u['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($u['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="q" class="form-label">Buscar</label>
                                    <input type="text" id="q" name="q" class="form-control" value="<?= htmlspecialchars($q) ?>" placeholder="Nombre de unidad">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">Filtrar</button>
                                    <a href="admin_metas_colectivas.php" class="btn btn-secondary">Limpiar</a>
                                </div>
                            </form>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted">Mostrando <?= count($unidades_data) ?> de <?= $total_registros ?> unidades</span>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Unidad</th>
                                            <th>Total Metas</th>
                                            <th>Evaluadas</th>
                                            <th>Suma Ponderación</th>
                                            <th>Estatus</th>
                                            <th>Calificación</th>
                                            <th>Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($unidades_data as $unidad): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($unidad['nombre']) ?></td>
                                                <td><?= (int)($unidad['total_metas'] ?? 0) ?></td>
                                                <td><?= (int)($unidad['metas_evaluadas'] ?? 0) ?></td>
                                                <td>
                                                    <?php if ((float)$unidad['suma_ponderacion'] > 0): ?>
                                                        <span class="<?= ((float)$unidad['suma_ponderacion'] == 100.0) ? 'badge bg-success' : 'badge bg-warning' ?>">
                                                            <?= number_format((float)$unidad['suma_ponderacion'], 2) ?>%
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $estatus = (int)($unidad['estatus'] ?? 0);
                                                    if ($estatus === 0) {
                                                        echo '<span class="badge bg-secondary">Sin Captura</span>';
                                                    } elseif ($estatus === 1) {
                                                        echo '<span class="badge bg-info">Capturadas</span>';
                                                    } elseif ($estatus === 2) {
                                                        echo '<span class="badge bg-success">Cerrado</span>';
                                                    } else {
                                                        echo '<span class="badge bg-dark">Desconocido</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if ($unidad['resultado'] !== null): ?>
                                                        <?= number_format((float)$unidad['resultado'], 2) ?>
                                                    <?php else: ?>
                                                        —
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="admin_metas_colectivas_detalle.php?unidad_id=<?= (int)$unidad['id'] ?>&periodo=<?= (int)$periodo ?>" class="btn btn-sm btn-primary">Editar metas</a>
                                                    <button
                                                        class="btn btn-sm btn-info"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#modalEstatus"
                                                        data-unidad="<?= (int)$unidad['id'] ?>"
                                                        data-periodo="<?= (int)$periodo ?>"
                                                        data-estatus="<?= (int)$estatus ?>"
                                                        data-unidad-nombre="<?= htmlspecialchars($unidad['nombre'], ENT_QUOTES) ?>">
                                                        Cambiar estatus
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <?php if ($total_paginas > 1): ?>
                    <nav aria-label="Paginación de metas colectivas" class="mt-3">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="admin_metas_colectivas.php?<?= $query_base ?>pagina=<?= max(1, $pagina - 1) ?>">Anterior</a>
                            </li>
                            <?php for ($i = max(1, $pagina - 2); $i <= min($total_paginas, $pagina + 2); $i++): ?>
                                <li class="page-item <?= ($i === $pagina) ? 'active' : '' ?>">
                                    <a class="page-link" href="admin_metas_colectivas.php?<?= $query_base ?>pagina=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($pagina >= $total_paginas) ? 'disabled' : '' ?>">
                                <a class="page-link" href="admin_metas_colectivas.php?<?= $query_base ?>pagina=<?= min($total_paginas, $pagina + 1) ?>">Siguiente</a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>

                </div>

                <?php require_once('assets/footer.php'); ?>

            </div>
        </div>

    </div>

    <div class="modal fade" id="modalEstatus" tabindex="-1" aria-labelledby="modalEstatusLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="post" action="admin_metas_colectivas_estatus.php">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalEstatusLabel">Actualizar estatus</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="unidad_id" id="modalUnidad">
                        <input type="hidden" name="periodo" id="modalPeriodo">

                        <p class="mb-2">Unidad: <strong id="modalUnidadNombre"></strong></p>

                        <div class="mb-3">
                            <label for="modalEstatusSelect" class="form-label">Nuevo estatus</label>
                            <select name="estatus" id="modalEstatusSelect" class="form-select" required>
                                <option value="0">Sin captura</option>
                                <option value="1">Capturadas</option>
                                <option value="2">Cerrado</option>
                            </select>
                        </div>

                        <div class="alert alert-info small d-none" id="modalAvisoCierre">
                            <i class="ph-info"></i> Esto actualizará el estatus para que los usuarios puedan descargar su cédula de evaluación.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('modalEstatus');
        var selectEstatus = document.getElementById('modalEstatusSelect');
        var avisoCierre = document.getElementById('modalAvisoCierre');

        function toggleAviso() {
            if (selectEstatus.value === '2') {
                avisoCierre.classList.remove('d-none');
            } else {
                avisoCierre.classList.add('d-none');
            }
        }

        modal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var unidad = button.getAttribute('data-unidad');
            var periodo = button.getAttribute('data-periodo');
            var estatus = button.getAttribute('data-estatus');
            var unidadNombre = button.getAttribute('data-unidad-nombre');

            document.getElementById('modalUnidad').value = unidad;
            document.getElementById('modalPeriodo').value = periodo;
            document.getElementById('modalUnidadNombre').textContent = unidadNombre || '-';
            if (estatus === '2') {
                selectEstatus.value = '2';
            } else if (estatus === '1') {
                selectEstatus.value = '1';
            } else {
                selectEstatus.value = '0';
            }
            toggleAviso();
        });

        selectEstatus.addEventListener('change', toggleAviso);
    });
    </script>

</body>
</html>
