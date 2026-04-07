<?php
require 'vendor/autoload.php';
require 'config/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheetIndex = 0;

// Función para crear una hoja por tabla
function agregarHoja($spreadsheet, $pdo, $nombreTabla, $nombreHoja, $sheetIndex) {
    if ($sheetIndex > 0) {
        $spreadsheet->createSheet();
    }
    $sheet = $spreadsheet->setActiveSheetIndex($sheetIndex);
    $sheet->setTitle($nombreHoja);

    $stmt = $pdo->query("SELECT * FROM $nombreTabla");
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($datos) > 0) {
        $columnas = array_keys($datos[0]);

        // Escribir encabezados
        $col = 'A';
        foreach ($columnas as $encabezado) {
            $sheet->setCellValue($col . '1', $encabezado);
            $col++;
        }

        // Escribir datos
        $fila = 2;
        foreach ($datos as $registro) {
            $col = 'A';
            foreach ($registro as $valor) {
                $sheet->setCellValue($col . $fila, $valor);
                $col++;
            }
            $fila++;
        }
    }
}

// Generar una hoja por tabla
agregarHoja($spreadsheet, $pdo, 'unidades', 'Unidades', $sheetIndex++);
agregarHoja($spreadsheet, $pdo, 'adscripciones', 'Adscripciones', $sheetIndex++);
agregarHoja($spreadsheet, $pdo, 'puestos', 'Puestos', $sheetIndex++);

// Guardar archivo
$filename = 'estructura_organizacional_' . date('Ymd_His') . '.xlsx';
$writer = new Xlsx($spreadsheet);
$writer->save($filename);

// Forzar descarga
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');
readfile($filename);
unlink($filename);
exit;
?>
