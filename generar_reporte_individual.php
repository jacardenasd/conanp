<?php
// ============================================
// DESCARGA REPORTE METAS INDIVIDUALES + FINALIZAR
// Cambio #4: Agregar botón FINALIZAR antes de descarga
// ============================================

require 'config/db.php';
require 'includes/session.php';
require 'includes/finalizaciones.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

checkLogin();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Parámetros
$user_id = $_GET['user_id'] ?? $_SESSION['user_id'];
$periodo = $_GET['periodo'] ?? $_SESSION['periodo'];
$confirmar_descarga = $_GET['confirmar_descarga'] ?? 0;

if (!$user_id || !$periodo) {
    die("Faltan parámetros.");
}

// Control de acceso: Solo el usuario o admin pueden descargar
if ($_SESSION['user_id'] != $user_id && $_SESSION['role'] < 2) {
    die("No tienes permisos para descargar este reporte.");
}

// Verificar estado de finalización
$stmt_final = $pdo->prepare("SELECT finalizado_metas FROM calificaciones WHERE user_id = ? AND periodo = ?");
$stmt_final->execute([$user_id, $periodo]);
$calificacion = $stmt_final->fetch();
$metas_finalizadas = $calificacion['finalizado_metas'] ?? 0;

// Procesar acciones POST (finalizar metas)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'finalizar_metas') {
        $stmt_check = $pdo->prepare("SELECT COUNT(*) as total FROM metas WHERE user_id = ? AND periodo = ?");
        $stmt_check->execute([$user_id, $periodo]);
        $count = $stmt_check->fetch()['total'] ?? 0;
        
        if ($count == 0) {
            $_SESSION['error'] = "❌ No tienes metas capturadas para finalizar.";
        } else {
            $stmt_exists = $pdo->prepare("SELECT COUNT(*) FROM calificaciones WHERE user_id = ? AND periodo = ?");
            $stmt_exists->execute([$user_id, $periodo]);
            $existe_calificacion = (int)$stmt_exists->fetchColumn() > 0;

            if ($existe_calificacion) {
                $stmt_upd = $pdo->prepare("
                    UPDATE calificaciones 
                    SET finalizado_metas = 1, fecha_finalizacion_metas = NOW(), usuario_finalizacion_id = ?
                    WHERE user_id = ? AND periodo = ?
                ");
                $stmt_upd->execute([$_SESSION['user_id'], $user_id, $periodo]);
            } else {
                $stmt_ins = $pdo->prepare("
                    INSERT INTO calificaciones (user_id, periodo, finalizado_metas, fecha_finalizacion_metas, usuario_finalizacion_id)
                    VALUES (?, ?, 1, NOW(), ?)
                ");
                $stmt_ins->execute([$user_id, $periodo, $_SESSION['user_id']]);
            }
            
            $_SESSION['mensaje'] = "✅ Tus metas han sido FINALIZADAS correctamente. Ya no podrás editarlas.";
            $metas_finalizadas = 1;
        }
    }
}

