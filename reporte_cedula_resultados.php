<?php
// ============================================
// DESCARGA CÉDULA DE RESULTADOS CON VALIDACIONES
// Cambio #8: Validar que todas las evaluaciones estén aprobadas
// ============================================

require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';
require 'vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

checkLogin();
asegurar_columna_capacitacion_contabiliza($pdo);

if (!isset($_GET['user_id']) || !isset($_GET['periodo'])) {
    die("Faltan parámetros obligatorios.");
}

$user_id = $_GET['user_id'];
$periodo = $_GET['periodo'];

// Control de acceso
if ($_SESSION['user_id'] != $user_id && $_SESSION['role'] < 2) {
    die("No tienes permisos para descargar este reporte.");
}

// Obtener datos del usuario
$stmt = $pdo->prepare("SELECT u.*, COALESCE(u.puesto_nombre, p.puesto) AS puesto_denominacion, un.nombre AS unidad, j.nombre AS jefe_nombre, j.apellido_paterno AS jefe_paterno, j.apellido_materno AS jefe_materno
                       FROM usuarios u
                       LEFT JOIN puestos p ON u.puesto_id = p.id
                       LEFT JOIN unidades un ON u.unidad_id = un.id
                       LEFT JOIN usuarios j ON u.jefe_id = j.user_id
                       WHERE u.user_id = ?");
$stmt->execute([$user_id]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);

// Calificaciones
$stmt = $pdo->prepare("SELECT individuales, gerenciales, capacitacion, actividades_extraordinarias, aportaciones_destacadas, estatus_metas, estatus_gerenciales FROM calificaciones WHERE user_id = ? AND periodo = ?");
$stmt->execute([$user_id, $periodo]);
$calificaciones = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$calificaciones) {
    die("No hay calificaciones registradas para este período.");
}

if ((int)($calificaciones['estatus_gerenciales'] ?? 0) >= 3 && (float)($calificaciones['gerenciales'] ?? 0) <= 0) {
    $sql_gerencial = "SELECT ROUND(SUM(eval.avg_valor * pesos.peso) / NULLIF(SUM(pesos.peso), 0), 2) AS calificacion_final
                     FROM usuarios u
                     JOIN (
                         SELECT user_id, competencia_id, AVG(valor) AS avg_valor
                         FROM competencias_evaluacion
                         WHERE user_id = ? AND periodo = ? AND tipo = 'jefe' AND valor > 0
                         GROUP BY user_id, competencia_id
                     ) AS eval ON eval.user_id = u.user_id
                     JOIN (
                         SELECT nivel, competencia_id,
                             CASE competencia_id
                                 WHEN 1 THEN vision
                                 WHEN 2 THEN liderazgo
                                 WHEN 3 THEN orientacion
                                 WHEN 4 THEN negociacion
                                 WHEN 5 THEN trabajo
                             END AS peso
                         FROM (
                             SELECT nivel, 1 AS competencia_id, vision, liderazgo, orientacion, negociacion, trabajo FROM competencias_pesos
                             UNION ALL
                             SELECT nivel, 2 AS competencia_id, vision, liderazgo, orientacion, negociacion, trabajo FROM competencias_pesos
                             UNION ALL
                             SELECT nivel, 3 AS competencia_id, vision, liderazgo, orientacion, negociacion, trabajo FROM competencias_pesos
                             UNION ALL
                             SELECT nivel, 4 AS competencia_id, vision, liderazgo, orientacion, negociacion, trabajo FROM competencias_pesos
                             UNION ALL
                             SELECT nivel, 5 AS competencia_id, vision, liderazgo, orientacion, negociacion, trabajo FROM competencias_pesos
                         ) AS pesos_ext
                     ) AS pesos ON u.puesto_nivel = pesos.nivel AND eval.competencia_id = pesos.competencia_id
                     WHERE u.user_id = ?";
    $stmt_gerencial = $pdo->prepare($sql_gerencial);
    $stmt_gerencial->execute([$user_id, $periodo, $user_id]);
    $gerencial_recalculada = $stmt_gerencial->fetchColumn();

    if ($gerencial_recalculada === false || $gerencial_recalculada === null) {
        $stmt_fallback = $pdo->prepare("SELECT ROUND(AVG(valor), 2) FROM competencias_evaluacion WHERE user_id = ? AND periodo = ? AND tipo = 'jefe' AND valor > 0");
        $stmt_fallback->execute([$user_id, $periodo]);
        $gerencial_recalculada = $stmt_fallback->fetchColumn();
    }

    if ($gerencial_recalculada !== false && $gerencial_recalculada !== null) {
        $calificaciones['gerenciales'] = $gerencial_recalculada;
    }
}

