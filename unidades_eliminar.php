<?php
require 'config/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = intval($_POST['id']);

  $stmt = $pdo->prepare("DELETE FROM unidades WHERE id = ?");
  $stmt->execute([$id]);

  header('Location: admin_unidades.php');
  exit;
}
?>
