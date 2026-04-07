<?php
/**
 * setup_importaciones.php
 * Script de setup para crear las tablas de auditoría de importaciones si no existen
 * Ejecutar una sola vez: http://localhost/conanp/setup_importaciones.php
 */

require 'config/db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Leer el archivo de migración
$migracion_sql = file_get_contents('sql/migraciones_importaciones_usuarios.sql');

// Dividir por puntos y coma y ejecutar cada statement
$statements = array_filter(
    array_map('trim', explode(';', $migracion_sql)),
    function($stmt) {
        return !empty($stmt) && strpos($stmt, '--') !== 0;
    }
);

$exito = 0;
$error = 0;

echo "🔧 Iniciando setup de tablas de importación...\n\n";

try {
    foreach ($statements as $statement) {
        if (empty(trim($statement))) continue;
        
        // Limpiar comentarios
        $lines = explode("\n", $statement);
        $statement = implode("\n", array_filter($lines, function($line) {
            return strpos(trim($line), '--') !== 0;
        }));
        
        $statement = trim($statement);
        if (empty($statement)) continue;
        
        try {
            $pdo->exec($statement);
            $exito++;
            echo "✅ Ejecutado: " . substr($statement, 0, 60) . "...\n";
        } catch (PDOException $e) {
            // Si la tabla ya existe, no es error
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "ℹ️  Tabla ya existe (ignorado)\n";
            } else {
                echo "❌ Error: " . $e->getMessage() . "\n";
                $error++;
            }
        }
    }
} catch (Exception $e) {
    echo "❌ Error fatal: " . $e->getMessage();
    exit;
}

echo "\n========================================\n";
echo "✅ Setup completado\n";
echo "   - Statements ejecutados: $exito\n";
echo "   - Errores: $error\n";
echo "========================================\n";
echo "\n✨ Las tablas de importación están listas.\n";
echo "🔗 Ir a: http://localhost/conanp/usuarios_importar.php\n";
?>
