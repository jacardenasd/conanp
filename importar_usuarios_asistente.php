<?php
/**
 * ASISTENTE DE IMPORTACIÓN DE USUARIOS DESDE CSV
 * Sistema de Evaluación del Desempeño CONANP
 * Fecha: 15 de Enero de 2026
 * 
 * Uso: http://localhost/conanp/importar_usuarios_asistente.php
 * 
 * ADVERTENCIA: Solo acceso para Super Admin (role = 3)
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Incluir configuración
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/variables.php';

// Verificar que sea Super Admin
checkLogin(3);  // role = 3 requerido

$mensaje = '';
$error = '';
$estadisticas = null;

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['accion'])) {
        $accion = $_POST['accion'];
        
        // ACCIÓN 1: Diagnosticar CSV
        if ($accion === 'diagnosticar') {
            $archivo = 'usuarios.csv';
            
            if (!file_exists($archivo)) {
                $error = "❌ Archivo usuarios.csv no encontrado en raíz del proyecto";
            } else {
                $lineas = 0;
                $usuarios_activos = 0;
                $usuarios_inactivos = 0;
                $vacantes = 0;
                $tipos_usuario = [];
                
                if (($handle = fopen($archivo, 'r')) !== false) {
                    // Saltar cabecera
                    fgetcsv($handle);
                    
                    while (($row = fgetcsv($handle)) !== false) {
                        $lineas++;
                        
                        // Contar por estatus
                        $estatus = trim($row[22] ?? '');
                        if ($estatus === 'ACTIVO') {
                            $usuarios_activos++;
                        } elseif ($estatus === 'INACTIVO') {
                            $usuarios_inactivos++;
                        }
                        
                        // Contar vacantes
                        $nombre = trim($row[2] ?? '');
                        if (empty($nombre) || $nombre === 'NULL') {
                            $vacantes++;
                        }
                        
                        // Contar por tipo_usuario
                        $tipo = trim($row[21] ?? 'NO_ESPECIFICADO');
                        $tipos_usuario[$tipo] = ($tipos_usuario[$tipo] ?? 0) + 1;
                    }
                    fclose($handle);
                    
                    $estadisticas = [
                        'total_lineas' => $lineas,
                        'activos' => $usuarios_activos,
                        'inactivos' => $usuarios_inactivos,
                        'vacantes' => $vacantes,
                        'tipos' => $tipos_usuario
                    ];
                    
                    $mensaje = "✅ CSV diagnosticado correctamente. Se encontraron $lineas registros.";
                }
            }
        }
        
        // ACCIÓN 2: Importar CSV a BD
        elseif ($accion === 'importar') {
            $archivo = 'usuarios.csv';
            
            if (!file_exists($archivo)) {
                $error = "❌ Archivo usuarios.csv no encontrado";
            } else {
                try {
                    $contador = 0;
                    $errores = [];
                    
                    if (($handle = fopen($archivo, 'r')) !== false) {
                        // Saltar cabecera
                        fgetcsv($handle);
                        
                        $pdo->beginTransaction();
                        
                        while (($row = fgetcsv($handle)) !== false && $contador < 1000) {
                            // Limpiar datos
                            $user_id = intval($row[0]);
                            $role = intval($row[1]);
                            $nombre = trim($row[2]);
                            $apellido_paterno = trim($row[3]);
                            $apellido_materno = trim($row[4]);
                            $rfc = trim($row[5]);
                            $homoclave = trim($row[6]);
                            $curp = trim($row[7]);
                            $idrusp = trim($row[8]);
                            $puesto_codigo = trim($row[9]);
                            $puesto_nivel = trim($row[10]);
                            $puesto_nombre = trim($row[11]);
                            $sexo = trim($row[12]);
                            $correo = trim($row[13]);
                            $nivel_estudios = trim($row[14]);
                            $fecha_alta = convertirFecha($row[15]);
                            $temporal = intval($row[16]);
                            $jefe_id = intval($row[17]) ?: null;
                            $unidad_id = trim($row[18]);
                            $adscripcion_id = trim($row[19]);
                            $permite_metas_colectivas = trim($row[20]);
                            $tipo_usuario = trim($row[21]);
                            $estatus = (trim($row[22]) === 'ACTIVO') ? 1 : 0;
                            
                            // Convertir tipo_usuario a número
                            if ($tipo_usuario === 'SPC') {
                                $tipo_usuario = 1;
                            } elseif ($tipo_usuario === 'Primer Nivel') {
                                $tipo_usuario = 2;
                            } else {
                                $tipo_usuario = 3;
                            }
                            
                            // Limpiar valores NULL
                            if ($nombre === 'NULL' || empty($nombre)) $nombre = null;
                            if ($rfc === 'NULL' || empty($rfc)) $rfc = null;
                            if ($curp === 'NULL' || empty($curp)) $curp = null;
                            if ($puesto_nivel === 'NULL' || empty($puesto_nivel)) $puesto_nivel = null;
                            
                            // Insertar o actualizar
                            $sql = "INSERT INTO usuarios 
                                (user_id, role, nombre, apellido_paterno, apellido_materno, RFC, homoclave, CURP, IDRUSP, 
                                 puesto_codigo, puesto_nivel, puesto_nombre, sexo, correo, nivel_estudios, fecha_alta, 
                                 temporal, jefe_id, unidad_id, adscripcion_id, permite_metas_colectivas, tipo_usuario, estatus, 
                                 requiere_cambio_password, password)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
                                ON DUPLICATE KEY UPDATE
                                role = VALUES(role),
                                nombre = VALUES(nombre),
                                apellido_paterno = VALUES(apellido_paterno),
                                apellido_materno = VALUES(apellido_materno),
                                RFC = VALUES(RFC),
                                puesto_nombre = VALUES(puesto_nombre),
                                correo = VALUES(correo),
                                estatus = VALUES(estatus),
                                tipo_usuario = VALUES(tipo_usuario)";
                            
                            $stmt = $pdo->prepare($sql);
                            $password_hash = password_hash('temporal123', PASSWORD_BCRYPT);
                            
                            $stmt->execute([
                                $user_id, $role, $nombre, $apellido_paterno, $apellido_materno, $rfc, $homoclave, $curp, $idrusp,
                                $puesto_codigo, $puesto_nivel, $puesto_nombre, $sexo, $correo, $nivel_estudios, $fecha_alta,
                                $temporal, $jefe_id, $unidad_id, $adscripcion_id, $permite_metas_colectivas, $tipo_usuario, $estatus,
                                $password_hash
                            ]);
                            
                            $contador++;
                        }
                        
                        fclose($handle);
                        $pdo->commit();
                        
                        $mensaje = "✅ Se importaron exitosamente <strong>$contador registros</strong> de usuarios. ";
                        $mensaje .= "Todos los usuarios tienen password temporal: <strong>temporal123</strong>";
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "❌ Error durante importación: " . $e->getMessage();
                }
            }
        }
        
        // ACCIÓN 3: Generar reporte de validación
        elseif ($accion === 'validar') {
            try {
                // Validar que todos tengan tipo_usuario
                $stmt = $pdo->query("SELECT COUNT(*) as cantidad FROM usuarios WHERE tipo_usuario IS NULL OR tipo_usuario = 0");
                $sin_tipo = $stmt->fetch(PDO::FETCH_ASSOC)['cantidad'];
                
                // Validar jefe_id válidos
                $stmt = $pdo->query("SELECT COUNT(*) as cantidad FROM usuarios u 
                    WHERE u.jefe_id > 0 AND NOT EXISTS (SELECT 1 FROM usuarios j WHERE j.user_id = u.jefe_id)");
                $jefes_invalidos = $stmt->fetch(PDO::FETCH_ASSOC)['cantidad'];
                
                // Distribución actual
                $stmt = $pdo->query("SELECT tipo_usuario, COUNT(*) as cantidad FROM usuarios WHERE estatus = 1 GROUP BY tipo_usuario");
                $distribucion = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $estadisticas = [
                    'sin_tipo_usuario' => $sin_tipo,
                    'jefes_invalidos' => $jefes_invalidos,
                    'distribucion' => $distribucion
                ];
                
                $mensaje = "✅ Validación completada. " . ($sin_tipo === 0 ? "Todos los usuarios tienen tipo_usuario asignado." : "⚠️ $sin_tipo usuarios sin tipo_usuario.");
            } catch (Exception $e) {
                $error = "❌ Error en validación: " . $e->getMessage();
            }
        }
    }
}

// Función auxiliar para convertir fecha
function convertirFecha($fecha) {
    if (empty($fecha) || $fecha === 'NULL') {
        return null;
    }
    try {
        $fecha_obj = DateTime::createFromFormat('d/m/Y', $fecha);
        return $fecha_obj ? $fecha_obj->format('Y-m-d') : null;
    } catch (Exception $e) {
        return null;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asistente de Importación de Usuarios</title>
    <link href="assets/css/ltr/all.min.css" rel="stylesheet">
    <style>
        .container-fluid { padding: 20px; }
        .card { margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .card-header { background: #007bff; color: white; padding: 15px; border-radius: 5px 5px 0 0; }
        .card-body { padding: 20px; }
        .btn { padding: 10px 20px; margin: 5px; }
        .alert { padding: 15px; margin: 15px 0; border-radius: 5px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .table { margin-top: 15px; }
        .table th { background: #f8f9fa; }
        .badge { padding: 5px 10px; border-radius: 3px; }
        .badge-success { background: #28a745; color: white; }
        .badge-warning { background: #ffc107; color: black; }
        .badge-danger { background: #dc3545; color: white; }
        .spinner { text-align: center; display: none; }
        .spinner.show { display: block; }
    </style>
</head>
<body>
<?php require_once('assets/main_navbar.php'); ?>

<div class="page-content">
    <?php require_once('assets/main_navigation.php'); ?>
    
    <div class="content-wrapper">
        <div class="content-inner">
            <div class="content container-fluid">
                
                <h1>📥 Asistente de Importación de Usuarios</h1>
                <p class="text-muted">Sistema de Evaluación del Desempeño CONANP</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <strong>Error:</strong> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($mensaje): ?>
                    <div class="alert alert-success">
                        <strong>Éxito:</strong> <?php echo $mensaje; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Tarjeta 1: Diagnosticar -->
                <div class="card">
                    <div class="card-header">
                        <strong>🔍 Paso 1: Diagnosticar Archivo CSV</strong>
                    </div>
                    <div class="card-body">
                        <p>Analiza el archivo usuarios.csv para verificar su integridad antes de importar.</p>
                        <form method="POST">
                            <input type="hidden" name="accion" value="diagnosticar">
                            <button type="submit" class="btn btn-primary">
                                <i class="ph-magnifying-glass"></i> Diagnosticar CSV
                            </button>
                        </form>
                        
                        <?php if ($estadisticas && isset($estadisticas['total_lineas'])): ?>
                            <div class="alert alert-success" style="margin-top: 15px;">
                                <strong>Resultados del diagnóstico:</strong>
                                <table class="table table-sm">
                                    <tr>
                                        <td>Total de registros:</td>
                                        <td><span class="badge badge-success"><?php echo $estadisticas['total_lineas']; ?></span></td>
                                    </tr>
                                    <tr>
                                        <td>Usuarios activos:</td>
                                        <td><span class="badge badge-success"><?php echo $estadisticas['activos']; ?></span></td>
                                    </tr>
                                    <tr>
                                        <td>Usuarios inactivos:</td>
                                        <td><span class="badge badge-warning"><?php echo $estadisticas['inactivos']; ?></span></td>
                                    </tr>
                                    <tr>
                                        <td>Vacantes (sin nombre):</td>
                                        <td><span class="badge badge-danger"><?php echo $estadisticas['vacantes']; ?></span></td>
                                    </tr>
                                    <tr>
                                        <td colspan="2"><strong>Distribución por tipo_usuario:</strong></td>
                                    </tr>
                                    <?php foreach ($estadisticas['tipos'] as $tipo => $cantidad): ?>
                                        <tr>
                                            <td style="padding-left: 30px;">- <?php echo htmlspecialchars($tipo); ?>:</td>
                                            <td><?php echo $cantidad; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Tarjeta 2: Importar -->
                <div class="card">
                    <div class="card-header">
                        <strong>⬆️ Paso 2: Importar Usuarios a Base de Datos</strong>
                    </div>
                    <div class="card-body">
                        <p>⚠️ <strong>ADVERTENCIA:</strong> Esta acción importará/actualizará los usuarios en la tabla.</p>
                        <p>Todos los usuarios nuevos recibirán password temporal: <code>temporal123</code></p>
                        
                        <form method="POST" onsubmit="return confirm('¿Deseas importar los usuarios desde el CSV a la base de datos?');">
                            <input type="hidden" name="accion" value="importar">
                            <button type="submit" class="btn btn-success">
                                <i class="ph-upload"></i> Importar Usuarios
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Tarjeta 3: Validar -->
                <div class="card">
                    <div class="card-header">
                        <strong>✅ Paso 3: Validar Base de Datos</strong>
                    </div>
                    <div class="card-body">
                        <p>Verifica la integridad de los datos en la base de datos después de importar.</p>
                        <form method="POST">
                            <input type="hidden" name="accion" value="validar">
                            <button type="submit" class="btn btn-info">
                                <i class="ph-check-circle"></i> Validar Base de Datos
                            </button>
                        </form>
                        
                        <?php if ($estadisticas && isset($estadisticas['sin_tipo_usuario'])): ?>
                            <div class="alert" style="margin-top: 15px; background: <?php echo ($estadisticas['sin_tipo_usuario'] === 0) ? '#d4edda' : '#fff3cd'; ?>; color: <?php echo ($estadisticas['sin_tipo_usuario'] === 0) ? '#155724' : '#856404'; ?>;">
                                <strong>Resultados de validación:</strong>
                                <table class="table table-sm" style="color: inherit;">
                                    <tr>
                                        <td>Usuarios sin tipo_usuario:</td>
                                        <td>
                                            <?php 
                                            if ($estadisticas['sin_tipo_usuario'] === 0) {
                                                echo '<span class="badge badge-success">✓ Ninguno (Correcto)</span>';
                                            } else {
                                                echo '<span class="badge badge-warning">⚠️ ' . $estadisticas['sin_tipo_usuario'] . '</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Jefe_id inválidos (jefe no existe):</td>
                                        <td>
                                            <?php 
                                            if ($estadisticas['jefes_invalidos'] === 0) {
                                                echo '<span class="badge badge-success">✓ Ninguno (Correcto)</span>';
                                            } else {
                                                echo '<span class="badge badge-danger">❌ ' . $estadisticas['jefes_invalidos'] . '</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2"><strong>Distribución de usuarios activos por tipo:</strong></td>
                                    </tr>
                                    <?php foreach ($estadisticas['distribucion'] as $dist): ?>
                                        <tr>
                                            <td style="padding-left: 30px;">- Tipo <?php echo $dist['tipo_usuario']; ?>:</td>
                                            <td><?php echo $dist['cantidad']; ?> usuarios</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Información -->
                <div class="card">
                    <div class="card-header">
                        <strong>ℹ️ Información Técnica</strong>
                    </div>
                    <div class="card-body">
                        <ul>
                            <li><strong>Archivo CSV:</strong> usuarios.csv (ubicado en raíz del proyecto)</li>
                            <li><strong>Tabla destino:</strong> usuarios</li>
                            <li><strong>Registros estimados:</strong> ~700 usuarios</li>
                            <li><strong>Password temporal:</strong> temporal123 (usuario debe cambiar al primer login)</li>
                            <li><strong>Permisos requeridos:</strong> Solo Super Admin (role = 3)</li>
                            <li><strong>Seguridad:</strong> Las transacciones se revierten si hay error</li>
                        </ul>
                    </div>
                </div>
                
            </div>
        </div>
        <?php require_once('assets/footer.php'); ?>
    </div>
</div>

</body>
</html>
