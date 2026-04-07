<?php 
session_start();

if ($_SESSION['sidebar_resized'] == 0) {$_SESSION['sidebar_resized'] = 1;} else {$_SESSION['sidebar_resized'] = 0;}
$url = $_GET['url'];
header("Location: " . $url);
?>
