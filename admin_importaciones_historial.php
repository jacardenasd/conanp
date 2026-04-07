    <?php
/**
 * HISTORIAL DE IMPORTACIONES DE USUARIOS
 * Permite ver todas las importaciones realizadas, filtrar y eliminar lotes
 */

require 'config/db.php';
require 'includes/session.php';
checkLogin(2);  // Requiere Admin o SuperAdmin
require 'includes/variables.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');

$mensaje = '';
$tipo_alerta = '';

/**
 * ELIMINAR IMPORTACIÓN (revertir lote)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
    $importacion_id = intval($_POST['importacion_id'] ?? 0);
    
    if ($importacion_id > 0) {
        try {
            $pdo->beginTransaction();
            
            // Obtener lista de usuarios importados
            $stmt = $pdo->prepare("
                SELECT user_id FROM importaciones_usuarios_detalle 
                WHERE importacion_id = ? AND user_id IS NOT NULL AND tipo_operacion = 'nuevo'
            ");
            $stmt->execute([$importacion_id]);
            $usuarios_para_eliminar = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Eliminar usuarios que fueron creados en este lote (solo los nuevos)
            if (count($usuarios_para_eliminar) > 0) {
                $placeholders = implode(',', array_fill(0, count($usuarios_para_eliminar), '?'));
                $stmt_delete = $pdo->prepare("DELETE FROM usuarios WHERE user_id IN ($placeholders)");
                $stmt_delete->execute($usuarios_para_eliminar);
            }
            
            // Eliminar registros de auditoría
            $stmt_delete_detail = $pdo->prepare("DELETE FROM importaciones_usuarios_detalle WHERE importacion_id = ?");
            $stmt_delete_detail->execute([$importacion_id]);
            
            // Eliminar importación
            $stmt_delete_imp = $pdo->prepare("DELETE FROM importaciones_usuarios WHERE id = ?");
            $stmt_delete_imp->execute([$importacion_id]);
            
            $pdo->commit();
            
            $mensaje = "✅ Lote eliminado exitosamente. Se han revirtado los cambios.";
            $tipo_alerta = "success";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = "❌ Error al eliminar lote: " . $e->getMessage();
            $tipo_alerta = "danger";
        }
    }
}

/**
 * FILTROS
 */
$filtro_fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$filtro_fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';
$filtro_admin = isset($_GET['admin_id']) ? intval($_GET['admin_id']) : 0;
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';

$where = ['1=1'];
$params = [];

if (!empty($filtro_fecha_desde)) {
    $where[] = 'DATE(i.fecha_importacion) >= ?';
    $params[] = $filtro_fecha_desde;
}

if (!empty($filtro_fecha_hasta)) {
    $where[] = 'DATE(i.fecha_importacion) <= ?';
    $params[] = $filtro_fecha_hasta;
}

if ($filtro_admin > 0) {
    $where[] = 'i.admin_id = ?';
    $params[] = $filtro_admin;
}

if (!empty($filtro_estado) && in_array($filtro_estado, ['completado', 'pendiente', 'error'])) {
    $where[] = 'i.estado = ?';
    $params[] = $filtro_estado;
}

$where_sql = implode(' AND ', $where);

