<!-- Modal Agregar Curso -->
<div class="modal fade" id="modalAgregar" tabindex="-1" aria-labelledby="modalAgregarLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="post" action="capacitacion_guardar.php" enctype="multipart/form-data" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalAgregarLabel">Agregar Curso</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body row g-3">
        <div class="col-md-12">
          <label>Empleado:</label>
          <select name="user_id" class="form-select" required>
            <?php foreach ($usuarios as $u): ?>
              <option value="<?= $u['user_id'] ?>"><?= $u['nombre_completo'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6"><label>Nombre del curso:</label><input type="text" name="nombre_curso" class="form-control" required></div>
        <div class="col-md-3"><label>Horas:</label><input type="number" name="horas" class="form-control" required></div>
        <div class="col-md-3"><label>Calificación:</label><input type="number" max="100" miin="0" name="calificacion" class="form-control" required></div>
        <div class="col-md-6"><label>Institución:</label><input type="text" name="institucion" class="form-control" required></div>
        <div class="col-md-3"><label>Fecha inicio:</label><input type="date" name="fecha_inicio" class="form-control" required></div>
        <div class="col-md-3"><label>Fecha fin:</label><input type="date" name="fecha_fin" class="form-control" required></div>
        <div class="col-md-3">
          <label>Modalidad:</label>
          <select name="modalidad" class="form-select" required>
            <?php foreach ($modalidades as $m): ?>
              <option value="<?= $m['id'] ?>"><?= $m['modalidad'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label>Categoría:</label>
          <select name="categoria" class="form-select" required>
            <?php foreach ($categorias as $c): ?>
              <option value="<?= $c['id'] ?>"><?= $c['categoria'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label>Correo:</label>
          <input type="text" name="correo" class="form-control">
          </div>
        <div class="col-md-3">
          <label>Telefono:</label>
          <input type="text" name="telefono" class="form-control">
          </div>
        <div class="col-md-12"><label>Archivo PDF:</label><input type="file" name="archivo_pdf" class="form-control" accept="application/pdf"></div>
        <input type="hidden" name="periodo" value="<?= $periodo_actual ?>">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" name="guardar" class="btn btn-sm btn-sm btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>
