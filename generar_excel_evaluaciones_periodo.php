<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';
require 'vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin(2);

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

function normalizar_texto_excel($texto, $aMayusculas = false) {
    if ($texto === null) return '';
    $texto = trim((string)$texto);
    if ($texto === '') return '';
    if (!mb_check_encoding($texto, 'UTF-8')) {
        $texto = mb_convert_encoding($texto, 'UTF-8', 'ISO-8859-1');
    }
    return $aMayusculas ? mb_strtoupper($texto, 'UTF-8') : $texto;
}

function insertar_logo_excel($hoja, $rutaLogo, $coordenada = 'A1', $ancho = 200, &$error = null): bool {
    if (!is_file($rutaLogo)) return false;
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
        error_log('No se pudo insertar logo en reporte evaluaciones: ' . $e->getMessage());
        return false;
    }
}

function valor_numerico_excel($valor): float {
    return is_numeric($valor) ? (float)$valor : 0.0;
}

function clasificacion_desempeno($valor): string {
    $valor = (float)$valor;
    if ($valor >= 90) return 'Sobresaliente';
    if ($valor >= 70) return 'Satisfactorio';
    if ($valor >= 60) return 'No satisfactorio';
    if ($valor >= 50) return 'No aprobatorio';
    return 'Deficiente';
}

function obtener_fecha_evaluacion_usuario(PDO $pdo, int $user_id, int $periodo, int $unidad_id): string {
    $stmt = $pdo->prepare("SELECT MAX(fecha_evento) FROM (
        SELECT MAX(m.fecha_evaluacion_jefe) AS fecha_evento
        FROM metas m
        WHERE m.user_id = ? AND m.periodo = ?
        UNION ALL
        SELECT MAX(cc.updated_at) AS fecha_evento
        FROM calificaciones_colectivas cc
        WHERE cc.unidad_id = ? AND cc.periodo = ?
        UNION ALL
        SELECT MAX(c.updated_at) AS fecha_evento
        FROM calificaciones c
        WHERE c.user_id = ? AND c.periodo = ?
        UNION ALL
        SELECT MAX(cap.updated_at) AS fecha_evento
        FROM capacitacion cap
        WHERE cap.user_id = ? AND cap.periodo = ?
        UNION ALL
        SELECT MAX(ce.fecha_validacion_jefe) AS fecha_evento
        FROM competencias_evaluacion ce
        WHERE ce.user_id = ? AND ce.periodo = ?
        UNION ALL
        SELECT MAX(ad.updated_at) AS fecha_evento
        FROM aportaciones_destacadas ad
        WHERE ad.user_id = ? AND ad.periodo = ?
        UNION ALL
        SELECT MAX(ae.updated_at) AS fecha_evento
        FROM actividades_extraordinarias ae
        WHERE ae.user_id = ? AND ae.periodo = ?
    ) AS fechas");

    $stmt->execute([
        $user_id, $periodo,
        $unidad_id, $periodo,
        $user_id, $periodo,
        $user_id, $periodo,
        $user_id, $periodo,
        $user_id, $periodo,
        $user_id, $periodo,
    ]);

    $fecha = $stmt->fetchColumn();
    if (!$fecha) {
        return '';
    }

    $timestamp = strtotime((string)$fecha);
    return $timestamp ? date('d/m/Y', $timestamp) : '';
}

$periodo = $_GET['periodo'] ?? null;
$unidad_id = $_GET['unidad_id'] ?? '';

if (!$periodo) {
    die('Falta el periodo.');
}

$directorio = __DIR__ . '/reportes/evaluaciones_periodo/';
if (!file_exists($directorio)) mkdir($directorio, 0777, true);

// Pre-cargar resultados colectivas por unidad para este periodo
$stmt_cc = $pdo->prepare('SELECT unidad_id, resultado, estatus FROM calificaciones_colectivas WHERE periodo = ?');
$stmt_cc->execute([$periodo]);
$colectivas_map = [];
foreach ($stmt_cc->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $colectivas_map[(int)$r['unidad_id']] = $r;
}

// Consulta principal: usuarios + calificaciones + superior jerárquico + sum horas capacitacion
$sql = "SELECT u.user_id, u.nombre, u.apellido_paterno, u.apellido_materno, u.RFC, u.curp, u.puesto_nombre, u.puesto_nivel, u.unidad_id, un.nombre AS unidad, 
    j.nombre AS jefe_nombre, j.apellido_paterno AS jefe_apellido_paterno, j.apellido_materno AS jefe_apellido_materno, j.RFC AS jefe_rfc, j.curp AS jefe_curp,
    c.individuales, c.estatus_metas, c.gerenciales, c.estatus_gerenciales, c.aportaciones_destacadas, c.estatus_aportaciones_destacadas, c.actividades_extraordinarias, c.estatus_actividades_extraordinarias, 
  (SELECT COALESCE(SUM(horas),0) FROM capacitacion cap WHERE cap.user_id = u.user_id AND cap.periodo = :periodo AND cap.validado = 1 AND COALESCE(cap.contabiliza_horas,1)=1) AS horas_validadas, 
  (SELECT COALESCE(SUM(horas),0) FROM capacitacion cap WHERE cap.user_id = u.user_id AND cap.periodo = :periodo AND cap.validado = 0) AS horas_no_validadas
