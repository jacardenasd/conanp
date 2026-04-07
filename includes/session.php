<?php
session_start();
function checkLogin($requiredRole = null) {
    if (!isset($_SESSION['user_id'])) {
        $current_url = $_SERVER['REQUEST_URI'];
        $_SESSION['redirect_after_login'] = $current_url;
   
        header("Location: login.php");
        exit();
    }
    if ($requiredRole !== null && $_SESSION['role'] < $requiredRole) {
        header("Location: index.php?info=1");
        exit();
    }
}

if (!function_exists('asegurar_columna_capacitacion_contabiliza')) {
    function asegurar_columna_capacitacion_contabiliza(PDO $pdo): void {
        $stmt = $pdo->query("SHOW COLUMNS FROM capacitacion LIKE 'contabiliza_horas'");
        $columna = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$columna) {
            $pdo->exec("ALTER TABLE capacitacion ADD COLUMN contabiliza_horas TINYINT(1) NOT NULL DEFAULT 1 AFTER validado");
        }
    }
}

if (!function_exists('asegurar_tabla_estatus_captura')) {
    function asegurar_tabla_estatus_captura(PDO $pdo): void {
        static $tabla_asegurada = false;
        if ($tabla_asegurada) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS estatus_captura_periodo (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            periodo INT NOT NULL,
            modulo VARCHAR(120) NOT NULL,
            bloqueado TINYINT(1) NOT NULL DEFAULT 0,
            actualizado_por INT NULL,
            fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_periodo_modulo (periodo, modulo),
            KEY idx_periodo (periodo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8";

        $pdo->exec($sql);
        $tabla_asegurada = true;
    }
}

if (!function_exists('guardar_bloqueo_por_periodo')) {
    function guardar_bloqueo_por_periodo($base_variable, $periodo, $bloqueado, $usuario_id = null): void {
        global $pdo;
        asegurar_tabla_estatus_captura($pdo);

        $periodo = (int)$periodo;
        $bloqueado = ((int)$bloqueado === 1) ? 1 : 0;
        $usuario_id = ($usuario_id !== null) ? (int)$usuario_id : null;

        $stmt = $pdo->prepare("INSERT INTO estatus_captura_periodo (periodo, modulo, bloqueado, actualizado_por)
                               VALUES (?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE
                                   bloqueado = VALUES(bloqueado),
                                   actualizado_por = VALUES(actualizado_por),
                                   fecha_actualizacion = CURRENT_TIMESTAMP");
        $stmt->execute([$periodo, $base_variable, $bloqueado, $usuario_id]);

        if (function_exists('obtener_variable')) {
            $nombre_variable = $base_variable . '_' . $periodo;
            $stmtLegacy = $pdo->prepare("INSERT INTO variables (nombre, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
            $stmtLegacy->execute([$nombre_variable, (string)$bloqueado]);
        }
    }
}

if (!function_exists('obtener_bloqueo_por_periodo')) {
    function obtener_bloqueo_por_periodo($base_variable, $periodo, $default = '0') {
        global $pdo;

        $periodo = (int)$periodo;
        asegurar_tabla_estatus_captura($pdo);

        $stmt = $pdo->prepare("SELECT bloqueado FROM estatus_captura_periodo WHERE periodo = ? AND modulo = ? LIMIT 1");
        $stmt->execute([$periodo, $base_variable]);
        $valor_tabla = $stmt->fetchColumn();

        if ($valor_tabla !== false && $valor_tabla !== null && $valor_tabla !== '') {
            return ((int)$valor_tabla === 1) ? '1' : '0';
        }

        if (function_exists('obtener_variable')) {
            $nombre_variable = $base_variable . '_' . $periodo;
            $valor = obtener_variable($nombre_variable);
            if ($valor !== null && $valor !== '') {
                return (string)$valor;
            }
        }

        return (string)$default;
    }
}

if (!function_exists('configuracion_estatus_captura')) {
    function configuracion_estatus_captura() {
        return [
            [
                'base' => 'bloquear_capacitacion',
                'titulo' => 'Captura de Capacitacion',
                'descripcion' => 'Permite o bloquea la captura/edicion de cursos para el periodo seleccionado.',
                'default' => '0',
            ],
            [
                'base' => 'bloquear_metas_individuales',
                'titulo' => 'Captura de Metas Individuales',
                'descripcion' => 'Permite o bloquea alta/edicion/eliminacion de metas individuales del periodo.',
                'default' => '0',
            ],
            [
                'base' => 'bloquear_metas_colectivas',
                'titulo' => 'Captura de Metas Colectivas',
                'descripcion' => 'Permite o bloquea alta/edicion/eliminacion de metas colectivas del periodo.',
                'default' => '0',
            ],
            [
                'base' => 'bloquear_cedula_firmada',
                'titulo' => 'Descarga/Carga de Cedula Firmada',
                'descripcion' => 'Controla la disponibilidad del proceso de cedula firmada para el periodo.',
                'default' => '0',
            ],
        ];
    }
}

if (!function_exists('modulo_bloqueado_por_periodo')) {
    function modulo_bloqueado_por_periodo($modulo, $periodo) {
        $mapa_modulos = [
            'capacitacion' => ['base' => 'bloquear_capacitacion', 'default' => '0'],
            'metas_individuales' => ['base' => 'bloquear_metas_individuales', 'default' => '0'],
            'metas_colectivas' => ['base' => 'bloquear_metas_colectivas', 'default' => '0'],
            'cedula_firmada' => ['base' => 'bloquear_cedula_firmada', 'default' => '0'],
            'pdf_metas_individuales' => ['base' => 'bloquear_pdf_metas_individuales', 'default' => '0'],
        ];

        if (!isset($mapa_modulos[$modulo])) {
            return false;
        }

        $config = $mapa_modulos[$modulo];
        $valor = obtener_bloqueo_por_periodo($config['base'], (int)$periodo, $config['default']);

        return ((string)$valor === '1');
    }
}
?>