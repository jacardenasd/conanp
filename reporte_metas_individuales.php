<?php
require 'config/db.php';
require 'includes/session.php';
require 'vendor/autoload.php';

if (!function_exists('mime_content_type')) {
    function mime_content_type($filename) {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $filename);
                finfo_close($finfo);
                if ($mime !== false) {
                    return $mime;
                }
            }
        }

        $ext = strtolower(pathinfo((string)$filename, PATHINFO_EXTENSION));
        $map = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml'
        ];

        return $map[$ext] ?? 'application/octet-stream';
    }
}

checkLogin(2);

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Style;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

function normalizar_texto_excel($texto, $aMayusculas = false) {
    if ($texto === null) {
        return '';
    }

    $texto = trim((string)$texto);
    if ($texto === '') {
        return '';
    }

    if (!mb_check_encoding($texto, 'UTF-8')) {
        $texto = mb_convert_encoding($texto, 'UTF-8', 'ISO-8859-1');
    }

    if (strpos($texto, 'Ã') !== false || strpos($texto, 'Â') !== false) {
        $reparado = @mb_convert_encoding($texto, 'UTF-8', 'ISO-8859-1');
        if ($reparado !== false && $reparado !== '') {
            $texto = $reparado;
        }
    }

    return $aMayusculas ? mb_strtoupper($texto, 'UTF-8') : $texto;
}

function insertar_logo_excel($hoja, $rutaLogo, $coordenada = 'A1', $ancho = 320, &$error = null): bool {
    if (!is_file($rutaLogo)) {
        return false;
    }

    try {
        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Logo institucional');
        $drawing->setPath($rutaLogo, false);
        $drawing->setCoordinates($coordenada);
        $drawing->setResizeProportional(true);
        $drawing->setWidth($ancho);
        $drawing->setOffsetX(8);
        $drawing->setOffsetY(4);
        $drawing->setWorksheet($hoja);
        return true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        error_log('No se pudo insertar logo en reporte_metas_individuales: ' . $e->getMessage());
        return false;
    }
}

if (!isset($_GET['periodo'])) {
    die("Falta el parámetro de periodo.");
}

$periodo = $_GET['periodo'];

// Obtener metas individuales del periodo
$stmt = $pdo->prepare("SELECT m.*, u.nombre, u.apellido_paterno, u.apellido_materno, u.rfc, u.curp, u.idrusp,
                              COALESCE(p.codigo_puesto, '') AS codigo_puesto, u.puesto_nivel AS nivel_puesto
                       FROM metas m
                       JOIN usuarios u ON m.user_id = u.user_id
                       LEFT JOIN puestos p ON u.puesto_id = p.id
                       WHERE m.periodo = ?");
$stmt->execute([$periodo]);
$metas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$spreadsheet = IOFactory::load("plantillas/layout_individuales.xlsx");
$ws = $spreadsheet->getSheetByName("Layout Metas Individuales");
if ($ws === null) {
    $ws = $spreadsheet->getActiveSheet();
}

// Inserta logo externo para evitar errores por imágenes incrustadas en la plantilla
$rutas_logo = [
    __DIR__ . '/assets/images/logo_excel_semarnat_conanp.png',
    __DIR__ . '/assets/images/logo_text_light.png'
];
$logo_insertado = false;
$logo_usado = '';
$logo_error = '';
foreach ($rutas_logo as $ruta_logo) {
    if (is_file($ruta_logo)) {
        if (insertar_logo_excel($ws, $ruta_logo, 'A1', 320, $logo_error)) {
            $ws->getRowDimension(1)->setRowHeight(52);
            $logo_insertado = true;
            $logo_usado = $ruta_logo;
        }
        break;
    }
}

if (!$logo_insertado) {
    $ws->setCellValue('A1', '[LOGO NO CARGADO]');
}

if (isset($_GET['debug_logo']) && $_GET['debug_logo'] === '1') {
    $ws->setCellValue('M1', $logo_insertado ? 'LOGO CARGADO' : 'LOGO NO CARGADO');
    $ws->setCellValue('M2', $logo_usado !== '' ? basename($logo_usado) : 'ninguno');
    $ws->setCellValue('M3', 'Drawings: ' . count($ws->getDrawingCollection()));
    $ws->setCellValue('M4', $logo_error !== '' ? $logo_error : 'sin error');
}

$fila = 7;
foreach ($metas as $meta) {
    $nombre = normalizar_texto_excel($meta['nombre']);
    $ap_paterno = normalizar_texto_excel($meta['apellido_paterno']);
    $ap_materno = normalizar_texto_excel($meta['apellido_materno']);
    $nombre_completo = "$ap_paterno $ap_materno $nombre";
    $indicador = normalizar_texto_excel($meta['indicador'], true);
    $palabras = explode(' ', $indicador);
    $columna_m = $palabras[0] ?? '';
    $columna_n = implode(' ', array_slice($palabras, 1));

    $ws->setCellValue("A$fila", $ap_paterno);
    $ws->setCellValue("B$fila", $ap_materno);
    $ws->setCellValue("C$fila", $nombre);
    $ws->setCellValue("D$fila", $nombre_completo);
    $ws->setCellValue("E$fila", $meta['rfc']);
    $ws->setCellValue("F$fila", $meta['curp']);
    $ws->setCellValue("G$fila", $meta['idrusp']);
    $ws->setCellValue("H$fila", $meta['codigo_puesto']);
    $ws->setCellValue("I$fila", $meta['nivel_puesto']);
    $ws->setCellValue("J$fila", "1. Política y Gobierno");
    $ws->setCellValue("K$fila", "Alineada a Meta Institucional");
    $ws->setCellValue("L$fila", "Atribuciones de Reglamento Interior (ARI)");
    $ws->setCellValue("M$fila", $columna_m);
    $ws->setCellValue("N$fila", $columna_n);
    $ws->setCellValue("O$fila", $indicador);
    $ws->setCellValue("P$fila", normalizar_texto_excel($meta['unidad']));
    $ws->setCellValue("Q$fila", $meta['ponderacion']);
    $ws->setCellValue("R$fila", normalizar_texto_excel($meta['sobresaliente'], true));
    $ws->setCellValue("S$fila", normalizar_texto_excel($meta['satisfactorio'], true));
    $ws->setCellValue("T$fila", normalizar_texto_excel($meta['no_satisfactorio'], true));
    $ws->setCellValue("U$fila", normalizar_texto_excel($meta['no_aprobatorio'], true));
    $ws->setCellValue("V$fila", normalizar_texto_excel($meta['deficiente'], true));

    // Aplicar estilos desde la fila 5
    for ($col = 'A'; $col <= 'V'; $col++) {
        $ws->duplicateStyle($ws->getStyle("{$col}7"), "{$col}{$fila}");
    }

    $fila++;
}

$filename = "layout_metas_individuales_{$periodo}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;
