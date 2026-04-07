<?php
require 'config/db.php';
require 'includes/session.php';

// SECCIÓN BLOQUEADA: La capacitación no está disponible actualmente
//header('Location: index.php');
//exit();
require 'includes/variables.php';
require_once 'vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin();

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$periodo = $_GET['periodo'] ?? '';
$empleado = $_GET['empleado'] ?? '';
$validado = $_GET['validado'] ?? '';
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';

$params = [];
$sql = "SELECT 
    c.nombre_curso, c.horas, c.calificacion, c.fecha_inicio, c.fecha_fin,
    cm.modalidad, cf.finalidad, cc.categoria,
    u.IDRUSP,
    CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS nombre_completo,
    tp.nombre AS tipo_usuario
FROM capacitacion c
JOIN usuarios u ON c.user_id = u.user_id
LEFT JOIN tipos_puesto tp ON u.tipo_usuario = tp.id
LEFT JOIN capacitacion_modalidades cm ON c.modalidad = cm.id
LEFT JOIN capacitacion_finalidades cf ON c.finalidad = cf.id
LEFT JOIN capacitacion_categorias cc ON c.categoria = cc.id
WHERE 1=1";

if ($periodo != '') {
    $sql .= " AND c.periodo = ?";
    $params[] = $periodo;
}
if ($empleado != '') {
    $sql .= " AND c.user_id = ?";
    $params[] = $empleado;
}
if ($validado !== '') {
    $sql .= " AND c.validado = ?";
    $params[] = $validado;
}
if ($fecha_inicio != '' && $fecha_fin != '') {
    $sql .= " AND c.fecha_inicio BETWEEN ? AND ?";
    $params[] = $fecha_inicio;
    $params[] = $fecha_fin;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cargar el layout original desde la raíz
$layoutFile = 'plantillas/layout_capacitacion.xlsx';
$spreadsheet = IOFactory::load($layoutFile);
$sheet = $spreadsheet->getActiveSheet();

// Insertar los datos desde la fila 4
$fila = 5;
foreach ($registros as $r) {
    $trimestre = "T" . ceil(date('n', strtotime($r['fecha_inicio'])) / 3);
    $sheet->setCellValue("A{$fila}", $trimestre);
    $sheet->setCellValue("B{$fila}", $r['nombre_completo']);
    $sheet->setCellValue("C{$fila}", $r['IDRUSP']);
    $sheet->setCellValue("D{$fila}", $r['tipo_usuario']);
    $sheet->setCellValue("E{$fila}", $r['nombre_curso']);
    $sheet->setCellValue("F{$fila}", $r['modalidad']);
    $sheet->setCellValue("G{$fila}", $r['categoria']);
    $sheet->setCellValue("H{$fila}", $r['horas']);
    $sheet->setCellValue("I{$fila}", $r['finalidad']);
    $sheet->setCellValue("J{$fila}", $r['calificacion']);
    $sheet->setCellValue("K{$fila}", date("d/m/Y", strtotime($r['fecha_inicio'])));
    $sheet->setCellValue("L{$fila}", date("d/m/Y", strtotime($r['fecha_fin'])));
    $fila++;
}

// Descargar el archivo generado
$filename = "Capacitacion_" . date("Ymd_His") . ".xlsx";
header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Cache-Control: max-age=0");

$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;
?>
