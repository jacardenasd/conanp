<?php
require 'config/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nombre = trim($_POST['nombre']);
  $unidad_id = intval($_POST['unidad_id']);

  $stmt = $pdo->prepare("INSERT INTO adscripciones (nombre, unidad_id) VALUES (?, ?)");
  $stmt->execute([$nombre, $unidad_id]);

  header('Location: admin_adscripciones.php');
  exit;
}
?>