// Si NO hay confirmación de descarga, mostrar página con botón FINALIZAR
if (!$confirmar_descarga && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Descargar Reporte - Metas Individuales</title>
        <link href="assets/css/ltr/all.min.css" rel="stylesheet">
        <style>
            .container { max-width: 700px; margin: 50px auto; padding: 30px; background: white; border-radius: 8px; }
            .alert { padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 5px solid; }
            .alert-success { background: #d4edda; color: #155724; border-left-color: #28a745; }
            .alert-warning { background: #fff3cd; color: #856404; border-left-color: #ffc107; }
            .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
            .info-box { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .btn { display: inline-block; padding: 10px 20px; margin: 10px 5px 10px 0; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; text-decoration: none; }
            .btn-primary { background: #007bff; color: white; }
            .btn-success { background: #28a745; color: white; }
            .btn-success:disabled { background: #ccc; cursor: not-allowed; }
            .btn-secondary { background: #6c757d; color: white; }
            .status-label { display: inline-block; padding: 5px 10px; border-radius: 3px; font-weight: bold; font-size: 12px; }
            .status-finalizado { background: #dc3545; color: white; }
            .status-pendiente { background: #ffc107; color: #333; }
        </style>
    </head>
    <body style="background: #f5f5f5;">
        <div class="container">
            <h2>📊 Reporte de Metas Individuales</h2>
            
            <?php if (isset($_SESSION['mensaje'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['mensaje']); unset($_SESSION['mensaje']); ?></div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            
            <div class="info-box">
                <strong>👤 Usuario:</strong> <?php echo htmlspecialchars($usuario['nombre']); ?><br>
                <strong>📅 Período:</strong> <?php echo htmlspecialchars($periodo); ?><br>
                <strong>Estado:</strong> <span class="status-label <?php echo $metas_finalizadas ? 'status-finalizado' : 'status-pendiente'; ?>">
                    <?php echo $metas_finalizadas ? '🔒 FINALIZADO' : '✏️ EN EDICIÓN'; ?>
                </span>
            </div>
            
            <div class="alert <?php echo $metas_finalizadas ? 'alert-warning' : 'alert-success'; ?>">
                <?php if ($metas_finalizadas): ?>
                    ✅ Tus metas están finalizadas. Tu jefe puede proceder con la evaluación.
                <?php else: ?>
                    ⚠️ Tus metas están listas. Una vez finalizadas, no podrás hacer cambios.
                <?php endif; ?>
            </div>
            
            <form method="GET" style="display: inline;">
                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">
                <input type="hidden" name="periodo" value="<?php echo htmlspecialchars($periodo); ?>">
                <input type="hidden" name="confirmar_descarga" value="1">
                <button type="submit" class="btn btn-primary">📥 Descargar Reporte (Excel)</button>
            </form>
            
            <?php if (!$metas_finalizadas): ?>
            <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ ¿Estás seguro de FINALIZAR tus metas? Una vez finalizado, NO podrás editarlas.')">
                <input type="hidden" name="accion" value="finalizar_metas">
                <button type="submit" class="btn btn-success">✅ FINALIZAR METAS</button>
            </form>
            <?php else: ?>
                <button class="btn btn-success" disabled>🔒 METAS FINALIZADAS</button>
            <?php endif; ?>
            
            <p><a href="mi_evaluacion.php" class="btn btn-secondary">← Volver</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Crear carpeta si no existe
$carpeta = __DIR__ . "/reportes/individuales";
if (!file_exists($carpeta)) {
    mkdir($carpeta, 0777, true);
}

// Obtener datos del usuario
$stmt = $pdo->prepare("SELECT u.*, p.puesto AS puesto, a.nombre AS adscripcion, un.nombre AS unidad
                       FROM usuarios u
                       LEFT JOIN puestos p ON u.puesto_id = p.id
                       LEFT JOIN adscripciones a ON u.adscripcion_id = a.id
                       LEFT JOIN unidades un ON u.unidad_id = un.id
                       WHERE u.user_id = ?");
$stmt->execute([$user_id]);
$usuario = $stmt->fetch();
if (!$usuario) {
    die("Usuario no encontrado.");
}

// Obtener metas del periodo
$stmt = $pdo->prepare("SELECT * FROM metas WHERE user_id = ? AND periodo = ?");
$stmt->execute([$user_id, $periodo]);
$metas = $stmt->fetchAll();

// Cargar plantilla prediseñada
$reader = IOFactory::createReader('Xlsx');
$spreadsheet = $reader->load(__DIR__ . '/plantillas/plantilla_reporte.xlsx');
$sheet = $spreadsheet->getActiveSheet();

// Rellenar información
$sheet->setCellValue('B3', $usuario['nombre']);
$sheet->setCellValue('B4', $usuario['RFC']);
$sheet->setCellValue('B5', $usuario['puesto']);
$sheet->setCellValue('B6', $usuario['unidad']);
$sheet->setCellValue('B7', $usuario['adscripcion']);
$sheet->setCellValue('B8', $periodo);

// Insertar metas a partir de fila 11
$fila = 11;
foreach ($metas as $i => $meta) {
    $sheet->setCellValue("A{$fila}", $i + 1);
    $sheet->setCellValue("B{$fila}", $meta['nombre_meta']);
    $sheet->setCellValue("C{$fila}", $meta['indicador']);
    $sheet->setCellValue("D{$fila}", $meta['unidad']);
    $sheet->setCellValue("E{$fila}", $meta['ponderacion']);
    $sheet->setCellValue("F{$fila}", $meta['resultado']);
    $fila++;
}

// Guardar archivo
$fecha = date("Ymd_His");
$nombreArchivo = "reporte_{$user_id}_{$periodo}_{$fecha}.xlsx";
$ruta = $carpeta . "/" . $nombreArchivo;
$writer = new Xlsx($spreadsheet);
$writer->save($ruta);

// Descargar
header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=\"$nombreArchivo\"");
readfile($ruta);
exit;
