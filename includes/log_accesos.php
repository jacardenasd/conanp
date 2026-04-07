<?php

function inicializar_tabla_log_accesos(PDO $pdo)
{
    static $inicializada = false;

    if ($inicializada) {
        return;
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS log_accesos_usuarios (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT NULL,
            username VARCHAR(100) NULL,
            evento VARCHAR(30) NOT NULL,
            resultado VARCHAR(20) NOT NULL DEFAULT 'OK',
            detalle VARCHAR(255) NULL,
            ip VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_log_accesos_fecha (fecha),
            INDEX idx_log_accesos_evento (evento),
            INDEX idx_log_accesos_user_id (user_id),
            INDEX idx_log_accesos_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    } catch (Throwable $e) {
        return;
    }

    $inicializada = true;
}

function obtener_ip_cliente_log()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return trim($_SERVER['HTTP_CLIENT_IP']);
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }

    return $_SERVER['REMOTE_ADDR'] ?? null;
}

function registrar_log_acceso(PDO $pdo, $evento, $userId = null, $username = null, $resultado = 'OK', $detalle = null)
{
    inicializar_tabla_log_accesos($pdo);

    $descripcionAuditoria = trim(($detalle ?? '') . ($username ? ' | usuario: ' . $username : ''));
    if ($descripcionAuditoria === '') {
        $descripcionAuditoria = 'Evento de acceso';
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO log_accesos_usuarios
            (user_id, username, evento, resultado, detalle, ip, user_agent, fecha)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");

        $stmt->execute([
            $userId,
            $username,
            $evento,
            $resultado,
            $detalle,
            obtener_ip_cliente_log(),
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
        ]);
    } catch (Throwable $e) {
        try {
            $stmtAuditoria = $pdo->prepare("INSERT INTO auditorias (user_id, tabla, accion, descripcion, fecha, usuario_id)
                VALUES (?, 'log_accesos_usuarios', ?, ?, NOW(), ?)");
            $stmtAuditoria->execute([
                $userId,
                strtoupper((string)$evento),
                substr($descripcionAuditoria, 0, 255),
                $userId
            ]);
        } catch (Throwable $e2) {
            return;
        }
    }
}
