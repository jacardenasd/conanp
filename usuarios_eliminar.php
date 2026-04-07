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

if (isset($_GET['user_id'])) {
    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE user_id = ?");
    $stmt->execute([$_GET['user_id']]);
}

header("Location: admin_usuarios.php");
exit;
?>
