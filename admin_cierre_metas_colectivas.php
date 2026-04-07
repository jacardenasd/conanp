<?php
require 'includes/session.php';
checkLogin(2);

$query = $_SERVER['QUERY_STRING'] ?? '';
$destino = 'admin_metas_colectivas.php' . ($query !== '' ? ('?' . $query) : '');

header('Location: ' . $destino);
exit;
?>
