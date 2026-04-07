<?php
require 'includes/session.php';
require 'includes/variables.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin(2);

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');
$puesto_id = $_SESSION['puesto_id'];
$user_id = $_SESSION['user_id']; 

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);
$unidad_id = $_SESSION['unidad_id']; 
$periodo = $_SESSION['periodo'];

$periodos = $pdo->query("SELECT anio FROM periodos ORDER BY anio DESC")->fetchAll(PDO::FETCH_ASSOC);
$unidades = $pdo->query("SELECT id, nombre FROM unidades ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

$periodo_ = $_GET['periodo'] ?? $periodo; 
$unidad_id_ = $_GET['unidad_id'] ?? '';
$q = trim($_GET['q'] ?? '');
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina = 25;
$offset = ($pagina - 1) * $por_pagina;
$info = $_GET['info'] ?? null;
asegurar_columna_capacitacion_contabiliza($pdo);

$sort = trim((string)($_GET['sort'] ?? 'persona'));
$dir = strtolower(trim((string)($_GET['dir'] ?? 'asc')));

$sort_map = [
  'persona' => 'u.nombre',
  'metas_individuales' => 'metas_capturadas',
  'estatus_metas' => 'c.estatus_metas',
  'actividades_extraordinarias' => 'actividades_extraordinarias',
  'estatus_actividades_extraordinarias' => 'c.estatus_actividades_extraordinarias',
  'aportaciones_destacadas' => 'aportaciones_destacadas',
  'estatus_aportaciones_destacadas' => 'c.estatus_aportaciones_destacadas',
  'estatus_gerenciales' => 'c.estatus_gerenciales',
];

if (!isset($sort_map[$sort])) {
  $sort = 'persona';
}
if ($dir !== 'asc' && $dir !== 'desc') {
  $dir = 'asc';
}

$order_by = $sort_map[$sort] . ' ' . strtoupper($dir);
if ($sort === 'persona') {
  $order_by .= ', u.apellido_paterno ' . strtoupper($dir) . ', u.apellido_materno ' . strtoupper($dir);
}

$where = " WHERE 1=1 ";
$params_filtro = [];
if ($unidad_id_ !== '') {
  $where .= " AND u.unidad_id = :unidad_id ";
  $params_filtro['unidad_id'] = (int)$unidad_id_;
}
if ($q !== '') {
  $where .= " AND (u.nombre LIKE :q OR u.apellido_paterno LIKE :q OR u.apellido_materno LIKE :q OR u.RFC LIKE :q) ";
  $params_filtro['q'] = "%{$q}%";
}

$sql = "
SELECT u.user_id AS user_id, u.nombre, u.apellido_paterno, u.apellido_materno, u.RFC, un.nombre AS unidad, u.puesto_nombre, 
  (SELECT COUNT(*) FROM metas m WHERE m.user_id = u.user_id AND m.periodo = :periodo) AS metas_capturadas,
  c.estatus_metas,
  (SELECT COUNT(*) FROM actividades_extraordinarias ae WHERE ae.user_id = u.user_id AND ae.periodo = :periodo) AS actividades_extraordinarias,
  c.estatus_actividades_extraordinarias,
  (SELECT COUNT(*) FROM aportaciones_destacadas ad WHERE ad.user_id = u.user_id AND ad.periodo = :periodo) AS aportaciones_destacadas,
  c.estatus_aportaciones_destacadas,
  c.estatus_gerenciales,
  (SELECT SUM(horas) FROM capacitacion cap WHERE cap.user_id = u.user_id AND cap.periodo = :periodo AND cap.validado = 1 AND COALESCE(cap.contabiliza_horas, 1) = 1) AS horas_validadas,
  (SELECT SUM(horas) FROM capacitacion cap WHERE cap.user_id = u.user_id AND cap.periodo = :periodo AND cap.validado = 0) AS horas_no_validadas
FROM usuarios u
LEFT JOIN puestos p ON u.puesto_id = p.id
LEFT JOIN unidades un ON u.unidad_id = un.id
LEFT JOIN calificaciones c ON c.user_id = u.user_id AND c.periodo = :periodo

{$where}
ORDER BY {$order_by}
LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':periodo', (int)$periodo_, PDO::PARAM_INT);
foreach ($params_filtro as $key => $value) {
  $stmt->bindValue(':' . $key, $value, $key === 'unidad_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sql_total = "SELECT COUNT(*)
FROM usuarios u
{$where}";
$stmt_total = $pdo->prepare($sql_total);
foreach ($params_filtro as $key => $value) {
  $stmt_total->bindValue(':' . $key, $value, $key === 'unidad_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt_total->execute();
$total_registros = (int)$stmt_total->fetchColumn();
$total_paginas = max(1, (int)ceil($total_registros / $por_pagina));

$query_base = $_GET;
unset($query_base['pagina']);
$query_base = http_build_query($query_base);
$query_base = $query_base !== '' ? $query_base . '&' : '';

function sort_link_admin_calif(array $currentQuery, string $column, string $currentSort, string $currentDir): string {
  $nextDir = ($currentSort === $column && $currentDir === 'asc') ? 'desc' : 'asc';
  $query = $currentQuery;
  $query['sort'] = $column;
  $query['dir'] = $nextDir;
  $query['pagina'] = 1;
  return 'admin_calificaciones.php?' . http_build_query($query);
}

function sort_icon_admin_calif(string $column, string $currentSort, string $currentDir): string {
  if ($currentSort !== $column) {
    return '⇅';
  }
  return $currentDir === 'asc' ? '↑' : '↓';
}

?>
<!DOCTYPE html>
<html lang="es" dir="ltr">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title><?php echo htmlspecialchars($nombre_sistema); ?></title>

	<!-- Global stylesheets -->
	<link href="assets/fonts/inter/inter.css" rel="stylesheet" type="text/css">
	<link href="assets/icons/phosphor/styles.min.css" rel="stylesheet" type="text/css">
	<link href="assets/css/ltr/all.min.css" id="stylesheet" rel="stylesheet" type="text/css">
	<!-- /global stylesheets -->

	<!-- Core JS files -->
	<script src="assets/demo/demo_configurator.js"></script>
		<script src="assets/js/bootstrap/bootstrap.bundle.min.js"></script>
	<link href="assets/icons/icomoon/styles.min.css" rel="stylesheet" type="text/css">

	<!-- /core JS files -->

	<!-- Theme JS files -->
	<script src="assets/js/jquery/jquery.min.js"></script>
	<script src="assets/js/vendor/tables/datatables/datatables.min.js"></script>
	<script src="assets/js/vendor/notifications/bootbox.min.js"></script>

	<script src="assets/js/app.js"></script>
	<script src="assets/demo/pages/components_modals.js"></script>
    <script src="assets/demo/pages/components_buttons.js"></script>
	<script src="assets/demo/pages/datatables_basic.js"></script>
	<script src="assets/demo/pages/dashboard.js"></script>
	<!-- /theme JS files -->

<script>
setTimeout(() => {
const alerta = document.getElementById('alerta-auto');
if (alerta) {
alerta.classList.remove('show');
alerta.classList.add('fade');
setTimeout(() => alerta.remove(), 500); // Espera a que termine la animación
}
}, 4000);
</script>
</head>

<body>

<?php require_once('assets/main_navbar.php'); ?>

	<!-- Page content -->
	<div class="page-content">

	<?php require_once('assets/main_navigation.php'); ?>

		<!-- Main content -->
		<div class="content-wrapper">

			<!-- Inner content -->
			<div class="content-inner">

				<!-- Page header -->
				<div class="page-header page-header-light shadow">

					<div class="page-header-content d-lg-flex border-top">
						<div class="d-flex">
							<div class="breadcrumb py-2">
								<a href="index.php" class="breadcrumb-item"><i class="ph-house"></i></a>
								<a href="#" class="breadcrumb-item">Administrar Calificaciones</a>
							</div>
						</div>

					</div>
				</div>
				<!-- /page header -->

				<!-- Content area -->
				<div class="content">

				          <?php if (isset($_GET['info'])): ?>
                    <?php
                    $clase_alerta = 'alert-success'; // valor por defecto

                    switch ($_GET['info']) {
                        case 1:
                            $texto = '✅ Los estatus fueron guardados correctamente..';
                            $clase_alerta = 'alert-success';
                            break;
                        default:
                            $texto = 'info no reconocido.';
                            $clase_alerta = 'alert-secondary';
                            break;
                    }
                    ?>

                    <div class="alert <?= $clase_alerta ?> border-0 alert-dismissible fade show" id="alerta-auto">
										<span class="fw-semibold"> <?= $texto ?>
										<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
								    </div>
                  <?php endif; ?>


					<!-- Customers -->
					<div class="card">
						<div class="card-header">
							<h5 class="mb-0">Administración de Estatus de Calificaciones</h5>
						</div>

						<div class="card-body">
								Selecciona.
								<p>&nbsp;</p>


  <form method="get" class="row g-3 mb-3">
    <div class="col-md-4">
      <label for="periodo" class="form-label">Periodo</label>
      <select name="periodo" id="periodo" class="form-select">
      <?php foreach ($periodos as $p): ?>
  <option value="<?= $p['anio'] ?>" <?= ($p['anio'] == $periodo_) ? 'selected' : '' ?>><?= $p['anio'] ?></option>
      <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label for="unidad_id" class="form-label">Unidad Administrativa</label>
      <select name="unidad_id" id="unidad_id" class="form-select">
        <option value="">-- Todas --</option>
        <?php foreach ($unidades as $u): ?>
          <option value="<?= $u['id'] ?>" <?= ($u['id'] == $unidad_id_) ? 'selected' : '' ?>><?= $u['nombre'] ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label for="q" class="form-label">Buscar</label>
      <input type="text" id="q" name="q" class="form-control" value="<?= htmlspecialchars($q) ?>" placeholder="Nombre o RFC">
    </div>
    <div class="col-md-1 align-self-end">
      <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
    </div>
  </form>

  <div class="d-flex justify-content-between align-items-center mb-3">
      <span class="text-muted">Mostrando <?= count($usuarios) ?> de <?= $total_registros ?> registros</span>
      <?php if ($q !== '' || $unidad_id_ !== '' || (string)$periodo_ !== (string)$periodo): ?>
          <a href="admin_calificaciones.php" class="btn btn-sm btn-light">Limpiar filtros</a>
      <?php endif; ?>
  </div>


  <div class="table-responsive">
    <form method="post" action="admin_calificaciones_guardar.php">
    <input type="hidden" name="periodo" value="<?= $periodo_ ?>">
    <table class="table table-striped">
      <thead class="thead-light">
        <tr class="bg-primary text-white">
          <th><a class="text-white text-decoration-none" href="<?= htmlspecialchars(sort_link_admin_calif($_GET, 'persona', $sort, $dir)) ?>">Nombre <?= sort_icon_admin_calif('persona', $sort, $dir) ?></a></th>
          <th style="width: 15%;"><a class="text-white text-decoration-none" href="<?= htmlspecialchars(sort_link_admin_calif($_GET, 'metas_individuales', $sort, $dir)) ?>">Metas<br/> Individuales <?= sort_icon_admin_calif('metas_individuales', $sort, $dir) ?></a><br/>
            <a class="text-white text-decoration-none" href="<?= htmlspecialchars(sort_link_admin_calif($_GET, 'estatus_metas', $sort, $dir)) ?>">Estatus <?= sort_icon_admin_calif('estatus_metas', $sort, $dir) ?></a></th>
          <th style="width: 15%;"><a class="text-white text-decoration-none" href="<?= htmlspecialchars(sort_link_admin_calif($_GET, 'actividades_extraordinarias', $sort, $dir)) ?>">Actividades Extraordinarias <?= sort_icon_admin_calif('actividades_extraordinarias', $sort, $dir) ?></a><br/>
            <a class="text-white text-decoration-none" href="<?= htmlspecialchars(sort_link_admin_calif($_GET, 'estatus_actividades_extraordinarias', $sort, $dir)) ?>">Estatus <?= sort_icon_admin_calif('estatus_actividades_extraordinarias', $sort, $dir) ?></a></th>
          <th style="width: 15%;"><a class="text-white text-decoration-none" href="<?= htmlspecialchars(sort_link_admin_calif($_GET, 'aportaciones_destacadas', $sort, $dir)) ?>">Aportaciones Destacadas <?= sort_icon_admin_calif('aportaciones_destacadas', $sort, $dir) ?></a><br/>
            <a class="text-white text-decoration-none" href="<?= htmlspecialchars(sort_link_admin_calif($_GET, 'estatus_aportaciones_destacadas', $sort, $dir)) ?>">Estatus <?= sort_icon_admin_calif('estatus_aportaciones_destacadas', $sort, $dir) ?></a></th>
          <th style="width: 15%;"><a class="text-white text-decoration-none" href="<?= htmlspecialchars(sort_link_admin_calif($_GET, 'estatus_gerenciales', $sort, $dir)) ?>">Evaluación<br/> Gerencial <?= sort_icon_admin_calif('estatus_gerenciales', $sort, $dir) ?></a></th>
          <th style="width: 15%;">Capacitación<br/> (horas)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($usuarios as $u): ?>
          <tr>
          <td>
              <div class="d-flex align-items-center">
                  <a class="d-block me-3">
                        <img src="fotos/<?= htmlspecialchars($u['foto'] ?? 'default.png') ?>" width="40" height="40" class="rounded-circle" alt="">
                  </a>

                  <div class="flex-fill">
                        <a class="fw-semibold"><?= $u['nombre'] . ' ' . $u['apellido_paterno'] . ' ' . $u['apellido_materno'] ?></a>
                        <div class="fs-sm text-muted">
                        <?= htmlspecialchars($u['puesto_nombre'] ?? '') ?>
                        </div>
                  </div>
              </div>
            </td>
            <td>Cantidad: <?= $u['metas_capturadas'] ?><br/>
            <select name="estatus_metas[<?= $u['user_id'] ?>]" class="form-select">
                <option value="0" <?= $u['estatus_metas'] == 0 ? 'selected' : '' ?>>Sin captura</option>
                <option value="1" <?= $u['estatus_metas'] == 1 ? 'selected' : '' ?>>Capturado</option>
                <option value="2" <?= $u['estatus_metas'] == 2 ? 'selected' : '' ?>>Resultados propuestos</option>
                <option value="3" <?= $u['estatus_metas'] == 3 ? 'selected' : '' ?>>Con calificación</option>
              </select>
            </td>
            <td>Cantidad: <?= $u['actividades_extraordinarias'] ?><br/>
            <?php if ($u['actividades_extraordinarias'] > 0): ?>
              <select name="estatus_ae[<?= $u['user_id'] ?>]" class="form-select">
              <option value="0" <?= $u['estatus_actividades_extraordinarias'] == 0 ? 'selected' : '' ?>>Reabierto / Sin cierre</option>
              <option value="1" <?= $u['estatus_actividades_extraordinarias'] == 1 ? 'selected' : '' ?>>Cerrado (sin validadas)</option>
              <option value="2" <?= $u['estatus_actividades_extraordinarias'] == 2 ? 'selected' : '' ?>>Cerrado (con validadas)</option>
              </select>
              <?php else: ?>
                No aplica
              <?php endif; ?>
            </td>
            <td>Cantidad: <?= $u['aportaciones_destacadas'] ?><br/>
            <?php if ($u['aportaciones_destacadas'] > 0): ?>
              <select name="estatus_ad[<?= $u['user_id'] ?>]" class="form-select">
              <option value="0" <?= $u['estatus_aportaciones_destacadas'] == 0 ? 'selected' : '' ?>>Reabierto / Sin cierre</option>
              <option value="1" <?= $u['estatus_aportaciones_destacadas'] == 1 ? 'selected' : '' ?>>Cerrado (sin validadas)</option>
              <option value="2" <?= $u['estatus_aportaciones_destacadas'] == 2 ? 'selected' : '' ?>>Cerrado (con validadas)</option>
              </select>
              <?php else: ?>
                No aplica
              <?php endif; ?>
            </td>
            <td><?php if ($u['estatus_gerenciales'] == 0 ) {echo "Sin evaluación";} else if ($u['estatus_gerenciales'] == 1 ) {echo "Autoevaluación";} else if ($u['estatus_gerenciales'] == 2 ) {echo "Evaluación pendiente";} else if ($u['estatus_gerenciales'] == 3 ) {echo "Evaluación jefe";} else { echo "-";}?>
              <select name="estatus_gerenciales[<?= $u['user_id'] ?>]" class="form-select">
                <option value="0" <?= $u['estatus_gerenciales'] == 0 ? 'selected' : '' ?>>Sin evaluación</option>
                <option value="1" <?= $u['estatus_gerenciales'] == 1 ? 'selected' : '' ?>>Autoevaluación</option>
                <option value="2" <?= $u['estatus_gerenciales'] == 2 ? 'selected' : '' ?>>Evaluación pendiente</option>
                <option value="3" <?= $u['estatus_gerenciales'] == 3 ? 'selected' : '' ?>>Evaluación jefe (Aprobado)</option>
              </select>
            </td>
            <td>Validadas: <?= $u['horas_validadas'] ?? 0 ?><br/>
                No Validadas:<?= $u['horas_no_validadas'] ?? 0 ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <p>&nbsp;</p>
    <button type="submit" class="btn btn-sm btn-success">Guardar Cambios</button>
    </form>
  </div>
</div>

  <?php if ($total_paginas > 1): ?>
  <nav aria-label="Paginación de calificaciones" class="mt-3">
    <ul class="pagination pagination-sm mb-0">
      <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
        <a class="page-link" href="admin_calificaciones.php?<?= $query_base ?>pagina=<?= max(1, $pagina - 1) ?>">Anterior</a>
      </li>
      <?php for ($i = max(1, $pagina - 2); $i <= min($total_paginas, $pagina + 2); $i++): ?>
        <li class="page-item <?= ($i === $pagina) ? 'active' : '' ?>">
          <a class="page-link" href="admin_calificaciones.php?<?= $query_base ?>pagina=<?= $i ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
      <li class="page-item <?= ($pagina >= $total_paginas) ? 'disabled' : '' ?>">
        <a class="page-link" href="admin_calificaciones.php?<?= $query_base ?>pagina=<?= min($total_paginas, $pagina + 1) ?>">Siguiente</a>
      </li>
    </ul>
  </nav>
  <?php endif; ?>




				</div>
				</div>
				<!-- /content area -->

				<?php require_once('assets/footer.php'); ?>


			</div>
			<!-- /inner content -->

		</div>
		<!-- /main content -->

	</div>
	<!-- /page content -->

<!-- Modal Agregar -->

</body>
</html>

