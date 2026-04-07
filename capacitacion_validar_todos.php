<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';

// SECCIÓN BLOQUEADA: La capacitación no está disponible actualmente
//header('Location: index.php');
//exit();


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin(2);
asegurar_columna_capacitacion_contabiliza($pdo);

$user_id_remitente = $_SESSION['user_id'];

// Recolectar los filtros recibidos por GET
$periodo = $_GET['periodo'] ?? '';
$empleado = $_GET['empleado'] ?? '';
$validado = $_GET['validado'] ?? '';
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';

// Construir WHERE dinámico basado en filtros
$where = "WHERE 1";
$params = [];

if ($periodo != '') {
    $where .= " AND periodo = ?";
    $params[] = $periodo;
}
if ($empleado != '') {
    $where .= " AND user_id = ?";
    $params[] = $empleado;
}
if ($validado !== '') {
    $where .= " AND validado = ?";
    $params[] = $validado;
}
if ($fecha_inicio != '' && $fecha_fin != '') {
    $where .= " AND fecha_inicio BETWEEN ? AND ?";
    $params[] = $fecha_inicio;
    $params[] = $fecha_fin;
}

// 1. Buscar los cursos que cumplen el filtro
$stmt = $pdo->prepare("SELECT nombre_curso, id, user_id FROM capacitacion $where");
$stmt->execute($params);
$cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Validar cursos e insertar mensajes
foreach ($cursos as $curso) {
    $id = intval($curso['id']);
    $user_id = intval($curso['user_id']);
    $nombre_curso = $curso['nombre_curso'];

    // Validar el curso
    $stmtUpdate = $pdo->prepare("UPDATE capacitacion SET validado = 1, contabiliza_horas = 1 WHERE id = ?");
    $stmtUpdate->execute([$id]);

    // Insertar mensaje para el usuario
    $mensaje = "Tu constancia del curso de capacitación ".$nombre_curso." fue aceptada y sumará para la acreditación de las 40 h de capacitación anual";
    $asunto = "Validación de Curso.";
    $fecha = date('Y-m-d H:i:s');
    $leido = 0;

    $stmtMensaje = $pdo->prepare("INSERT INTO mensajes (remitente_id, destinatario_id, asunto, mensaje, fecha, leido) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtMensaje->execute([$user_id_remitente, $user_id, $asunto, $mensaje, $fecha, $leido]);
}

// 3. Redireccionar de regreso a admin_capacitacion.php, conservando los filtros
$query_string = http_build_query([
    'periodo' => $periodo,
    'empleado' => $empleado,
    'validado' => $validado,
    'fecha_inicio' => $fecha_inicio,
    'fecha_fin' => $fecha_fin,
    'info' => 1 // Para mostrar mensaje de éxito
]);

header("Location: admin_capacitacion.php?$query_string");
exit;
?>