FROM usuarios u
LEFT JOIN unidades un ON u.unidad_id = un.id
LEFT JOIN usuarios j ON u.jefe_id = j.user_id
LEFT JOIN calificaciones c ON c.user_id = u.user_id AND c.periodo = :periodo
WHERE 1=1";

if ($unidad_id !== '') {
    $sql .= ' AND u.unidad_id = :unidad_id';
}

$sql .= ' ORDER BY un.nombre ASC, u.apellido_paterno ASC, u.apellido_materno ASC';

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':periodo', (int)$periodo, PDO::PARAM_INT);
if ($unidad_id !== '') $stmt->bindValue(':unidad_id', (int)$unidad_id, PDO::PARAM_INT);
$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Preparar statements reutilizables
$stmt_metas_calc = $pdo->prepare("SELECT ROUND(COALESCE(SUM(CASE WHEN resultado_final > 0 THEN (resultado_final * ponderacion / 100) ELSE 0 END),0),2) FROM metas WHERE user_id = ? AND periodo = ?");
$stmt_aport_count = $pdo->prepare("SELECT COUNT(*) FROM aportaciones_destacadas WHERE user_id = ? AND periodo = ? AND validado = 1");
$stmt_activ_count = $pdo->prepare("SELECT COUNT(*) FROM actividades_extraordinarias WHERE user_id = ? AND periodo = ? AND validado = 1");

$sql_gerencial = "SELECT ROUND(SUM(avg_valor * peso) / SUM(peso), 2) AS calificacion_final
FROM (
    SELECT eval.competencia_id, eval.avg_valor,
    CASE 
        WHEN eval.competencia_id = 1 THEN cp.vision
        WHEN eval.competencia_id = 2 THEN cp.liderazgo
        WHEN eval.competencia_id = 3 THEN cp.orientacion
        WHEN eval.competencia_id = 4 THEN cp.negociacion
        WHEN eval.competencia_id = 5 THEN cp.trabajo
    END as peso
    FROM (
        SELECT competencia_id, AVG(valor) AS avg_valor
        FROM competencias_evaluacion
        WHERE user_id = :uid AND periodo = :periodo AND tipo = 'jefe' AND valor > 0
        GROUP BY competencia_id
    ) AS eval
    JOIN usuarios u ON u.user_id = :uid
    JOIN competencias_pesos cp ON u.puesto_nivel = cp.nivel
) AS calc
WHERE peso > 0";

$stmt_gerencial = $pdo->prepare($sql_gerencial);
$stmt_gerencial_fallback = $pdo->prepare("SELECT ROUND(AVG(valor),2) FROM competencias_evaluacion WHERE user_id = ? AND periodo = ? AND tipo = 'jefe' AND valor > 0");

// Crear spreadsheet
$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Evaluaciones ' . $periodo);

$rutas_logo = [__DIR__ . '/assets/images/logo_excel_semarnat_conanp.png', __DIR__ . '/assets/images/logo_text_light.png'];
$logo_insertado = false;
$logo_error = '';
foreach ($rutas_logo as $ruta) {
    if (is_file($ruta)) {
        if (insertar_logo_excel($sheet, $ruta, 'A1', 160, $logo_error)) {
            $logo_insertado = true; break;
        }
    }
}

// Encabezados
$headers = ['RFC','Nombre Completo','Unidad','Puesto','Nivel de puesto','Fecha de evaluación','Resultado Metas Individuales','Resultado Metas Colectivas','Capacitación (Resultado)','Resultado Autoevaluación gerencial','Actividades Extraordinarias','Aportaciones Destacadas','Calificación Parcial','Calificación Final','Valoración final','Nombre del Superior Jerárquico que Evaluó','RFC del Superior Jerárquico','CURP del Superior Jerárquico'];
$col = 'A';
foreach ($headers as $h) {
    $sheet->setCellValue($col . '4', $h);
    $sheet->getColumnDimension($col)->setAutoSize(true);
    $col++;
}

