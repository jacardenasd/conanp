<?php
require 'config/db.php';

$unidad_id = $_GET['unidad_id'] ?? '';

if ($unidad_id != '') {
    $stmt = $pdo->prepare("SELECT id, nombre FROM adscripciones WHERE unidad_id = ? ORDER BY nombre ASC");
    $stmt->execute([$unidad_id]);
    $adscripciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($adscripciones as $a) {
        echo "<option value='{$a['id']}'>{$a['nombre']}</option>";
    }
} else {
    echo "<option value=''>Seleccione unidad</option>";
}
?>
