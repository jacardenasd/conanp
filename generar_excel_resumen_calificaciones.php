<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';
require 'vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin(3);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

function normalizar_texto_excel($texto) {
    if ($texto === null) return '';
    $texto = trim((string)$texto);
    if ($texto === '') return '';
    if (!mb_check_encoding($texto, 'UTF-8')) {
        $texto = mb_convert_encoding($texto, 'UTF-8', 'ISO-8859-1');
    }
    return $texto;
}

function insertar_logo_excel($hoja, $rutaLogo, $coordenada = 'A1', $ancho = 200) {
    if (!is_file($rutaLogo)) return false;
    try {
        $drawing = new Drawing();
        $drawing->setPath($rutaLogo, false);
        $drawing->setCoordinates($coordenada);
        $drawing->setWidth($ancho);
        $drawing->setWorksheet($hoja);
        return true;
    } catch (Throwable $e) {
        error_log('Error logo resumen: ' . $e->getMessage());
        return false;
    }
}

$periodo = $_GET['periodo'] ?? null;
$unidad_id = $_GET['unidad_id'] ?? null;
$q = trim((string)($_GET['q'] ?? ''));

if (!$periodo) {
    die("Faltan parámetros.");
}

$where = " WHERE u.estatus = 1 ";
if ($unidad_id !== '' && $unidad_id !== null) {
    $where .= " AND u.unidad_id = :unidad_id ";
}
if ($q !== '') {
    $where .= " AND (u.nombre LIKE :q OR u.apellido_paterno LIKE :q OR u.apellido_materno LIKE :q OR u.RFC LIKE :q) ";
}

$sql = "
SELECT
    u.user_id,
    u.RFC,
    u.puesto_nombre,
    u.unidad_id,
    CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS nombre_completo,
    un.nombre AS unidad,
    COALESCE(c.individuales, 0) AS metas_individuales,
    COALESCE(cc.resultado, 0) AS metas_colectivas,
    COALESCE(c.gerenciales, 0) AS autogerenciales,
    COALESCE(c.capacitacion, 0) AS capacitacion,
    COALESCE(c.aportaciones_destacadas, 0) AS aportaciones_destacadas,
    COALESCE(c.actividades_extraordinarias, 0) AS actividades_extraordinarias
FROM usuarios u
LEFT JOIN unidades un ON un.id = u.unidad_id
LEFT JOIN calificaciones c ON c.user_id = u.user_id AND c.periodo = :periodo
LEFT JOIN calificaciones_colectivas cc ON cc.unidad_id = u.unidad_id AND cc.periodo = :periodo
{$where}
ORDER BY un.nombre ASC, u.apellido_paterno ASC, u.apellido_materno ASC, u.nombre ASC
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':periodo', (int)$periodo, PDO::PARAM_INT);
if ($unidad_id !== '' && $unidad_id !== null) {
    $stmt->bindValue(':unidad_id', (int)$unidad_id, PDO::PARAM_INT);
}
if ($q !== '') {
    $stmt->bindValue(':q', "%{$q}%", PDO::PARAM_STR);
}
$stmt->execute();
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Resumen Calificaciones');

// logo
$rutas_logo = [__DIR__ . '/assets/images/logo_excel_semarnat_conanp.png', __DIR__ . '/assets/images/logo_text_light.png'];
foreach ($rutas_logo as $rl) {
    if (is_file($rl)) { insertar_logo_excel($sheet, $rl, 'A1', 160); break; }
}

// Encabezados
$headers = ['ID','RFC','Nombre Completo','Puesto','Unidad','Metas Individuales','Metas Colectivas','Autogerenciales','Capacitacion','Aportaciones Destacadas','Actividades Extraordinarias'];
$col = 'A';
foreach ($headers as $h) {
    $sheet->setCellValue($col.'4', $h);
    $col++;
}

$fila = 5;
foreach ($registros as $r) {
    $sheet->setCellValue('A' . $fila, (int)$r['user_id']);
    $sheet->setCellValue('B' . $fila, normalizar_texto_excel($r['RFC']));
    $sheet->setCellValue('C' . $fila, normalizar_texto_excel($r['nombre_completo']));
    $sheet->setCellValue('D' . $fila, normalizar_texto_excel($r['puesto_nombre'] ?? '-'));
    $sheet->setCellValue('E' . $fila, normalizar_texto_excel($r['unidad'] ?? '-'));
    $sheet->setCellValue('F' . $fila, (float)$r['metas_individuales']);
    $sheet->setCellValue('G' . $fila, (float)$r['metas_colectivas']);
    $sheet->setCellValue('H' . $fila, (float)$r['autogerenciales']);
    $sheet->setCellValue('I' . $fila, (float)$r['capacitacion']);
    $sheet->setCellValue('J' . $fila, (float)$r['aportaciones_destacadas']);
    $sheet->setCellValue('K' . $fila, (float)$r['actividades_extraordinarias']);
    $fila++;
}

// Auto-size columns A-K
foreach (range('A','K') as $c) {
    $sheet->getColumnDimension($c)->setAutoSize(true);
}

$directorio = __DIR__ . '/reportes/resumen/';
if (!file_exists($directorio)) mkdir($directorio, 0777, true);
$fecha = date('Ymd_His');
$nombre_archivo = "RESUMEN_CALIFICACIONES_{$periodo}_{$fecha}.xlsx";
$ruta = $directorio . $nombre_archivo;

$writer = new Xlsx($spreadsheet);
$writer->save($ruta);

if (ob_get_level()) ob_end_clean();
header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=\"{$nombre_archivo}\"");
header("Content-Length: " . filesize($ruta));
header("Cache-Control: max-age=0");
readfile($ruta);
exit;

?>
