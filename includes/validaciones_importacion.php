<?php
/**
 * FUNCIONES DE VALIDACIÓN PARA IMPORTACIÓN DE USUARIOS
 * Sistema de Evaluación CONANP
 * Fecha: 2026-03-30
 * 
 * Propósito: Proporcionar funciones reutilizables de validación y conversión
 * para el proceso de importación de usuarios desde archivos Excel/CSV
 */

// ========================================
// VALIDACIONES DE ESTRUCTURA
// ========================================

/**
 * Valida RFC solo por presencia (sin validar estructura)
 * 
 * @param string $rfc RFC a validar
 * @param bool $permitir_vacio Si true, string vacío es válido
 * @return array ['valido' => bool, 'error' => string|null]
 */
function validar_rfc($rfc, $permitir_vacio = false) {
    if (empty(trim((string)$rfc))) {
        return [
            'valido' => $permitir_vacio,
            'error' => $permitir_vacio ? null : 'RFC es requerido'
        ];
    }

    return ['valido' => true, 'error' => null];
}

/**
 * Valida CURP solo por presencia (sin validar estructura)
 * 
 * @param string $curp CURP a validar
 * @param bool $permitir_vacio Si true, string vacío es válido
 * @return array ['valido' => bool, 'error' => string|null]
 */
function validar_curp($curp, $permitir_vacio = false) {
    if (empty(trim((string)$curp))) {
        return [
            'valido' => $permitir_vacio,
            'error' => $permitir_vacio ? null : 'CURP es requerido'
        ];
    }

    return ['valido' => true, 'error' => null];
}

/**
 * Valida formato de correo electrónico
 * 
 * @param string $correo Email a validar
 * @param bool $permitir_vacio Si true, string vacío es válido
 * @return array ['valido' => bool, 'error' => string|null]
 */
function validar_correo($correo, $permitir_vacio = false) {
    if (empty($correo)) {
        return [
            'valido' => $permitir_vacio,
            'error' => $permitir_vacio ? null : 'Correo es requerido'
        ];
    }

    $correo = strtolower(trim($correo));
    
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        return [
            'valido' => false,
            'error' => "Email inválido: '$correo'"
        ];
    }

    return ['valido' => true, 'error' => null];
}

/**
 * Valida fecha en verschillende formatos
 * Acepta: YYYY-MM-DD, DD/MM/YYYY, DD-MM-YYYY
 * 
 * @param string $fecha Fecha a validar
 * @param bool $permitir_vacio Si true, string vacío es válido
 * @return array ['valido' => bool, 'error' => string|null, 'fecha_convertida' => string|null]
 */