// Calcular capacitación desde horas validadas por administrador
$stmt = $pdo->prepare("SELECT SUM(horas) FROM capacitacion WHERE user_id = ? AND periodo = ? AND validado = 1 AND COALESCE(contabiliza_horas, 1) = 1");
$stmt->execute([$user_id, $periodo]);
$horas_capacitacion = (float)($stmt->fetchColumn() ?? 0);
$capacitacion_calificacion = $horas_capacitacion > 0 ? min(100, round(($horas_capacitacion / 40) * 100, 2)) : 0;

// ============================================
// VALIDAR REQUISITOS PARA DESCARGA
// ============================================
$estatus_metas = $calificaciones['estatus_metas'] ?? 0;
$estatus_gerenciales = $calificaciones['estatus_gerenciales'] ?? 0;
$requiere_colectivas = ((int)($u['permite_metas_colectivas'] ?? 0) === 1);
if (!$requiere_colectivas) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM metas_colectivas WHERE unidad_id = ? AND periodo = ?");
    $stmt->execute([$u['unidad_id'], $periodo]);
    $requiere_colectivas = ((int)($stmt->fetchColumn() ?? 0)) > 0;
}

$estatus_colectivas = 0;
if ($requiere_colectivas) {
    $stmt = $pdo->prepare("SELECT estatus FROM calificaciones_colectivas WHERE unidad_id = ? AND periodo = ?");
    $stmt->execute([$u['unidad_id'], $periodo]);
    $estatus_colectivas = (int)($stmt->fetchColumn() ?? 0);
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM aportaciones_destacadas WHERE user_id = ? AND periodo = ? AND COALESCE(validado_rh, 0) = 0 AND COALESCE(rechazado_por_rh, 0) = 0");
$stmt->execute([$user_id, $periodo]);
$aportaciones_pendientes_admin = (int)($stmt->fetchColumn() ?? 0);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM actividades_extraordinarias WHERE user_id = ? AND periodo = ? AND COALESCE(validado_rh, 0) = 0 AND COALESCE(rechazado_por_rh, 0) = 0");
$stmt->execute([$user_id, $periodo]);
$actividades_pendientes_admin = (int)($stmt->fetchColumn() ?? 0);

$pendientes_admin = $aportaciones_pendientes_admin + $actividades_pendientes_admin;

// Valores esperados: 3 = evaluado y aprobado, 2 = evaluado sin aprobación
$puede_descargar = ($estatus_metas == 3)
    && ($estatus_gerenciales == 3)
    && (!$requiere_colectivas || $estatus_colectivas >= 2)
    && ($pendientes_admin === 0);

if (!$puede_descargar) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Descarga de Cédula - Requisitos Pendientes</title>
        <link href="assets/css/ltr/all.min.css" rel="stylesheet">
        <style>
            .container { max-width: 700px; margin: 50px auto; padding: 30px; background: white; border-radius: 8px; }
            .alert { padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 5px solid; }
            .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
            .progress-item { padding: 10px; margin: 10px 0; border-radius: 5px; }
            .progress-ok { background: #d4edda; color: #155724; }
            .progress-pending { background: #fff3cd; color: #856404; }
            .btn { display: inline-block; padding: 10px 20px; margin: 10px 5px 10px 0; border: none; border-radius: 5px; cursor: pointer; }
            .btn-secondary { background: #6c757d; color: white; }
        </style>
    </head>
    <body style="background: #f5f5f5;">
        <div class="container">
            <h2>❌ No puedes descargar la Cédula aún</h2>
            
            <div class="alert alert-danger">
                <strong>Requisitos faltantes para finalizar la evaluación:</strong>
            </div>
            
            <div class="progress-item <?php echo ($estatus_metas == 3) ? 'progress-ok' : 'progress-pending'; ?>">
                <strong>✓ Metas Individuales:</strong> 
                <?php echo ($estatus_metas == 3) ? '✅ Evaluadas y aprobadas (Estado: ' . $estatus_metas . ')' : '❌ Pendiente (Estado: ' . $estatus_metas . ')'; ?>
            </div>
            
            <div class="progress-item <?php echo (!$requiere_colectivas || $estatus_colectivas >= 2) ? 'progress-ok' : 'progress-pending'; ?>">
                <strong>✓ Metas Colectivas:</strong> 
                <?php echo (!$requiere_colectivas) ? '✅ Sin metas colectivas en la unidad' : (($estatus_colectivas >= 2) ? '✅ Evaluadas (Estado: ' . $estatus_colectivas . ')' : '❌ Pendiente (Estado: ' . $estatus_colectivas . ')'); ?>
            </div>

            <div class="progress-item <?php echo ($pendientes_admin === 0) ? 'progress-ok' : 'progress-pending'; ?>">
                <strong>✓ Aportaciones/Actividades:</strong>
                <?php echo ($pendientes_admin === 0) ? '✅ Sin pendientes de dictamen por Admin' : '❌ Hay ' . $pendientes_admin . ' registro(s) pendiente(s) de validación o descarte por RH'; ?>
            </div>
            
            <div class="progress-item <?php echo ($estatus_gerenciales == 3) ? 'progress-ok' : 'progress-pending'; ?>">
                <strong>✓ Autoevaluación Gerencial/Competencias:</strong> 
                <?php echo ($estatus_gerenciales == 3) ? '✅ Evaluada y aprobada (Estado: ' . $estatus_gerenciales . ')' : '❌ Pendiente (Estado: ' . $estatus_gerenciales . ')'; ?>
            </div>
            
            <p style="margin-top: 20px;">Por favor, completa todos los requisitos antes de descargar tu cédula de resultados.</p>
            <p><a href="mi_evaluacion.php" class="btn btn-secondary">← Volver a Mi Evaluación</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}


$stmt = $pdo->prepare("SELECT resultado FROM calificaciones_colectivas WHERE unidad_id = ? AND periodo = ?");
$stmt->execute([$u['unidad_id'], $periodo]);
$colectiva_raw = $stmt->fetchColumn();
$colectiva = is_numeric($colectiva_raw) ? (float)$colectiva_raw : 0;

function asignacion($valor) {
    if ($valor >= 90) return "Sobresaliente";
    elseif ($valor >= 70) return "Satisfactorio";
    elseif ($valor >= 60) return "No satisfactorio";
    elseif ($valor >= 50) return "No aprobatorio";
    else return "Deficiente";
}

$spreadsheet = IOFactory::load("plantillas/cedula_resultados.xlsx");
$ws = $spreadsheet->getActiveSheet();

$rfc = trim((string)($u['RFC'] ?? $u['rfc'] ?? ''));
$curp = trim((string)($u['CURP'] ?? $u['curp'] ?? ''));
$idrusp = trim((string)($u['IDRUSP'] ?? $u['idrusp'] ?? ''));

// Datos personales
$ws->setCellValue("F8", $u['nombre'] . ' ' . $u['apellido_paterno'] . ' ' . $u['apellido_materno']);
$ws->setCellValue("F9", $u['puesto_denominacion']);
$ws->setCellValue("F10", $u['unidad']);
$ws->setCellValue("D11", $rfc);
$ws->setCellValue("G11", $curp);
$ws->setCellValue("J11", $idrusp);
$ws->setCellValue("F13", date('d/m/Y'));
$ws->setCellValue("B15", "EVALUACIÓN DEL DESEMPEÑO $periodo");
$ws->mergeCells("B15:L15");

// Calificaciones
$ws->setCellValue("F19", $calificaciones['individuales']);
$ws->setCellValue("F21", $colectiva);
$ws->setCellValue("F25", $capacitacion_calificacion);
if ($calificaciones['actividades_extraordinarias'] > 0) {
    $ws->setCellValue("F27", $calificaciones['actividades_extraordinarias']);
}
if ($calificaciones['aportaciones_destacadas'] > 0) {
    $ws->setCellValue("F29", $calificaciones['aportaciones_destacadas']);
}

// Gerenciales
$ws->setCellValue("F23", $calificaciones['gerenciales']);

// Cálculo parcial y final
$parcial = $calificaciones['individuales'] * 0.25 + $colectiva * 0.25 + $calificaciones['gerenciales'] * 0.25 + $capacitacion_calificacion * 0.25;
$final = $parcial + $calificaciones['actividades_extraordinarias'] + $calificaciones['aportaciones_destacadas'];
$ws->setCellValue("F32", round($parcial, 2));
$ws->setCellValue("F34", round($final, 2));
$ws->setCellValue("F36", asignacion($final));

// Asignaciones
$ws->setCellValue("I19", asignacion($calificaciones['individuales']));
$ws->setCellValue("I21", asignacion($colectiva));
$ws->setCellValue("I23", asignacion($calificaciones['gerenciales']));

// Evaluado y jefe inmediato
$ws->setCellValue("C39", $u['jefe_nombre'] . ' ' . $u['jefe_paterno'] . ' ' . $u['jefe_materno']);
$ws->setCellValue("I39", $u['nombre'] . ' ' . $u['apellido_paterno'] . ' ' . $u['apellido_materno']);

// Combinación de celdas
$combinar = [
    "F19:H19", "F21:H21", "I21:K21", "I19:K19", "F23:H23", "I23:K23", "F25:K25", "F27:K27", "F29:K29",
    "F32:K32", "F34:K34", "F36:K36", "F8:K8", "F9:K9", "F10:K10", "F12:K12", "F13:K13", "D11:E11",
    "G11:H11", "J11:K11", "C39:E39", "I39:K39"
];
foreach ($combinar as $rango) {
    $ws->mergeCells($rango);
}

$ws->getStyle('F8:K8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$ws->getStyle('C39:E39')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$ws->getStyle('I39:K39')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$rfcc = $rfc !== '' ? $rfc : 'SINRFC';

// Descargar archivo
$filename = "cedula_resultados_".$rfcc."_".$periodo.".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;
?>
