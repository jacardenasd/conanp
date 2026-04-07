<?php
require 'config/db.php';
require 'includes/session.php';
checkLogin(2);

$periodo = $_POST['periodo'] ?? null;
if (!$periodo) {
    die("Periodo no especificado");
}

$estatus_metas = $_POST['estatus_metas'] ?? [];
$estatus_ae = $_POST['estatus_ae'] ?? [];
$estatus_ad = $_POST['estatus_ad'] ?? [];
$estatus_gerenciales = $_POST['estatus_gerenciales'] ?? [];

foreach ($estatus_metas as $user_id => $valor) {
    $user_id = (int)$user_id;
    $nuevo_estatus_metas = (int)$valor;
    $nuevo_estatus_ae = (int)($estatus_ae[$user_id] ?? 0);
    $nuevo_estatus_ad = (int)($estatus_ad[$user_id] ?? 0);
    $nuevo_estatus_gerenciales = (int)($estatus_gerenciales[$user_id] ?? 0);

    $stmt = $pdo->prepare("SELECT id FROM calificaciones WHERE user_id = ? AND periodo = ?");
    $stmt->execute([$user_id, $periodo]);
    $existe = $stmt->fetchColumn();

    $prev_estatus = [
        'estatus_actividades_extraordinarias' => 0,
        'estatus_aportaciones_destacadas' => 0,
        'estatus_gerenciales' => 0,
    ];
    $stmt_prev = $pdo->prepare("SELECT estatus_actividades_extraordinarias, estatus_aportaciones_destacadas, estatus_gerenciales FROM calificaciones WHERE user_id = ? AND periodo = ?");
    $stmt_prev->execute([$user_id, $periodo]);
    $prev_row = $stmt_prev->fetch(PDO::FETCH_ASSOC);
    if ($prev_row) {
        $prev_estatus = $prev_row;
    }

    if ($existe) {
        $update = $pdo->prepare("UPDATE calificaciones SET 
            estatus_metas = :estatus_metas,
            estatus_actividades_extraordinarias = :estatus_ae,
            estatus_aportaciones_destacadas = :estatus_ad,
            estatus_gerenciales = :estatus_gerenciales
            WHERE user_id = :user_id AND periodo = :periodo");
    } else {
        $update = $pdo->prepare("INSERT INTO calificaciones 
            (user_id, periodo, estatus_metas, estatus_actividades_extraordinarias, estatus_aportaciones_destacadas, estatus_gerenciales)
            VALUES (:user_id, :periodo, :estatus_metas, :estatus_ae, :estatus_ad, :estatus_gerenciales)");
    }

    $update->execute([
        'estatus_metas' => $nuevo_estatus_metas,
        'estatus_ae' => $nuevo_estatus_ae,
        'estatus_ad' => $nuevo_estatus_ad,
        'estatus_gerenciales' => $nuevo_estatus_gerenciales,
        'user_id' => $user_id,
        'periodo' => $periodo
    ]);

    // Reapertura efectiva para validaciones del jefe inmediato
    $prev_ae = (int)($prev_estatus['estatus_actividades_extraordinarias'] ?? 0);
    $prev_ad = (int)($prev_estatus['estatus_aportaciones_destacadas'] ?? 0);
    if ($prev_ae >= 1 && $nuevo_estatus_ae === 0) {
        $pdo->prepare("UPDATE calificaciones SET actividades_extraordinarias = 0 WHERE user_id = ? AND periodo = ?")
            ->execute([$user_id, $periodo]);

        $stmt_msg_ae = $pdo->prepare("INSERT INTO mensajes (remitente_id, destinatario_id, asunto, mensaje, fecha, leido) VALUES (?, ?, ?, ?, NOW(), 0)");
        $stmt_msg_ae->execute([
            $_SESSION['user_id'],
            $user_id,
            "Reapertura de Actividades Extraordinarias periodo {$periodo}",
            "Se habilito nuevamente la validacion de Actividades Extraordinarias para el periodo {$periodo}."
        ]);
    }
    if ($prev_ad >= 1 && $nuevo_estatus_ad === 0) {
        $pdo->prepare("UPDATE calificaciones SET aportaciones_destacadas = 0 WHERE user_id = ? AND periodo = ?")
            ->execute([$user_id, $periodo]);

        $stmt_msg_ad = $pdo->prepare("INSERT INTO mensajes (remitente_id, destinatario_id, asunto, mensaje, fecha, leido) VALUES (?, ?, ?, ?, NOW(), 0)");
        $stmt_msg_ad->execute([
            $_SESSION['user_id'],
            $user_id,
            "Reapertura de Aportaciones Destacadas periodo {$periodo}",
            "Se habilito nuevamente la validacion de Aportaciones Destacadas para el periodo {$periodo}."
        ]);
    }

    // Reapertura efectiva de autoevaluacion gerencial
    $prev_ger = (int)($prev_estatus['estatus_gerenciales'] ?? 0);
    $requiere_reapertura_gerencial = false;
    if ($nuevo_estatus_gerenciales <= 1) {
        $stmt_bloqueo = $pdo->prepare("SELECT COUNT(*) FROM competencias_evaluacion WHERE user_id = ? AND periodo = ? AND tipo = 'auto' AND bloqueado_edicion = 1");
        $stmt_bloqueo->execute([$user_id, $periodo]);
        $tiene_bloqueo_auto = (int)$stmt_bloqueo->fetchColumn() > 0;

        $stmt_jefe = $pdo->prepare("SELECT COUNT(*) FROM competencias_evaluacion WHERE user_id = ? AND periodo = ? AND tipo = 'jefe'");
        $stmt_jefe->execute([$user_id, $periodo]);
        $tiene_eval_jefe = (int)$stmt_jefe->fetchColumn() > 0;

        $requiere_reapertura_gerencial = ($prev_ger >= 2) || $tiene_bloqueo_auto || $tiene_eval_jefe;
    }

    if ($requiere_reapertura_gerencial) {
        $pdo->prepare("UPDATE competencias_evaluacion SET bloqueado_edicion = 0 WHERE user_id = ? AND periodo = ? AND tipo = 'auto'")
            ->execute([$user_id, $periodo]);

        $pdo->prepare("DELETE FROM competencias_evaluacion WHERE user_id = ? AND periodo = ? AND tipo = 'jefe'")
            ->execute([$user_id, $periodo]);

        $pdo->prepare("UPDATE calificaciones SET gerenciales = 0 WHERE user_id = ? AND periodo = ?")
            ->execute([$user_id, $periodo]);

        $stmt_msg_ger = $pdo->prepare("INSERT INTO mensajes (remitente_id, destinatario_id, asunto, mensaje, fecha, leido) VALUES (?, ?, ?, ?, NOW(), 0)");
        $stmt_msg_ger->execute([
            $_SESSION['user_id'],
            $user_id,
            "Reapertura de Autoevaluacion Gerencial periodo {$periodo}",
            "Se habilito nuevamente tu autoevaluacion gerencial para el periodo {$periodo}."
        ]);
    }
}

header("Location: admin_calificaciones.php?periodo=$periodo&info=1");
exit;