function validar_y_convertir_fecha($fecha, $permitir_vacio = false) {
    if (empty($fecha) || $fecha === 'NULL') {
        return [
            'valido' => $permitir_vacio,
            'error' => $permitir_vacio ? null : 'Fecha es requerida',
            'fecha_convertida' => null
        ];
    }

    $fecha = trim((string)$fecha);
    // Normalizar espacios y posibles caracteres no separables de Excel/copiado
    $fecha = str_replace("\xC2\xA0", ' ', $fecha);
    $fecha = preg_replace('/\s+/', ' ', $fecha);

    // Si trae hora (ej. 3/30/2026 00:00), conservar solo la parte de fecha
    if (strpos($fecha, ' ') !== false) {
        $partes_fecha = explode(' ', $fecha);
        $fecha = trim($partes_fecha[0]);
    }

    $fecha_convertida = null;

    // Formato Excel (numérico)
    if (is_numeric($fecha)) {
        try {
            $timestamp = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($fecha);
            $fecha_convertida = date('Y-m-d', $timestamp);
        } catch (Exception $e) {
            return [
                'valido' => false,
                'error' => "Fecha Excel inválida: $fecha",
                'fecha_convertida' => null
            ];
        }
    }
    else {
        // Probar formatos explícitos en orden de prioridad
        $formatos = ['Y-m-d', 'Y/m/d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'm-d-Y'];
        foreach ($formatos as $formato) {
            $fecha_obj = DateTime::createFromFormat('!' . $formato, $fecha);
            if ($fecha_obj && $fecha_obj->format($formato) === $fecha) {
                $fecha_convertida = $fecha_obj->format('Y-m-d');
                break;
            }
        }

        // Fallback para entradas menos estrictas
        if ($fecha_convertida === null) {
            $timestamp = strtotime($fecha);
            if ($timestamp !== false) {
                $fecha_convertida = date('Y-m-d', $timestamp);
            }
        }

        if ($fecha_convertida === null) {
        return [
            'valido' => false,
                'error' => "Formato de fecha no reconocido: '$fecha'. Use YYYY-MM-DD, DD/MM/YYYY, MM/DD/YYYY o DD-MM-YYYY",
            'fecha_convertida' => null
        ];
        }
    }

    return [
        'valido' => true,
        'error' => null,
        'fecha_convertida' => $fecha_convertida
    ];
}

/**
 * Valida que username sea RFC a 10 posiciones (REQUERIDO)
 * Formato: 4 letras + 6 dígitos (primeras 10 posiciones del RFC)
 * 
 * @param string $username Username a validar
 * @return array ['valido' => bool, 'error' => string|null, 'username_normalizado' => string|null]
 */
function validar_username($username) {
    if (empty($username)) {
        return [
            'valido' => false,
            'error' => 'Username (RFC a 10 posiciones) es requerido',
            'username_normalizado' => null
        ];
    }

    $username = strtoupper(trim($username));
    
    // RFC a 10 posiciones: 4 letras + 6 dígitos
    if (!preg_match('/^[A-ZÑ&]{4}\d{6}$/', $username)) {
        return [
            'valido' => false,
            'error' => "Username debe ser RFC a 10 posiciones: 4 letras + 6 dígitos. Formato: 'ABCD123456'",
            'username_normalizado' => null
        ];
    }

    return [
        'valido' => true,
        'error' => null,
        'username_normalizado' => $username
    ];
}

/**
 * Valida rol (debe ser 1, 2 o 3)
 * 1 = Usuario, 2 = Admin, 3 = SuperAdmin
 * 
 * @param mixed $role Role a validar
 * @return array ['valido' => bool, 'error' => string|null, 'role_numerico' => int|null]
 */
function validar_role($role) {
    if (empty($role)) {
        return [
            'valido' => false,
            'error' => 'Role es requerido',
            'role_numerico' => null
        ];
    }

    $role_numerico = intval($role);
    
    if (!in_array($role_numerico, [1, 2, 3])) {
        return [
            'valido' => false,
            'error' => "Role inválido: '$role'. Debe ser 1 (Usuario), 2 (Admin) o 3 (SuperAdmin)",
            'role_numerico' => null
        ];
    }

    return [
        'valido' => true,
        'error' => null,
        'role_numerico' => $role_numerico
    ];
}

/**
 * Valida tipo_usuario (1=SPC, 2=Primer Nivel, 3=Eventual, 4=Operativo, 5=Otro)
 * 
 * @param mixed $tipo_usuario Tipo a validar
 * @return array ['valido' => bool, 'error' => string|null, 'tipo_numerico' => int|null]
 */
function validar_tipo_usuario($tipo_usuario) {
    if (empty($tipo_usuario)) {
        return [
            'valido' => false,
            'error' => 'Tipo de usuario es requerido',
            'tipo_numerico' => null
        ];
    }

    $tipo_numerico = intval($tipo_usuario);
    
    if (!in_array($tipo_numerico, [1, 2, 3, 4, 5])) {
        return [
            'valido' => false,
            'error' => "Tipo de usuario inválido: '$tipo_usuario'. Valores válidos: 1(SPC), 2(Primer Nivel), 3(Eventual), 4(Operativo), 5(Otro)",
            'tipo_numerico' => null
        ];
    }

    return [
        'valido' => true,
        'error' => null,
        'tipo_numerico' => $tipo_numerico
    ];
}

/**
 * Valida que campos requeridos no estén vacíos
 * 
 * @param array $datos Array asociativo con datos
 * @param array $campos Nombres de campos requeridos
 * @return array ['valido' => bool, 'campos_faltantes' => array]
 */
function validar_campos_requeridos($datos, $campos = []) {
    $campos_faltantes = [];
    
    foreach ($campos as $campo) {
        if (!isset($datos[$campo]) || ($datos[$campo] === '' || $datos[$campo] === null || $datos[$campo] === 'NULL')) {
            $campos_faltantes[] = $campo;
        }
    }

    return [
        'valido' => count($campos_faltantes) === 0,
        'campos_faltantes' => $campos_faltantes
    ];
}

/**
 * Valida que un ID referenciado exista en la BD
 * 
 * @param PDO $pdo Conexión a BD
 * @param string $tabla Tabla donde buscar
 * @param string $id_campo Campo ID (ej: 'id', 'user_id')
 * @param mixed $id_valor Valor a buscar
 * @param bool $permitir_nulo Si true, null/0 es válido
 * @return array ['existe' => bool, 'error' => string|null]
 */
function validar_referencia_existe($pdo, $tabla, $id_campo, $id_valor, $permitir_nulo = false) {
    if (empty($id_valor) || $id_valor === 'NULL') {
        return [
            'existe' => $permitir_nulo,
            'error' => $permitir_nulo ? null : "ID no puede estar vacío"
        ];
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as existe FROM $tabla WHERE $id_campo = ?");
        $stmt->execute([$id_valor]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'existe' => (int)$resultado['existe'] > 0,
            'error' => (int)$resultado['existe'] > 0 ? null : "$tabla con $id_campo = '$id_valor' no existe"
        ];
    } catch (Exception $e) {
        return [
            'existe' => false,
            'error' => "Error validando referencia: " . $e->getMessage()
        ];
    }
}

/**
 * Valida que username sea único en la BD
 * 
 * @param PDO $pdo Conexión a BD
 * @param string $username Username a validar
 * @param int $excluir_user_id Si se proporciona, excluye este user_id de la validación (para updates)
 * @return array ['unico' => bool, 'error' => string|null]
 */
function validar_username_unico($pdo, $username, $excluir_user_id = null) {
    if (empty($username)) {
        return [
            'unico' => false,
            'error' => 'Username vacío'
        ];
    }

    try {
        if ($excluir_user_id) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as existe FROM usuarios WHERE username = ? AND user_id != ?");
            $stmt->execute([$username, $excluir_user_id]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) as existe FROM usuarios WHERE username = ?");
            $stmt->execute([$username]);
        }
        
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'unico' => (int)$resultado['existe'] === 0,
            'error' => (int)$resultado['existe'] > 0 ? "Username ya existe: '$username'" : null
        ];
    } catch (Exception $e) {
        return [
            'unico' => false,
            'error' => "Error validando username: " . $e->getMessage()
        ];
    }
}

