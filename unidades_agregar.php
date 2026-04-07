<?php
require 'config/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nombre = trim($_POST['nombre']);

  $stmt = $pdo->prepare("INSERT INTO unidades (nombre ) VALUES (?)");
  $stmt->execute([$nombre]);

  header('Location: admin_unidades.php');
  exit;
}
?>