$fila = 5;
foreach ($usuarios as $u) {
    $user_id = (int)$u['user_id'];
    $unidad_id_int = (int)($u['unidad_id'] ?? 0);

    // Metas individuales: preferir valor en calificaciones, si no existe calcular
    $metas_val = $u['individuales'];
    if ($metas_val === null) {
        $stmt_metas_calc->execute([$user_id, $periodo]);
        $metas_val = $stmt_metas_calc->fetchColumn();
        $metas_val = $metas_val !== null ? $metas_val : 0;
    }

    // Colectivas por unidad
    $resultado_colectiva = '';
    if ($unidad_id_int > 0 && isset($colectivas_map[$unidad_id_int])) {
        $resultado_colectiva = $colectivas_map[$unidad_id_int]['resultado'];
    }

    // Aportaciones
    $aportaciones = $u['aportaciones_destacadas'];
    if ($aportaciones === null) {
        $stmt_aport_count->execute([$user_id, $periodo]);
        $aportaciones = (int)$stmt_aport_count->fetchColumn();
    }

    // Actividades
    $actividades = $u['actividades_extraordinarias'];
    if ($actividades === null) {
        $stmt_activ_count->execute([$user_id, $periodo]);
        $actividades = (int)$stmt_activ_count->fetchColumn();
    }

    // Gerenciales: preferir valor en calificaciones, si no existe intentar calcular
    $gerenciales = $u['gerenciales'];
    if ($gerenciales === null) {
        $stmt_gerencial->execute([':uid' => $user_id, ':periodo' => $periodo]);
        $gerenciales = $stmt_gerencial->fetchColumn();
        if ($gerenciales === null) {
            $stmt_gerencial_fallback->execute([$user_id, $periodo]);
            $gerenciales = $stmt_gerencial_fallback->fetchColumn();
        }
        $gerenciales = $gerenciales !== null ? $gerenciales : '';
    }

    // Horas capacitacion
    $horas_val = $u['horas_validadas'] ?? 0;
    $horas_no = $u['horas_no_validadas'] ?? 0;
    $capacitacion_calificacion = $horas_val > 0 ? min(100, round(($horas_val / 40) * 100, 2)) : 0;

    $metas_val_num = valor_numerico_excel($metas_val);
    $resultado_colectiva_num = valor_numerico_excel($resultado_colectiva);
    $aportaciones_num = valor_numerico_excel($aportaciones);
    $actividades_num = valor_numerico_excel($actividades);
    $gerenciales_num = valor_numerico_excel($gerenciales);

    $calificacion_parcial = round((
        $metas_val_num +
        $resultado_colectiva_num +
        $gerenciales_num +
        $capacitacion_calificacion
    ) / 4, 2);

    $calificacion_final = round($calificacion_parcial + $actividades_num + $aportaciones_num, 2);
    $valoracion_final = clasificacion_desempeno($calificacion_final);
    $fecha_evaluacion = obtener_fecha_evaluacion_usuario($pdo, $user_id, (int)$periodo, $unidad_id_int);
    $nombre_superior = normalizar_texto_excel(trim(($u['jefe_nombre'] ?? '') . ' ' . ($u['jefe_apellido_paterno'] ?? '') . ' ' . ($u['jefe_apellido_materno'] ?? '')));

    // Escribir fila
    $cols = [];
    $cols[] = $u['RFC'] ?? '';
    $cols[] = normalizar_texto_excel(($u['nombre'] ?? '') . ' ' . ($u['apellido_paterno'] ?? '') . ' ' . ($u['apellido_materno'] ?? ''));
    $cols[] = $u['unidad'] ?? '';
    $cols[] = $u['puesto_nombre'] ?? '';
    $cols[] = $u['puesto_nivel'] ?? '';
    $cols[] = $fecha_evaluacion;
    $cols[] = ($metas_val !== '' && $metas_val !== null) ? $metas_val : '';
    $cols[] = $resultado_colectiva;
    $cols[] = $capacitacion_calificacion;
    $cols[] = $gerenciales;
    $cols[] = $actividades;
    $cols[] = $aportaciones;
    $cols[] = $calificacion_parcial;
    $cols[] = $calificacion_final;
    $cols[] = $valoracion_final;
    $cols[] = $nombre_superior;
    $cols[] = $u['jefe_rfc'] ?? '';
    $cols[] = $u['jefe_curp'] ?? '';

    $col = 'A';
    foreach ($cols as $val) {
        $sheet->setCellValue($col . $fila, $val);
        $col++;
    }

    $fila++;
}

// Ajustes estéticos
$sheet->getStyle('A4:R4')->getFont()->setBold(true);
$sheet->getRowDimension(4)->setRowHeight(20);

$fecha = date('Ymd_His');
$nombre_archivo = "EVALUACIONES_{$periodo}_{$fecha}.xlsx";
$ruta = $directorio . $nombre_archivo;
$writer = new Xlsx($spreadsheet);
$writer->save($ruta);

if (ob_get_level()) ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . basename($nombre_archivo) . '"');
header('Content-Length: ' . filesize($ruta));
header('Cache-Control: max-age=0');
readfile($ruta);
exit;

?>