
<?php
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
$puesto_id = $_SESSION['puesto_id'];
$periodo = 2025;
$el_nivel = 6;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['agregar'])) {
        $stmt = $pdo->prepare("INSERT INTO competencias_valores (competencia_id, nivel, valor) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['competencia_id'], $_POST['nivel'], $_POST['valor']]);
    }
    if (isset($_POST['editar'])) {
        $stmt = $pdo->prepare("UPDATE competencias_valores SET valor = ? WHERE id = ?");
        $stmt->execute([$_POST['valor'], $_POST['id']]);
    }
    if (isset($_GET['eliminar'])) {
        $stmt = $pdo->prepare("DELETE FROM competencias_valores WHERE id = ?");
        $stmt->execute([$_GET['eliminar']]);
    }
}

$competencias = $pdo->query("SELECT * FROM competencias")->fetchAll(PDO::FETCH_ASSOC);
$valores = $pdo->query("SELECT v.*, c.competencia FROM competencias_valores v
                        JOIN competencias c ON v.competencia_id = c.competencia_id
                        ORDER BY nivel, competencia")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Valores por Competencia y Nivel</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">

<h4>Administrar Valores por Competencia y Nivel</h4>

<form method="post" class="row g-2 mb-4">
  <input type="hidden" name="agregar" value="1">
  <div class="col-md-4">
    <label>Competencia</label>
    <select name="competencia_id" class="form-control" required>
      <?php foreach ($competencias as $c): ?>
        <option value="<?= $c['competencia_id'] ?>"><?= htmlspecialchars($c['competencia']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <label>Nivel</label>
    <input type="number" name="nivel" class="form-control" min="1" max="6" required>
  </div>
  <div class="col-md-3">
    <label>Valor</label>
    <input type="number" name="valor" class="form-control" required>
  </div>
  <div class="col-md-3 d-flex align-items-end">
    <button class="btn btn-success w-100">Agregar</button>
  </div>
</form>

<table class="table table-bordered table-sm">
  <thead class="table-light">
    <tr><th>Competencia</th><th>Nivel</th><th>Valor</th><th>Acciones</th></tr>
  </thead>
  <tbody>
    <?php foreach ($valores as $v): ?>
      <tr>
        <form method="post">
          <input type="hidden" name="id" value="<?= $v['id'] ?>">
          <input type="hidden" name="editar" value="1">
          <td><?= htmlspecialchars($v['competencia']) ?></td>
          <td><?= $v['nivel'] ?></td>
          <td><input type="number" name="valor" value="<?= $v['valor'] ?>" class="form-control form-control-sm"></td>
          <td>
            <button class="btn btn-sm btn-primary">Guardar</button>
            <a href="?eliminar=<?= $v['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar valor?')">Eliminar</a>
          </td>
        </form>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

</body>
</html>
