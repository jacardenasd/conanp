/**
 * DOCUMENTO DE RECOMENDACIONES DE MEJORA
 * Sistema de Importación de Usuarios - CONANP
 * Fecha: 2026-03-30
 * 
 * Este documento proporciona recomendaciones para mejorar aún más
 * el sistema de importación y mantenerlo actualizado.
 */

// =========================================
// 1. MEJORAS DE FUNCIONALIDAD
// =========================================

// 1.1 VALIDACIÓN DE EMAIL EN REALES
// PROBLEMA: Actualmente solo valida formato, no puede verificar si el email realmente existe
// SOLUCIÓN: Implementar validación SMTP o usar servicio como MailboxValidator
// IMPACTO: Evitar usuarios con correos falsos
// ESFUERZO: Moderado (requiere librería externa)
// PRIORIDAD: Media

// 1.2 DUPLICACIÓN DE USUARIOS POR RFC
// PROBLEM: Un usuario podría importarse 2 veces con RFC diferente
// SOLUCIÓN: Agregar validación que check: RFC único en BD (como username)
// CÓDIGO A AGREGAR: En validaciones_importacion.php
/*
function validar_rfc_unico($pdo, $rfc, $excluir_user_id = null) {
    if (empty($rfc)) return ['unico' => true, 'error' => null];
    
    if ($excluir_user_id) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as existe FROM usuarios WHERE RFC = ? AND user_id != ?");
        $stmt->execute([$rfc, $excluir_user_id]);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) as existe FROM usuarios WHERE RFC = ?");
        $stmt->execute([$rfc]);
    }
    
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    return [
        'unico' => (int)$resultado['existe'] === 0,
        'error' => (int)$resultado['existe'] > 0 ? "RFC ya existe: '$rfc'" : null
    ];
}
*/
// IMPACTO: Prevenir duplicados por RFC
// ESFUERZO: Bajo (20 líneas de código)
// PRIORIDAD: Alta

// 1.3 IMPORTACIÓN DE MÚLTIPLES ARCHIVOS EN PARALELO
// PROBLEMA: Si carga 2 archivos simultáneamente, pueden haber conflictos
// SOLUCIÓN: Agregar queue de importaciones y procesar una por una
// UBICACIÓN: Nueva tabla: importaciones_queue
// PRIORIDAD: Baja (más bien un nice-to-have)

// 1.4 DETECCIÓN DE CAMBIOS EN ACTUALIZACIONES
// PROBLEMA: Actualiza usuario aunque no cambie nada (impacta auditoría)
// SOLUCIÓN: Comparar datos antes/después y solo actualizar si hay cambios
// ESFUERZO: Bajo
// IMPACTO: Mejor auditoría, menos escrituras en BD

// =========================================
// 2. MEJORAS DE PERFORMANCE
// =========================================

// 2.1 ÍNDICES ADICIONALES
// RECOMENDACIÓN: Agregar índices en tablas relacionadas
/*
CREATE INDEX idx_usuarios_rfc ON usuarios(RFC);
CREATE INDEX idx_usuarios_correo ON usuarios(correo);
CREATE INDEX idx_usuarios_jefe_id ON usuarios(jefe_id);
CREATE INDEX idx_usuarios_unidad_id ON usuarios(unidad_id);
CREATE INDEX idx_usuarios_adscripcion_id ON usuarios(adscripcion_id);
*/
// IMPACTO: Búsquedas más rápidas durante validación
// ESFUERZO: Muy bajo
// PRIORIDAD: Media

// 2.2 PROCESAR POR LOTES (CHUNKING)
// PROBLEMA: Archivos muy grandes (>10,000 usuarios) pueden causar timeout
// SOLUCIÓN: Procesar en chunks de 500-1000 usuarios
// UBICACIÓN: Modificar usuarios_importar.php línea ~110
// ESFUERZO: Moderado
// PRIORIDAD: Media (solo si hay archivos grandes)

// 2.3 CACHÉ DE VALIDACIONES
// PROBLEMA: Valida jefe_id para cada usuario (query por cada fila)
// SOLUCIÓN: Cargar todas las unidades/jefes al inicio en array
// ESFUERZO: Bajo
// IMPACTO: 30-40% más rápido

// =========================================
// 3. MEJORAS DE SEGURIDAD
// =========================================

