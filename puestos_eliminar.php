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

$id = $_GET['id'];
$stmt = $pdo->prepare("DELETE FROM puestos WHERE id = ?");
$stmt->execute([$id]);

header("Location: admin_puestos.php?info=3");
exit;
?>