/**
 * Valida que email sea único en la BD
 * 
 * @param PDO $pdo Conexión a BD
 * @param string $email Email a validar
 * @param int $excluir_user_id Si se proporciona, excluye este user_id
 * @return array ['unico' => bool, 'error' => string|null]
 */
function validar_email_unico($pdo, $email, $excluir_user_id = null) {
    if (empty($email)) {
        return [
            'unico' => true,
            'error' => null
        ];
    }

    try {
        if ($excluir_user_id) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as existe FROM usuarios WHERE LOWER(correo) = LOWER(?) AND user_id != ?");
            $stmt->execute([$email, $excluir_user_id]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) as existe FROM usuarios WHERE LOWER(correo) = LOWER(?)");
            $stmt->execute([$email]);
        }
        
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'unico' => (int)$resultado['existe'] === 0,
            'error' => (int)$resultado['existe'] > 0 ? "Email ya existe: '$email'" : null
        ];
    } catch (Exception $e) {
        return [
            'unico' => false,
            'error' => "Error validando email: " . $e->getMessage()
        ];
    }
}

/**
 * Valida sexo (H, M, Otro)
 * 
 * @param string $sexo Sexo a validar
 * @param bool $permitir_vacio Si true, string vacío es válido
 * @return array ['valido' => bool, 'error' => string|null, 'sexo_convertido' => string|null]
 */
