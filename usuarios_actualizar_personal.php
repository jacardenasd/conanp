<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin();

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');


if (!isset($_POST['user_id'])) {
    echo "ID de usuario no proporcionado.";
    exit;
}

$user_id = $_POST['user_id'];

// Ejecutar UPDATE
$query = "UPDATE usuarios SET
    nombre = :nombre,
    apellido_paterno = :apellido_paterno,
    apellido_materno = :apellido_materno,
    RFC = :RFC,
    CURP = :CURP,
    IDRUSP = :IDRUSP,
    sexo = :sexo,
    correo = :correo,
    nivel_estudios = :nivel_estudios,
    jefe_id = :jefe_id,
    unidad_id = :unidad_id,
    adscripcion_id = :adscripcion_id,
    puesto_id = :puesto_id";
$query .= " WHERE user_id = :user_id";

$stmt = $pdo->prepare($query);
$params = [
    ':nombre' => $_POST['nombre'],
    ':apellido_paterno' => $_POST['apellido_paterno'],
    ':apellido_materno' => $_POST['apellido_materno'],
    ':RFC' => $_POST['RFC'],
    ':CURP' => $_POST['CURP'],
    ':IDRUSP' => $_POST['IDRUSP'],
    ':sexo' => $_POST['sexo'],
    ':correo' => $_POST['correo'],
    ':nivel_estudios' => $_POST['nivel_estudios'],
    ':jefe_id' => $_POST['jefe_id'],
    ':unidad_id' => $_POST['unidad_id'],
    ':adscripcion_id' => $_POST['adscripcion_id'],
    ':puesto_id' => $_POST['puesto_id'],
    ':user_id' => $user_id
];
$stmt->execute($params);

header("Location: mis_datos.php?info=1");
exit;
?>
