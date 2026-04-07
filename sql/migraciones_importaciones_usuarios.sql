-- ========================================
-- MIGRACIÓN: Sistema de Auditoría de Importaciones de Usuarios
-- Fecha: 2026-03-30
-- Descripción: Crea tablas para registrar y auditar importaciones en lote
-- ========================================

-- Tabla: importaciones_usuarios (registro de lotes)
CREATE TABLE IF NOT EXISTS `importaciones_usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_archivo` varchar(255) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `fecha_importacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `total_filas` int(11) NOT NULL DEFAULT 0,
  `total_nuevos` int(11) NOT NULL DEFAULT 0,
  `total_actualizados` int(11) NOT NULL DEFAULT 0,
  `total_errores` int(11) NOT NULL DEFAULT 0,
  `estado` enum('completado','pendiente','error') NOT NULL DEFAULT 'completado',
  `mensaje_resumen` longtext NULL DEFAULT NULL,
  `hash_archivo` varchar(64) NULL DEFAULT NULL,
  `ip_origen` varchar(45) NULL DEFAULT NULL,
  `user_agent` text NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `fecha_importacion` (`fecha_importacion`),
  KEY `estado` (`estado`),
  CONSTRAINT `fk_imp_usuarios_admin` FOREIGN KEY (`admin_id`) REFERENCES `usuarios` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=1 CHARACTER SET=utf8 COLLATE=utf8_general_ci;

-- Tabla: importaciones_usuarios_detalle (detalles de cada registro importado)
CREATE TABLE IF NOT EXISTS `importaciones_usuarios_detalle` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `importacion_id` int(11) NOT NULL,
  `user_id` int(11) NULL DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `nombre` varchar(50) NULL DEFAULT NULL,
  `apellido_paterno` varchar(50) NULL DEFAULT NULL,
  `apellido_materno` varchar(50) NULL DEFAULT NULL,
  `RFC` varchar(13) NULL DEFAULT NULL,
  `CURP` varchar(255) NULL DEFAULT NULL,
  `IDRUSP` varchar(20) NULL DEFAULT NULL,
  `correo` varchar(150) NULL DEFAULT NULL,
  `tipo_operacion` enum('nuevo','actualizado','error') NOT NULL,
  `validaciones_pasadas` tinyint(1) NOT NULL DEFAULT 0,
  `errores` text NULL DEFAULT NULL,
  `fila_numero` int(11) NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `importacion_id` (`importacion_id`),
  KEY `user_id` (`user_id`),
  KEY `username` (`username`),
  KEY `tipo_operacion` (`tipo_operacion`),
  KEY `validaciones_pasadas` (`validaciones_pasadas`),
  CONSTRAINT `fk_imp_detalle_importacion` FOREIGN KEY (`importacion_id`) REFERENCES `importaciones_usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_imp_detalle_usuario` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 CHARACTER SET=utf8 COLLATE=utf8_general_ci;

-- Índices compuestos para queries comunes (comentados para compatibilidad MySQL 5.7)
-- Los índices simples ya están definidos arriba en las tablas
-- CREATE INDEX `idx_importacion_admin_fecha` ON `importaciones_usuarios` (`admin_id`, `fecha_importacion` DESC);
-- CREATE INDEX `idx_detalle_importacion_operacion` ON `importaciones_usuarios_detalle` (`importacion_id`, `tipo_operacion`);
-- CREATE INDEX `idx_detalle_usuario_importacion` ON `importaciones_usuarios_detalle` (`user_id`, `importacion_id`);

-- ========================================
-- CONSULTAS ÚTILES PARA ANÁLISIS
-- ========================================

-- Ver todas las importaciones con resumen
-- SELECT i.id, i.nombre_archivo, u.nombre, u.apellido_paterno, i.fecha_importacion, 
--        i.total_nuevos, i.total_actualizados, i.total_errores, i.estado
-- FROM importaciones_usuarios i
-- LEFT JOIN usuarios u ON i.admin_id = u.user_id
-- ORDER BY i.fecha_importacion DESC;

-- Ver detalles de una importación específica
-- SELECT id.*, 
--        CASE WHEN id.user_id IS NULL THEN 'NO CREADO' ELSE 'ID=' || user_id END as estado_usuario
-- FROM importaciones_usuarios_detalle id
-- WHERE id.importacion_id = 1
-- ORDER BY id.fila_numero;

-- Usuarios importados en las últimas 7 días
-- SELECT COUNT(*) as total_usuarios, COUNT(DISTINCT importacion_id) as total_lotes
-- FROM importaciones_usuarios_detalle
-- WHERE id.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
--   AND tipo_operacion != 'error';
