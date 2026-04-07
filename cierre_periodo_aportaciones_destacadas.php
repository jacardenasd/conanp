<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin();

// Verificar si es un POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['cierre_periodo'])) {
    header("Location: cols_aportaciones_destacadas.php?info=7");
    exit;
}

$colaborador_id = $_POST['colaborador_id'] ?? 0;
$periodo = $_POST['periodo'] ?? $_SESSION['periodo'];
$user_id = $_SESSION['user_id'];

if (!$colaborador_id) {
    header("Location: cols_aportaciones_destacadas.php?info=7");
    exit;
}

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);

// Verificar si ya fue cerrado
$stmt = $pdo->prepare("SELECT estatus_aportaciones_destacadas FROM calificaciones WHERE user_id = ? AND periodo = ?");
$stmt->execute([$colaborador_id, $periodo]);
$estatus_actual = $stmt->fetchColumn();

if ($estatus_actual >= 2) {
    // Ya estaba cerrado
    header("Location: cols_aportaciones_destacadas.php?info=10&colaborador_id=$colaborador_id&periodo=$periodo");
    exit;
}

// Contar aportaciones destacadas validadas por jefe
$stmt = $pdo->prepare("SELECT COUNT(*) FROM aportaciones_destacadas WHERE user_id = ? AND periodo = ? AND validado = 1");
$stmt->execute([$colaborador_id, $periodo]);
$total_aportaciones_validadas = (int)$stmt->fetchColumn();

// Determinar estatus final del cierre:
// 2 = proceso cerrado con al menos una validada
// 1 = hubo captura pero no validación efectiva
$estatus_final = ($total_aportaciones_validadas > 0) ? 2 : 1;

// Actualizar o crear registro en calificaciones
$stmt = $pdo->prepare("SELECT COUNT(*) FROM calificaciones WHERE user_id = ? AND periodo = ?");
$stmt->execute([$colaborador_id, $periodo]);
$tiene_calificacion = $stmt->fetchColumn();

if ($tiene_calificacion == 0) {
    // Crear nuevo registro
    $stmt = $pdo->prepare("INSERT INTO calificaciones (user_id, periodo, aportaciones_destacadas, estatus_aportaciones_destacadas) VALUES (?, ?, ?, ?)");
    $stmt->execute([$colaborador_id, $periodo, $total_aportaciones_validadas, $estatus_final]);
} else {
    // Actualizar registro existente
    $stmt = $pdo->prepare("UPDATE calificaciones SET aportaciones_destacadas = ?, estatus_aportaciones_destacadas = ? WHERE user_id = ? AND periodo = ?");
    $stmt->execute([$total_aportaciones_validadas, $estatus_final, $colaborador_id, $periodo]);
}

// Enviar mensaje al usuario evaluado
$asunto       = "Cierre de Aportaciones Destacadas periodo {$periodo}";
$textoMensaje = sprintf("Tu Superior Ha cerrado la evaluación de Aportaciones Destacadas del periodo %s. Ya no se podrá realizar cambios.", $periodo);

$stmtMsg = $pdo->prepare("INSERT INTO mensajes (remitente_id, destinatario_id, asunto, mensaje, fecha, leido) VALUES (?, ?, ?, ?, NOW(), 0)");
$stmtMsg->execute([$user_id, $colaborador_id, $asunto, $textoMensaje]);

header("Location: cols_aportaciones_destacadas.php?info=9&colaborador_id=$colaborador_id&periodo=$periodo");
exit;
?>
