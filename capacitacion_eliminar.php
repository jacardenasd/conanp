<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar'], $_POST['id'])) {
  require 'config/db.php';
  require 'includes/session.php';

  checkLogin(2);

  $role = (int)($_SESSION['role'] ?? 0);
  if ($role !== 3) {
    header("Location: admin_capacitacion.php?info=8");
    exit;
  }

  $id = (int)$_POST['id'];

  $stmt = $pdo->prepare("DELETE FROM capacitacion WHERE id = ?");
  $stmt->execute([$id]);

  header("Location: admin_capacitacion.php?info=3");
  exit;
}
?>

<?php foreach ($capacitaciones as $c): ?>
<!-- Modal Eliminar Curso -->
<div class="modal fade" id="modalEliminar<?= $c['id'] ?>" tabindex="-1" aria-labelledby="modalEliminarLabel<?= $c['id'] ?>" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" action="capacitacion_eliminar.php" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalEliminarLabel<?= $c['id'] ?>">Confirmar Eliminación</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        ¿Estás seguro de que deseas eliminar el curso <strong><?= $c['nombre_curso'] ?></strong>?
        <input type="hidden" name="id" value="<?= $c['id'] ?>">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" name="eliminar" class="btn btn-sm btn-danger">Eliminar</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>
