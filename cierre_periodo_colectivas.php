<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin();

$periodo = isset($_POST['periodo']) ? (int)$_POST['periodo'] : (int)($_SESSION['periodo'] ?? 0);
$periodo_sesion = (int)($_SESSION['periodo'] ?? 0);

if ($periodo <= 0) {
    $periodo = $periodo_sesion;
}
$unidad_id = $_POST['unidad_id']; 
$estatus_periodo = $_POST['estatus_periodo'];
$estatus_metas = $_POST['estatus_metas'];

//ver si ya existen resultados
$stmt = $pdo->prepare("SELECT COUNT(*) FROM calificaciones_colectivas WHERE unidad_id = ? AND periodo = ?");
$stmt->execute([$unidad_id, $periodo]);
$cantidad_calificaciones = $stmt->fetchColumn();

if (isset($_POST['cierre_periodo']) AND $estatus_periodo == 'Evaluación') {

$estatus_metas = 2; 

// 1. Verificar que existen metas
$stmt = $pdo->prepare("SELECT COUNT(*) FROM metas_colectivas WHERE unidad_id = ? AND periodo = ?");
$stmt->execute([$unidad_id, $periodo]);
$total_metas = $stmt->fetchColumn();

if ($total_metas == 0) {
header("Location: mis_metas_colectivas.php?info=9");
echo 1;
    exit;
}

// 2. Verificar que todas tienen resultado capturado
$stmt = $pdo->prepare("SELECT COUNT(*) FROM metas_colectivas WHERE unidad_id = ? AND periodo = ? AND resultado = ?");
$stmt->execute([$unidad_id, $periodo, 0]);
$metas_incompletas = $stmt->fetchColumn();

if ($metas_incompletas > 0) {
header("Location: mis_metas_colectivas.php?info=9");
echo 2;
    exit;
}

// 3. Verificar que la suma de las ponderaciones sea 100
$stmt = $pdo->prepare("SELECT SUM(ponderacion) AS suma_ponderacion FROM metas_colectivas WHERE unidad_id = ? AND periodo = ?");
$stmt->execute([$unidad_id, $periodo]);
$suma_ponderacion = $stmt->fetchColumn();

if ($suma_ponderacion != 100) {
header("Location: mis_metas_colectivas.php?info=9");
echo 3;
    exit;
}

// calificacion final de las metas en el periodo
$stmt = $pdo->prepare("SELECT SUM(resultado * ponderacion / 100) AS calificacion_final FROM metas_colectivas WHERE unidad_id = ? AND periodo = ? AND resultado != ?");
$stmt->execute([$unidad_id, $periodo, 0]);
$calificacion_final = $stmt->fetchColumn();    

if ($cantidad_calificaciones) { 
    $stmt_updates = $pdo->prepare("UPDATE calificaciones_colectivas SET estatus = ?, resultado = ? WHERE unidad_id = ? AND periodo = ?");
    $stmt_updates->execute([$estatus_metas, $calificacion_final, $unidad_id, $periodo]);
} else {
    $stmt_inserts = $pdo->prepare("INSERT INTO calificaciones_colectivas (estatus, resultado, unidad_id, periodo) VALUES (?, ?, ?, ?)");
    $stmt_inserts->execute([$estatus_metas, $calificacion_final, $unidad_id, $periodo]);
}

} else {

// es captura
$estatus_metas = 1; 

if ($cantidad_calificaciones) { 
    $stmt_updates = $pdo->prepare("UPDATE calificaciones_colectivas SET estatus = ? WHERE unidad_id = ? AND periodo = ?");
    $stmt_updates->execute([$estatus_metas, $unidad_id, $periodo]);
} else {
    $stmt_inserts = $pdo->prepare("INSERT INTO calificaciones_colectivas (estatus, unidad_id, periodo) VALUES (?, ?, ?)");
    $stmt_inserts->execute([$estatus_metas, $unidad_id, $periodo]);
}

}

header("Location: metas_colectivas.php?info=9");
exit;
?>
