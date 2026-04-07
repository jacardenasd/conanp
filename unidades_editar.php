<?php
require 'config/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = intval($_POST['id']);
  $nombre = trim($_POST['nombre']);

  $stmt = $pdo->prepare("UPDATE unidades SET nombre = ? WHERE id = ?");
  $stmt->execute([$nombre, $id]);

  header('Location: admin_unidades.php');
  exit;
}
?>
