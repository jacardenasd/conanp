<?php
require 'config/db.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Html as HtmlWriter;
use Dompdf\Dompdf;
use Dompdf\Options;

// Parámetros
$user_id = $_GET['user_id'] ?? '';
$periodo = $_GET['periodo'] ?? '';
if (!$user_id || !$periodo) {
    die("Faltan parámetros.");
}

// Crear carpeta si no existe
$carpeta = __DIR__ . "/reportes/individuales";
if (!file_exists($carpeta)) {
    mkdir($carpeta, 0777, true);
}

// Obtener datos del usuario
$stmt = $pdo->prepare("SELECT u.*, p.puesto AS puesto, a.nombre AS adscripcion, un.nombre AS unidad
                       FROM usuarios u
                       LEFT JOIN puestos p ON u.puesto_id = p.id
                       LEFT JOIN adscripciones a ON u.adscripcion_id = a.id
                       LEFT JOIN unidades un ON u.unidad_id = un.id
                       WHERE u.user_id = ?");
$stmt->execute([$user_id]);
$usuario = $stmt->fetch();
if (!$usuario) {
    die("Usuario no encontrado.");
}

// Obtener metas del periodo
$stmt = $pdo->prepare("SELECT * FROM metas WHERE user_id = ? AND periodo = ?");
$stmt->execute([$user_id, $periodo]);
$metas = $stmt->fetchAll();

// Cargar plantilla
$reader = IOFactory::createReader('Xlsx');
$spreadsheet = $reader->load(__DIR__ . '/plantillas/plantilla_reporte.xlsx');
$sheet = $spreadsheet->getActiveSheet();

// Rellenar datos generales
$sheet->setCellValue('B3', $usuario['nombre']);
$sheet->setCellValue('B4', $usuario['RFC']);
$sheet->setCellValue('B5', $usuario['puesto']);
$sheet->setCellValue('B6', $usuario['unidad']);
$sheet->setCellValue('B7', $usuario['adscripcion']);
$sheet->setCellValue('B8', $periodo);

// Rellenar metas a partir de fila 11
$fila = 11;
foreach ($metas as $i => $meta) {
    $sheet->setCellValue("A{$fila}", $i + 1);
    $sheet->setCellValue("B{$fila}", $meta['nombre_meta']);
    $sheet->setCellValue("C{$fila}", $meta['indicador']);
    $sheet->setCellValue("D{$fila}", $meta['unidad']);
    $sheet->setCellValue("E{$fila}", $meta['ponderacion']);
    $sheet->setCellValue("F{$fila}", $meta['resultado']);
    $fila++;
}

// Convertir a HTML
ob_start();
$htmlWriter = new HtmlWriter($spreadsheet);
$htmlWriter->save('php://output');
$htmlContent = ob_get_clean();

// Generar PDF con Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($htmlContent);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Guardar PDF
$fecha = date("Ymd_His");
$nombreArchivo = "reporte_{$user_id}_{$periodo}_{$fecha}.pdf";
$ruta = $carpeta . "/" . $nombreArchivo;
file_put_contents($ruta, $dompdf->output());

// Descargar
header("Content-Type: application/pdf");
header("Content-Disposition: attachment; filename=\"$nombreArchivo\"");
readfile($ruta);
exit;
