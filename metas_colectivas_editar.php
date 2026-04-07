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

$id = $_GET['id'] ?? null;
$mensaje = "";

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM metas_colectivas WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $meta = $stmt->fetch();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $indicador = $_POST['indicador'];
        $ponderacion = $_POST['ponderacion'];
        $periodo = $_POST['periodo'];

        $update = $pdo->prepare("UPDATE metas_colectivas SET indicador = :indicador, ponderacion = :ponderacion, periodo = :periodo WHERE id = :id");
        $update->execute([
            'indicador' => $indicador,
            'ponderacion' => $ponderacion,
            'periodo' => $periodo,
            'id' => $id
        ]);

        $mensaje = "Meta actualizada correctamente.";
    }
} else {
    header("Location: admin_metas_colectivas.php");
    exit;
}
?>

<?php include 'includes/header.php'; ?>
<div class="container mt-4">
    <h3>Editar Meta Colectiva</h3>
    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= $mensaje ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="mb-3">
            <label class="form-label">Descripción</label>
            <textarea name="indicador" class="form-control" required><?= htmlspecialchars($meta['indicador']) ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Ponderación (%)</label>
            <input type="number" name="ponderacion" class="form-control" value="<?= htmlspecialchars($meta['ponderacion']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Periodo</label>
            <input type="text" name="periodo" class="form-control" value="<?= htmlspecialchars($meta['periodo']) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
        <a href="admin_metas_colectivas.php" class="btn btn-secondary">Cancelar</a>
    </form>
</div>
<?php include 'includes/footer.php'; ?>
