<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin();

// SECCIÓN BLOQUEADA: La capacitación no está disponible actualmente
//header('Location: index.php');
//exit();

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');
$periodo = $_SESSION['periodo'];
$captura_capacitacion_bloqueada = modulo_bloqueado_por_periodo('capacitacion', (int)$periodo);

// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();

$user_id = $_SESSION['user_id']; 

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);
$role = $_SESSION['role'];
asegurar_columna_capacitacion_contabiliza($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

if ($_SERVER['CONTENT_LENGTH'] > 4 * 1024 * 1024) {
    header("Location: mi_capacitacion.php?info=4");
    exit;

}

  if (isset($_POST['guardar'])) {
    $nombre = $_POST['nombre_curso'];
    $horas = $_POST['horas'];
    $calificacion = $_POST['calificacion'];
    $institucion = $_POST['institucion'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $modalidad = $_POST['modalidad'];
    $finalidad = null;
    $correo = $_POST['correo'];
    $telefono = $_POST['telefono'];
    $categoria = null;
    $archivo_nombre = null;

    if ($captura_capacitacion_bloqueada) {
      header("Location: mi_capacitacion.php?info=12");
      exit;
    }
    
    // Validar que el periodo esté en estado "Captura"
    if ($estatus_periodo === 'Evaluación') {
        header("Location: mi_capacitacion.php?info=10&error=evaluacion");
        exit;
    }

    // Verifica si ya existe el curso con el mismo nombre, fechas y usuario
    $stmt_verifica = $pdo->prepare("SELECT COUNT(*) FROM capacitacion WHERE user_id = ? AND nombre_curso = ? AND fecha_inicio = ? AND fecha_fin = ?");
    $stmt_verifica->execute([$user_id, $nombre, $fecha_inicio, $fecha_fin]);
    $existe = $stmt_verifica->fetchColumn();

    if ($existe > 0) {
      // Ya existe, redirige con aviso
      header("Location: mi_capacitacion.php?info=9");
      exit;
    }

    $errores = [];
    $archivo_nombre = null;
    if (isset($_FILES['archivo_pdf']) && $_FILES['archivo_pdf']['error'] === UPLOAD_ERR_OK) {
        $archivo_tmp = $_FILES['archivo_pdf']['tmp_name'];
        $archivo_original = $_FILES['archivo_pdf']['name'];
        $archivo_tipo = $_FILES['archivo_pdf']['type'];
        $archivo_tamano = $_FILES['archivo_pdf']['size'];
        if ($archivo_tipo !== 'application/pdf') {
            $errores[] = "El archivo debe ser un PDF.";
        }
        if ($archivo_tamano > 4 * 1024 * 1024) {
            header("Location: mi_capacitacion.php?info=4");
            exit;

        }
        if (empty($errores)) {
            $archivo_nombre = limpiar_nombre_archivo($archivo_original);
            $ruta_destino = 'capacitacion/' . $archivo_nombre;
            if (!is_dir('capacitacion')) {
                mkdir('capacitacion', 0755, true);
            }
            move_uploaded_file($archivo_tmp, $ruta_destino);
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO capacitacion (user_id, nombre_curso, horas, calificacion, institucion, fecha_inicio, fecha_fin, archivo_pdf, periodo, modalidad, finalidad, correo, telefono, categoria) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $nombre, $horas, $calificacion, $institucion, $fecha_inicio, $fecha_fin, $archivo_nombre, $periodo, $modalidad, $finalidad, $correo, $telefono, $categoria]);
    header("Location: mi_capacitacion.php?info=1");
    exit;
  }

  if (isset($_POST['actualizar'])) {
    if ($captura_capacitacion_bloqueada) {
      header("Location: mi_capacitacion.php?info=12");
      exit;
    }

    $id = $_POST['id'];

    // Consulta para obtener el nombre actual del archivo
    $stmt = $pdo->prepare("SELECT archivo_pdf FROM capacitacion WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $archivo_actual = $row['archivo_pdf'] ?? '';

    $nombre = $_POST['nombre_curso'];
    $horas = $_POST['horas'];
    $calificacion = $_POST['calificacion'];
    $institucion = $_POST['institucion'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $modalidad = $_POST['modalidad'];
    $correo = $_POST['correo'];
    $telefono = $_POST['telefono'];
    $archivo_nombre = null;

    $stmt = $pdo->prepare("SELECT * FROM capacitacion WHERE id = ?");
    $stmt->execute([$id]);
    $curso = $stmt->fetch();
    if (!$curso || ($curso['user_id'] != $user_id && $rol !== 'admin' && $rol !== 'superadmin')) {
      header("Location: mi_capacitacion.php");
      exit;
    }

    // Se conservan los valores actuales al no capturarlos en el formulario de edicion.
    $finalidad = $curso['finalidad'];
    $categoria = $curso['categoria'];

    $errores = [];
    $archivo_nombre = null;
    if (isset($_FILES['archivo_pdf']) && $_FILES['archivo_pdf']['error'] === UPLOAD_ERR_OK) {
        $archivo_tmp = $_FILES['archivo_pdf']['tmp_name'];
        $archivo_original = $_FILES['archivo_pdf']['name'];
        $archivo_tipo = $_FILES['archivo_pdf']['type'];
        $archivo_tamano = $_FILES['archivo_pdf']['size'];
        if ($archivo_tipo !== 'application/pdf') {
            $errores[] = "El archivo debe ser un PDF.";
        }
        if ($archivo_tamano > 4 * 1024 * 1024) {
            header("Location: mi_capacitacion.php?info=4");
            exit;
        }
        if (empty($errores)) {
            $archivo_nombre = limpiar_nombre_archivo($archivo_original);
            $ruta_destino = 'capacitacion/' . $archivo_nombre;
            if (!is_dir('capacitacion')) {
                mkdir('capacitacion', 0755, true);
            }
            move_uploaded_file($archivo_tmp, $ruta_destino);
        }
    } else {$archivo_nombre = $archivo_actual;}
    
    $stmt = $pdo->prepare("UPDATE capacitacion SET nombre_curso = ?, horas = ?, calificacion = ?, institucion = ?, fecha_inicio = ?, fecha_fin = ?, archivo_pdf = ?, periodo = ?, telefono = ?, correo = ?, modalidad = ?, finalidad = ?, categoria = ? WHERE id = ?");
    $stmt->execute([$nombre, $horas, $calificacion, $institucion, $fecha_inicio, $fecha_fin, $archivo_nombre, $periodo, $telefono, $correo, $modalidad, $finalidad, $categoria, $id]);

    header("Location: mi_capacitacion.php?info=2");
    exit;
  }

  if (isset($_POST['eliminar'])) {
    if ($captura_capacitacion_bloqueada) {
      header("Location: mi_capacitacion.php?info=12");
      exit;
    }

    $id = $_POST['id'];
    $stmt = $pdo->prepare("DELETE FROM capacitacion WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    header("Location: mi_capacitacion.php?info=3");
    exit;
  }
}

$stmt = $pdo->prepare("SELECT c.id, c.user_id, c.finalidad, c.categoria, c.observaciones, c.modalidad, c.telefono, c.correo, c.nombre_curso, c.horas, c.calificacion, c.institucion, c.fecha_inicio, c.fecha_fin, c.archivo_pdf, c.validado, c.contabiliza_horas, c.periodo, CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS empleado, cm.modalidad AS modalidad_texto, cf.finalidad AS finalidad_texto, cc.categoria AS categoria_texto FROM capacitacion c JOIN usuarios u ON c.user_id = u.user_id LEFT JOIN capacitacion_modalidades cm ON c.modalidad = cm.id LEFT JOIN capacitacion_finalidades cf ON c.finalidad = cf.id LEFT JOIN capacitacion_categorias cc ON c.categoria = cc.id WHERE c.user_id = ? AND c.periodo = ? ORDER BY fecha_inicio DESC"); 
$stmt->execute([$user_id, $periodo]);
$cursos = $stmt->fetchAll(); 

// Cargar periodos
$periodos = $pdo->query("SELECT id, anio FROM periodos ORDER BY anio")->fetchAll(PDO::FETCH_ASSOC);

// Validar suma de horas
$stmt = $pdo->prepare("SELECT SUM(horas) FROM capacitacion WHERE user_id = ? AND periodo = ? AND validado = 1 AND COALESCE(contabiliza_horas, 1) = 1");
$stmt->execute([$user_id, $periodo]);
$suma = $stmt->fetchColumn();

$modalidades = $pdo->query("SELECT * FROM capacitacion_modalidades ORDER BY modalidad ASC")->fetchAll();
$finalidades = $pdo->query("SELECT * FROM capacitacion_finalidades ORDER BY finalidad ASC")->fetchAll();
$categorias = $pdo->query("SELECT * FROM capacitacion_categorias ORDER BY categoria ASC")->fetchAll();

//evaluacion especial
$stmt = $pdo->prepare("SELECT COUNT(*) FROM especiales WHERE user_id = ? AND periodo = ?");
$stmt->execute([$user_id, $periodo]);
$especial = $stmt->fetchColumn() > 0;

$avisos = $pdo->query("SELECT a.*, CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS autor FROM avisos a JOIN usuarios u ON a.usuario_id = u.user_id WHERE tipo = 2 ORDER BY a.fecha DESC limit 4")->fetchAll();

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
								<a href="#" class="breadcrumb-item">Mi Capacitación</a>
								<span class="breadcrumb-item active">Cursos  <?= $periodo ?></span>
							</div>

							<a href="#breadcrumb_elements" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
								<i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
							</a>
						</div>

					</div>
				</div>
				<!-- /page header -->

				<!-- Content area -->
				<div class="content">

				<div class="d-flex align-items-start mb-4">
							<img src="assets/images/avatars/Nocomments7.png" alt="Guía" class="rounded-circle mr-3" width="70" height="70">
							<div class="bg-light border p-3 rounded shadow-sm">
              En la sección de <a href="archivos_descargar.php">documentación</a>, puedes consultar diversos archivos y material de apoyo.
							</div>
							</div>



                <?php if (isset($_GET['info'])): ?>
                    <?php
                    $clase_alerta = 'alert-success'; 

                    switch ($_GET['info']) {
                        case 1:
                            $texto = '✅ Curso guardado correctamente.';
                            $clase_alerta = 'alert-success';
                            break;
                        case 2:
                            $texto = '✏️ Curso actualizado correctamente.';
                            $clase_alerta = 'alert-info';
                            break;
                        case 3:
                            $texto = '🗑️ Curso eliminado correctamente.';
                            $clase_alerta = 'alert-danger';
                            break;
                        case 4:
                            $texto = '⚠️ El archivo es muy pesado';
                            $clase_alerta = 'alert-warning';
                            break;
                        case 9:
                            $texto = '⚠️ El curso ya existe';
                            $clase_alerta = 'alert-warning';
                            break;
                        case 10:
                            $texto = '🚫 No se puede registrar capacitación durante el periodo de EVALUACIÓN. Por favor, intenta después.';
                            $clase_alerta = 'alert-danger';
                            break;
                        case 11:
                          $texto = '🚫 El registro de capacitación para este periodo no está disponible.';
                          $clase_alerta = 'alert-danger';
                          break;
                        case 12:
                          $texto = '🚫 La captura de capacitación está cerrada para este periodo por configuración administrativa.';
                            $clase_alerta = 'alert-danger';
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


                    <?php if ( $suma > 0 ) { ?>
                    <div class="alert alert-warning  border-0 alert-dismissible fade show">
                    Actualmente cuentas con <span class="fw-semibold"><?= $suma ?></span> horas registradas y Validadas por el área de Capacitación. Recuerda que debes acreditar al menos 40 hras de capacitación anual.
										<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
								    </div>
                    <?php } else { ?>
                      <div class="alert alert-warning  border-0 alert-dismissible fade show">
                    Actualmente <span class="fw-semibold">no cuentas </span> con horas registradas y Validadas por el área de Capacitación. Recuerda que debes acreditar al menos 40 hras de capacitación anual.
										<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
								    </div>
                    <?php } ?>
                    



  <!-- Basic table -->
		<div class="card">
      <div class="card-header">
        <h4>Mi Capacitación</h4>
      </div>

      <div class="card-body">
        <div class="row">
          <p>Bienvenido(a) a tu registro de Capacitación.</p>
          <p>Para dudas o aclaraciones, comunícate al teléfono <b>55 5449 7000</b> extensiones <b>17005, 17097, 17217</b> y <b>17218 </b> o al correo capacitacion@conanp.gob.mx.<br/>
          Asegúrate de capturar toda la información solicitada, así como de cargar la evidencia documental de cada curso en formato PDF.<br/>
          Te recordamos que los cursos cargados serán validados por el área de Capacitación, quien verificará que cumplan con lo establecido por la Secretaría de Buen Gobierno.<br/>
          No olvides realizar el registro y carga de la capacitación en el trimestre que corresponda. Posterior a la fecha de cierre del trimestre, ya no te será permitido.</p>
        </div>
      </div>
		</div>
            
      <div class="card">
        <div class="card-header">
          <h4 class="mb-0">📢 Avisos</h4>
        </div>

        <div class="card-body pb-0">
          <div class="row">
            <?php
            $col1 = [];
            $col2 = [];
            foreach ($avisos as $index => $aviso) {if ($index % 2 == 0) {$col1[] = $aviso; } else { $col2[] = $aviso; } }

            function renderAvisoMedia($a, $id) {
              $imagen = (!empty($a['imagen']) && file_exists("imagenes_avisos/" . $a['imagen'])) 
                ? "imagenes_avisos/" . htmlspecialchars($a['imagen']) 
                : "imagenes_avisos/cover2.jpg"; // Fallback
              $extracto = substr(strip_tags($a['mensaje']), 0, 120) . '...';
              ?>
              <div class="d-sm-flex align-items-sm-start mb-3">
                <a href="#" class="d-inline-block position-relative me-sm-3 mb-3 mb-sm-0" data-bs-toggle="modal" data-bs-target="#modalAviso<?= $id ?>">
                  <img src="<?= $imagen ?>" class="flex-shrink-0 rounded" height="100" alt="">
                </a>

                <div class="row">
                <div class="form-group col-md-6"><label class="col-lg-3 col-form-label">Modalidad</label>

              <div class="modal fade" id="modalAviso<?= $id ?>" tabindex="-1" aria-labelledby="modalLabel<?= $id ?>" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title" id="modalLabel<?= $id ?>"><?= htmlspecialchars($a['titulo']) ?></h5>
                      <small class="text-muted">Publicado por <?= htmlspecialchars($a['autor']) ?> el <?= date('d/m/Y', strtotime($a['fecha'])) ?></small>
                    </div>
                  </div>
                </div>
              </div>
              <?php } ?>

            <!-- Columna 1 -->
            <div class="col-xl-6">
              <?php foreach ($col1 as $i => $a) renderAvisoMedia($a, 'col1_' . $i); ?>
            </div>

            <!-- Columna 2 -->
            <div class="col-xl-6">
              <?php foreach ($col2 as $i => $a) renderAvisoMedia($a, 'col2_' . $i); ?>
            </div>
          </div>
        </div>
    </div>

          
            
    <div class="card">
			<div class="card-body">
        <table class="table table-xl">
            <thead class="thead-light">
            <tr class="bg-info text-white">
                <th>Curso</th>
                <th>Horas</th>
                <th>Calificación</th>
                <th>Institución</th>
                <th>Fecha</th>
                <th>Modalidad</th>
                <th>Constancia</th>
                <th>Validado</th>
                <th style="width:15%">Acciones</th>
              </tr>
            </thead>
            <tbody>
            <?php if (count($cursos) === 0): ?>
              <tr><td colspan="8">No se encuentran registros en el periodo seleccionado.</td></tr>
            <?php else: ?>
              <?php foreach ($cursos as $c): ?>
                <tr>
                  <td><?= htmlspecialchars($c['nombre_curso']) ?></td>
                  <td><?= $c['horas'] ?></td>
                  <td><?= htmlspecialchars($c['calificacion']) ?></td>
                  <td><?= htmlspecialchars($c['institucion']) ?></td>
                  <td><?= date('d/m/Y', strtotime($c['fecha_inicio'])) ?></td>
                  <td><?= $c['modalidad_texto'] ?></td>
                  <td>
                    <?php if ($c['archivo_pdf']): ?><a href="capacitacion/<?= $c['archivo_pdf'] ?>" target="_blank" class="btn btn-sm btn-warning">Ver</a><?php else: ?><span class="text-muted">—</span><?php endif; ?>
                  </td> 
                  <td>
                    <?php if ($c['validado'] == 1): ?>
                      <?php if ((int)($c['contabiliza_horas'] ?? 1) === 1): ?>
                        <span class="badge bg-success">Si (suma horas)</span>
                      <?php else: ?>
                        <span class="badge bg-success">Si (sin suma horas)</span>
                      <?php endif; ?>
                    <?php else: ?>
                      No
                    <?php endif; ?>
                    <?php if ($c['observaciones'] != '') { ?>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalobservaciones<?= $c['id'] ?>"> Observaciones</button>
                    <?php }?>
                  </td>
                  <td>
                  <?php if ($c['validado'] == 0) { ?>    
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalEditar<?= $c['id'] ?>">Editar</button>
                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#modalEliminar<?= $c['id'] ?>">Borrar</button>
                  <?php } else { echo "-";} ?>    
                  </td>
                </tr>

              <!-- Modal eliminar -->
            <div class="modal fade" id="modalEliminar<?= $c['id'] ?>" tabindex="-1" role="dialog">
              <div class="modal-dialog">
                <div class="modal-content">
                  <form method="post" >
                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                    <div class="modal-header"><h5 class="modal-title">Eliminar Curso</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p><strong><?= htmlspecialchars($c['nombre_curso']) ?></strong></p>
                        <p>Esta acción no se puede deshacer.</p>
                    </div>
                    <div class="modal-footer">
                        <button name="eliminar" class="btn btn-sm  btn-danger">Eliminar</button>
                        <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

              <!-- Modal eliminar -->
              <div class="modal fade" id="modalobservaciones<?= $c['id'] ?>" tabindex="-1" role="dialog">
              <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Observaciones</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p><strong><?= htmlspecialchars($c['observaciones']) ?></strong></p>
                    </div>
                </div>
              </div>
            </div>


          <!-- Modal editar -->
          <div class="modal fade" id="modalEditar<?= $c['id'] ?>" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg">
            <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
              <div class="modal-header"><h5 class="modal-title">Edita Curso</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
                <div class="modal-body">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">

                <div class="form-row">

                  <div class="form-group col-md-12"><label class="col-lg-3 col-form-label">Curso</label>
                      <input type="text" name="nombre_curso" class="form-control" value="<?= htmlspecialchars($c['nombre_curso']) ?>" required>
                  </div>

                  <div class="row">
                    <div class="form-group col-md-6"><label class="col-lg-3 col-form-label">Horas</label>
                      <input type="number" min="1" max="500" name="horas" class="form-control" value="<?= $c['horas'] ?>" required>
                    </div>
                    <div class="form-group col-md-6"><label class="col-lg-3 col-form-label">Calificación</label>
                      <input type="number" min="1" max="100" name="calificacion" class="form-control" value="<?= $c['calificacion'] ?>" required>
                    </div>
                  </div>

                </div>


                <div class="form-row">

                  <div class="form-group col-md-12"><label class="col-lg-3 col-form-label">Institución</label>
                      <input type="text" name="institucion" class="form-control" value="<?= htmlspecialchars($c['institucion']) ?>" required>
                  </div>
                    
                  <div class="row">
                    <div class="form-group col-md-6"><label class="col-lg-3 col-form-label">Fecha Inicio</label>
                      <input type="date" name="fecha_inicio" class="form-control" value="<?= $c['fecha_inicio'] ?>" required>
                    </div>
                    <div class="form-group col-md-6"><label class="col-lg-3 col-form-label">Fecha Fin</label>
                    <input type="date" name="fecha_fin" class="form-control" value="<?= $c['fecha_fin'] ?>" required>
                    </div>
                  </div>


                <div class="form-row">

                  <div class="row">
                    <div class="form-group col-md-6"><label class="col-lg-3 col-form-label">Correo:</label>
                      <input type="mail" name="correo" class="form-control" value="<?= $c['correo'] ?>" required>
                    </div>
                    <div class="form-group col-md-6"><label class="col-lg-3 col-form-label">Telefono</label>
                    <input type="text" name="telefono" class="form-control" value="<?= $c['telefono'] ?>">
                    </div>
                  </div>


                <div class="form-row">

                  <div class="row">
                    <div class="form-group col-md-6"><label class="col-lg-3 col-form-label">Modalidad</label>
                    <select name="modalidad" class="form-select" required>
                      <?php foreach ($modalidades as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= $c['modalidad'] == $m['id'] ? 'selected' : '' ?>><?= $m['modalidad'] ?></option>
                      <?php endforeach; ?>
                    </select>
                    </div>
                    <div class="form-group col-md-6"><label class="col-lg-3 col-form-label">PDF</label>
                    <input type="file" name="archivo_pdf" class="form-control" class="form-control-file" accept="application/pdf">
                    </div>
                  </div>

                <div class="modal-footer">
                  <button name="actualizar" class="btn btn-sm btn-primary">Guardar cambios</button>
                </div>

                </div>

              </form>
            </div>
            </div>
          </div>


              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
            <tfoot>
                  <tr>
                  <th></th>
                  <th>Total <?php if ($suma > 0) { echo $suma ;} else { echo "0";} ?> </th>
                  <th></th>
                  <th></th>
                  <th></th>
                  <th></th>
                  <th></th>
                    <th></th>
                    <th></th>
                  </tr>
                </tfoot>
        </table>
      </div>
    </div>

  <?php if (($estatus_periodo == 'Captura' OR ($especial) OR $periodo != 2025) && !$captura_capacitacion_bloqueada) { ?>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalAgregar">Agregar Curso</button>
  <?php } elseif ($captura_capacitacion_bloqueada) { ?>
    <div class="alert alert-warning border-0 mt-3">La captura de capacitación está cerrada para el periodo <?= (int)$periodo ?>.</div>
  <?php } ?>
 
</div>
				<!-- /content area -->

				<?php require_once('assets/footer.php'); ?>

			</div>
			<!-- /inner content -->

		</div>
		<!-- /main content -->

	</div>
	<!-- /page content -->


<!-- Modal agregar -->
<div class="modal fade" id="modalAgregar" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg">
  <div  class="modal-content">
  <form method="post" enctype="multipart/form-data">
  <div class="modal-header"><h5 class="modal-title">Agregar Curso</h5>
	  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
      <div class="modal-body">
      <div class="form-row">
          <div class="form-group col-md-12"><label class="col-lg-3 col-form-label">Curso</label>
            <input type="text" name="nombre_curso" class="form-control" required>
          </div>
          <div class="row">
        <div class="form-group col-md-6"><label class="col-lg-3 col-form-label">Horas</label>
            <input type="number" min="1" max="500" name="horas" class="form-control" required>
          </div>
          <div class="form-group col-md-6"><label class="col-lg-3 col-form-label">Calificación</label>
            <input type="number" min="1" max="100" name="calificacion" class="form-control" required>
          </div>
        </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-12"><label class="col-lg-3 col-form-label">Institución</label>
            <input type="text" name="institucion" class="form-control" required>
          </div>
          <div class="row">
          <div class="form-group col-md-6"><label class="col-lg-3 col-form-label">Fecha Inicio</label>
            <input type="date" name="fecha_inicio" class="form-control" required>
          </div>
          <div class="form-group col-md-6"><label class="col-lg-3 col-form-label">Fecha Fin</label>
          <input type="date" name="fecha_fin" class="form-control" required>
          </div>
          </div>
          <div class="row">
          <div class="form-group col-md-6"><label class="col-lg-3 col-form-label">Correo</label>
            <input type="text" name="correo" class="form-control" required>
          </div>
          <div class="form-group col-md-6"><label class="col-lg-3 col-form-label">Telefono</label>
          <input type="text" name="telefono" class="form-control" required>
          </div>
          </div>
          <div class="row">
          <div class="form-group col-md-6"><label class="col-lg-3 col-form-label">Modalidad</label>
          <select name="modalidad" class="form-select" required>
            <?php foreach ($modalidades as $m): ?>
              <option value="<?= $m['id'] ?>"><?= $m['modalidad'] ?></option>
            <?php endforeach; ?>
          </select>
          </div>
          <div class="form-group col-md-6"><label class="col-lg-3 col-form-label">PDF</label>
          <input type="file" name="archivo_pdf" class="form-control" required class="form-control-file" accept="application/pdf">
          </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button name="guardar" class="btn btn-sm btn-success">Guardar</button>
      </div>
    </form>
  </div>
</div>

</body>
</html>
