<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';
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

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin();

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

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
        error_log('No se pudo insertar logo en generar_excel_individual: ' . $e->getMessage());
        return false;
    }
}

// Obtener datos por GET
$user_id = $_GET['user_id'] ?? null;
$periodo = $_GET['periodo'] ?? null;

if (!$user_id || !$periodo) {
    die("Faltan parámetros.");
}

$directorio = __DIR__ . '/reportes/individuales/';
if (!file_exists($directorio)) {
    mkdir($directorio, 0777, true); // crea recursivamente con permisos
}

// Consultar metas
$stmt = $pdo->prepare("SELECT * FROM metas WHERE user_id = ? AND periodo = ?");
$stmt->execute([$user_id, $periodo]);
$metas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Consultar datos del usuario
$stmt2 = $pdo->prepare("SELECT u.nombre, u.apellido_paterno, u.apellido_materno, u.rfc, u.curp, u.idrusp, u.puesto_nombre AS puesto, un.nombre AS unidad, j.nombre AS jefe_nombre, j.apellido_paterno AS jefe_apellido_paterno, j.apellido_materno AS jefe_apellido_materno FROM usuarios u LEFT JOIN adscripciones a ON u.adscripcion_id = a.id LEFT JOIN unidades un ON u.unidad_id = un.id LEFT JOIN usuarios j ON u.jefe_id = j.user_id WHERE u.user_id = ?");
$stmt2->execute([$user_id]);
$usuario = $stmt2->fetch(PDO::FETCH_ASSOC);


// Cargar plantilla
$reader = IOFactory::createReader('Xlsx');
$spreadsheet = $reader->load(__DIR__ . '/reportes/plantilla_individuales.xlsx');
$sheet = $spreadsheet->getActiveSheet();

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
        if (insertar_logo_excel($sheet, $ruta_logo, 'A1', 320, $logo_error)) {
            $sheet->getRowDimension(1)->setRowHeight(52);
            $logo_insertado = true;
            $logo_usado = $ruta_logo;
        }
        break;
    }
}

if (!$logo_insertado) {
    $sheet->setCellValue('A1', '[LOGO NO CARGADO]');
}

if (isset($_GET['debug_logo']) && $_GET['debug_logo'] === '1') {
    $sheet->setCellValue('M1', $logo_insertado ? 'LOGO CARGADO' : 'LOGO NO CARGADO');
    $sheet->setCellValue('M2', $logo_usado !== '' ? basename($logo_usado) : 'ninguno');
    $sheet->setCellValue('M3', 'Drawings: ' . count($sheet->getDrawingCollection()));
    $sheet->setCellValue('M4', $logo_error !== '' ? $logo_error : 'sin error');
}

$encabezado = "REPORTE DE METAS INDIVIDUALES ".$periodo;

// Escribir datos generales
$sheet->setCellValue("F8", normalizar_texto_excel($usuario["nombre"]) . " " . normalizar_texto_excel($usuario["apellido_paterno"]) . " " . normalizar_texto_excel($usuario["apellido_materno"]));
$sheet->setCellValue("F9", normalizar_texto_excel($usuario["puesto"]));
$sheet->setCellValue("F10", normalizar_texto_excel($usuario["unidad"]));
$sheet->setCellValue("D11", $usuario["rfc"]);
$sheet->setCellValue("G11", $usuario["curp"]);
$sheet->setCellValue("J11", $usuario["idrusp"]);
$sheet->setCellValue("F12", date("d/m/Y"));
$sheet->setCellValue("A14", $encabezado);
$sheet->setCellValue("C29", normalizar_texto_excel($usuario["jefe_nombre"]) . " " . normalizar_texto_excel($usuario["jefe_apellido_paterno"]) . " " . normalizar_texto_excel($usuario["jefe_apellido_materno"]));

// Insertar metas
$fila_inicio = 18;
$total_ponderacion = 0;
foreach ($metas as $i => $meta) {
    $fila = $fila_inicio + $i;
    $ponderacion = (float)$meta["ponderacion"];
    $sheet->setCellValue("B{$fila}", normalizar_texto_excel($meta["indicador"]));
    $sheet->setCellValue("D{$fila}", normalizar_texto_excel($meta["satisfactorio"]));
    $sheet->setCellValue("F{$fila}", normalizar_texto_excel($meta["no_satisfactorio"]));
    $sheet->setCellValue("H{$fila}", normalizar_texto_excel($meta["no_aprobatorio"]));
    $sheet->setCellValue("J{$fila}", normalizar_texto_excel($meta["deficiente"]));
    $sheet->setCellValue("L{$fila}", normalizar_texto_excel($meta["unidad"]));
    $sheet->setCellValue("M{$fila}", $ponderacion);
    $total_ponderacion += $ponderacion;

    // Combinar columnas por fila de metas
    $sheet->mergeCells("B{$fila}:C{$fila}");
    $sheet->mergeCells("D{$fila}:E{$fila}");
    $sheet->mergeCells("F{$fila}:G{$fila}");
    $sheet->mergeCells("H{$fila}:I{$fila}");
    $sheet->mergeCells("J{$fila}:K{$fila}");
}

// Registrar total de ponderación en la fila TOTAL de la cédula
$sheet->setCellValue("M25", $total_ponderacion);

// Combinaciones fijas de celdas de B-K en filas 18 a 23
for ($f = 18; $f <= 24; $f++) {
    $sheet->mergeCells("B{$f}:C{$f}");
    $sheet->mergeCells("D{$f}:E{$f}");
    $sheet->mergeCells("F{$f}:G{$f}");
    $sheet->mergeCells("H{$f}:I{$f}");
    $sheet->mergeCells("J{$f}:K{$f}");
}



// Combinar encabezados fijos
$sheet->mergeCells("A14:M14");
$sheet->mergeCells("C29:E29");
$sheet->mergeCells("I29:K29");
$sheet->mergeCells("F9:K9");
$sheet->mergeCells("F8:K8");
$sheet->mergeCells("F10:K10");
$sheet->mergeCells("F12:K12");
$sheet->mergeCells("D11:E11");
$sheet->mergeCells("G11:H11");
$sheet->mergeCells("J11:K11");
$sheet->mergeCells("J11:K11");

// Agregar nombre del evaluado en I29:K29 (firmante)
$nombre_evaluado = normalizar_texto_excel($usuario["nombre"]) . " " . normalizar_texto_excel($usuario["apellido_paterno"]) . " " . normalizar_texto_excel($usuario["apellido_materno"]);
$sheet->setCellValue("I29", $nombre_evaluado);
$sheet->duplicateStyle($sheet->getStyle("C29:E29"), "I29:K29");
$sheet->getStyle("I29:K29")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Guardar archivo en servidor
$fecha = date("Ymd_His");
$nombre_archivo = "INDIVIDUALES_{$user_id}_{$periodo}_{$fecha}.xlsx";
$ruta = $directorio . $nombre_archivo;
$writer = new Xlsx($spreadsheet);
$writer->save($ruta);

// Limpiar cualquier output previo para evitar corrupción del Excel
if (ob_get_level()) {
    ob_end_clean();
}

// Forzar descarga con headers correctos
header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=\"{$nombre_archivo}\"");
header("Content-Length: " . filesize($ruta));
header("Cache-Control: max-age=0");
header("Pragma: public");

// Enviar archivo
readfile($ruta);
exit;
?>
