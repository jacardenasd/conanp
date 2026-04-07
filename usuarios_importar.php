<?php
/**
 * IMPORTADOR DE USUARIOS - SISTEMA DE EVALUACIÓN CONANP
 * 
 * ===========================================
 * DESCRIPCIÓN DEL SISTEMA DE IMPORTACIÓN
 * ===========================================
 * 
 * Este módulo permite importar/actualizar usuarios desde archivos Excel (.xlsx)
 * con validaciones completas, auditoría y registr de cambios.
 * 
 * REQUISITOS DE ACCESO:
 * - Usuario debe tener rol = 2 (Admin) o 3 (SuperAdmin)
 * 
 * CARACTERÍSTICAS:
 * ✓ Validación completa de datos antes de importación
 * ✓ Registro de auditoría de cada importación
 * ✓ Reporte detallado de errores
 * ✓ Transacciones: se revierte todo si hay error
 * ✓ Puede actualizar usuarios existentes (match por username o user_id)
 * ✓ Historial de importaciones con opción de eliminar lotes
 * 
 * PASO A PASO:
 * 1. Usuario carga archivo Excel (.xlsx)
 * 2. Sistema valida estructura y campos requeridos
 * 3. Cada fila se valida individualmente
 * 4. Si todas las validaciones pasan, se importan los datos
 * 5. Se registra en tabla importaciones_usuarios para auditoría
 * 6. Usuario ve report detallado de lo importado/actualizado
 * 
 * CAMPOS OBLIGATORIOS:
 * - username: RFC a 10 posiciones o identificador único (3-50 caracteres)
 * - nombre: Nombre completo
 * - apellido_paterno: Apellido paterno
 * - correo: Email válido y único
 * 
 * CAMPOS OPCIONALES:
 * - user_id: Identificador de empleado (para matches exactos)
 * - apellido_materno: Apellido materno
 * - RFC: RFC de 13 caracteres
 * - CURP: CURP de 18 caracteres
 * - IDRUSP: Número de RUSP
 * - role: 1(Usuario), 2(Admin), 3(SuperAdmin) [default: 1]
 * - tipo_usuario: 1(SPC), 2(Primer Nivel), 3(Eventual), 4(Operativo), 5(Otro)
 * - sexo: H(Hombre), M(Mujer), Otro
 * - fecha_alta: Fecha de contratación (DD/MM/YYYY o YYYY-MM-DD)
 * - temporal: 0(No), 1(Sí) - indica si es puesto temporal
 * - jefe_id: user_id del jefe inmediato
 * - unidad_id: ID de la unidad administrativa
 * - adscripcion_id: ID de la adscripción (área específica)
 * - puesto_codigo: Código del puesto
 * - puesto_nivel: Nivel del puesto (1-6)
 * - puesto_nombre: Descripción del puesto
 * 
 * VALIDACIONES APLICADAS:
 * - RFC: Formato correcto (6 letras + 8 dígitos + 3 caracteres)
 * - CURP: Formato correcto (18 caracteres específicos)
 * - Email: Formato válido y sin duplicados
 * - Username: Sin duplicados, 3-50 caracteres
 * - Fechas: Convertidas automáticamente (DD/MM/YYYY → YYYY-MM-DD)
 * - Dependencias: jefe_id y unidad_id deben existir en BD
 * 
 * NOTA: Las contraseñas se generan como: password (campo temporal no se modifica)
 * Los usuarios deben cambiar contraseña en primer login (requiere_cambio_password=1)
 */

require 'includes/session.php';
checkLogin(2);  // Requiere Admin o SuperAdmin
require 'config/db.php';
require 'includes/variables.php';
require 'includes/validaciones_importacion.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');

$mensaje = "";
$tipo_alerta = "";
$detalles_importacion = null;

