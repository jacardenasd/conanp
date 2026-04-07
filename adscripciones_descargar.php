<?php
/**
 * adscripciones_descargar.php
 * Desc: Descarga el catálogo de adscripciones en Excel
 */

require 'vendor/autoload.php';
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

checkLogin();

try {
    // Obtener todas las adscripciones con sus unidades relacionadas
    $stmt = $pdo->prepare("
        SELECT a.id, a.nombre, u.nombre as nombre_unidad
        FROM adscripciones a
        LEFT JOIN unidades u ON u.id = a.unidad_id
        ORDER BY u.nombre ASC, a.nombre ASC
    ");
    $stmt->execute();
    $adscripciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Crear el Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Catálogo Adscripciones');

    // Establecer encabezados
    $sheet->setCellValue('A1', 'ID');
    $sheet->setCellValue('B1', 'Nombre de Adscripción');
    $sheet->setCellValue('C1', 'Unidad Administrativa');

    // Dar formato a la fila de encabezados
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '00B050']],
        'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
        'borders' => ['allBorders' => ['borderStyle' => 'thin']],
    ];

    for ($col = 'A'; $col <= 'C'; $col++) {
        $sheet->getStyle($col . '1')->applyFromArray($headerStyle);
    }

    // Llenar datos
    $fila = 2;
    foreach ($adscripciones as $adscripcion) {
        $sheet->setCellValue('A' . $fila, $adscripcion['id']);
        $sheet->setCellValue('B' . $fila, $adscripcion['nombre']);
        $sheet->setCellValue('C' . $fila, $adscripcion['nombre_unidad'] ?? '');
        
        // Aplicar bordes a todas las celdas de datos
        for ($col = 'A'; $col <= 'C'; $col++) {
            $sheet->getStyle($col . $fila)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => 'thin']],
                'alignment' => ['vertical' => 'top', 'wrapText' => true],
            ]);
        }
        
        $fila++;
    }

    // Ajustar ancho de columnas
    $sheet->getColumnDimension('A')->setWidth(10);
    $sheet->getColumnDimension('B')->setWidth(45);
    $sheet->getColumnDimension('C')->setWidth(40);

    // Crear archivo temporal
    $filename = 'Catalogo_Adscripciones_' . date('Y-m-d_His') . '.xlsx';
    $tempFile = sys_get_temp_dir() . '/' . $filename;

    $writer = new Xlsx($spreadsheet);
    $writer->save($tempFile);

    // Forzar descarga
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    
    readfile($tempFile);
    unlink($tempFile);
    exit;
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?>
