<?php
require 'includes/session.php';
checkLogin(3); // Solo superadmin
require 'config/db.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Encabezados
$sheet->fromArray([
    'user_id', 'Nombre', 'Meta', 'Indicador', 'Unidad', 'Ponderación',
    'Sobresaliente', 'Satisfactorio', 'No Satisfactorio',
    'No Aprobatorio', 'Deficiente', 'Estatus'
], NULL, 'A1');

// Consultar metas con usuario
$sql = "SELECT m.*, u.nombre, u.apellido_paterno, u.apellido_materno, u.user_id
        FROM metas m
        JOIN usuarios u ON m.user_id = u.user_id
        ORDER BY u.user_id";
$stmt = $pdo->query($sql);
$metas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Llenar filas
$row = 2;
foreach ($metas as $meta) {
    $nombre_completo = $meta['nombre'] . ' ' . $meta['apellido_paterno'] . ' ' . $meta['apellido_materno'];
    $sheet->fromArray([
        $meta['user_id'],
        $nombre_completo,
        $meta['nombre_meta'],
        $meta['indicador'],
        $meta['unidad'],
        $meta['ponderacion'],
        $meta['sobresaliente'],
        $meta['satisfactorio'],
        $meta['no_satisfactorio'],
        $meta['no_aprobatorio'],
        $meta['deficiente'],
        $meta['estatus']
    ], NULL, 'A' . $row);
    $row++;
}

// Descargar archivo
$filename = "metas_" . date('Ymd_His') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;
?>
