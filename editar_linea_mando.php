
<?php
require 'includes/session.php';
require 'includes/variables.php';

$user_id = $_SESSION['user_id']; 

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevo_jefe = $_POST['jefe_id'];
    $stmt = $pdo->prepare("UPDATE usuarios SET jefe_id = ? WHERE user_id = ?");
    $stmt->execute([$nuevo_jefe, $user_id]);
    header("Location: mis_datos.php?mensaje=jefe_actualizado");
    exit();
}

$stmt = $pdo->prepare("SELECT jefe_id FROM usuarios WHERE user_id = ?");
$stmt->execute([$user_id]);
$usuario = $stmt->fetch();

// Lista de posibles jefes
$jefes = $pdo->query("SELECT user_id, nombre, apellido_paterno, apellido_materno FROM usuarios ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Superior Jerárquico</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">

<h4>Cambiar Superior Jerárquico</h4>
<form method="post" class="form-group">
  <label for="jefe_id" class="form-label">Seleccione al nuevo superior:</label>
  <select name="jefe_id" id="jefe_id" class="form-control" required>
    <option value="">Seleccione...</option>
    <?php foreach ($jefes as $j): ?>
      <option value="<?= $j['user_id'] ?>" <?= $usuario['jefe_id'] == $j['user_id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($j['nombre'] . ' ' . $j['apellido_paterno'] . ' ' . $j['apellido_materno']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <br>
  <button class="btn btn-success">Actualizar</button>
  <a href="mis_datos.php" class="btn btn-secondary">Cancelar</a>
</form>

</body>
</html>