function validar_sexo($sexo, $permitir_vacio = false) {
    if (empty($sexo) || $sexo === 'NULL') {
        if ($permitir_vacio) {
            return ['valido' => true, 'error' => null, 'sexo_convertido' => null];
        }
        return ['valido' => false, 'error' => 'Sexo es requerido', 'sexo_convertido' => null];
    }

    $sexo = strtoupper(trim($sexo));
    
    // Convertir valores comunes
    $conversiones = [
        'H' => 'H', 'HOMBRE' => 'H', 'MASCULINO' => 'H', 'MALE' => 'H', '1' => 'H',
        'M' => 'M', 'MUJER' => 'M', 'FEMENINO' => 'M', 'FEMALE' => 'M', '2' => 'M',
        'O' => 'Otro', 'OTRO' => 'Otro', 'OTHER' => 'Otro', '3' => 'Otro'
    ];

    $sexo_convertido = $conversiones[$sexo] ?? null;

    if (!$sexo_convertido) {
        return [
            'valido' => false,
            'error' => "Sexo inválido: '$sexo'. Aceptados: H (Hombre), M (Mujer), Otro",
            'sexo_convertido' => null
        ];
    }

    return [
        'valido' => true,
        'error' => null,
        'sexo_convertido' => $sexo_convertido
    ];
}

/**
 * Valida que temporal sea 0 o 1
 * 
 * @param mixed $temporal Valor a validar
 * @param bool $permitir_vacio Si true, null es válido (por defecto 0)
 * @return array ['valido' => bool, 'error' => string|null, 'temporal_numerico' => int]
 */
function validar_temporal($temporal, $permitir_vacio = false) {
    if (empty($temporal) || $temporal === 'NULL') {
        return [
            'valido' => true,
            'error' => null,
            'temporal_numerico' => 0
        ];
    }

    $temporal_numerico = intval($temporal);
    
    if (!in_array($temporal_numerico, [0, 1])) {
        return [
            'valido' => false,
            'error' => "Temporal inválido: '$temporal'. Debe ser 0 (No) o 1 (Sí)",
            'temporal_numerico' => null
        ];
    }

    return [
        'valido' => true,
        'error' => null,
        'temporal_numerico' => $temporal_numerico
    ];
}

/**
 * Sanitiza/normaliza nombre
 * 
 * @param string $nombre Nombre a sanitizar
 * @return string Nombre normalizado
 */
function sanitizar_nombre($nombre) {
    if (empty($nombre) || $nombre === 'NULL') {
        return null;
    }
    return trim($nombre);
}

/**
 * Sanitiza/normaliza apellido
 * 
 * @param string $apellido Apellido a sanitizar
 * @return string Apellido normalizado
 */
function sanitizar_apellido($apellido) {
    if (empty($apellido) || $apellido === 'NULL') {
        return null;
    }
    return trim($apellido);
}

/**
 * Convierte valor booleano (0/1, true/false, sí/no)
 * 
 * @param mixed $valor Valor a convertir
 * @return int 0 o 1
 */
function convertir_booleano($valor) {
    if (is_bool($valor)) {
        return $valor ? 1 : 0;
    }
    
    $valor_str = strtolower(trim((string)$valor));
    
    if (in_array($valor_str, ['1', 'true', 'sí', 'si', 'verdadero', 'yes', 'true'])) {
        return 1;
    }
    
    return 0;
}

/**
 * Genera hash de archivo para detectar duplicados
 * 
 * @param string $ruta_archivo Ruta al archivo
 * @return string|null Hash SHA256 del archivo o null si no existe
 */
function generar_hash_archivo($ruta_archivo) {
    if (!file_exists($ruta_archivo)) {
        return null;
    }
    return hash_file('sha256', $ruta_archivo);
}

// ========================================
// FUNCIONES DE CONSOLIDACIÓN
// ========================================

/**
 * Valida un registro completo de usuario importado
 * Realiza todas las validaciones necesarias según nueva estructura
 * 
 * CAMBIOS IMPORTANTES:
 * - username siempre es RFC a 10 posiciones (obligatorio, único)
 * - apellido_materno es obligatorio
 * - RFC, CURP, sexo son obligatorios
 * - puesto_nombre es obligatorio (convertido a MAYÚSCULAS)
 * - puesto_nivel siempre es 6 (no se solicita)
 * - role siempre es 1 (no se solicita, admin asigna después)
 * - temporal siempre es 0 (no se solicita)
 * - user_id es asignado automáticamente (no se solicita)
 * 
 * @param array $fila Fila de datos del usuario
 * @param int $numero_fila Número de fila en archivo (para referencia de error)
 * @param PDO $pdo PDO para validaciones de referencia
 * @return array ['valido' => bool, 'errores' => array, 'data_procesada' => array|null]
 */
