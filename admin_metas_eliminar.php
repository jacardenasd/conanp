<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin(2);

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');
$return_user_id = $_GET['return_user_id'] ?? '';
$return_periodo = $_GET['return_periodo'] ?? '';
$return_modo = $_GET['return_modo'] ?? '';

function resetear_estatus_individuales_admin(PDO $pdo, int $user_id_objetivo, int $periodo_objetivo): void {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM metas WHERE user_id = ? AND periodo = ?");
    $stmt->execute([$user_id_objetivo, $periodo_objetivo]);
    $total_metas = (int)$stmt->fetchColumn();

    $nuevo_estatus = ($total_metas > 0) ? 1 : 0;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM calificaciones WHERE user_id = ? AND periodo = ?");
    $stmt->execute([$user_id_objetivo, $periodo_objetivo]);
    $existe_calificacion = (int)$stmt->fetchColumn();

    if ($existe_calificacion > 0) {
        $stmt = $pdo->prepare("UPDATE calificaciones SET estatus_metas = ?, individuales = 0 WHERE user_id = ? AND periodo = ?");
        $stmt->execute([$nuevo_estatus, $user_id_objetivo, $periodo_objetivo]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO calificaciones (user_id, periodo, estatus_metas, individuales) VALUES (?, ?, ?, 0)");
        $stmt->execute([$user_id_objetivo, $periodo_objetivo, $nuevo_estatus]);
    }
}

$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $pdo->prepare("SELECT user_id, periodo FROM metas WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $meta = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("DELETE FROM metas WHERE id = :id");
    $stmt->execute(['id' => $id]);

    if ($meta) {
        resetear_estatus_individuales_admin(
            $pdo,
            (int)$meta['user_id'],
            (int)$meta['periodo']
        );
    }
}

$destino = "admin_metas_individuales.php?info=3";
if ($return_user_id !== '') {
    $destino .= "&user_id=" . urlencode((string)$return_user_id);
}
if ($return_periodo !== '') {
    $destino .= "&periodo=" . urlencode((string)$return_periodo);
}
if ($return_modo !== '') {
    $destino .= "&modo=" . urlencode((string)$return_modo);
}

header("Location: " . $destino);
exit;
?>