// 3.1 CIFRADO DE CONTRASEÑA TEMPORAL
// ACTUAL: Almacena 'temporal123' en claro en logs
// RECOMENDADO: Generar hash único por usuario
/*
$password_temporal = substr(bin2hex(random_bytes(8)), 0, 12); // ej: "a7b3c2d9e1f4"
$password_hash = password_hash($password_temporal, PASSWORD_DEFAULT);
// Enviar 'a7b3c2d9e1f4' en email/SMS, guardar hash en BD
*/
// IMPACTO: Seguridad mejorada
// ESFUERZO: Moderado
// PRIORIDAD: Alta

// 3.2 PERMISOS GRANULARES
// ACTUAL: Solo requiere role = 2 (admin)
// RECOMENDADO: Permitir admins solo de su unidad importar usuarios de su unidad
// ESFUERZO: Moderado
// IMPACTO: Mejor control de acceso
// PRIORIDAD: Baja

// 3.3 VALIDACIÓN DE IP ORIGEN
// RECOMENDACIÓN: Bloquear importaciones desde IPs no confiables
// UBICACIÓN: admin_importaciones_historial.php
// ESFUERZO: Bajo
// PRIORIDAD: Baja

// =========================================
// 4. MEJORAS DE UX/UI
// =========================================

// 4.1 PREVIEW DEL ARCHIVO ANTES DE IMPORTAR
// PROBLEMA: Usuario no ve qué va a importar hasta que procesa
// SOLUCIÓN: Mostrar preview de primeras 10 filas con validaciones
// ESFUERZO: Moderado
// IMPACTO: Mejora confianza del usuario
// PRIORIDAD: Media

// 4.2 DESCARGA DE REPORTE DE ERRORES EN EXCEL
// ACTUAL: Muestra errores en tabla HTML
// RECOMENDADO: Opción para descargar Excel con errores destacados
// ESFUERZO: Moderado
// IMPACTO: Facilita corrección de errores
// PRIORIDAD: Media

// 4.3 PROGRESO BAR EN TIEMPO REAL
// PROBLEMA: Usuarios grandes tardan sin feedback visual
// SOLUCIÓN: AJAX para actualizar progreso cada 100 usuarios
// ESFUERZO: Alto
// IMPACTO: Mejora UX
// PRIORIDAD: Baja

// 4.4 PLANTILLA CON DATOS DE EJEMPLO
// ACTUAL: Plantilla vacía
// RECOMENDADO: Incluir 2-3 ejemplos de datos válidos
// ESFUERZO: Muy bajo
// IMPACTO: Reduce errores de formato
// PRIORIDAD: Media

// =========================================
// 5. INTEGRACIÓN CON OTROS MÓDULOS
// =========================================

// 5.1 NOTIFICACIÓN A USUARIOS NUEVOS
// RECOMENDACIÓN: Enviar email a nuevo usuario con:
//   - Username/Email
//   - Contraseña temporal
//   - Link para cambiar contraseña
//   - Instrucciones de primer login
// UBICACIÓN: Agregar en usuarios_importar.php después de crear usuario
// ESFUERZO: Moderado
// PRIORIDAD: Alta

// 5.2 CREACIÓN AUTOMÁTICA DE CARPETA DE ARCHIVOS
// ACTUAL: Usuario no tiene carpeta en /documentos
// RECOMENDADO: Crear carpeta al importar (si tiene puesto)
// ESFUERZO: Bajo
// PRIORIDAD: Baja

// 5.3 NOTIFICACIÓN AL JEFE
// RECOMENDACIÓN: Si se asigna jefe_id, notificar al jefe
// UBICACIÓN: sistema de mensajes internos
// ESFUERZO: Bajo
// PRIORIDAD: Baja

// =========================================
// 6. MANTENIMIENTO Y MONITOREO
// =========================================

// 6.1 LIMPIAR IMPORTACIONES ANTIGUAS
// RECOMENDACIÓN: Archivar importaciones con > 90 días
// QUERY:
/*
DELETE FROM importaciones_usuarios_detalle 
WHERE importacion_id IN (
    SELECT id FROM importaciones_usuarios 
    WHERE fecha_importacion < DATE_SUB(NOW(), INTERVAL 90 DAY)
);

DELETE FROM importaciones_usuarios 
WHERE fecha_importacion < DATE_SUB(NOW(), INTERVAL 90 DAY);
*/
// ESFUERZO: Muy bajo (solo crear script cron)
// PRIORIDAD: Baja

