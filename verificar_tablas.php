<?php
/**
 * verificar_tablas.php
 * Verifica si las tablas de importación existen y las crea si no están presentes
 */

require 'config/db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "🔍 Verificando tablas de auditoría de importaciones...\n\n";

// Verificar tabla importaciones_usuarios
$stmt = $pdo->prepare("SHOW TABLES LIKE 'importaciones_usuarios'");
$stmt->execute();
$tabla1_existe = $stmt->rowCount() > 0;

// Verificar tabla importaciones_usuarios_detalle
$stmt = $pdo->prepare("SHOW TABLES LIKE 'importaciones_usuarios_detalle'");
$stmt->execute();
$tabla2_existe = $stmt->rowCount() > 0;

if ($tabla1_existe && $tabla2_existe) {
    echo "✅ Ambas tablas existen y están listas para usar\n";
    echo "   - importaciones_usuarios (audit log de lotes)\n";
    echo "   - importaciones_usuarios_detalle (detalles por usuario)\n";
    exit;
}

echo "⚠️  Creando tablas faltantes...\n\n";

try {
    // Crear tabla importaciones_usuarios
    if (!$tabla1_existe) {
        echo "📦 Creando tabla importaciones_usuarios...\n";
        $sql1 = "
        CREATE TABLE `importaciones_usuarios` (
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
        ) ENGINE=InnoDB AUTO_INCREMENT=1 CHARACTER SET=utf8 COLLATE=utf8_general_ci
        ";
        $pdo->exec($sql1);
        echo "   ✅ Tabla importaciones_usuarios creada\n";
    }
    
    // Crear tabla importaciones_usuarios_detalle
    if (!$tabla2_existe) {
        echo "📦 Creando tabla importaciones_usuarios_detalle...\n";
        $sql2 = "
        CREATE TABLE `importaciones_usuarios_detalle` (
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
        ) ENGINE=InnoDB AUTO_INCREMENT=1 CHARACTER SET=utf8 COLLATE=utf8_general_ci
        ";
        $pdo->exec($sql2);
        echo "   ✅ Tabla importaciones_usuarios_detalle creada\n";
    }
    
    echo "\n========================================\n";
    echo "✅ Setup completado exitosamente\n";
    echo "========================================\n";
    echo "\n✨ Las tablas de auditoría están listas para usar.\n";
    
} catch (PDOException $e) {
    echo "❌ Error al crear las tablas:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
?>
