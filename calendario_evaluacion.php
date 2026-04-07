<?php
// Obtener eventos del calendario
$eventos = $pdo->query("SELECT * FROM calendario_evaluacion ORDER BY id")->fetchAll();
?>
  <div class="card-body">
    <?php if (count($eventos) === 0): ?>
      <p class="text-muted">No hay eventos registrados.</p>
    <?php else: ?>
      A continuación se muestra el calendario de evaliación.
      <table class="table table-sm">
        <thead>
          <tr>
            <th>Etapa/Periodo</th>
            <th>Inicio Captura</th>
            <th>Fin Captura</th>
            <th>Inicio Evaluación</th>
            <th>Fin Evaluación</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($eventos as $e): ?>
            <tr>
              <td><?= htmlspecialchars($e['titulo']) ?></td>
              <td><?= date('d/m/Y', strtotime($e['fecha_inicio_captura'])) ?></td>
              <td><?= date('d/m/Y', strtotime($e['fecha_fin_captura'])) ?></td>
              <td><?= date('d/m/Y', strtotime($e['fecha_inicio_evaluacion'])) ?></td>
              <td><?= date('d/m/Y', strtotime($e['fecha_fin_evaluacion'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
</div>