// 6.2 REPORTE SEMANAL DE IMPORTACIONES
// RECOMENDACIÓN: Crear reporte automático para admins
// INCLUIR: Total usuarios importados, actualizados, errores por semana
// ESFUERZO: Bajo
// PRIORIDAD: Baja

// 6.3 MONITOREO DE ERRORES COMUNES
// RECOMENDACIÓN: Query para ver errores más frecuentes
/*
SELECT 
    errores,
    COUNT(*) as frecuencia
FROM importaciones_usuarios_detalle
WHERE tipo_operacion = 'error'
GROUP BY errores
ORDER BY frecuencia DESC;
*/
// IMPACTO: Identificar patrones de errores
// PRIORIDAD: Baja

// =========================================
// 7. DOCUMENTACIÓN
// =========================================

// 7.1 VIDEO TUTORIAL
// RECOMENDACIÓN: Grabar video de 2-3 minutos mostrando
//   - Cómo llenar la plantilla
//   - Cómo cargar archivo
//   - Cómo interpretar errores
// PÚBLICO: Admins de unidades
// ESFUERZO: Bajo
// PRIORIDAD: Media

// 7.2 GUÍA DE TROUBLESHOOTING
// RECOMENDACIÓN: Crear página con preguntas frecuentes
//   - ¿Por qué falla mi RFC?
//   - ¿Qué significa "email duplicado"?
//   - ¿Puedo actualizar usuario existente?
//   - ¿Cómo recuperar de un error?
// ESFUERZO: Bajo
// PRIORIDAD: Media

// 7.3 EJEMPLOS DE ERRORES
// RECOMENDACIÓN: Agregar a usuarios_importar.php sección "Ejemplos de errores"
// UBICACIÓN: Nuevo acordeón en sección instrucciones
// ESFUERZO: Bajo
// PRIORIDAD: Media

// =========================================
// 8. TESTING
// =========================================

// 8.1 TEST UNITARIO: validaciones_importacion.php
// CREAR: /tests/test_validaciones.php
// INCLUIR: Tests para cada función de validación
// ESFUERZO: Moderado
// PRIORIDAD: Baja

// 8.2 TEST DE CARGA
// CREAR: Script que importa 10,000 usuarios y mide tiempo
// OBJETIVO: Verificar que tarda < 5 minutos
// ESFUERZO: Bajo
// PRIORIDAD: Baja

// 8.3 TEST DE INTEGRIDAD
// CREAR: Script que valida que BD no tenga inconsistencias
// VERIFICAR:
//   - No hay usuarios sin jefe_id -><- pero jefe existe
//   - No hay orfandades
//   - RFC/Email únicos realmente
// ESFUERZO: Moderado
// PRIORIDAD: Baja

// =========================================
// RESUMEN DE PRIORIDADES
// =========================================

/*
ALTA (Implementar pronto):
  - Validación RFC único
  - Cifrado de contraseña temporal
  - Notificación a usuarios nuevos

MEDIA (Implementar en próximas 2 sprint):
  - Índices adicionales
  - Preview del archivo
  - Detección de cambios
  - Reporte de errores Excel
  - Mejoras de documentación

BAJA (Nice-to-have, cuando tenga tiempo):
  - Procesamiento por chunking
  - Progreso bar real-time
  - Mejoras de seguridad avanzada
  - Limpieza de importaciones antiguas
*/

// =========================================
// CÓMO IMPLEMENTAR UNA MEJORA
// =========================================

/*
1. Escoger una mejora de ALTA prioridad
2. Crear rama: git checkout -b feature/mejora-xxxxx
3. Implementar cambios
4. Probar manualmente: 
   - Importar archivo sin errores
   - Importar archivo con errores
   - Verificar BD
   - Ver historial
5. Actualizar memoria en /memories/repo/
6. Commit + Push
7. Crear merge request

EJEMPLO MEJORA IMPLEMENTACIÓN:
Si vas a agregar validar_rfc_unico():

1. Abrir includes/validaciones_importacion.php
2. Copiar la función de arriba
3. Agregar al final del archivo
4. En usuarios_importar.php línea ~165, agregar:
   
   $val_rfc_unico = validar_rfc_unico($pdo, $data['RFC']);
   if (!$val_rfc_unico['unico']) {
       $errores[] = $val_rfc_unico['error'];
   }

5. Probar con usuario que ya existe RFC
6. Commit
*/

?>
