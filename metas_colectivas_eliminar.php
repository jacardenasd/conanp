<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin();

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');
$periodo_activo = (int)($_SESSION['periodo'] ?? 0);

function resetear_estatus_colectivas_periodo_activo(PDO $pdo, int $unidad_id, int $periodo_objetivo, int $periodo_activo): void {
    if ($periodo_activo <= 0 || $periodo_objetivo !== $periodo_activo) {
        return;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM metas_colectivas WHERE unidad_id = ? AND periodo = ?");
    $stmt->execute([$unidad_id, $periodo_objetivo]);
    $total_metas = (int)$stmt->fetchColumn();

    $nuevo_estatus = ($total_metas > 0) ? 1 : 0;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM calificaciones_colectivas WHERE unidad_id = ? AND periodo = ?");
    $stmt->execute([$unidad_id, $periodo_objetivo]);
    $existe_calificacion = (int)$stmt->fetchColumn();

    if ($existe_calificacion > 0) {
        $stmt = $pdo->prepare("UPDATE calificaciones_colectivas SET estatus = ?, resultado = 0 WHERE unidad_id = ? AND periodo = ?");
        $stmt->execute([$nuevo_estatus, $unidad_id, $periodo_objetivo]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO calificaciones_colectivas (unidad_id, periodo, estatus, resultado) VALUES (?, ?, ?, 0)");
        $stmt->execute([$unidad_id, $periodo_objetivo, $nuevo_estatus]);
    }
}

$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $pdo->prepare("SELECT unidad_id, periodo FROM metas_colectivas WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $meta = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("DELETE FROM metas_colectivas WHERE id = :id");
    $stmt->execute(['id' => $id]);

    if ($meta) {
        resetear_estatus_colectivas_periodo_activo(
            $pdo,
            (int)$meta['unidad_id'],
            (int)$meta['periodo'],
            $periodo_activo
        );
    }
}

header("Location: admin_metas_colectivas.php");
exit;
?>
