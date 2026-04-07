<?php
require 'config/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = intval($_POST['id']);
  $nombre = trim($_POST['nombre']);
  $unidad_id = intval($_POST['unidad_id']);

  $stmt = $pdo->prepare("UPDATE adscripciones SET nombre = ?, unidad_id = ? WHERE id = ?");
  $stmt->execute([$nombre, $unidad_id, $id]);

  header('Location: admin_adscripciones.php');
  exit;
}
?>
