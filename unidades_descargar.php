<?php
/**
 * unidades_descargar.php
 * Desc: Descarga el catálogo de unidades en Excel
 */

require 'vendor/autoload.php';
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

checkLogin();

try {
    // Obtener todas las unidades
    $stmt = $pdo->prepare("SELECT id, nombre FROM unidades ORDER BY nombre ASC");
    $stmt->execute();
    $unidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Crear el Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Catálogo Unidades');

    // Establecer encabezados
    $sheet->setCellValue('A1', 'ID');
    $sheet->setCellValue('B1', 'Nombre de Unidad');

    // Dar formato a la fila de encabezados
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1F4E78']],
        'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
        'borders' => ['allBorders' => ['borderStyle' => 'thin']],
    ];

    for ($col = 'A'; $col <= 'B'; $col++) {
        $sheet->getStyle($col . '1')->applyFromArray($headerStyle);
    }

    // Llenar datos
    $fila = 2;
    foreach ($unidades as $unidad) {
        $sheet->setCellValue('A' . $fila, $unidad['id']);
        $sheet->setCellValue('B' . $fila, $unidad['nombre']);
        
        // Aplicar bordes a todas las celdas de datos
        for ($col = 'A'; $col <= 'B'; $col++) {
            $sheet->getStyle($col . $fila)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => 'thin']],
                'alignment' => ['vertical' => 'top', 'wrapText' => true],
            ]);
        }
        
        $fila++;
    }

    // Ajustar ancho de columnas
    $sheet->getColumnDimension('A')->setWidth(10);
    $sheet->getColumnDimension('B')->setWidth(50);

    // Crear archivo temporal
    $filename = 'Catalogo_Unidades_' . date('Y-m-d_His') . '.xlsx';
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
