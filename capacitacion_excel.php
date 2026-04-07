<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';
require_once 'vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin(2);


use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$periodo = $_GET['periodo'] ?? '';
$empleado = $_GET['empleado'] ?? '';
$unidad_id = $_GET['unidad_id'] ?? '';
$trimestre = $_GET['trimestre'] ?? '';
$validado = $_GET['validado'] ?? '';
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';


function quitar_acentos($cadena) {
    $originales = ['Á','É','Í','Ó','Ú','Ñ','Ü','á','é','í','ó','ú','ñ','ü'];
    $modificadas = ['A','E','I','O','U','N','U','A','E','I','O','U','N','U'];
    return str_replace($originales, $modificadas, $cadena);
}


$params = [];
$sql = "SELECT 
    c.nombre_curso, c.horas, c.calificacion, c.fecha_inicio, c.fecha_fin, cm.modalidad, cc.categoria,  u.IDRUSP, CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS nombre_completo, tp.nombre AS tipo_usuario, c.correo, c.telefono
FROM capacitacion c
JOIN usuarios u ON c.user_id = u.user_id
LEFT JOIN tipos_puesto tp ON u.tipo_usuario = tp.id
LEFT JOIN capacitacion_modalidades cm ON c.modalidad = cm.id
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
if ($unidad_id != '') {
    $sql .= " AND u.unidad_id = ?";
    $params[] = $unidad_id;
}
if ($trimestre != '' && in_array((int)$trimestre, [1,2,3,4], true)) {
    $mes_inicio = (((int)$trimestre - 1) * 3) + 1;
    $mes_fin = $mes_inicio + 2;
    $sql .= " AND MONTH(c.fecha_inicio) BETWEEN ? AND ?";
    $params[] = $mes_inicio;
    $params[] = $mes_fin;
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
    $sheet->setCellValue("B{$fila}", mb_strtoupper(quitar_acentos($r['nombre_completo']), "UTF-8"));
    $sheet->setCellValue("C{$fila}", $r['IDRUSP']);
    $sheet->setCellValue("D{$fila}", mb_strtoupper(quitar_acentos($r['tipo_usuario']), "UTF-8"));
    $sheet->setCellValue("E{$fila}", mb_strtoupper(quitar_acentos($r['nombre_curso']), "UTF-8"));
    $sheet->setCellValue("F{$fila}", mb_strtoupper(quitar_acentos($r['modalidad']), "UTF-8"));
    $sheet->setCellValue("G{$fila}", mb_strtoupper(quitar_acentos($r['categoria']), "UTF-8"));
    $sheet->setCellValue("H{$fila}", $r['horas']);
    $sheet->setCellValue("I{$fila}", mb_strtoupper(quitar_acentos($r['correo']), "UTF-8"));
    $sheet->setCellValue("J{$fila}", mb_strtoupper(quitar_acentos($r['telefono']), "UTF-8"));
    $sheet->setCellValue("K{$fila}", $r['calificacion']);
    $sheet->setCellValue("L{$fila}", date("d/m/Y", strtotime($r['fecha_inicio'])));
    $sheet->setCellValue("M{$fila}", date("d/m/Y", strtotime($r['fecha_fin'])));
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
