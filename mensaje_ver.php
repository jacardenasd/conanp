<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id > 0) {
	header("Location: mensaje_detalle.php?id={$id}");
	exit;
}

header('Location: mensajes.php');
exit;
