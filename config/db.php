<?php
$host = 'localhost';
$dbname = 'evaluacion_conanp';
//$username = 'jacardenasd';
//$password = 'Card3n4x!Dom';
$username = 'root';
$password = 'root';


try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("ERROR: No se pudo conectar. " . $e->getMessage());
}
?>