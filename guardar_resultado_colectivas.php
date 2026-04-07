
<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin();

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');
$user_id = $_SESSION['user_id']; 

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);
$unidad_id = $_SESSION['unidad_id'];

// Usar el periodo activo de sesión para evitar guardar resultados en un año incorrecto
$periodo = (int)($_SESSION['periodo'] ?? 0);
if ($periodo <= 0) {
  $stmt = $pdo->prepare("SELECT valor FROM variables WHERE nombre = 'periodo_actual'");
  $stmt->execute();
  $periodo = (int)$stmt->fetchColumn();
}

// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['usuario_id'])) {



  // Calcular resultado ponderado de metas colectivas
  $stmt = $pdo->prepare("SELECT resultado, ponderacion FROM metas_colectivas WHERE unidad_id = ? AND periodo = ?");
  $stmt->execute([$unidad_id, $periodo]);
  $metas = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $suma = 0;
  foreach ($metas as $m) {
    $suma += ($m['resultado'] * $m['ponderacion'] / 100);
  }

  $resultado_final = round($suma, 1);

  // Insertar o actualizar en la tabla calificaciones
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM calificaciones_colectivas WHERE unidad_id = ? AND periodo = ?");
  $stmt->execute([$unidad_id, $periodo]);
  $existe = $stmt->fetchColumn();

  if ($existe) {
    $stmt = $pdo->prepare("UPDATE calificaciones_colectivas SET resultado = ? WHERE unidad_id = ? AND periodo = ?");
    $stmt->execute([$resultado_final, $unidad_id, $periodo]);
  } else {
    $stmt = $pdo->prepare("INSERT INTO calificaciones_colectivas (unidad_id, periodo, resultado) VALUES (?, ?, ?)");
    $stmt->execute([$unidad_id, $periodo, $resultado_final]);
  }

  header("Location: mis_metas_colectivas.php?usuario=$unidad_id&mensaje=guardado");
  exit;
}
?>
