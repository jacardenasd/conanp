<?php

function asegurar_tabla_instrumentos_pnd(PDO $pdo): void {
    $sql = "CREATE TABLE IF NOT EXISTS instrumentos_pnd (
        id INT(11) NOT NULL AUTO_INCREMENT,
        nombre VARCHAR(255) NOT NULL,
        orden INT(11) NOT NULL DEFAULT 0,
        estatus TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";

    $pdo->exec($sql);

    $total = (int)$pdo->query("SELECT COUNT(*) FROM instrumentos_pnd")->fetchColumn();
    $instrumentos_base = [
        [1, 'Atribuciones de Reglamento Interior (ARI)'],
        [2, 'Programa Operativo Anual (POA)'],
        [3, 'Programa Sectorial (PS)']
    ];

    if ($total === 0) {
        $stmt = $pdo->prepare("INSERT INTO instrumentos_pnd (orden, nombre, estatus) VALUES (?, ?, 1)");
        foreach ($instrumentos_base as $item) {
            $stmt->execute([$item[0], $item[1]]);
        }
    } else {
        $stmtExiste = $pdo->prepare("SELECT id FROM instrumentos_pnd WHERE nombre = ? LIMIT 1");
        $stmtInsert = $pdo->prepare("INSERT INTO instrumentos_pnd (orden, nombre, estatus) VALUES (?, ?, 1)");
        $stmtActivar = $pdo->prepare("UPDATE instrumentos_pnd SET estatus = 1 WHERE nombre = ?");

        foreach ($instrumentos_base as $item) {
            $stmtExiste->execute([$item[1]]);
            $id = (int)$stmtExiste->fetchColumn();
            if ($id > 0) {
                $stmtActivar->execute([$item[1]]);
            } else {
                $stmtInsert->execute([$item[0], $item[1]]);
            }
        }

        $instrumentos_pnd_previos = [
            'Gobernanza con justicia y participación ciudadana.',
            'Desarrollo con bienestar y humanismo.',
            'Economía moral y trabajo.',
            'Desarrollo sustentable.'
        ];
        $stmtInactivar = $pdo->prepare("UPDATE instrumentos_pnd SET estatus = 0 WHERE nombre = ?");
        foreach ($instrumentos_pnd_previos as $nombre_pnd) {
            $stmtInactivar->execute([$nombre_pnd]);
        }
    }
}

function obtener_instrumentos_pnd(PDO $pdo, bool $soloActivos = true): array {
    asegurar_tabla_instrumentos_pnd($pdo);

    $sql = "SELECT id, nombre, orden, estatus FROM instrumentos_pnd";
    if ($soloActivos) {
        $sql .= " WHERE estatus = 1";
    }
    $sql .= " ORDER BY orden ASC, nombre ASC";

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtener_mapa_instrumentos_pnd(PDO $pdo, bool $soloActivos = false): array {
    $rows = obtener_instrumentos_pnd($pdo, $soloActivos);
    $map = [];
    foreach ($rows as $row) {
        $map[(int)$row['id']] = $row['nombre'];
    }
    return $map;
}