function validar_registro_usuario_completo($fila, $numero_fila, $pdo) {
    $errores = [];
    $data_procesada = [];
    
    // ==========================================
    // VALIDAR CAMPOS REQUERIDOS
    // ==========================================
    
    $campos_requeridos = ['username', 'nombre', 'apellido_paterno', 'apellido_materno', 
                          'RFC', 'CURP', 'sexo', 'puesto_nombre', 'unidad_id', 'adscripcion_id'];
    
    $validacion_reqs = validar_campos_requeridos($fila, $campos_requeridos);
    if (!$validacion_reqs['valido']) {
        foreach ($validacion_reqs['campos_faltantes'] as $campo) {
            $errores[] = "Campo requerido vacío: $campo";
        }
    }

    // ==========================================
    // USERNAME = RFC A 10 POSICIONES
    // ==========================================
    
    $val_username = validar_username($fila['username'] ?? '');
    if (!$val_username['valido']) {
        $errores[] = $val_username['error'];
    } else {
        $username_normalizado = $val_username['username_normalizado'];
        $data_procesada['username'] = $username_normalizado;
        
        // Validar unicidad
        $val_unico = validar_username_unico($pdo, $username_normalizado);
        if (!$val_unico['unico']) {
            // Si no es único, probablemente es una actualización (usuario existe por RFC)
            // No es error, se actualizará
        }
    }

    // ==========================================
    // NOMBRE
    // ==========================================
    
    $nombre = sanitizar_nombre($fila['nombre'] ?? '');
    if (empty($nombre)) {
        $errores[] = "Nombre no puede estar vacío";
    } else {
        $data_procesada['nombre'] = $nombre;
    }

    // ==========================================
    // APELLIDO PATERNO
    // ==========================================
    
    $ap_pat = sanitizar_apellido($fila['apellido_paterno'] ?? '');
    if (empty($ap_pat)) {
        $errores[] = "Apellido paterno no puede estar vacío";
    } else {
        $data_procesada['apellido_paterno'] = $ap_pat;
    }

    // ==========================================
    // APELLIDO MATERNO (OBLIGATORIO)
    // ==========================================
    
    $ap_mat = sanitizar_apellido($fila['apellido_materno'] ?? '');
    if (empty($ap_mat)) {
        $errores[] = "Apellido materno es requerido";
    } else {
        $data_procesada['apellido_materno'] = $ap_mat;
    }

    // ==========================================
    // RFC (OBLIGATORIO)
    // ==========================================
    
    $val_rfc = validar_rfc($fila['RFC'] ?? '', false);  // false = no permitir vacío
    if (!$val_rfc['valido']) {
        $errores[] = $val_rfc['error'];
    } else {
        $data_procesada['RFC'] = strtoupper(trim($fila['RFC']));
    }

    // ==========================================
    // CURP (OBLIGATORIO)
    // ==========================================
    
    $val_curp = validar_curp($fila['CURP'] ?? '', false);  // false = no permitir vacío
    if (!$val_curp['valido']) {
        $errores[] = $val_curp['error'];
    } else {
        $data_procesada['CURP'] = strtoupper(trim($fila['CURP']));
    }

    // ==========================================
    // SEXO (OBLIGATORIO)
    // ==========================================
    
    $val_sexo = validar_sexo($fila['sexo'] ?? '', false);  // false = no permitir vacío
    if (!$val_sexo['valido']) {
        $errores[] = $val_sexo['error'];
    } else {
        $data_procesada['sexo'] = $val_sexo['sexo_convertido'];
    }

    // ==========================================
    // CORREO
    // ==========================================
    // ==========================================
    // CORREO (opcional)
    // ==========================================
    
    if (!empty($fila['correo'] ?? '')) {
        $val_correo = validar_correo($fila['correo']);
        if (!$val_correo['valido']) {
            $errores[] = $val_correo['error'];
        } else {
            $data_procesada['correo'] = strtolower(trim($fila['correo']));
            
            // Validar email único (si es nuevo)
            $val_email_unico = validar_email_unico($pdo, $data_procesada['correo']);
            if (!$val_email_unico['unico']) {
                $errores[] = $val_email_unico['error'];
            }
        }
    }

    // ==========================================
    // IDRUSP (opcional)
    // ==========================================
    
    if (!empty($fila['IDRUSP'] ?? '')) {
        $data_procesada['IDRUSP'] = trim($fila['IDRUSP']);
    }

    // ==========================================
    // PUESTO_NOMBRE (OBLIGATORIO, convertir a MAYÚSCULAS)
    // ==========================================
    
    $puesto_nombre = $fila['puesto_nombre'] ?? '';
    if (empty($puesto_nombre)) {
        $errores[] = "Puesto/Nombre es requerido";
    } else {
        $data_procesada['puesto_nombre'] = strtoupper(trim($puesto_nombre));
    }

    // ==========================================
    // PUESTO_CÓDIGO (opcional)
    // ==========================================
    
    if (!empty($fila['puesto_codigo'] ?? '')) {
        $data_procesada['puesto_codigo'] = trim($fila['puesto_codigo']);
    }

    // ==========================================
    // UNIDAD_ID (OBLIGATORIO, debe existir)
    // ==========================================
    
    $unidad_id = $fila['unidad_id'] ?? '';
    if (empty($unidad_id)) {
        $errores[] = "Unidad es requerida";
    } else {
        $val_unidad = validar_referencia_existe($pdo, 'unidades', 'id', $unidad_id, false);
        if (!$val_unidad['existe']) {
            $errores[] = "Unidad ID inválida: {$unidad_id} no existe en catálogo";
        } else {
            $data_procesada['unidad_id'] = intval($unidad_id);
        }
    }

    // ==========================================
    // ADSCRIPCIÓN_ID (OBLIGATORIO, debe existir)
    // ==========================================
    
    $adscripcion_id = $fila['adscripcion_id'] ?? '';
    if (empty($adscripcion_id)) {
        $errores[] = "Adscripción es requerida";
    } else {
        $val_ads = validar_referencia_existe($pdo, 'adscripciones', 'id', $adscripcion_id, false);
        if (!$val_ads['existe']) {
            $errores[] = "Adscripción ID inválida: {$adscripcion_id} no existe en catálogo";
        } else {
            $data_procesada['adscripcion_id'] = intval($adscripcion_id);
        }
    }

    // ==========================================
    // JEFE_ID (opcional pero si existe debe ser válido)
    // ==========================================
    
    if (!empty($fila['jefe_id'] ?? '')) {
        $val_jefe = validar_referencia_existe($pdo, 'usuarios', 'user_id', $fila['jefe_id'], true);
        if (!$val_jefe['existe'] && $fila['jefe_id'] != 0) {
            $errores[] = "Jefe ID inválido: {$fila['jefe_id']} no existe en BD";
        } else if ($val_jefe['existe']) {
            $data_procesada['jefe_id'] = intval($fila['jefe_id']);
        }
    }

    // ==========================================
    // FECHA_ALTA (opcional)
    // ==========================================

    if (!empty($fila['fecha_alta'] ?? '')) {
        $val_fecha = validar_y_convertir_fecha($fila['fecha_alta'], true);
        if (!$val_fecha['valido']) {
            $errores[] = $val_fecha['error'];
        } else {
            $data_procesada['fecha_alta'] = $val_fecha['fecha_convertida'];
        }
    }

    // ==========================================
    // CAMPOS QUE SIEMPRE TIENEN VALOR FIJO
    // ==========================================
    
    // role siempre 1 (Usuario normal)
    $data_procesada['role'] = 1;
    
    // temporal siempre 0 (No es temporal)
    $data_procesada['temporal'] = 0;
    
    // puesto_nivel siempre 6
    $data_procesada['puesto_nivel'] = 6;
    
    // requiere_cambio_password siempre 1 para actualizaciones (se verifica en usuarios_importar.php)
    // password será RFC a 10 posiciones (username)

    return [
        'valido' => count($errores) === 0,
        'errores' => $errores,
        'data_procesada' => $data_procesada,
        'numero_fila' => $numero_fila
    ];
}

?>
