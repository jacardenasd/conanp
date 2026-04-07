<?php
require 'config/db.php';
require 'includes/session.php';

// SECCIÓN BLOQUEADA: La capacitación no está disponible actualmente
//header('Location: index.php');
//exit();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin();

if (isset($_POST['actualizar'])) {
    $id = $_POST['id'];
    $nombre = $_POST['nombre_curso'];
    $horas = $_POST['horas'];
    $calificacion = $_POST['calificacion'];
    $institucion = $_POST['institucion'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $modalidad = $_POST['modalidad'];
    $correo = $_POST['correo'];
    $telefono = $_POST['telefono'];
    $observaciones = $_POST['observaciones'];
    $categoria = $_POST['categoria'];
    $archivo_nombre = null;

    if (isset($_FILES['archivo_pdf']) && $_FILES['archivo_pdf']['error'] === UPLOAD_ERR_OK) {
        $archivo_tmp = $_FILES['archivo_pdf']['tmp_name'];
        $archivo_original = $_FILES['archivo_pdf']['name'];
        $archivo_nombre = time() . '_' . $archivo_original;
        move_uploaded_file($archivo_tmp, 'capacitacion/' . $archivo_nombre);
        $sql = "UPDATE capacitacion SET nombre_curso=?, horas=?, calificacion=?, institucion=?, fecha_inicio=?, fecha_fin=?, modalidad=?, correo=?, observaciones=?, telefono=?, categoria=?, archivo_pdf=? WHERE id=?";
        $params = [$nombre, $horas, $calificacion, $institucion, $fecha_inicio, $fecha_fin, $modalidad, $correo, $observaciones, $telefono, $categoria, $archivo_nombre, $id];
    } else {
        $sql = "UPDATE capacitacion SET nombre_curso=?, horas=?, calificacion=?, institucion=?, fecha_inicio=?, fecha_fin=?, modalidad=?, correo=?, observaciones=?, telefono=?, categoria=? WHERE id=?";
        $params = [$nombre, $horas, $calificacion, $institucion, $fecha_inicio, $fecha_fin, $modalidad, $correo, $observaciones, $telefono, $categoria, $id];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    header("Location: admin_capacitacion.php?info=2");
    exit;
}
?>
