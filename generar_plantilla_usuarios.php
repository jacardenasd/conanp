<?php
/**
 * generar_plantilla_usuarios.php
 * Genera la plantilla Excel correcta para importación de usuarios con los nuevos requisitos
 * EJECUTAR SOLO UNA VEZ para crear/actualizar plantillas/plantilla_usuarios.xlsx
 */

require 'config/db.php';
require 'vendor/autoload.php';  // Incluir autoloader de Composer

// Usar PhpOffice para crear el Excel
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Usuarios');

// ENCABEZADOS - Columnas en orden exacto como las espera el procesador
$columnas = [
    'A' => 'username',
    'B' => 'nombre',
    'C' => 'apellido_paterno',
    'D' => 'apellido_materno',
    'E' => 'RFC',
    'F' => 'CURP',
    'G' => 'sexo',
    'H' => 'correo',
    'I' => 'puesto_nombre',
    'J' => 'IDRUSP',
    'K' => 'jefe_id',
    'L' => 'unidad_id',
    'M' => 'adscripcion_id',
    'N' => 'puesto_codigo',
    'O' => 'fecha_alta',
];

// Estilo para encabezados
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 12,
    ],
    'fill' => [
        'fillType' => 'solid',
        'startColor' => ['rgb' => '1F4E78'],
    ],
    'alignment' => [
        'horizontal' => 'center',
        'vertical' => 'center',
        'wrapText' => true,
    ],
    'borders' => [
        'allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => '000000']],
    ],
];

// Establecer encabezados en fila 1
$colLetra = 'A';
foreach ($columnas as $letra => $nombre) {
    $sheet->setCellValue($letra . '1', $nombre);
    $sheet->getStyle($letra . '1')->applyFromArray($headerStyle);
    $colLetra = $letra;
}

// FILA 2: EJEMPLO de datos correctos
$ejemplo = [
    'A' => 'ABCD123456',       // username - RFC a 10 posiciones (4 letras + 6 dígitos)
    'B' => 'Juan',              // nombre
    'C' => 'Pérez',             // apellido_paterno
    'D' => 'García',            // apellido_materno
    'E' => 'ABC1234PQ0AB12',   // RFC completo (13 caracteres)
    'F' => 'ABC1234PQ0AB12ABC01',  // CURP (18 caracteres)
    'G' => 'H',                 // sexo (H, M, Otro)
    'H' => 'juan.perez@example.com',  // correo
    'I' => 'ANALISTA PROGRAMADOR',    // puesto_nombre
    'J' => '',                  // IDRUSP (opcional)
    'K' => '',                  // jefe_id (opcional, user_id del jefe)
    'L' => '1',                 // unidad_id (debe existir en catálogo)
    'M' => '1',                 // adscripcion_id (debe existir en catálogo)
    'N' => 'AP001',             // puesto_codigo (opcional)
    'O' => '2025-01-15',        // fecha_alta (YYYY-MM-DD)
];

// Estilo para ejemplo
$exampleStyle = [
    'fill' => [
        'fillType' => 'solid',
        'startColor' => ['rgb' => 'E8F4F8'],
    ],
    'alignment' => [
        'vertical' => 'center',
        'wrapText' => true,
    ],
    'borders' => [
        'allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'CCCCCC']],
    ],
    'font' => [
        'italic' => true,
        'size' => 10,
    ],
];

foreach ($ejemplo as $letra => $valor) {
    $sheet->setCellValue($letra . '2', $valor);
    $sheet->getStyle($letra . '2')->applyFromArray($exampleStyle);
}

// FILA 3: DESCRIPCIÓN DE CAMPOS (2 fila)
$descripcion = [
    'A' => 'RFC a 10 (4 letras + 6 dígitos)',
    'B' => 'Nombre',
    'C' => 'Apellido paterno',
    'D' => 'Apellido materno (OBLIGATORIO)',
    'E' => 'RFC completo 13 caracteres (OBLIGATORIO)',
    'F' => 'CURP 18 caracteres (OBLIGATORIO)',
    'G' => 'H/M/Otro (OBLIGATORIO)',
    'H' => 'Email válido, único si se proporciona (opcional)',
    'I' => 'Puesto, mayúsculas (OBLIGATORIO)',
    'J' => 'Número RUSP (opcional)',
    'K' => 'ID del jefe directo (opcional)',
    'L' => 'ID unidad, ver catálogo (OBLIGATORIO)',
    'M' => 'ID adscripción, ver catálogo (OBLIGATORIO)',
    'N' => 'Código puesto (opcional)',
    'O' => 'Fecha YYYY-MM-DD o DD/MM/YYYY (opcional)',
];

$descStyle = [
    'fill' => [
        'fillType' => 'solid',
        'startColor' => ['rgb' => 'FFF2CC'],
    ],
    'alignment' => [
        'horizontal' => 'left',
        'vertical' => 'center',
        'wrapText' => true,
    ],
    'borders' => [
        'allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'CCCCCC']],
    ],
    'font' => [
        'size' => 9,
    ],
];

foreach ($descripcion as $letra => $valor) {
    $sheet->setCellValue($letra . '3', $valor);
    $sheet->getStyle($letra . '3')->applyFromArray($descStyle);
}

// Establecer altura de filas para mejor lectura
$sheet->getRowDimension(1)->setRowHeight(30);
$sheet->getRowDimension(2)->setRowHeight(25);
$sheet->getRowDimension(3)->setRowHeight(40);
$sheet->getRowDimension(4)->setRowHeight(20);

// Ajustar ancho de columnas
$sheet->getColumnDimension('A')->setWidth(18);  // username
$sheet->getColumnDimension('B')->setWidth(15);  // nombre
$sheet->getColumnDimension('C')->setWidth(18);  // apellido_paterno
$sheet->getColumnDimension('D')->setWidth(18);  // apellido_materno
$sheet->getColumnDimension('E')->setWidth(20);  // RFC
$sheet->getColumnDimension('F')->setWidth(22);  // CURP
$sheet->getColumnDimension('G')->setWidth(12);  // sexo
$sheet->getColumnDimension('H')->setWidth(25);  // correo
$sheet->getColumnDimension('I')->setWidth(28);  // puesto_nombre
$sheet->getColumnDimension('J')->setWidth(15);  // IDRUSP
$sheet->getColumnDimension('K')->setWidth(15);  // jefe_id
$sheet->getColumnDimension('L')->setWidth(15);  // unidad_id
$sheet->getColumnDimension('M')->setWidth(15);  // adscripcion_id
$sheet->getColumnDimension('N')->setWidth(15);  // puesto_codigo
$sheet->getColumnDimension('O')->setWidth(18);  // fecha_alta

// Congelar filas de encabezado y descripción
$sheet->freezePane('A4');

// Guardar archivo
$filepath = 'plantillas/plantilla_usuarios.xlsx';
$writer = new Xlsx($spreadsheet);
$writer->save($filepath);

echo "✅ Plantilla Excel creada correctamente: $filepath\n";
echo "📋 Campos incluidos: " . count($columnas) . "\n";
echo "   Obligatorios: 10\n";
echo "   Opcionales: 5\n";
echo "   Automáticos: No incluyen en Excel\n";
exit;
?>
