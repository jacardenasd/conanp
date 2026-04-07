<?php

function asegurar_tabla_ejes_pnd(PDO $pdo): void {
    $sql = "CREATE TABLE IF NOT EXISTS ejes_pnd (
        id INT(11) NOT NULL AUTO_INCREMENT,
        nombre VARCHAR(255) NOT NULL,
        orden INT(11) NOT NULL DEFAULT 0,
        estatus TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";

    $pdo->exec($sql);

    $total = (int)$pdo->query("SELECT COUNT(*) FROM ejes_pnd")->fetchColumn();
    if ($total === 0) {
        $semillas = [
            [1, 'Gobernanza con justicia y participación ciudadana.'],
            [2, 'Desarrollo con bienestar y humanismo.'],
            [3, 'Economía moral y trabajo.'],
            [4, 'Desarrollo sustentable.']
        ];

        $stmt = $pdo->prepare("INSERT INTO ejes_pnd (orden, nombre, estatus) VALUES (?, ?, 1)");
        foreach ($semillas as $item) {
            $stmt->execute([$item[0], $item[1]]);
        }
    }
}

function asegurar_columna_eje_pnd_metas_colectivas(PDO $pdo): void {
    $db = (string)$pdo->query("SELECT DATABASE()")->fetchColumn();
    if ($db === '') {
        return;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'metas_colectivas' AND COLUMN_NAME = 'eje_pnd_id'");
    $stmt->execute([$db]);
    $existe = (int)$stmt->fetchColumn() > 0;

    if (!$existe) {
        $pdo->exec("ALTER TABLE metas_colectivas ADD COLUMN eje_pnd_id INT(11) NULL DEFAULT NULL AFTER instrumento");
    }
}

function obtener_ejes_pnd(PDO $pdo, bool $soloActivos = true): array {
    asegurar_tabla_ejes_pnd($pdo);

    $sql = "SELECT id, nombre, orden, estatus FROM ejes_pnd";
    if ($soloActivos) {
        $sql .= " WHERE estatus = 1";
    }
    $sql .= " ORDER BY orden ASC, nombre ASC";

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
