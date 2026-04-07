<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin(2);

$user_id = $_SESSION['user_id']; 
$role = $_SESSION['role'];
asegurar_columna_capacitacion_contabiliza($pdo);

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);

if (isset($_GET['id'])) {
    // Verificar si es para quitar validación (solo SUPER_ADMIN)
    if (isset($_GET['action']) && $_GET['action'] === 'quitar_validacion') {
        if ($role != 3) {
            header("Location: admin_capacitacion.php?info=8&error=no_permiso");
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE capacitacion SET validado = 0, validado_rh = 0, contabiliza_horas = 1 WHERE id = ?");
        $stmt->execute([$_GET['id']]);

        // Insertar mensaje para el usuario
        $stmtUser = $pdo->prepare("SELECT nombre_curso, user_id FROM capacitacion WHERE id = ?");
        $stmtUser->execute([$_GET['id']]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $el_curso = $user['nombre_curso'];
            $mensaje = "La validación del curso de capacitación ".$el_curso." ha sido removida. Por favor, reenvía los documentos corregidos.";
            $asunto = "Validación Removida de Curso.";
            $fecha = date('Y-m-d H:i:s');
            $leido = 0;

            $stmtMensaje = $pdo->prepare("INSERT INTO mensajes (remitente_id, destinatario_id, asunto, mensaje, fecha, leido) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtMensaje->execute([$user_id, $user['user_id'], $asunto, $mensaje, $fecha, $leido]);
        }

        header("Location: admin_capacitacion.php?info=6");
        exit;
    }
    
    $aceptar_sin_suma = (isset($_GET['action']) && $_GET['action'] === 'aceptar_sin_suma');

    // Validar / aceptar
    $stmt = $pdo->prepare("UPDATE capacitacion SET validado = 1, contabiliza_horas = ? WHERE id = ?");
    $stmt->execute([$aceptar_sin_suma ? 0 : 1, $_GET['id']]);


// Insertar mensaje para el usuario
$stmtUser = $pdo->prepare("SELECT nombre_curso, user_id FROM capacitacion WHERE id = ?");
$stmtUser->execute([$_GET['id']]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $el_curso = $user['nombre_curso'];
    if ($aceptar_sin_suma) {
        $mensaje = "Tu constancia del curso de capacitación " . $el_curso . " fue aceptada y no sumará para la acreditación de las 40 h de capacitación anual";
        $asunto = "Constancia aceptada (sin suma de horas).";
    } else {
        $mensaje = "Tu constancia del curso de capacitación " . $el_curso . " fue aceptada y sumará para la acreditación de las 40 h de capacitación anual";
        $asunto = "Validación de Curso.";
    }
    $fecha = date('Y-m-d H:i:s');
    $leido = 0;

    $stmtMensaje = $pdo->prepare("INSERT INTO mensajes (remitente_id, destinatario_id, asunto, mensaje, fecha, leido) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtMensaje->execute([$user_id, $user['user_id'], $asunto, $mensaje, $fecha, $leido]);
}

    header("Location: admin_capacitacion.php?info=" . ($aceptar_sin_suma ? '11' : '5'));
    exit;
}
?>
