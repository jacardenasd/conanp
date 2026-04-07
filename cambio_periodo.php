<?php
session_start();

echo $_POST['periodo'];
echo $_SESSION['periodo'];
echo $_GET['url'];

if (isset($_POST['periodo'])) {$_SESSION['periodo'] = $_POST['periodo'];}
$url = $_GET['url'];
header("Location: " . $url);
?>
