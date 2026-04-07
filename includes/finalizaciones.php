<?php
/**
 * Helper para gestionar finalizaciones y validaciones
 * Sistema de Evaluación del Desempeño CONANP
 * Cambios cliente Enero 2026
 */

/**
 * Finalizar metas individuales (bloquear edición post-PDF)
 * Solo el usuario o admin puede finalizarlas
 */
function finalizar_metas_pdf($pdo, $user_id, $periodo, $usuario_id_finalizacion) {
    try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM calificaciones WHERE user_id = ? AND periodo = ?");
            $stmt->execute([$user_id, $periodo]);
            $existe = (int)$stmt->fetchColumn() > 0;

            if ($existe) {
                $stmt = $pdo->prepare("
                    UPDATE calificaciones 
                    SET finalizado_metas = 1,
                        fecha_finalizacion_metas = NOW(),
                        usuario_finalizacion_id = ?
                    WHERE user_id = ? AND periodo = ?
                ");
                return $stmt->execute([$usuario_id_finalizacion, $user_id, $periodo]);
            }

            $stmt = $pdo->prepare("
                INSERT INTO calificaciones (user_id, periodo, finalizado_metas, fecha_finalizacion_metas, usuario_finalizacion_id)
                VALUES (?, ?, 1, NOW(), ?)
            ");
            return $stmt->execute([$user_id, $periodo, $usuario_id_finalizacion]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Verificar si metas están finalizadas (bloqueadas de edición)
 */
function metas_finalizadas($pdo, $user_id, $periodo) {
    $stmt = $pdo->prepare("
        SELECT finalizado_metas FROM calificaciones 
        WHERE user_id = ? AND periodo = ?
    ");
    $stmt->execute([$user_id, $periodo]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result && $result['finalizado_metas'] == 1;
}

/**
 * Marcar metas como "usuario terminó captura" (bloqueo hacia usuario)
 */
function usuario_termino_captura($pdo, $meta_id, $user_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE metas 
            SET usuario_termino_captura = 1,
                fecha_termino_usuario = NOW()
            WHERE id = ? AND user_id = ?
        ");
        return $stmt->execute([$meta_id, $user_id]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Marcar metas como "jefe evaluó" (bloqueo definitivo)
 */
function jefe_termino_evaluacion($pdo, $meta_id, $jefe_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE metas 
            SET jefe_evaluo = 1,
                fecha_evaluacion_jefe = NOW(),
                metas_finalizadas = 1
            WHERE id = ? AND ? IN (
                SELECT jefe_id FROM usuarios WHERE user_id = ?
            )
        ");
        return $stmt->execute([$meta_id, $jefe_id, $meta_id]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Validar actividad extraordinaria o aportación destacada por jefe
 */
function validar_por_jefe($pdo, $tabla, $registro_id, $jefe_id, $estado = 1) {
    try {
        $stmt = $pdo->prepare("
            UPDATE $tabla 
            SET validado = ?,
                usuario_validacion_jefe_id = ?,
                fecha_validacion_jefe = NOW(),
                rechazado_por_jefe = ?
            WHERE id = ?
        ");
        $rechazado = ($estado == 0) ? 1 : 0;
        return $stmt->execute([$estado, $jefe_id, $rechazado, $registro_id]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Validar por RH y permitir que super admin revoque
 */
function validar_por_rh($pdo, $tabla, $registro_id, $rh_id, $estado = 1) {
    try {
        $stmt = $pdo->prepare("
            UPDATE $tabla 
            SET validado_rh = ?,
                usuario_validacion_rh_id = ?,
                fecha_validacion_rh = NOW(),
                rechazado_por_rh = ?
            WHERE id = ?
        ");
        $rechazado = ($estado == 0) ? 1 : 0;
        return $stmt->execute([$estado, $rh_id, $rechazado, $registro_id]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Quitar validación de RH (solo super admin)
 */
function revocar_validacion_rh($pdo, $tabla, $registro_id, $super_admin_id) {
    try {
        $permitir = obtener_variable('permitir_devalidacion_rh') ?? '1';
        if ($permitir != 1) {
            return false;
        }
        
        $stmt = $pdo->prepare("
            UPDATE $tabla 
            SET validado_rh = 0,
                usuario_validacion_rh_id = ?,
                fecha_validacion_rh = NOW(),
                motivo_devalidacion = 'Revocada por Super Admin',
                rechazado_por_rh = 0
            WHERE id = ?
        ");
        return $stmt->execute([$super_admin_id, $registro_id]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Bloquear edición de competencias tras validación del jefe
 */
function bloquear_competencias_evaluacion($pdo, $user_id, $periodo, $jefe_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE competencias_evaluacion 
            SET bloqueado_edicion = 1,
                fecha_validacion_jefe = NOW(),
                usuario_validacion_jefe_id = ?
            WHERE user_id = ? AND periodo = ? AND tipo = 'auto'
        ");
        return $stmt->execute([$jefe_id, $user_id, $periodo]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Verificar si registro está bloqueado para captura (período 2025)
 */
function registro_bloqueado($pdo, $tabla, $periodo) {
    if ($tabla == 'capacitacion') {
        $clave = 'bloquear_capacitacion_' . (int)$periodo;
        $bloquear = obtener_variable($clave);
        if ($bloquear === null || $bloquear === '') {
            $bloquear = ((int)$periodo === 2025)
                ? (obtener_variable('bloquear_capacitacion_2025') ?? '1')
                : '0';
        }
        return (string)$bloquear === '1';
    } elseif ($tabla == 'metas_colectivas') {
        $clave = 'bloquear_metas_colectivas_' . (int)$periodo;
        $bloquear = obtener_variable($clave);
        if ($bloquear === null || $bloquear === '') {
            $bloquear = ((int)$periodo === 2025)
                ? (obtener_variable('bloquear_metas_colectivas_2025') ?? '1')
                : '0';
        }
        return (string)$bloquear === '1';
    }
    return false;
}

/**
 * RECALCULAR SEMÁFOROS Y PUNTAJES DE APORTACIONES/ACTIVIDADES
 * Cambio cliente Febrero 2026 - Problema F2
 * Se debe ejecutar después de eliminar un registro para actualizar estados
 */
function recalcular_semaforo_aportaciones_actividades($pdo, $user_id, $periodo) {
    try {
        // Recalcular Aportaciones Destacadas
        $stmt_aportaciones = $pdo->prepare("
            SELECT COUNT(*) as total, 
                   SUM(CASE WHEN validado = 1 THEN 1 ELSE 0 END) as validadas
            FROM aportaciones_destacadas 
            WHERE user_id = ? AND periodo = ?
        ");
        $stmt_aportaciones->execute([$user_id, $periodo]);
        $aportaciones = $stmt_aportaciones->fetch(PDO::FETCH_ASSOC);
        
        // Calcular estatus de aportaciones
        $total_aportaciones = $aportaciones['total'];
        $validadas_aportaciones = $aportaciones['validadas'];
        
        if ($total_aportaciones == 0) {
            $estatus_aportaciones = 0; // Sin captura
            $puntaje_aportaciones = 0;
        } elseif ($validadas_aportaciones == 0) {
            $estatus_aportaciones = 1; // Con captura, pendiente validación
            $puntaje_aportaciones = 0;
        } else {
            $estatus_aportaciones = 2; // Validadas
            $puntaje_aportaciones = $validadas_aportaciones; // 1 punto por cada una validada
        }
        
        // Recalcular Actividades Extraordinarias
        $stmt_actividades = $pdo->prepare("
            SELECT COUNT(*) as total, 
                   SUM(CASE WHEN validado = 1 THEN 1 ELSE 0 END) as validadas
            FROM actividades_extraordinarias 
            WHERE user_id = ? AND periodo = ?
        ");
        $stmt_actividades->execute([$user_id, $periodo]);
        $actividades = $stmt_actividades->fetch(PDO::FETCH_ASSOC);
        
        // Calcular estatus de actividades
        $total_actividades = $actividades['total'];
        $validadas_actividades = $actividades['validadas'];
        
        if ($total_actividades == 0) {
            $estatus_actividades = 0; // Sin captura
            $puntaje_actividades = 0;
        } elseif ($validadas_actividades == 0) {
            $estatus_actividades = 1; // Con captura, pendiente validación
            $puntaje_actividades = 0;
        } else {
            $estatus_actividades = 2; // Validadas
            $puntaje_actividades = $validadas_actividades * 2; // 2 puntos por cada una validada
        }
        
        // Verificar si existe registro en calificaciones
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM calificaciones WHERE user_id = ? AND periodo = ?");
        $stmt_check->execute([$user_id, $periodo]);
        
        if ($stmt_check->fetchColumn() > 0) {
            // Actualizar registro existente
            $stmt_update = $pdo->prepare("
                UPDATE calificaciones 
                SET aportaciones_destacadas = ?,
                    estatus_aportaciones_destacadas = ?,
                    actividades_extraordinarias = ?,
                    estatus_actividades_extraordinarias = ?
                WHERE user_id = ? AND periodo = ?
            ");
            $stmt_update->execute([
                $puntaje_aportaciones, 
                $estatus_aportaciones,
                $puntaje_actividades, 
                $estatus_actividades,
                $user_id, 
                $periodo
            ]);
        } else {
            // Crear nuevo registro
            $stmt_insert = $pdo->prepare("
                INSERT INTO calificaciones 
                (user_id, periodo, aportaciones_destacadas, estatus_aportaciones_destacadas, 
                 actividades_extraordinarias, estatus_actividades_extraordinarias) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt_insert->execute([
                $user_id, 
                $periodo, 
                $puntaje_aportaciones, 
                $estatus_aportaciones,
                $puntaje_actividades, 
                $estatus_actividades
            ]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error recalculando semáforos: " . $e->getMessage());
        return false;
    }
}
?>
