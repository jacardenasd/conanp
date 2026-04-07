<?php

require 'config/db.php';

function obtener_variable($nombre) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT valor FROM variables WHERE nombre = ?");
    $stmt->execute([$nombre]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['valor'] : null;
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

if (!function_exists('asegurar_columna_capacitacion_contabiliza')) {
    function asegurar_columna_capacitacion_contabiliza(PDO $pdo): void {
        static $columna_asegurada = false;
        if ($columna_asegurada) {
            return;
        }

        $stmt = $pdo->query("SHOW COLUMNS FROM capacitacion LIKE 'contabiliza_horas'");
        $columna = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$columna) {
            $pdo->exec("ALTER TABLE capacitacion ADD COLUMN contabiliza_horas TINYINT(1) NOT NULL DEFAULT 1 AFTER validado");
        }

        $columna_asegurada = true;
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

        // Sincronización temporal con variables legacy para compatibilidad.
        $nombre_variable = $base_variable . '_' . $periodo;
        $stmtLegacy = $pdo->prepare("INSERT INTO variables (nombre, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        $stmtLegacy->execute([$nombre_variable, (string)$bloqueado]);
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

        $nombre_variable = $base_variable . '_' . $periodo;
        $valor = obtener_variable($nombre_variable);

        if ($valor === null || $valor === '') {
            // Compatibilidad con esquemas previos (principalmente 2025).
            if ($periodo === 2025) {
                $legacy = obtener_variable($base_variable . '_2025');
                if ($legacy !== null && $legacy !== '') {
                    $valor = $legacy;
                }
            }
        }

        if ($valor === null || $valor === '') {
            $valor = $default;
        }

        return (string)$valor;
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

function limpiar_nombre_archivo($nombre_original) {
    $nombre = pathinfo($nombre_original, PATHINFO_FILENAME);
    $extension = pathinfo($nombre_original, PATHINFO_EXTENSION);
    $nombre = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nombre);
    $nombre = strtolower($nombre);
    return time() . '_' . $nombre . '.' . $extension;
}

function convertir_urls_en_links($texto) {
    // Detecta URLs que comienzan con http o https
    $patron = '/(https?:\/\/[^\s]+)/i';
    $reemplazo = '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>';
    return preg_replace($patron, $reemplazo, $texto);
}


function verificarDatosUsuario($pdo, $user_id) {
    // Consulta usando PDO
    $stmt = $pdo->prepare("
        SELECT jefe_id, adscripcion_id, unidad_id, puesto_nombre 
        FROM usuarios 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $datos = $stmt->fetch(PDO::FETCH_ASSOC);

    // Cambio #3: Se eliminó la validación que redirige a editar_datos_personales.php
    // La función ahora solo retorna los datos del usuario
    
    // Retornar datos (pueden estar incompletos pero no forzamos redirección)
    return $datos;
}

function obtener_estatus_prerequisitos_colaborador(PDO $pdo, int $colaborador_id, int $periodo): array {
    $stmt = $pdo->prepare("SELECT estatus_metas, estatus_gerenciales FROM calificaciones WHERE user_id = ? AND periodo = ?");
    $stmt->execute([$colaborador_id, $periodo]);
    $estatus = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $estatus_metas = (int)($estatus['estatus_metas'] ?? 0);
    $estatus_gerenciales = (int)($estatus['estatus_gerenciales'] ?? 0);

    $stmt = $pdo->prepare("SELECT COUNT(*) AS total_metas,
                                  SUM(CASE WHEN resultado IS NOT NULL AND resultado <> '' AND resultado <> 0 THEN 1 ELSE 0 END) AS metas_con_propuesta
                           FROM metas
                           WHERE user_id = ? AND periodo = ?");
    $stmt->execute([$colaborador_id, $periodo]);
    $metas = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $total_metas = (int)($metas['total_metas'] ?? 0);
    $metas_con_propuesta = (int)($metas['metas_con_propuesta'] ?? 0);
    $metas_listas = ($total_metas > 0 && $metas_con_propuesta >= $total_metas);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM competencias_evaluacion WHERE user_id = ? AND periodo = ? AND tipo = 'auto'");
    $stmt->execute([$colaborador_id, $periodo]);
    $autoeval_registros = (int)($stmt->fetchColumn() ?? 0);
    $gerenciales_listas = ($autoeval_registros > 0);

    $estatus_metas_efectivo = max($estatus_metas, $metas_listas ? 2 : 0);
    $estatus_gerenciales_efectivo = max($estatus_gerenciales, $gerenciales_listas ? 2 : 0);

    return [
        'estatus_metas' => $estatus_metas,
        'estatus_gerenciales' => $estatus_gerenciales,
        'estatus_metas_efectivo' => $estatus_metas_efectivo,
        'estatus_gerenciales_efectivo' => $estatus_gerenciales_efectivo,
        'metas_listas' => $metas_listas,
        'gerenciales_listas' => $gerenciales_listas,
        'puede_evaluar_jefe' => ($estatus_metas_efectivo >= 2 && $estatus_gerenciales_efectivo >= 2),
    ];
}

?>