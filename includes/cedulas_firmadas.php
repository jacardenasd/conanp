<?php

function asegurar_tabla_cedulas_firmadas(PDO $pdo): void {
    $sql = "CREATE TABLE IF NOT EXISTS cedulas_firmadas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        periodo INT NOT NULL,
        nombre_archivo VARCHAR(255) NOT NULL,
        fecha_subida DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_user_periodo (user_id, periodo),
        KEY idx_periodo (periodo),
        KEY idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";

    $pdo->exec($sql);
}
