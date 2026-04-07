<?php
// ============================================
// AUTOEVALUACIÓN GERENCIAL / COMPETENCIAS
// Cambio #7: Bloquear edición cuando jefe valida
// ============================================

require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';
require 'includes/finalizaciones.php';

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
$puesto_id = $_SESSION['puesto_id'];
$periodo = 2025;
$el_nivel = 6;

// ============================================
// VERIFICAR SI ESTÁ BLOQUEADO POR JEFE
// ============================================
$stmt_block = $pdo->prepare("
    SELECT bloqueado_edicion FROM competencias_evaluacion 
    WHERE user_id = ? AND periodo = ? AND tipo = 'auto' 
    LIMIT 1
");
$stmt_block->execute([$user_id, $periodo]);
$competencia_bloqueada = $stmt_block->fetch();
$bloqueado = $competencia_bloqueada && $competencia_bloqueada['bloqueado_edicion'] == 1;

if ($bloqueado) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
      <meta charset="UTF-8">
      <title>Autoevaluación Bloqueada</title>
      <link href="assets/css/ltr/all.min.css" rel="stylesheet">
    </head>
    <body>
        <div style="max-width: 600px; margin: 50px auto; padding: 30px; background: #f8d7da; border-radius: 8px;">
            <h3 style="color: #721c24;">🔒 Autoevaluación Bloqueada</h3>
            <p style="color: #721c24;">Tu superior jerárquico ha finalizado la evaluación de competencias. Ya no puedes hacer cambios.</p>
            <p><a href="mi_evaluacion.php" style="background: #007bff; color: white; padding: 10px 15px; border-radius: 5px; text-decoration: none;">← Volver a Mi Evaluación</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Obtener el nivel del puesto del usuario actual
$stmt = $pdo->prepare("SELECT puesto_nivel FROM usuarios WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$nivel = $stmt->fetchColumn();

// Obtener competencias
$competencias = $pdo->query("SELECT * FROM competencias")->fetchAll(PDO::FETCH_ASSOC);

// Matriz de valores por nivel y competencia
$valoresQuery = $pdo->prepare("SELECT competencia_id, valor FROM competencias_valores WHERE nivel = ?");
$valoresQuery->execute([$nivel]);
$valoresRaw = $valoresQuery->fetchAll(PDO::FETCH_KEY_PAIR); // competencia_id => valor
// Opciones Likert
$opciones = ['Muy Característico', 'Característico', 'Poco Característico', 'No es Característico', 'No Aplica'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Evaluación de Competencias</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container my-4">

<h4>Evaluación de Competencias Laborales</h4>
<p>Selecciona la opción que mejor describa tu comportamiento para cada uno de los siguientes aspectos.</p>

<form action="guardar_competencias.php" method="post">
  <input type="hidden" name="nivel" value="<?= $nivel ?>">
  <?php foreach ($competencias as $index => $comp): ?>
    <div class="card mb-4">
      <div class="card-header bg-primary text-white">
        <strong><?= ($index+1) . '. ' . htmlspecialchars($comp['competencia']) ?></strong>
      </div>
      <div class="card-body">
        <p><em><?= htmlspecialchars($comp['descripcion']) ?></em></p>
        <?php
          $stmt = $pdo->prepare("SELECT * FROM competencias_descripcion 
                                 WHERE competencia_id = ? AND nivel = ?");
          $stmt->execute([$comp['competencia_id'], $nivel]);
          $descripciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
          foreach ($descripciones as $desc):
        ?>
          <div class="mb-2">
            <p class="mb-1"><strong>🧩</strong> <?= htmlspecialchars($desc['descripcion']) ?></p>
            <?php foreach ($opciones as $opt): ?>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio"
                       name="evaluacion[<?= $desc['id'] ?>]" value="<?= $opt ?>" required>
                <label class="form-check-label"><?= $opt ?></label>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <button class="btn btn-success">Guardar Evaluación</button>
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