// Obtener importaciones
$sql = "
    SELECT 
        i.*,
        u.nombre as admin_nombre,
        u.apellido_paterno as admin_apellido_paterno,
        u.apellido_materno as admin_apellido_materno
    FROM importaciones_usuarios i
    LEFT JOIN usuarios u ON i.admin_id = u.user_id
    WHERE $where_sql
    ORDER BY i.fecha_importacion DESC
    LIMIT 100
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$importaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de admins para filtro
$stmt_admins = $pdo->query("
    SELECT DISTINCT u.user_id, u.nombre, u.apellido_paterno, u.apellido_materno
    FROM importaciones_usuarios i
    JOIN usuarios u ON i.admin_id = u.user_id
    ORDER BY u.nombre ASC
");
$admins = $stmt_admins->fetchAll(PDO::FETCH_ASSOC);

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
                            <a href="admin_usuarios.php" class="breadcrumb-item">Administración</a>
                            <a href="usuarios_importar.php" class="breadcrumb-item">Importación</a>
                            <span class="breadcrumb-item active">Historial</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>📋 Historial de Importaciones</h2>
                    <a href="usuarios_importar.php" class="btn btn-primary">
                        <i class="ph-plus"></i> Nueva importación
                    </a>
                </div>

                <?php if (!empty($mensaje)): ?>
                    <div class="alert alert-<?= $tipo_alerta ?> alert-dismissible fade show" role="alert">
                        <?= $mensaje ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- FILTROS -->
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="ph-funnel"></i> Filtros</h5>
                    </div>
                    <div class="card-body">
                        <form method="get" class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">Desde:</label>
                                <input type="date" class="form-control" name="fecha_desde" value="<?= htmlspecialchars($filtro_fecha_desde) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Hasta:</label>
                                <input type="date" class="form-control" name="fecha_hasta" value="<?= htmlspecialchars($filtro_fecha_hasta) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Admin:</label>
                                <select class="form-select" name="admin_id">
                                    <option value="0">Todos</option>
                                    <?php foreach ($admins as $admin): ?>
                                        <option value="<?= $admin['user_id'] ?>" <?= $filtro_admin === $admin['user_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($admin['nombre'] . ' ' . $admin['apellido_paterno']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Estado:</label>
                                <select class="form-select" name="estado">
                                    <option value="">Todos</option>
                                    <option value="completado" <?= $filtro_estado === 'completado' ? 'selected' : '' ?>>Completado</option>
                                    <option value="pendiente" <?= $filtro_estado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                    <option value="error" <?= $filtro_estado === 'error' ? 'selected' : '' ?>>Error</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="ph-magnifying-glass"></i> Buscar
                                </button>
                                <a href="admin_importaciones_historial.php" class="btn btn-sm btn-secondary">
                                    <i class="ph-arrow-clockwise"></i> Limpiar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- TABLA DE IMPORTACIONES -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Total: <?= count($importaciones) ?> importaciones</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Admin</th>
                                    <th>Archivo</th>
                                    <th>Nuevos</th>
                                    <th>Actualizados</th>
                                    <th>Errores</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($importaciones) > 0): ?>
                                    <?php foreach ($importaciones as $imp): ?>
                                    <tr>
                                        <td>
                                            <small><?= date('d/m/Y H:i', strtotime($imp['fecha_importacion'])) ?></small>
                                        </td>
                                        <td>
                                            <small>
                                                <?= htmlspecialchars($imp['admin_nombre'] ?? 'N/A') ?>
                                                <?= htmlspecialchars($imp['admin_apellido_paterno'] ?? '') ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small><code><?= htmlspecialchars(basename($imp['nombre_archivo'])) ?></code></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?= $imp['total_nuevos'] ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= $imp['total_actualizados'] ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-danger"><?= $imp['total_errores'] ?></span>
                                        </td>
                                        <td>
                                            <?php if ($imp['estado'] === 'completado'): ?>
                                                <span class="badge bg-success">✓ Completado</span>
                                            <?php elseif ($imp['estado'] === 'pendiente'): ?>
                                                <span class="badge bg-warning">⏳ Pendiente</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">✗ Error</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="#" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#detallesModal<?= $imp['id'] ?>" title="Ver detalles">
                                                    <i class="ph-eye"></i>
                                                </a>
                                                <?php if ($imp['estado'] === 'completado'): ?>
                                                    <form method="post" style="display: inline;" onsubmit="return confirm('¿Estás seguro? Se eliminarán los <?= $imp['total_nuevos'] ?> usuarios nuevos de este lote.');">
                                                        <input type="hidden" name="accion" value="eliminar">
                                                        <input type="hidden" name="importacion_id" value="<?= $imp['id'] ?>">
                                                        <button type="submit" class="btn btn-outline-danger" title="Eliminar lote">
                                                            <i class="ph-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="ph-file-x"></i> No hay importaciones registradas
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </div>
    </div>

</div>

<?php require_once('assets/footer.php'); ?>

<!-- MODALES DE DETALLES -->
<?php foreach ($importaciones as $imp): ?>
<div class="modal fade" id="detallesModal<?= $imp['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles Importación #<?= $imp['id'] ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Archivo:</strong> <?= htmlspecialchars($imp['nombre_archivo']) ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Fecha:</strong> <?= date('d/m/Y H:i:s', strtotime($imp['fecha_importacion'])) ?>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="card bg-success bg-opacity-10">
                            <div class="card-body text-center">
                                <h6 class="text-success"><?= $imp['total_nuevos'] ?></h6>
                                <small>Nuevos</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info bg-opacity-10">
                            <div class="card-body text-center">
                                <h6 class="text-info"><?= $imp['total_actualizados'] ?></h6>
                                <small>Actualizados</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger bg-opacity-10">
                            <div class="card-body text-center">
                                <h6 class="text-danger"><?= $imp['total_errores'] ?></h6>
                                <small>Errores</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-secondary bg-opacity-10">
                            <div class="card-body text-center">
                                <h6 class="text-secondary"><?= $imp['total_filas'] ?></h6>
                                <small>Total filas</small>
                            </div>
                        </div>
                    </div>
                </div>

                <hr>

                <strong>Usuarios importados:</strong>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>User ID</th>
                                <th>Username</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Tipo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->prepare("
                                SELECT id.user_id, id.username, id.nombre, id.correo, id.tipo_operacion, id.errores
                                FROM importaciones_usuarios_detalle id
                                WHERE id.importacion_id = ?
                                ORDER BY id.fila_numero ASC
                                LIMIT 50
                            ");
                            $stmt->execute([$imp['id']]);
                            $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($detalles as $detalle):
                            ?>
                            <tr>
                                <td><small><?= $detalle['user_id'] ?? 'N/A' ?></small></td>
                                <td><small><code><?= htmlspecialchars($detalle['username']) ?></code></small></td>
                                <td><small><?= htmlspecialchars($detalle['nombre'] ?? 'N/A') ?></small></td>
                                <td><small><?= htmlspecialchars($detalle['correo'] ?? 'N/A') ?></small></td>
                                <td>
                                    <?php if ($detalle['tipo_operacion'] === 'nuevo'): ?>
                                        <span class="badge bg-success">Nuevo</span>
                                    <?php elseif ($detalle['tipo_operacion'] === 'actualizado'): ?>
                                        <span class="badge bg-info">Actualizado</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Error</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($detalle['tipo_operacion'] === 'error' && !empty($detalle['errores'])): ?>
                            <tr>
                                <td colspan="5">
                                    <small class="text-danger">
                                        <strong>Errores:</strong> <?= htmlspecialchars($detalle['errores']) ?>
                                    </small>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

</body>
</html>
