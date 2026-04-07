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

$unidad_id = $_POST['unidad_id'] ?? null;
$periodo = $_POST['periodo'] ?? null;
$estatus = $_POST['estatus'] ?? null;

if ($unidad_id && $periodo && in_array($estatus, ['0', '1', '2'])) {
    // Verifica si existe el registro
    $sql_check = "SELECT id FROM calificaciones_colectivas WHERE unidad_id = ? AND periodo = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$unidad_id, $periodo]);
    $existe = $stmt_check->fetchColumn();

    $resultado = ($estatus === '2') ? null : 0;

    if ($existe) {
        // Actualiza
        $sql = "UPDATE calificaciones_colectivas SET estatus = ?, resultado = ? WHERE unidad_id = ? AND periodo = ?";
        $pdo->prepare($sql)->execute([$estatus, $resultado, $unidad_id, $periodo]);
    } else {
        // Inserta
        $sql = "INSERT INTO calificaciones_colectivas (unidad_id, periodo, estatus, resultado) VALUES (?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$unidad_id, $periodo, $estatus, $resultado]);
    }

}

header("Location: admin_metas_colectivas.php?periodo=" . urlencode($periodo));
exit;

?>