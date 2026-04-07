<?php foreach ($capacitaciones as $c): ?>
<!-- Modal Editar Curso -->
<div class="modal fade" id="modalEditar<?= $c['id'] ?>" tabindex="-1" aria-labelledby="modalEditarLabel<?= $c['id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="post" action="capacitacion_actualizar.php" enctype="multipart/form-data" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalEditarLabel<?= $c['id'] ?>">Editar Curso</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body row g-3">
        <input type="hidden" name="id" value="<?= $c['id'] ?>">
        <div class="col-md-6"><label>Nombre del curso:</label><input type="text" name="nombre_curso" value="<?= $c['nombre_curso'] ?>" class="form-control" required></div>
        <div class="col-md-3"><label>Horas:</label><input type="number" name="horas" value="<?= $c['horas'] ?>" class="form-control" required></div>
        <div class="col-md-3"><label>Calificación:</label><input type="number" name="calificacion" value="<?= $c['calificacion'] ?>" class="form-control" required></div>
        <div class="col-md-6"><label>Institución:</label><input type="text" name="institucion" value="<?= $c['institucion'] ?>" class="form-control" required></div>
        <div class="col-md-3"><label>Fecha inicio:</label><input type="date" name="fecha_inicio" value="<?= $c['fecha_inicio'] ?>" class="form-control" required></div>
        <div class="col-md-3"><label>Fecha fin:</label><input type="date" name="fecha_fin" value="<?= $c['fecha_fin'] ?>" class="form-control" required></div>
        <div class="col-md-3">
          <label>Modalidad:</label>
          <select name="modalidad" class="form-select" required>
            <?php foreach ($modalidades as $m): ?>
              <option value="<?= $m['id'] ?>" <?= $c['id'] == $m['id'] ? 'selected' : '' ?>><?= $m['modalidad'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label>categoria:</label>
          <select name="categoria" class="form-select" required>
            <?php foreach ($categorias as $f): ?>
              <option value="<?= $f['id'] ?>" <?= $c['id'] == $f['id'] ? 'selected' : '' ?>><?= $f['categoria'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label>Correo:</label>
          <input type="text" name="correo" value="<?= $c['correo'] ?>" class="form-control">
           </div>
           <div class="col-md-3">
          <label>Teléfono:</label>
          <input type="text" name="telefono" value="<?= $c['telefono'] ?>" class="form-control">
          </div>
          <div class="col-md-12">
          <label>Observaciones:</label>
          <textarea name="observaciones" class="form-control" rows="2"><?= $c['observaciones'] ?></textarea>
          </div>
        <div class="col-md-12"><label>Archivo PDF (opcional):</label><input type="file" name="archivo_pdf" class="form-control" accept="application/pdf"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" name="actualizar" class="btn btn-sm btn-primary">Actualizar</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>
