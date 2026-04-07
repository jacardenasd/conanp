<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin(2);

if (isset($_POST['guardar'])) {
    $user_id = $_POST['user_id'];
    $nombre = $_POST['nombre_curso'];
    $horas = $_POST['horas'];
    $calificacion = $_POST['calificacion'];
    $institucion = $_POST['institucion'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $modalidad = $_POST['modalidad'];
    $correo = $_POST['correo'];
    $telefono = $_POST['telefono'];
    $categoria = $_POST['categoria'];
    $periodo = $_POST['periodo'];
    
    // Validar bloqueo de capacitación para 2025 desde variables
    // EXCEPCIÓN: Los administradores pueden agregar en cualquier momento
    if ($_SESSION['role'] != 3) {
        $clave_bloqueo_cap = 'bloquear_capacitacion_' . (int)$periodo;
        $bloquear_cap = obtener_variable($clave_bloqueo_cap);
        if ($bloquear_cap === null || $bloquear_cap === '') {
            $bloquear_cap = ((int)$periodo === 2025)
                ? (obtener_variable('bloquear_capacitacion_2025') ?? '1')
                : '0';
        }

        if ((string)$bloquear_cap === '1') {
            header("Location: admin_capacitacion.php?info=10&error=2025_bloqueado");
            exit;
        }
    }
    
    // NO RESTRINGIR POR PERÍODO DE EVALUACIÓN EN ADMINISTRACIÓN
    // Los administradores pueden agregar capacitación en cualquier momento
    
    $archivo_nombre = null;

    if (isset($_FILES['archivo_pdf']) && $_FILES['archivo_pdf']['error'] === UPLOAD_ERR_OK) {
        $archivo_tmp = $_FILES['archivo_pdf']['tmp_name'];
        $archivo_original = $_FILES['archivo_pdf']['name'];
        $archivo_nombre = time() . '_' . $archivo_original;
        move_uploaded_file($archivo_tmp, 'capacitacion/' . $archivo_nombre);
    }

    $stmt = $pdo->prepare("INSERT INTO capacitacion (user_id, nombre_curso, horas, calificacion, institucion, fecha_inicio, fecha_fin, modalidad, correo, telefono, categoria, archivo_pdf, validado, periodo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)");
    $stmt->execute([$user_id, $nombre, $horas, $calificacion, $institucion, $fecha_inicio, $fecha_fin, $modalidad, $correo, $telefono, $categoria, $archivo_nombre, $periodo]);

    header("Location: admin_capacitacion.php?info=1");
    exit;
}
?>
