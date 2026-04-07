<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';
require 'includes/instrumentos_pnd.php';
require 'includes/ejes_pnd.php';
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
        error_log('No se pudo insertar logo en generar_excel_colectivas: ' . $e->getMessage());
        return false;
    }
}

function resolver_nombre_catalogo($valorCrudo, array $mapaCatalogo, string $default = 'No definido'): string {
    if ($valorCrudo === null || $valorCrudo === '') {
        return $default;
    }

    // Caso normal: el valor guardado es ID entero del catalogo.
    $valorId = (int)$valorCrudo;
    if ($valorId > 0 && isset($mapaCatalogo[$valorId])) {
        return (string)$mapaCatalogo[$valorId];
    }

    // Respaldo para datos historicos: si ya viene texto, usarlo directo.
    if (is_string($valorCrudo)) {
        $texto = trim($valorCrudo);
        if ($texto !== '' && !ctype_digit($texto)) {
            return $texto;
        }
    }

    return $default;
}

// Obtener datos por GET
$periodo = $_GET['periodo'] ?? null;
$unidad_id = $_GET['unidad_id'];

if (!$unidad_id || !$periodo) {
    die("Faltan parámetros.");
}

$directorio = __DIR__ . '/reportes/colectivas/';
if (!file_exists($directorio)) {
    mkdir($directorio, 0777, true); // crea recursivamente con permisos
}

// Consultar metas
$stmt = $pdo->prepare("SELECT * FROM metas_colectivas WHERE unidad_id = ? AND periodo = ?");
$stmt->execute([$unidad_id, $periodo]);
$metas = $stmt->fetchAll(PDO::FETCH_ASSOC);
$mapa_instrumentos = obtener_mapa_instrumentos_pnd($pdo, false);
$mapa_unidades_legacy = [];
$stmtUnidadesLegacy = $pdo->query("SELECT id, nombre FROM unidades_medida");
if ($stmtUnidadesLegacy) {
    foreach ($stmtUnidadesLegacy->fetchAll(PDO::FETCH_ASSOC) as $rowUnidad) {
        $mapa_unidades_legacy[(int)$rowUnidad['id']] = (string)$rowUnidad['nombre'];
    }
}
asegurar_columna_eje_pnd_metas_colectivas($pdo);
$mapa_ejes_pnd = [];
foreach (obtener_ejes_pnd($pdo, false) as $eje) {
    $mapa_ejes_pnd[(int)$eje['id']] = $eje['nombre'];
}

// CORRECCIÓN: Obtener los datos del EVALUADOR DIRECTIVO de esta unidad específica
// No del usuario en sesión, sino del titular/evaluador directivo asignado a esta unidad
$stmt2 = $pdo->prepare("SELECT u.nombre, u.apellido_paterno, u.apellido_materno, u.rfc, u.curp, u.idrusp, 
                        u.puesto_nombre AS puesto, un.nombre AS unidad, 
                        j.nombre AS jefe_nombre, j.apellido_paterno AS jefe_apellido_paterno, 
                        j.apellido_materno AS jefe_apellido_materno 
                        FROM usuarios u 
                        LEFT JOIN adscripciones a ON u.adscripcion_id = a.id 
                        LEFT JOIN unidades un ON u.unidad_id = un.id 
                        LEFT JOIN usuarios j ON u.jefe_id = j.user_id 
                        WHERE u.unidad_id = ? AND u.permite_metas_colectivas = 1 AND u.estatus = 1
                        LIMIT 1");
$stmt2->execute([$unidad_id]);
$usuario = $stmt2->fetch(PDO::FETCH_ASSOC);

// Validar que existe un evaluador directivo para esta unidad
if (!$usuario) {
    die("Error: No se encontró un evaluador directivo asignado a esta unidad administrativa.");
}


// Cargar plantilla
$reader = IOFactory::createReader('Xlsx');
$spreadsheet = $reader->load(__DIR__ . '/reportes/plantilla_colectivas.xlsx');
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

$encabezado = "REPORTE DE METAS COLECTIVAS ".$periodo;

// Escribir datos generales
$sheet->setCellValue("F8", normalizar_texto_excel($usuario["nombre"]) . " " . normalizar_texto_excel($usuario["apellido_paterno"]) . " " . normalizar_texto_excel($usuario["apellido_materno"]));
$sheet->setCellValue("F9", normalizar_texto_excel($usuario["puesto"]));
$sheet->setCellValue("F10", normalizar_texto_excel($usuario["unidad"]));
$sheet->setCellValue("F11", date("d/m/Y"));
$sheet->setCellValue("A13", $encabezado);
$sheet->setCellValue("E26", normalizar_texto_excel($usuario["nombre"]) . " " . normalizar_texto_excel($usuario["apellido_paterno"]) . " " . normalizar_texto_excel($usuario["apellido_materno"]));

// Insertar metas
$fila_inicio = 16;
$suma_ponderacion = 0;
foreach ($metas as $i => $meta) {
    $instrumento = ((int)$periodo <= 2025)
        ? resolver_nombre_catalogo($meta['instrumento'] ?? null, $mapa_unidades_legacy, 'No definido')
        : resolver_nombre_catalogo($meta['instrumento'] ?? null, $mapa_instrumentos, 'No definido');
    $eje_pnd = resolver_nombre_catalogo($meta['eje_pnd_id'] ?? null, $mapa_ejes_pnd, 'No definido');

    $fila = $fila_inicio + $i;
    $sheet->setCellValue("A{$fila}", $i + 1);
    $sheet->setCellValue("B{$fila}", normalizar_texto_excel($eje_pnd));
    $sheet->setCellValue("D{$fila}", normalizar_texto_excel($instrumento));
    $sheet->setCellValue("F{$fila}", normalizar_texto_excel($meta["indicador"]));
    $sheet->setCellValue("H{$fila}", normalizar_texto_excel($meta["satisfactorio"]));
    $sheet->setCellValue("J{$fila}", normalizar_texto_excel($meta["unidad"]));
    $sheet->setCellValue("L{$fila}", $meta["ponderacion"]);
    
    // Acumular la suma de ponderaciones
    $suma_ponderacion += $meta["ponderacion"];

    // Combinar columnas por fila de metas
    $sheet->mergeCells("B{$fila}:C{$fila}");
    $sheet->mergeCells("D{$fila}:E{$fila}");
    $sheet->mergeCells("F{$fila}:G{$fila}");
    $sheet->mergeCells("H{$fila}:I{$fila}");
    $sheet->mergeCells("J{$fila}:K{$fila}");
}

// Escribir el total de ponderación en L23
$sheet->setCellValue("L23", $suma_ponderacion);
$sheet->getStyle('L23')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Combinaciones fijas de celdas de B-K en filas 18 a 23
for ($f = 16; $f <= 22; $f++) {
    $sheet->mergeCells("B{$f}:C{$f}");
    $sheet->mergeCells("D{$f}:E{$f}");
    $sheet->mergeCells("F{$f}:G{$f}");
    $sheet->mergeCells("H{$f}:I{$f}");
    $sheet->mergeCells("J{$f}:K{$f}");
}

// Combinar encabezados fijos
$sheet->mergeCells("F8:K8");
$sheet->mergeCells("F9:K9");
$sheet->mergeCells("F10:K10");
$sheet->mergeCells("F11:K11");
$sheet->mergeCells("E26:H26");
$sheet->mergeCells("A13:L13");
$sheet->getStyle('E26:H26')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Guardar archivo en servidor
$fecha = date("Ymd_His");
$nombre_archivo = "colectivas_{$unidad_id}_{$periodo}_{$fecha}.xlsx";
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