/**
 * PROCESAR IMPORTACIÓN
 * Flujo: Validación → Procesamiento → Auditoría
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
    
    $archivo_tmp = $_FILES['archivo']['tmp_name'];
    $nombre_archivo_original = $_FILES['archivo']['name'];
    
    try {
        // Cargar el archivo Excel
        $spreadsheet = IOFactory::load($archivo_tmp);
        $hoja = $spreadsheet->getActiveSheet();
        $filas = $hoja->toArray();
        
        if (count($filas) < 4) {
            $mensaje = "❌ El archivo debe contener al menos una fila de datos a partir de la fila 4";
            $tipo_alerta = "danger";
        } else {
            
            // Inicializar contadores
            $nuevos = 0;
            $actualizados = 0;
            $errores_totales = 0;
            $detalles_filas = [];
            $hash_archivo = hash_file('sha256', $archivo_tmp);
            
            // Comenzar transacción
            $pdo->beginTransaction();
            
            // PASO 1: Registrar la importación
            $stmt_importacion = $pdo->prepare("
                INSERT INTO importaciones_usuarios 
                (nombre_archivo, admin_id, total_filas, estado, hash_archivo, ip_origen, user_agent)
                VALUES (?, ?, ?, 'pendiente', ?, ?, ?)
            ");
            
            $ip_origen = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $stmt_importacion->execute([
                $nombre_archivo_original,
                $_SESSION['user_id'],
                max(0, count($filas) - 3),  // Excluir filas 1, 2 y 3
                $hash_archivo,
                $ip_origen,
                $user_agent
            ]);
            
            $importacion_id = $pdo->lastInsertId();
            
            // PASO 2: Procesar cada fila
            foreach ($filas as $numero_fila => $fila) {
                $fila_excel = $numero_fila + 1;
                
                // Iniciar importación desde la fila 4
                if ($numero_fila < 3) continue;
                
                // Expandir fila a array asociativo con columnas esperadas nuevas
                $columnas = ['username', 'nombre', 'apellido_paterno', 'apellido_materno', 
                             'RFC', 'CURP', 'sexo', 'correo', 'puesto_nombre',
                             'IDRUSP', 'jefe_id', 'unidad_id', 'adscripcion_id', 
                             'puesto_codigo', 'fecha_alta'];
                
                $fila_datos = [];
                foreach ($columnas as $idx => $colname) {
                    $fila_datos[$colname] = $fila[$idx] ?? null;
                }
                
                // Validar registro completo (sin parámetro $es_actualizacion)
                $validacion = validar_registro_usuario_completo($fila_datos, $fila_excel, $pdo);
                
                if (!$validacion['valido']) {
                    // Registrar error en detalle
                    $errores_totales++;
                    
                    $stmt_detalle = $pdo->prepare("
                        INSERT INTO importaciones_usuarios_detalle
                        (importacion_id, username, fila_numero, tipo_operacion, validaciones_pasadas, errores)
                        VALUES (?, ?, ?, 'error', 0, ?)
                    ");
                    
                    $errores_concatenados = implode(" | ", $validacion['errores']);
                    
                    $stmt_detalle->execute([
                        $importacion_id,
                        $fila_datos['username'] ?? 'N/A',
                        $fila_excel,
                        $errores_concatenados
                    ]);
                    
                    $detalles_filas[] = [
                        'fila' => $fila_excel,
                        'tipo' => 'error',
                        'username' => $fila_datos['username'] ?? 'N/A',
                        'errores' => $validacion['errores']
                    ];
                    
                } else {
                    // Datos validados correctamente
                    $data = $validacion['data_procesada'];
                    
                    // Determinar si es nuevo o actualización (buscar por username = RFC a 10)
                    $stmt_buscar = $pdo->prepare("SELECT user_id FROM usuarios WHERE username = ? LIMIT 1");
                    $stmt_buscar->execute([$data['username']]);
                    $usuario_existente = $stmt_buscar->fetch(PDO::FETCH_ASSOC);
                    $user_id_existente = $usuario_existente ? $usuario_existente['user_id'] : null;
                    
                    // Preparar campos para inserción/actualización
                    $campos_datos = [
                        'username' => $data['username'],
                        'nombre' => $data['nombre'],
                        'apellido_paterno' => $data['apellido_paterno'],
                        'apellido_materno' => $data['apellido_materno'] ?? null,
                        'RFC' => $data['RFC'] ?? null,
                        'CURP' => $data['CURP'] ?? null,
                        'IDRUSP' => $data['IDRUSP'] ?? null,
                        'sexo' => $data['sexo'] ?? null,
                        'correo' => $data['correo'] ?? null,
                        'role' => 1,  // Siempre 1 por defecto
                        'puesto_nombre' => $data['puesto_nombre'],
                        'puesto_nivel' => 6,  // Siempre 6
                        'temporal' => 0,  // Siempre 0
                        'unidad_id' => $data['unidad_id'] ?? null,
                        'adscripcion_id' => $data['adscripcion_id'] ?? null,
                        'jefe_id' => $data['jefe_id'] ?? null,
                        'puesto_codigo' => $fila_datos['puesto_codigo'] ?? null,
                        'fecha_alta' => $data['fecha_alta'] ?? null,
                    ];
                    
                    if ($user_id_existente) {
                        // ACTUALIZAR usuario existente (NO cambiar password ni requiere_cambio_password)
                        $set_clause = implode(", ", array_map(fn($k) => "$k = ?", array_keys($campos_datos)));
                        $sql_update = "UPDATE usuarios SET $set_clause, updated_at = NOW() WHERE user_id = ?";
                        
                        $params_update = array_values($campos_datos);
                        $params_update[] = $user_id_existente;
                        
                        $stmt_update = $pdo->prepare($sql_update);
                        $stmt_update->execute($params_update);
                        
                        $actualizados++;
                        
                        // Registrar en detalle
                        $stmt_detalle = $pdo->prepare("
                            INSERT INTO importaciones_usuarios_detalle
                            (importacion_id, user_id, username, nombre, apellido_paterno, RFC, CURP, IDRUSP, correo,
                             fila_numero, tipo_operacion, validaciones_pasadas)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'actualizado', 1)
                        ");
                        
                        $stmt_detalle->execute([
                            $importacion_id,
                            $user_id_existente,
                            $data['username'],
                            $data['nombre'],
                            $data['apellido_paterno'],
                            $data['RFC'] ?? null,
                            $data['CURP'] ?? null,
                            $data['IDRUSP'] ?? null,
                            $data['correo'] ?? null,
                            $numero_fila
                        ]);
                        
                        $detalles_filas[] = [
                            'fila' => $numero_fila,
                            'tipo' => 'actualizado',
                            'user_id' => $user_id_existente,
                            'username' => $data['username'],
                            'nombre' => $data['nombre']
                        ];
                        
                    } else {
                        // INSERTAR nuevo usuario
                        // Password =  RFC a 10 posiciones (username) convertido a hash
                        $password_rfc = $data['username'];  // RFC a 10 posiciones
                        $password_hash = password_hash($password_rfc, PASSWORD_DEFAULT);
                        
                        $campos_datos['password'] = $password_hash;
                        $campos_datos['requiere_cambio_password'] = 1;  // Forzar cambio en primer login
                        $campos_datos['estatus'] = 1;  // Activo
                        
                        $placeholders = implode(", ", array_fill(0, count($campos_datos), "?"));
                        $columnas_insert = implode(", ", array_keys($campos_datos));
                        $sql_insert = "INSERT INTO usuarios ($columnas_insert) VALUES ($placeholders)";
                        
                        $stmt_insert = $pdo->prepare($sql_insert);
                        $stmt_insert->execute(array_values($campos_datos));
                        
                        $user_id_nuevo = $pdo->lastInsertId();
                        $nuevos++;
                        
                        // Registrar en detalle
                        $stmt_detalle = $pdo->prepare("
                            INSERT INTO importaciones_usuarios_detalle
                            (importacion_id, user_id, username, nombre, apellido_paterno, RFC, CURP, IDRUSP, correo,
                             fila_numero, tipo_operacion, validaciones_pasadas)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'nuevo', 1)
                        ");
                        
                        $stmt_detalle->execute([
                            $importacion_id,
                            $user_id_nuevo,
                            $data['username'],
                            $data['nombre'],
                            $data['apellido_paterno'],
                            $data['RFC'] ?? null,
                            $data['CURP'] ?? null,
                            $data['IDRUSP'] ?? null,
                            $data['correo'] ?? null,
                            $numero_fila
                        ]);
                        
                        $detalles_filas[] = [
                            'fila' => $numero_fila,
                            'tipo' => 'nuevo',
                            'user_id' => $user_id_nuevo,
                            'username' => $data['username'],
                            'nombre' => $data['nombre']
                        ];
                    }
                }
            }
            
            // PASO 3: Actualizar estado de importación
            $stmt_actualizar_imp = $pdo->prepare("
                UPDATE importaciones_usuarios 
                SET total_nuevos = ?, total_actualizados = ?, total_errores = ?, estado = 'completado', 
                    mensaje_resumen = ?
                WHERE id = ?
            ");
            
            $resumen = "Importación completada: $nuevos nuevos, $actualizados actualizados, $errores_totales errores";
            $stmt_actualizar_imp->execute([
                $nuevos,
                $actualizados,
                $errores_totales,
                $resumen,
                $importacion_id
            ]);
            
            // Confirmar transacción
            $pdo->commit();
            
            // Preparar mensaje de éxito
            $mensaje = "✅ <strong>Importación completada exitosamente</strong><br>";
            $mensaje .= "📊 Resumen: <strong>$nuevos nuevos</strong>, <strong>$actualizados actualizados</strong>, <strong>$errores_totales errores</strong><br>";
            $mensaje .= "🔑 <em>Usuarios nuevos creados con password = RFC a 10 posiciones (username). Deben cambiar el password al primer login.</em>";
            $tipo_alerta = ($errores_totales > 0) ? "warning" : "success";
            
            $detalles_importacion = [
                'importacion_id' => $importacion_id,
                'nuevos' => $nuevos,
                'actualizados' => $actualizados,
                'errores' => $errores_totales,
                'detalles_filas' => $detalles_filas,
                'hash_archivo' => $hash_archivo
            ];
            
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = "❌ Error fatal al procesar archivo: " . $e->getMessage();
        $tipo_alerta = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="es" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo htmlspecialchars($nombre_sistema); ?></title>

    <link href="assets/fonts/inter/inter.css" rel="stylesheet" type="text/css">
    <link href="assets/icons/phosphor/styles.min.css" rel="stylesheet" type="text/css">
    <link href="assets/css/ltr/all.min.css" id="stylesheet" rel="stylesheet" type="text/css">

    <script src="assets/demo/demo_configurator.js"></script>
    <script src="assets/js/bootstrap/bootstrap.bundle.min.js"></script>
    <link href="assets/icons/icomoon/styles.min.css" rel="stylesheet" type="text/css">

    <script src="assets/js/jquery/jquery.min.js"></script>
    <script src="assets/js/vendor/tables/datatables/datatables.min.js"></script>
    <script src="assets/js/vendor/notifications/bootbox.min.js"></script>

    <script src="assets/js/app.js"></script>
    <script src="assets/demo/pages/components_modals.js"></script>
    <script src="assets/demo/pages/components_buttons.js"></script>
</head>
<body>

<?php require_once('assets/main_navbar.php'); ?>

<div class="page-content">

<?php require_once('assets/main_navigation.php'); ?>

    <div class="content-wrapper">
        <div class="content-inner">

            <div class="page-header page-header-light shadow">
                <div class="page-header-content d-lg-flex border-top">
                    <div class="d-flex">
                        <div class="breadcrumb py-2">
                            <a href="index.php" class="breadcrumb-item"><i class="ph-house"></i></a>
                            <a href="admin_usuarios.php" class="breadcrumb-item">Administración</a>
                            <span class="breadcrumb-item active">Importar Usuarios</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content">

                <!-- Botón para ver historial -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>📥 Importar Usuarios desde Excel</h2>
                    <a href="admin_importaciones_historial.php" class="btn btn-info">
                        <i class="ph-list"></i> Ver historial de importaciones
                    </a>
                </div>

                <!-- Alertas de resultado -->
                <?php if (!empty($mensaje)): ?>
                    <div class="alert alert-<?= $tipo_alerta ?> alert-dismissible fade show" role="alert">
                        <?= $mensaje ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- SECCIÓN 1: INSTRUCCIONES -->
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="ph-info"></i> Instrucciones paso a paso</h5>
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="instructivos">
                            
                            <!-- Paso 1 -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#paso1">
                                        <strong>Paso 1:</strong> Preparar el archivo Excel
                                    </button>
                                </h2>
                                <div id="paso1" class="accordion-collapse collapse show" data-bs-parent="#instructivos">
                                    <div class="accordion-body">
                                        <p><strong>Campos requeridos para la importación:</strong></p>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Campo Excel</th>
                                                        <th>Req.</th>
                                                        <th>Descripción y Ejemplo</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr class="table-active">
                                                        <td colspan="3"><strong>CAMPOS OBLIGATORIOS:</strong></td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>username</code></td>
                                                        <td>✓</td>
                                                        <td><strong>RFC a 10 posiciones</strong> (4 letras + 6 dígitos). Ej: <code>ABCD123456</code>. <em>Se convierte a mayúsculas automáticamente.</em></td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>nombre</code></td>
                                                        <td>✓</td>
                                                        <td>Nombre de pila. Ej: <code>Juan</code></td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>apellido_paterno</code></td>
                                                        <td>✓</td>
                                                        <td>Apellido paterno. Ej: <code>Pérez</code></td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>apellido_materno</code></td>
                                                        <td>✓</td>
                                                        <td>Apellido materno. Ej: <code>García</code></td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>RFC</code></td>
                                                        <td>✓</td>
                                                        <td>RFC completo (13 caracteres). Ej: <code>ABC123XY0AB12</code></td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>CURP</code></td>
                                                        <td>✓</td>
                                                        <td>CURP (18 caracteres). Ej: <code>ABC123XY0AB12ABC01</code></td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>sexo</code></td>
                                                        <td>✓</td>
                                                        <td>H (Hombre), M (Mujer) u Otro</td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>puesto_nombre</code></td>
                                                        <td>✓</td>
                                                        <td>Nombre del puesto. Ej: <code>ANALISTA PROGRAMADOR</code>. <em>Se convierte a mayúsculas.</em></td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>unidad_id</code></td>
                                                        <td>✓</td>
                                                        <td>ID de la unidad administrativa. <strong><a href="unidades_descargar.php" class="btn btn-sm btn-outline-success">Debe existir en el catálogo de unidades.</a> </strong></td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>adscripcion_id</code></td>
                                                        <td>✓</td>
                                                        <td>ID de la adscripción (área específica). <strong><a href="dscripciones_descargar.php" class="btn btn-sm btn-outline-success" ">Debe existir en el catálogo de adscripciones.</a></strong></td>
                                                    </tr>
                                                    <tr class="table-active">
                                                        <td colspan="3"><strong>CAMPOS OPCIONALES:</strong></td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>correo</code></td>
                                                        <td>—</td>
                                                        <td>Email válido. Ej: <code>juan.perez@example.com</code>. Si se proporciona, <strong>debe ser único</strong></td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>IDRUSP</code></td>
                                                        <td>—</td>
                                                        <td>Número RUSP del empleado (opcional)</td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>jefe_id</code></td>
                                                        <td>—</td>
                                                        <td>user_id del jefe inmediato. <strong>Debe existir en el sistema.</strong></td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>puesto_codigo</code></td>
                                                        <td>—</td>
                                                        <td>Código identificador del puesto (opcional)</td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>fecha_alta</code></td>
                                                        <td>—</td>
                                                        <td>Fecha de contratación (DD/MM/YYYY o YYYY-MM-DD)</td>
                                                    </tr>
                                                    <tr class="table-active">
                                                        <td colspan="3"><strong>CAMPOS AUTOMÁTICOS (no incluir en Excel):</strong></td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>user_id</code></td>
                                                        <td>—</td>
                                                        <td>Se asigna automáticamente (secuencial)</td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>password</code></td>
                                                        <td>—</td>
                                                        <td><strong>Se genera automáticamente</strong> a partir del username (RFC a 10). Els usuarios deben cambiarla al primer login.</td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>role</code></td>
                                                        <td>—</td>
                                                        <td>Siempre = 1 (Usuario normal). Los admin asignan roles después.</td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>puesto_nivel</code></td>
                                                        <td>—</td>
                                                        <td>Siempre = 6 (nivel por defecto)</td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>temporal</code></td>
                                                        <td>—</td>
                                                        <td>Siempre = 0 (no temporal)</td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>requiere_cambio_password</code></td>
                                                        <td>—</td>
                                                        <td>Siempre = 1 para usuarios nuevos (se fuerza cambio en primer login)</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="alert alert-info mt-3">
                                            <strong>Descargar catálogos y plantilla:</strong>
                                            <a href="plantillas/plantilla_usuarios.xlsx" class="btn btn-sm btn-outline-success" download>
                                                Plantilla usuarios
                                            </a>
                                            <a href="unidades_descargar.php" class="btn btn-sm btn-outline-primary" download>
                                              Catálogo Unidades
                                            </a>
                                            <a href="adscripciones_descargar.php" class="btn btn-sm btn-outline-primary" download>
                                                Catálogo Adscripciones
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Paso 2 -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#paso2">
                                        <strong>Paso 2:</strong> Validaciones automáticas
                                    </button>
                                </h2>
                                <div id="paso2" class="accordion-collapse collapse" data-bs-parent="#instructivos">
                                    <div class="accordion-body">
                                        <p><strong>El sistema valida automáticamente:</strong></p>
                                        <ul>
                                            <li><strong>Campos obligatorios (10):</strong> username, nombre, apellido_paterno, apellido_materno, RFC, CURP, sexo, puesto_nombre, unidad_id, adscripcion_id</li>
                                            <li><strong>Username (RFC a 10):</strong> Exactamente 4 letras mayúsculas + 6 dígitos (ej: ABCD123456). <strong>Debe ser único.</strong> Se convierte automáticamente a mayúsculas.</li>
                                            <li><strong>Email:</strong> Formato válido, único en la base de datos</li>
                                            <li><strong>RFC:</strong> 13 caracteres (6 letras + 8 dígitos + 3 caracteres)</li>
                                            <li><strong>CURP:</strong> 18 caracteres alfanuméricos</li>
                                            <li><strong>Nombres y apellidos:</strong> Sin números, sin caracteres especiales</li>
                                            <li><strong>Puesto_nombre:</strong> Se convierte automáticamente a MAYÚSCULAS</li>
                                            <li><strong>Unidad_id y adscripcion_id:</strong> <strong>Deben existir obligatoriamente</strong> en los catálogos de unidades y adscripciones</li>
                                            <li><strong>Jefe_id (si aplica):</strong> Debe corresponder a un usuario existente en el sistema</li>
                                            <li><strong>Fechas:</strong> Convertidas automáticamente a YYYY-MM-DD</li>
                                            <li><strong>Sexo:</strong> Valores permitidos: H, M, Otro</li>
                                        </ul>
                                        <p class="text-info mt-3"><i class="ph-info"></i> <strong>Password automático:</strong> Los usuarios nuevos se crean con password = <strong>RFC a 10 posiciones (username)</strong> en formato hash. <strong>Deben cambiar el password obligatoriamente al primer login.</strong></p>
                                        <p class="text-success"><strong>Actualizar usuarios existentes:</strong> Si el username ya existe, se actualiza toda la información EXCEPTO la password y la bandera requiere_cambio_password.</p>
                                        <p class="text-warning mt-2"><i class="ph-warning"></i> Si hay errores de validación, <strong>no se importa nada</strong> (transacción reversible - se revierte completamente).</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Paso 3 -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#paso3">
                                        <strong>Paso 3:</strong> Cargar archivo
                                    </button>
                                </h2>
                                <div id="paso3" class="accordion-collapse collapse" data-bs-parent="#instructivos">
                                    <div class="accordion-body">
                                        <ol>
                                            <li>Descarga la plantilla Excel (si aún no la tienes)</li>
                                            <li>Rellena los datos de los usuarios con los campos obligatorios</li>
                                            <li>Guarda el archivo en formato Excel (.xlsx)</li>
                                            <li>Haz clic en "Seleccionar archivo" y elige tu archivo Excel</li>
                                            <li>Haz clic en "Cargar usuarios"</li>
                                            <li>El sistema procesa todos los registros y muestra un resumen detallado</li>
                                        </ol>
                                        <p class="text-info mt-3"><i class="ph-info"></i> <strong>Usuarios nuevos:</strong> Se crean con <strong>password = RFC a 10 posiciones (username)</strong> en formato hash. <strong>Los usuarios deben cambiar obligatoriamente el password al primer login.</strong></p>
                                        <p class="text-warning mt-2"><i class="ph-warning"></i> <strong>Importante:</strong> Si el username (RFC a 10) ya existe en el sistema, se actualiza toda la información del usuario EXCEPTO el password.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Paso 4 -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#paso4">
                                        <strong>Paso 4:</strong> Ver historial
                                    </button>
                                </h2>
                                <div id="paso4" class="accordion-collapse collapse" data-bs-parent="#instructivos">
                                    <div class="accordion-body">
                                        <p>Haz clic en "Ver historial de importaciones" para:</p>
                                        <ul>
                                            <li>Ver todas las importaciones realizadas</li>
                                            <li>Consultar detalles de cada usuario importado</li>
                                            <li>Eliminar un lote completo de usuarios (revierte los cambios)</li>
                                            <li>Filtrar por fecha, admin, estado</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 2: FORMULARIO DE CARGA -->
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="ph-upload"></i> Cargar archivo</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data" id="formImportacion">
                            <div class="mb-3">
                                <label for="archivo" class="form-label">Archivo Excel (.xlsx):</label>
                                <input type="file" class="form-control" name="archivo" id="archivo" accept=".xlsx,.xls" required>
                                <small class="text-muted">Solo archivos Excel: .xlsx o .xls</small>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ph-upload-simple"></i> Cargar usuarios
                                </button>
                                <a href="admin_usuarios.php" class="btn btn-secondary">
                                    <i class="ph-arrow-left"></i> Regresar
                                </a>
                                <a href="plantillas/plantilla_usuarios.xlsx" class="btn btn-warning" download>
                                    <i class="ph-download"></i> Descargar plantilla
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- SECCIÓN 3: RESULTADO DETALLADO -->
                <?php if ($detalles_importacion): ?>
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="ph-info"></i> Detalles de la importación</h5>
                    </div>
                    <div class="card-body">
                        
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <div class="card bg-success bg-opacity-10 border-success">
                                    <div class="card-body text-center">
                                        <h5 class="card-title text-success"><?= $detalles_importacion['nuevos'] ?></h5>
                                        <p class="card-text">Usuarios nuevos</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info bg-opacity-10 border-info">
                                    <div class="card-body text-center">
                                        <h5 class="card-title text-info"><?= $detalles_importacion['actualizados'] ?></h5>
                                        <p class="card-text">Usuarios actualizados</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-danger bg-opacity-10 border-danger">
                                    <div class="card-body text-center">
                                        <h5 class="card-title text-danger"><?= $detalles_importacion['errores'] ?></h5>
                                        <p class="card-text">Errores</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-secondary bg-opacity-10 border-secondary">
                                    <div class="card-body text-center">
                                        <h5 class="card-title text-secondary"><?= $detalles_importacion['importacion_id'] ?></h5>
                                        <p class="card-text">ID Importación</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (count($detalles_importacion['detalles_filas']) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fila</th>
                                        <th>Usuario</th>
                                        <th>Tipo</th>
                                        <th>Detalles</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($detalles_importacion['detalles_filas'] as $detalle): ?>
                                    <tr>
                                        <td><small class="text-muted">#<?= $detalle['fila'] ?></small></td>
                                        <td>
                                            <strong><?= htmlspecialchars($detalle['username']) ?></strong>
                                            <?php if (isset($detalle['nombre'])): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($detalle['nombre']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($detalle['tipo'] === 'nuevo'): ?>
                                                <span class="badge bg-success">Nuevo</span>
                                            <?php elseif ($detalle['tipo'] === 'actualizado'): ?>
                                                <span class="badge bg-info">Actualizado</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Error</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($detalle['tipo'] === 'error'): ?>
                                                <small class="text-danger">
                                                    <?php foreach ($detalle['errores'] as $error): ?>
                                                        <div>• <?= htmlspecialchars($error) ?></div>
                                                    <?php endforeach; ?>
                                                </small>
                                            <?php elseif (isset($detalle['user_id'])): ?>
                                                <small class="text-muted">ID: <?= $detalle['user_id'] ?></small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
                <?php endif; ?>

            </div>

        </div>
    </div>

</div>

<?php require_once('assets/footer.php'); ?>

</body>
</html>