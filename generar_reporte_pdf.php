<?php
require 'config/db.php';
require 'vendor/autoload.php';

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

// Construir HTML
$html = "
<style>
body { font-family: Arial, sans-serif; }
h2 { text-align: center; }
table { border-collapse: collapse; width: 100%; margin-top: 20px; }
th, td { border: 1px solid #999; padding: 8px; text-align: left; }
th { background-color: #eee; }
.info { margin-bottom: 10px; }
</style>

<h2>Reporte Individual de Desempeño</h2>
<div class='info'><strong>Nombre:</strong> {$usuario['nombre']}</div>
<div class='info'><strong>RFC:</strong> {$usuario['RFC']}</div>
<div class='info'><strong>Puesto:</strong> {$usuario['puesto']}</div>
<div class='info'><strong>Unidad:</strong> {$usuario['unidad']}</div>
<div class='info'><strong>Adscripción:</strong> {$usuario['adscripcion']}</div>
<div class='info'><strong>Periodo:</strong> {$periodo}</div>

<table>
  <tr>
    <th>#</th>
    <th>Meta</th>
    <th>Indicador</th>
    <th>Unidad</th>
    <th>Ponderación</th>
    <th>Resultado</th>
  </tr>";

foreach ($metas as $i => $meta) {
    $html .= "
    <tr>
      <td>".($i + 1)."</td>
      <td>{$meta['nombre_meta']}</td>
      <td>{$meta['indicador']}</td>
      <td>{$meta['unidad']}</td>
      <td>{$meta['ponderacion']}</td>
      <td>{$meta['resultado']}</td>
    </tr>";
}

$html .= "</table>";

// Generar PDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Guardar PDF
$fecha = date("Ymd_His");
$nombreArchivo = "reporte_{$user_id}_{$periodo}_{$fecha}.pdf";
$ruta = $carpeta . "/" . $nombreArchivo;
file_put_contents($ruta, $dompdf->output());

// Descargar PDF
header("Content-Type: application/pdf");
header("Content-Disposition: attachment; filename=\"$nombreArchivo\"");
readfile($ruta);
exit;
