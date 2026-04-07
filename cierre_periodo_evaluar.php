<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin();

if (!function_exists('obtener_estatus_prerequisitos_colaborador')) {
    function obtener_estatus_prerequisitos_colaborador($pdo, $colaborador_id, $periodo) {
        $stmt = $pdo->prepare("SELECT estatus_metas, estatus_gerenciales FROM calificaciones WHERE user_id = ? AND periodo = ?");
        $stmt->execute([$colaborador_id, $periodo]);
        $estatus = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $estatus_metas = (int)($estatus['estatus_metas'] ?? 0);
        $estatus_gerenciales = (int)($estatus['estatus_gerenciales'] ?? 0);

        $stmt = $pdo->prepare("SELECT COUNT(*) AS total_metas,
                                      SUM(CASE WHEN resultado IS NOT NULL AND resultado <> '' AND resultado <> 0 THEN 1 ELSE 0 END) AS metas_con_propuesta
                               FROM metas
                               WHERE user_id = ? AND periodo = ?");
        $stmt->execute([$colaborador_id, $periodo]);
        $metas = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $total_metas = (int)($metas['total_metas'] ?? 0);
        $metas_con_propuesta = (int)($metas['metas_con_propuesta'] ?? 0);
        $metas_listas = ($total_metas > 0 && $metas_con_propuesta >= $total_metas);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM competencias_evaluacion WHERE user_id = ? AND periodo = ? AND tipo = 'auto'");
        $stmt->execute([$colaborador_id, $periodo]);
        $autoeval_registros = (int)($stmt->fetchColumn() ?? 0);
        $gerenciales_listas = ($autoeval_registros > 0);

        $estatus_metas_efectivo = max($estatus_metas, $metas_listas ? 2 : 0);
        $estatus_gerenciales_efectivo = max($estatus_gerenciales, $gerenciales_listas ? 2 : 0);

        return [
            'estatus_metas' => $estatus_metas,
            'estatus_gerenciales' => $estatus_gerenciales,
            'estatus_metas_efectivo' => $estatus_metas_efectivo,
            'estatus_gerenciales_efectivo' => $estatus_gerenciales_efectivo,
            'metas_listas' => $metas_listas,
            'gerenciales_listas' => $gerenciales_listas,
            'puede_evaluar_jefe' => ($estatus_metas_efectivo >= 2 && $estatus_gerenciales_efectivo >= 2),
        ];
    }
}


$estatus_metas = 3; // estatus evaluado, no hay mas
$colaborador_id = (int)($_POST['colaborador_id'] ?? 0);
$estatus_periodo = $_POST['estatus_periodo'];
$periodo = $_SESSION['periodo'];
$user_id = $_SESSION['user_id']; 

if ($colaborador_id <= 0) {
    header("Location: mis_colaboradores.php?info=8");
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE user_id = ? AND jefe_id = ?");
$stmt->execute([$colaborador_id, $user_id]);
if ((int)$stmt->fetchColumn() <= 0) {
    header("Location: mis_colaboradores.php?info=8");
    exit;
}

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);

// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();

if (isset($_POST['cierre_periodo'])) {

// 0. Verificar prerequisitos del colaborador antes de evaluar por jefe
$prerequisitos = obtener_estatus_prerequisitos_colaborador($pdo, $colaborador_id, (int)$periodo);
$estatus_metas_colaborador = (int)$prerequisitos['estatus_metas_efectivo'];
$estatus_gerenciales_colaborador = (int)$prerequisitos['estatus_gerenciales_efectivo'];

if ($estatus_metas_colaborador < 2 || $estatus_gerenciales_colaborador < 2) {
    header("Location: evaluar_individuales.php?info=7&colaborador_id=$colaborador_id");
    exit;
}

// 1. Verificar que existen metas
$stmt = $pdo->prepare("SELECT COUNT(*) FROM metas WHERE user_id = ? AND periodo = ?");
$stmt->execute([$colaborador_id, $periodo]);
$total_metas = $stmt->fetchColumn();

if ($total_metas == 0) {
    header("Location: evaluar_individuales.php?info=2&colaborador_id=$colaborador_id");
    exit;
}

// 2. Verificar que todas tienen resultado capturado
$stmt = $pdo->prepare("SELECT COUNT(*) FROM metas WHERE user_id = ? AND periodo = ? AND (resultado_final IS NULL OR resultado_final = '')");
$stmt->execute([$colaborador_id, $periodo]);
$metas_incompletas = $stmt->fetchColumn();

if ($metas_incompletas > 0) {
    header("Location: evaluar_individuales.php?info=22&colaborador_id=$colaborador_id");
    exit;
}

// 2.1 Verificar que el colaborador haya propuesto resultado en todas sus metas
$stmt = $pdo->prepare("SELECT COUNT(*) FROM metas WHERE user_id = ? AND periodo = ? AND (resultado IS NULL OR resultado = '')");
$stmt->execute([$colaborador_id, $periodo]);
$metas_sin_propuesta = $stmt->fetchColumn();

if ($metas_sin_propuesta > 0) {
    header("Location: evaluar_individuales.php?info=7&colaborador_id=$colaborador_id");
    exit;
}

// 3. Verificar que la suma de las ponderaciones sea 100
$stmt = $pdo->prepare("SELECT SUM(ponderacion) AS suma_ponderacion FROM metas WHERE user_id = ? AND periodo = ?");
$stmt->execute([$colaborador_id, $periodo]);
$suma_ponderacion = $stmt->fetchColumn();

if ($suma_ponderacion != 100) {
    header("Location: evaluar_individuales.php?info=222&colaborador_id=$colaborador_id");
    exit;
}

// calificacion final de las metas en el periodo
$stmt = $pdo->prepare("SELECT SUM(resultado_final * ponderacion / 100) AS calificacion_final FROM metas WHERE user_id = ? AND periodo = ? AND resultado_final != 0");
$stmt->execute([$colaborador_id, $periodo]);
$calificacion_final = $stmt->fetchColumn();    

//ver si ya existen resultados
$stmt = $pdo->prepare("SELECT COUNT(*) FROM calificaciones WHERE user_id = ? AND periodo = ?");
$stmt->execute([$colaborador_id, $periodo]);
$cantidad_calificaciones = $stmt->fetchColumn();

if ($cantidad_calificaciones) { 
    $stmt_updates = $pdo->prepare("UPDATE calificaciones SET estatus_metas = ?, individuales = ? WHERE user_id = ? AND periodo = ?");
    $stmt_updates->execute([$estatus_metas, $calificacion_final, $colaborador_id, $periodo]);
} else {
    $stmt_inserts = $pdo->prepare("INSERT INTO calificaciones (estatus_metas, individuales, user_id, periodo) VALUES (?, ?, ?, ?)");
    $stmt_inserts->execute([$estatus_metas, $calificacion_final, $colaborador_id, $periodo]);
}

$asunto       = "Evaluación de metas periodo {$periodo}";
$textoMensaje = sprintf("He evaluado tus metas del periodo %s. Por favor, revisa la evaluación.", $periodo);

$stmtMsg = $pdo->prepare("INSERT INTO mensajes (remitente_id, destinatario_id, asunto, mensaje, fecha, leido) VALUES (?, ?, ?, ?, NOW(), 0)");
$stmtMsg->execute([$user_id, $colaborador_id, $asunto, $textoMensaje]);

}
header("Location: evaluar_individuales.php?info=9&colaborador_id=$colaborador_id");
exit;
?>
