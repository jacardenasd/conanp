<?php
require 'config/db.php';
require 'includes/session.php';


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin();


$user_id = $_GET['user_id'];

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE user_id = ?");
$stmt->execute([$user_id]);
$usuario = $stmt->fetch();

if (!$usuario) {
  echo "<p>Usuario no encontrado.</p>";
  exit();
}

$usuarios = $pdo->query("SELECT user_id, nombre FROM usuarios ORDER BY nombre")->fetchAll();
$unidades = $pdo->query("SELECT id, nombre FROM unidades ORDER BY nombre")->fetchAll();
$adscripciones = $pdo->query("SELECT id, nombre FROM adscripciones ORDER BY nombre")->fetchAll();
$puestos = $pdo->query("SELECT id, puesto FROM puestos ORDER BY puesto")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, correo = ?, jefe_id = ?, unidad_id = ?, adscripcion_id = ?, puesto_id = ? WHERE user_id = ?");
  $stmt->execute([
    $_POST['nombre'],
    $_POST['correo'],
    $_POST['jefe_id'],
    $_POST['unidad_id'],
    $_POST['adscripcion_id'],
    $_POST['puesto_id'],
    $user_id
  ]);
  header("Location: usuarios_admin.php?mensaje=actualizado");
  exit();
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Editar Usuario</title>
  <link rel="stylesheet" href="assets/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
  <h3>Editar Usuario</h3>
  <form method="post">
    <div class="form-group">
      <label>user_id</label>
      <input class="form-control" value="<?= htmlspecialchars($usuario['user_id']) ?>" disabled>
    </div>
    <div class="form-group">
      <label>Nombre</label>
      <input name="nombre" class="form-control" value="<?= htmlspecialchars($usuario['nombre']) ?>" required>
    </div>
    <div class="form-group">
      <label>Correo</label>
      <input name="correo" type="email" class="form-control" value="<?= htmlspecialchars($usuario['correo']) ?>" required>
    </div>
    <div class="form-group">
      <label>Jefe Inmediato</label>
      <select name="jefe_id" class="form-control" required>
        <option value="">Seleccione</option>
        <?php foreach ($usuarios as $u): ?>
          <option value="<?= $u['user_id'] ?>" <?= $usuario['jefe_id'] == $u['user_id'] ? 'selected' : '' ?>>
            <?= $u['nombre'] ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Unidad Administrativa</label>
      <select name="unidad_id" class="form-control" required>
        <option value="">Seleccione</option>
        <?php foreach ($unidades as $u): ?>
          <option value="<?= $u['id'] ?>" <?= $usuario['unidad_id'] == $u['id'] ? 'selected' : '' ?>>
            <?= $u['nombre'] ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Adscripción</label>
      <select name="adscripcion_id" class="form-control" required><option value="">Seleccione</option></select>
    </div>
    <div class="form-group">
      <label>Puesto</label>
      <select name="puesto_id" class="form-control" required>
        <option value="">Seleccione</option>
        <?php foreach ($puestos as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $usuario['puesto_id'] == $p['id'] ? 'selected' : '' ?>>
            <?= $p['nombre'] ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary">Actualizar</button>
    <a href="usuarios_admin.php" class="btn btn-secondary">Cancelar</a>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const unidadSelect = document.querySelector('select[name="unidad_id"]');
  const adscripcionSelect = document.querySelector('select[name="adscripcion_id"]');

  const adscripcionesPorUnidad = {
    <?php
    $map = [];
    foreach ($adscripciones as $a) {
      $map[$a['unidad_id']][] = ['id' => $a['id'], 'nombre' => $a['nombre']];
    }
    foreach ($map as $unidad_id => $ads) {
      echo "$unidad_id: [";
      foreach ($ads as $a) {
        echo "{ id: " . $a['id'] . ", nombre: '" . $a['nombre'] . "' },";
      }
      echo "],";
    }
    ?>
  };

  function cargarAdscripciones(unidadId, seleccionada = null) {
    adscripcionSelect.innerHTML = '<option value="">Seleccione</option>';
    if (adscripcionesPorUnidad[unidadId]) {
      adscripcionesPorUnidad[unidadId].forEach(function (a) {
        const option = document.createElement('option');
        option.value = a.id;
        option.textContent = a.nombre;
        if (a.id == seleccionada) {
          option.selected = true;
        }
        adscripcionSelect.appendChild(option);
      });
    }
  }

  unidadSelect.addEventListener('change', function () {
    cargarAdscripciones(this.value);
  });

  // Cargar inicial con valor ya asignado
  cargarAdscripciones(unidadSelect.value, "<?= $usuario['adscripcion_id'] ?>");
});
</script>

</body>
</html>
