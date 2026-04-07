<?php
// ============================================
// DESCARGA REPORTE METAS INDIVIDUALES + FINALIZAR
// Cambio #4: Agregar botón FINALIZAR antes de descarga
// ============================================

require 'config/db.php';
require 'includes/session.php';
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

// Verificar estado de finalización
$stmt_final = $pdo->prepare("SELECT finalizado_metas, archivo_individuales FROM calificaciones WHERE user_id = ? AND periodo = ?");
$stmt_final->execute([$user_id, $periodo]);
$calificacion = $stmt_final->fetch();
$metas_finalizadas = $calificacion['finalizado_metas'] ?? 0;
$archivo_pdf_cargado = !empty($calificacion['archivo_individuales']);

// ============================================
// PROCESAR ACCIONES (Finalizar o Rechazar)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'finalizar_metas') {
        // Validar que el usuario tenga metas capturadas
        $stmt_check = $pdo->prepare("SELECT COUNT(*) as total FROM metas WHERE user_id = ? AND periodo = ?");
        $stmt_check->execute([$user_id, $periodo]);
        $count = $stmt_check->fetch()['total'] ?? 0;
        
        if ($count == 0) {
            $_SESSION['error'] = "❌ No tienes metas capturadas para finalizar.";
        } else {
            // Registrar finalización
            $stmt_exists = $pdo->prepare("SELECT COUNT(*) FROM calificaciones WHERE user_id = ? AND periodo = ?");
            $stmt_exists->execute([$user_id, $periodo]);
            $existe_calificacion = (int)$stmt_exists->fetchColumn() > 0;

            if ($existe_calificacion) {
                $stmt_update = $pdo->prepare("
                    UPDATE calificaciones 
                    SET finalizado_metas = 1, 
                        fecha_finalizacion_metas = NOW(), 
                        usuario_finalizacion_id = ?
                    WHERE user_id = ? AND periodo = ?
                ");
                $stmt_update->execute([$_SESSION['user_id'], $user_id, $periodo]);
            } else {
                $stmt_insert = $pdo->prepare("
                    INSERT INTO calificaciones (user_id, periodo, finalizado_metas, fecha_finalizacion_metas, usuario_finalizacion_id)
                    VALUES (?, ?, 1, NOW(), ?)
                ");
                $stmt_insert->execute([$user_id, $periodo, $_SESSION['user_id']]);
            }
            
            $_SESSION['mensaje'] = "✅ Tus metas han sido FINALIZADAS correctamente. Ya no podrás editarlas.";
            $metas_finalizadas = 1;
            
            // Registrar en bitácora si existe tabla de auditoría
            $stmt_audit = $pdo->prepare("
                INSERT INTO auditorias (user_id, tabla, accion, descripcion, fecha, usuario_id)
                VALUES (?, 'calificaciones', 'FINALIZAR_METAS', 'Usuario finalizó sus metas individuales', NOW(), ?)
            ");
            @$stmt_audit->execute([$user_id, $_SESSION['user_id']]);
        }
    }
}

// Si NO hay confirmación de descarga y NO hay acción, mostrar página con botón FINALIZAR
if (!$confirmar_descarga && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Descargar Reporte - Metas Individuales</title>
        <link href="assets/css/ltr/all.min.css" rel="stylesheet">
        <style>
            .container { max-width: 700px; margin: 50px auto; padding: 30px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .page-header { margin-bottom: 30px; }
            .alert { padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 5px solid; }
            .alert-success { background: #d4edda; color: #155724; border-left-color: #28a745; }
            .alert-warning { background: #fff3cd; color: #856404; border-left-color: #ffc107; }
            .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
            .info-box { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .info-box strong { display: block; margin-bottom: 5px; color: #333; }
            .btn { display: inline-block; padding: 10px 20px; margin: 10px 5px 10px 0; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; text-decoration: none; }
            .btn-primary { background: #007bff; color: white; }
            .btn-primary:hover { background: #0056b3; }
            .btn-success { background: #28a745; color: white; }
            .btn-success:hover { background: #218838; }
            .btn-success:disabled { background: #ccc; cursor: not-allowed; }
            .btn-secondary { background: #6c757d; color: white; }
            .btn-secondary:hover { background: #545b62; }
            .status-label { display: inline-block; padding: 5px 10px; border-radius: 3px; font-weight: bold; font-size: 12px; }
            .status-finalizado { background: #dc3545; color: white; }
            .status-pendiente { background: #ffc107; color: #333; }
            .mt-20 { margin-top: 20px; }
        </style>
    </head>
    <body style="background: #f5f5f5;">
        <div class="container">
            <div class="page-header">
                <h2>📊 Reporte de Metas Individuales</h2>
            </div>
            
            <?php if (isset($_SESSION['mensaje'])): ?>
                <div class="alert alert-success">
                    <strong><?php echo htmlspecialchars($_SESSION['mensaje']); ?></strong>
                </div>
                <?php unset($_SESSION['mensaje']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <strong><?php echo htmlspecialchars($_SESSION['error']); ?></strong>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <div class="info-box">
                <strong>👤 Usuario:</strong> <?php echo htmlspecialchars($usuario['nombre']); ?><br>
                <strong>📅 Período:</strong> <?php echo htmlspecialchars($periodo); ?><br>
                <strong>🏢 Unidad:</strong> <?php echo htmlspecialchars($usuario['unidad'] ?? 'N/A'); ?>
            </div>
            
            <div class="alert <?php echo $metas_finalizadas ? 'alert-warning' : 'alert-success'; ?>">
                <strong>Estado Actual:</strong> 
                <span class="status-label <?php echo $metas_finalizadas ? 'status-finalizado' : 'status-pendiente'; ?>">
                    <?php echo $metas_finalizadas ? '🔒 FINALIZADO' : '✏️ EN EDICIÓN'; ?>
                </span>
                
                <?php if ($metas_finalizadas): ?>
                    <p style="margin-top: 10px;">Tus metas ya están finalizadas. <strong>No puedes editarlas ni cargarlas nuevamente.</strong> Tu jefe podrá proceder con la evaluación.</p>
                <?php elseif (!$archivo_pdf_cargado): ?>
                    <p style="margin-top: 10px;">⚠️ <strong>Debes cargar el formato de metas firmado</strong> antes de poder finalizar. Carga el PDF en la sección de "Carga de Metas individuales Firmadas".</p>
                <?php else: ?>
                    <p style="margin-top: 10px;">✅ Archivo firmado cargado. Tus metas están listas para ser finalizadas. Una vez finalizado, <strong>no podrás hacer cambios</strong>.</p>
                <?php endif; ?>
            </div>
            
            <h4>Opciones:</h4>
            <div style="margin: 20px 0;">
                <!-- Botón: Descargar Reporte -->
                <form method="GET" style="display: inline;">
                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">
                    <input type="hidden" name="periodo" value="<?php echo htmlspecialchars($periodo); ?>">
                    <input type="hidden" name="confirmar_descarga" value="1">
                    <button type="submit" class="btn btn-primary">
                        📥 Descargar Reporte (Excel)
                    </button>
                </form>
                
                <!-- Botón: Finalizar Metas -->
                <?php if (!$metas_finalizadas && $archivo_pdf_cargado): ?>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ ADVERTENCIA:\n\n¿Estás seguro de FINALIZAR tus metas?\n\nUna vez finalizado:\n• NO podrás editar metas\n• NO podrás eliminar metas\n• Tu jefe puede iniciar la evaluación\n• Solo un Super Admin puede revertir esto\n\n¿Deseas continuar?')">
                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">
                        <input type="hidden" name="periodo" value="<?php echo htmlspecialchars($periodo); ?>">
                        <input type="hidden" name="accion" value="finalizar_metas">
                        <button type="submit" class="btn btn-success">
                            ✅ FINALIZAR METAS
                        </button>
                    </form>
                <?php elseif (!$metas_finalizadas && !$archivo_pdf_cargado): ?>
                    <button class="btn btn-success" disabled title="Debes cargar el PDF firmado primero">⚠️ FINALIZAR (Carga PDF primero)</button>
                <?php else: ?>
                    <button class="btn btn-success" disabled>🔒 METAS FINALIZADAS (Super Admin puede revertir)</button>
                <?php endif; ?>
            </div>
            
            <hr class="mt-20">
            <p><a href="mi_evaluacion.php" class="btn btn-secondary">← Volver a Mi Evaluación</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================
// GENERAR EXCEL PARA DESCARGA
// ============================================

// Obtener metas del periodo
$stmt = $pdo->prepare("SELECT * FROM metas WHERE user_id = ? AND periodo = ? ORDER BY id");
$stmt->execute([$user_id, $periodo]);
$metas = $stmt->fetchAll();

// Crear carpeta si no existe
$carpeta = __DIR__ . "/reportes/individuales";
if (!file_exists($carpeta)) {
    mkdir($carpeta, 0777, true);
}

// Cargar plantilla prediseñada
$reader = IOFactory::createReader('Xlsx');
$spreadsheet = $reader->load(__DIR__ . '/plantillas/plantilla_reporte.xlsx');
$sheet = $spreadsheet->getActiveSheet();

// Rellenar información del usuario
$sheet->setCellValue('B3', $usuario['nombre'] ?? '');
$sheet->setCellValue('B4', $usuario['RFC'] ?? '');
$sheet->setCellValue('B5', $usuario['puesto'] ?? '');
$sheet->setCellValue('B6', $usuario['unidad'] ?? '');
$sheet->setCellValue('B7', $usuario['adscripcion'] ?? '');
$sheet->setCellValue('B8', $periodo);

// Insertar metas a partir de fila 11
$fila = 11;
foreach ($metas as $i => $meta) {
    $sheet->setCellValue("A{$fila}", $i + 1);
    $sheet->setCellValue("B{$fila}", $meta['nombre_meta'] ?? '');
    $sheet->setCellValue("C{$fila}", $meta['indicador'] ?? '');
    $sheet->setCellValue("D{$fila}", $meta['unidad'] ?? '');
    $sheet->setCellValue("E{$fila}", $meta['ponderacion'] ?? 0);
    $sheet->setCellValue("F{$fila}", $meta['resultado'] ?? 0);
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
?>
