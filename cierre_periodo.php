<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin();

if (isset($_POST['cierre_periodo'])) {

$periodo = $_SESSION['periodo'];
$user_id = $_POST['user_id']; 
$estatus_metas = $_POST['estatus_metas'];

// Obtener estatus actual de calificaciones
$stmt = $pdo->prepare("SELECT estatus_metas FROM calificaciones WHERE user_id = ? AND periodo = ?");
$stmt->execute([$user_id, $periodo]);
$estatus_actual = (int)($stmt->fetchColumn() ?? 0);

// Cambio #5: No permitir cerrar si ya fue evaluado por el jefe (estatus >= 3)
if ($estatus_actual >= 3) {
    header("Location: metas_individuales.php?info=error");
    exit;
}

// Validar que todas las metas tengan propuesta de resultado
$stmt = $pdo->prepare("SELECT COUNT(*) FROM metas WHERE user_id = ? AND periodo = ? AND resultado = ?");
$stmt->execute([$user_id, $periodo, 0]);
$metas_con_evaluacion = $stmt->fetchColumn();

// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();

// Cambio #5: Determinar nuevo estado solo si no está ya en estado 2 o superior
if ($estatus_actual < 2) {
    if ($metas_con_evaluacion == 0) { // Todas las metas tienen propuesta de evaluación
        $estatus_metas = 2;
    } elseif ($metas_con_evaluacion > 0) {
        $estatus_metas = 1;
    }
} else {
    // Mantener el estado actual si ya está en 2 o superior
    $estatus_metas = $estatus_actual;
} 

// si no hay calificacion la agregamos
$stmt = $pdo->prepare("SELECT COUNT(*) FROM calificaciones WHERE user_id = ? AND periodo = ?");
$stmt->execute([$user_id, $periodo]);
$tiene_califiacion = $stmt->fetchColumn();

if ($tiene_califiacion == 0) {
    $stmt = $pdo->prepare("INSERT INTO calificaciones (user_id, periodo, estatus_metas) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $periodo, $estatus_metas]);
} else {
    $stmt = $pdo->prepare("UPDATE calificaciones SET estatus_metas = ? WHERE user_id = ? AND periodo = ?");
    $stmt->execute([$estatus_metas, $user_id, $periodo]);
    }

$stmtJefe = $pdo->prepare("SELECT jefe_id FROM usuarios WHERE user_id = ?");
$stmtJefe->execute([$user_id]);
$jefe_id = $stmtJefe->fetchColumn();


if ($estatus_metas == 1) {
$asunto       = "Captura de metas periodo {$periodo}";
$textoMensaje = sprintf("He cerrado la captura de mis metas del periodo %s. Por favor, revisa la evaluación.", $periodo); 
} else if ($estatus_metas == 2){
$asunto       = "Propuesta de resultado de metas periodo {$periodo}";
$textoMensaje = sprintf("He capturado la propuesta de resultado de mis metas del periodo %s. Por favor, revisa la evaluación.", $periodo); 
} 

if ($estatus_metas == 1 OR $estatus_metas == 2) {
$stmtMsg = $pdo->prepare("INSERT INTO mensajes (remitente_id, destinatario_id, asunto, mensaje, fecha, leido)\n                         SELECT ?, user_id, ?, ?, NOW(), 0\n                         FROM usuarios\n                         WHERE user_id = ?\n                         LIMIT 1");
$stmtMsg->execute([$user_id, $asunto, $textoMensaje, $jefe_id]);
}
}
header("Location: metas_individuales.php?info=9");
exit;
?>
