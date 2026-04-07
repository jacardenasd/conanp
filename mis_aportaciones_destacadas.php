<?php
// ============================================
// MIS APORTACIONES DESTACADAS + VALIDACIÓN CASCADA
// Cambio #5: Agregar flujo de validación jefe→RH
// ============================================

require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';
require 'includes/finalizaciones.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin();

// ============================================
// PROCESAR ACCIONES DE VALIDACIÓN
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    $id = intval($_POST['id'] ?? 0);
    $tabla = 'aportaciones_destacadas';
    
    // Validación del Jefe
    if ($accion === 'validar_jefe') {
        if (validar_por_jefe($pdo, $tabla, $id, $_SESSION['user_id'], 1)) {
            $_SESSION['mensaje'] = '✅ Aportación validada por Superior Jerárquico';
        }
    } elseif ($accion === 'rechazar_jefe') {
        if (validar_por_jefe($pdo, $tabla, $id, $_SESSION['user_id'], 0)) {
            $_SESSION['mensaje'] = '❌ Aportación rechazada por Superior Jerárquico';
        }
    } 
    // Validación de RH
    elseif ($accion === 'validar_rh') {
        if (validar_por_rh($pdo, $tabla, $id, $_SESSION['user_id'], 1)) {
            $_SESSION['mensaje'] = '✅ Aportación validada por RH';
        }
    } elseif ($accion === 'rechazar_rh') {
        if (validar_por_rh($pdo, $tabla, $id, $_SESSION['user_id'], 0)) {
            $_SESSION['mensaje'] = '❌ Aportación rechazada por RH';
        }
    }
    // Revocar validación de RH (solo super admin)
    elseif ($accion === 'revocar_rh' && $_SESSION['role'] == 3) {
        if (revocar_validacion_rh($pdo, $tabla, $id, $_SESSION['user_id'])) {
            $_SESSION['mensaje'] = '🔄 Validación de RH revocada';
        }
    }
    
    header('Location: mis_aportaciones_destacadas.php');
    exit;
}

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');
$user_id = $_SESSION['user_id']; 

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);
$periodo = $_SESSION['periodo'];

// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


if ($_SERVER['CONTENT_LENGTH'] > 4 * 1024 * 1024) {
	header("Location: mis_aportaciones_destacadas.php?info=4");
	exit;
}

	   	if (isset($_POST['agregar'])) {

			$errores = [];
			$archivo_nombre = null;
			$archivo_cargado = false;
			
			if (isset($_FILES['archivo_pdf']) && $_FILES['archivo_pdf']['error'] === UPLOAD_ERR_OK) {
				$archivo_tmp = $_FILES['archivo_pdf']['tmp_name'];
				$archivo_original = $_FILES['archivo_pdf']['name'];
				$archivo_tipo = $_FILES['archivo_pdf']['type'];
				$archivo_tamano = $_FILES['archivo_pdf']['size'];
				
				// Validar tamaño
				if ($archivo_tamano > 4 * 1024 * 1024) {
					header("Location: mis_aportaciones_destacadas.php?info=4");
					exit;
				}
				
				// Validar MIME type - permitir variaciones de PDF
				$mime_tipos_validos = ['application/pdf', 'application/x-pdf', 'application/x-bzpdf', 'application/x-gzpdf'];
				$es_pdf_valido = in_array($archivo_tipo, $mime_tipos_validos);
				
				// Validar magic bytes del archivo (PDF siempre comienza con %PDF)
				$file_magic = fread(fopen($archivo_tmp, 'r'), 4);
				$es_pdf_magic = (strpos($file_magic, '%PDF') === 0);
				
				if (!$es_pdf_valido && !$es_pdf_magic) {
					$errores[] = "El archivo debe ser un PDF válido.";
				}
				
				if (empty($errores)) {
					$archivo_nombre = limpiar_nombre_archivo($archivo_original);
					$ruta_destino = 'aportaciones/' . $archivo_nombre;
					if (!is_dir('aportaciones')) {
						mkdir('aportaciones', 0755, true);
					}
					if (move_uploaded_file($archivo_tmp, $ruta_destino)) {
						$archivo_cargado = true;
					} else {
						$errores[] = "No se pudo guardar el archivo en el servidor.";
					}
				}
			} elseif (isset($_FILES['archivo_pdf']) && $_FILES['archivo_pdf']['error'] !== UPLOAD_ERR_NO_FILE) {
				$errores[] = "Hubo un error al cargar el archivo. Por favor intenta de nuevo.";
			}
			
			// Solo insertar si no hay errores
			if (empty($errores)) {
				$stmt = $pdo->prepare("INSERT INTO aportaciones_destacadas (user_id, descripcion, periodo, estatus, archivo_pdf) VALUES (?, ?,?, ?, ?)");
		        $stmt->execute([$user_id, $_POST['descripcion'], $periodo, 1, $archivo_nombre]);
				header("Location: mis_aportaciones_destacadas.php?info=1");
				exit();
			} else {
				// Mostrar error
				$_SESSION['error_archivo'] = $errores[0];
				header("Location: mis_aportaciones_destacadas.php?info=6");
				exit();
			}

		} elseif (isset($_POST['editar'])) {
			$id = $_POST['id'];

			// Verificar si ya fue validada por el superior
			$stmt = $pdo->prepare("SELECT validado FROM aportaciones_destacadas WHERE id = ?");
			$stmt->execute([$id]);
			$aportacion = $stmt->fetch(PDO::FETCH_ASSOC);
			
			if ($aportacion && $aportacion['validado'] == 1) {
				header("Location: mis_aportaciones_destacadas.php?info=5");
				exit;
			}

			    // Consulta para obtener el nombre actual del archivo
				$stmt = $pdo->prepare("SELECT archivo_pdf FROM aportaciones_destacadas WHERE id = ?");
				$stmt->execute([$id]);
				$row = $stmt->fetch(PDO::FETCH_ASSOC);
				$archivo_actual = $row['archivo_pdf'] ?? '';

				$errores = [];
			$archivo_nombre = $archivo_actual;
			if (isset($_FILES['archivo_pdf']) && $_FILES['archivo_pdf']['error'] === UPLOAD_ERR_OK) {
				$archivo_tmp = $_FILES['archivo_pdf']['tmp_name'];
				$archivo_original = $_FILES['archivo_pdf']['name'];
				$archivo_tipo = $_FILES['archivo_pdf']['type'];
				$archivo_tamano = $_FILES['archivo_pdf']['size'];
				
				if ($archivo_tamano > 4 * 1024 * 1024) {
					header("Location: mis_aportaciones_destacadas.php?info=4");
					exit;
				}
				
				$mime_tipos_validos = ['application/pdf', 'application/x-pdf', 'application/x-bzpdf', 'application/x-gzpdf'];
				$es_pdf_valido = in_array($archivo_tipo, $mime_tipos_validos);
				$file_magic = fread(fopen($archivo_tmp, 'r'), 4);
				$es_pdf_magic = (strpos($file_magic, '%PDF') === 0);
				
				if (!$es_pdf_valido && !$es_pdf_magic) {
					$errores[] = "El archivo debe ser un PDF valido.";
				}
				
				if (empty($errores)) {
					$archivo_nombre = limpiar_nombre_archivo($archivo_original);
					$ruta_destino = 'aportaciones/' . $archivo_nombre;
					if (!is_dir('aportaciones')) {
						mkdir('aportaciones', 0755, true);
					}
					if (!move_uploaded_file($archivo_tmp, $ruta_destino)) {
						$errores[] = "No se pudo guardar el archivo en el servidor.";
					}
				}
			}
			
			if (empty($errores)) {
		        $stmt = $pdo->prepare("UPDATE aportaciones_destacadas SET descripcion = ?, periodo = ?, estatus = ?, archivo_pdf = ?  WHERE id = ?");
		        $stmt->execute([$_POST['descripcion'], $periodo, 1, $archivo_nombre, $id]);
		    	header("Location: mis_aportaciones_destacadas.php?info=2");
		    	exit();
		} else {
			$_SESSION['error_archivo'] = $errores[0];
			header("Location: mis_aportaciones_destacadas.php?info=6");
			exit();
		}
    	} elseif (isset($_POST['eliminar'])) {
		$id = $_POST['id'];
		
		// Verificar si ya fue validada por el superior (Cambio #E2)
		$stmt = $pdo->prepare("SELECT validado FROM aportaciones_destacadas WHERE id = ?");
		$stmt->execute([$id]);
		$aportacion = $stmt->fetch(PDO::FETCH_ASSOC);
		
		if ($aportacion && $aportacion['validado'] == 1) {
			header("Location: mis_aportaciones_destacadas.php?info=5"); // info=5: ya validada, no se puede eliminar
			exit;
		}
		
		$stmt = $pdo->prepare("DELETE FROM aportaciones_destacadas WHERE id = ?");
		$stmt->execute([$id]);
		
		// Cambio cliente Febrero 2026 - F2: Recalcular semáforos después de eliminar
		if (!function_exists('recalcular_semaforo_aportaciones_actividades')) {
			require_once __DIR__ . '/includes/finalizaciones.php';
		}
		if (function_exists('recalcular_semaforo_aportaciones_actividades')) {
			recalcular_semaforo_aportaciones_actividades($pdo, $user_id, $periodo);
		} else {
			error_log('No se encontró la función recalcular_semaforo_aportaciones_actividades al eliminar aportación destacada.');
		}
		
		header("Location: mis_aportaciones_destacadas.php?info=3");
		exit();
		}
}

$stmt = $pdo->prepare("SELECT * FROM aportaciones_destacadas where user_id = ? AND periodo = ? ORDER BY id");
$stmt->execute([$user_id, $periodo]);
$aportaciones_destacadas = $stmt->fetchAll(PDO::FETCH_ASSOC);

//evaluacion especial
$stmt = $pdo->prepare("SELECT COUNT(*) FROM especiales WHERE user_id = ? AND periodo = ?");
$stmt->execute([$user_id, $periodo]);
$especial = $stmt->fetchColumn() > 0;
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
								<a href="#" class="breadcrumb-item">Mi evaluación</a>
								<span class="breadcrumb-item active">Aportaciones Destacadas  <?= $periodo ?></span>
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
                            $texto = '✅ Registro guardado correctamente.';
                            $clase_alerta = 'alert-success';
                            break;
                        case 2:
                            $texto = '✏️ Registro actualizado correctamente.';
                            $clase_alerta = 'alert-info';
                            break;
                        case 3:
                            $texto = '🗑️ Registro eliminado correctamente.';
                            $clase_alerta = 'alert-danger';
                            break;
                            case 4:
                              $texto = '⚠️ El archivo es muy pesado';
                              $clase_alerta = 'alert-warning';
                              break;
                            case 5:
                              $texto = '🔒 No puedes editar o eliminar este registro porque ya fue validado por tu superior jerárquico.';
                              $clase_alerta = 'alert-warning';
                              break;
                          default:
                            $texto = 'info no reconocido.';
                            $clase_alerta = 'alert-secondary';
                            break;
                    }
                    ?>

                    <div class="alert <?= $clase_alerta ?> border-0 alert-dismissible fade show">
										<span class="fw-semibold"> <?= $texto ?>
										<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
								    </div>

                    <?php endif; ?>



                    <div class="position-relative mb-4">
                    <div class="bg-white border p-3 ps-5 rounded shadow-sm">
                        <strong>🎯 Recuerda:</strong> Esta valoración es opcional, además serán validadas por el superior jerárquico y por el Área de Capacitación para que sean consideradas.
                    </div>
                    <img src="assets/images/avatars/E-commerce-2.png" class="position-absolute top-0 start-0 translate-middle-y ms-2 rounded-circle border" width="50" height="50">
                    </div>

<div class="card">
  <div class="card-header">
    <h5 class="mb-0 fw-semibold text-primary">
      <i class="ph-star-four me-2 text-secondary"></i>Aportaciones Destacadas
    </h5>
  </div>

  <div class="card-body">
    <p class="mb-4">
      Son acciones realizadas por iniciativa de la <strong>persona servidora pública evaluada</strong>, cuyos resultados puedan ser verificados y documentados. Estas deben contribuir a:
    </p>

    <ul class="mb-4 ps-4">
      <li>Mejorar el desempeño de sus funciones.</li>
      <li>Desarrollar el capital humano de otras personas servidoras públicas.</li>
      <li>Aportar beneficios a la CONANP o a la ciudadanía.</li>
    </ul>

    <p class="fw-semibold text-dark mb-2">Cada aportación debe cumplir con los siguientes requisitos:</p>
    <ol class="ps-4 mb-0">
      <li>Contar con una <strong>calificación satisfactoria</strong> en el cumplimiento individual de metas.</li>
      <li>No estar contemplada en otro rubro de evaluación del desempeño.</li>
      <li>Ser una acción <strong>voluntaria y no solicitada</strong> por los superiores.</li>
      <li>Facilitar o mejorar funciones, metas o beneficios institucionales o ciudadanos.</li>
      <li>No generar presiones presupuestales ni afectar otras áreas.</li>
      <li>Haber sido informada y <strong>aprobada por los superiores</strong> en su momento.</li>
      <li>Contar con <strong>evidencia documental verificable</strong>.</li>
    </ol>
  </div>
</div>

<div class="card">
  <div class="card-header">
						<div class="table-responsive">
						<table class="table table-xl">
							<thead class="thead-light">
							<tr class="bg-info text-white">
									<th>ID</th>
									<th>Descipción de la Aportación</th>
									<th>Archivo</th>
									<th>Estatus Validación</th>
									<th>Estatus Validación RH</th>
									<th style="width:15%">Acciones</th>
								</tr>
							</thead>
							<tbody>
							<?php if (count($aportaciones_destacadas) === 0): ?>
								<tr><td colspan="4">No se encuentran registros en el periodo seleccionado.</td></tr>
							<?php else: ?>
								<?php foreach ($aportaciones_destacadas as $u): ?>
								<tr>
									<td><?= $u['id'] ?></td>
									<td><?= htmlspecialchars($u['descripcion']) ?></td>
									<td><?php if ($u['archivo_pdf']): ?><a href="aportaciones/<?= $u['archivo_pdf'] ?>" target="_blank" class="btn btn-sm btn-warning">Ver</a><?php else: ?><span class="text-muted">—</span><?php endif; ?></td> 
									<td><?php if ($u['validado'] == 1){ echo "Validado"; } else { echo "-";}?></td>
									<td><?php if ($u['validado_rh'] == 1){ echo "Validado"; } else { echo "-";}?></td>
								<td>
								<?php if ($u['validado'] == 1) { ?>
									<!-- Cambio #E2: Bloqueado por validación del superior -->
									<span class="badge bg-success">🔒 Validado por superior</span>
								<?php } else if (($estatus_periodo === 'Captura' OR $estatus_periodo === 'Evaluación' OR ($especial)) AND ( $u['estatus']) != 2) {?>
									<button class="btn btn-info btn-sm btn-editar" data-bs-toggle="modal" data-bs-target="#modalEditar<?= $u['id'] ?>">Editar</button>
									<button class="btn btn-danger btn-sm btn-eliminar" data-bs-toggle="modal" data-bs-target="#modalEliminar<?= $u['id'] ?>">Eliminar</button>
									<?php } else {echo "<span class='fs-sm text-muted'>Periodo Cerrado</span>"; } ?>
								</td>
								</tr>

<!-- Modal Editar -->
<div class="modal fade" id="modalEditar<?= $u['id'] ?>" tabindex="-1">
	<div class="modal-dialog modal-lg">
	<div class="modal-content">
    <form method="post"  enctype="multipart/form-data">
      <input type="hidden" name="id" id="idEditar">
	  <div class="modal-header">
		<h5 class="modal-title">Editar Aportación Destacada</h5>
		<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
	</div>
      <div class="modal-body">

	    <div class="form-group">
            <label>Descripcion</label>
			<textarea name="descripcion" class="form-control" rows="4" required><?= htmlspecialchars($u['descripcion']) ?></textarea>
        </div>

		<div class="form-group col-md-6"><label class="col-lg-3 col-form-label">PDF</label>
            <input type="file" name="archivo_pdf" class="form-control" class="form-control-file" accept="application/pdf">
          </div>

	</div>
      <div class="modal-footer">
        <button name="editar" class="btn btn-sm btn-info">Editar</button>
		<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
		<input type="hidden" name="id" value="<?= $u['id'] ?>"></input>
		<input type="hidden" name="user_id" value="<?= $user_id ?>"></input>
		<input type="hidden" name="periodo"  value="<?= $periodo ?>"></input>
		<input type="hidden" name="estatus"  value="1"></input>
	      </div>
    </form>
  </div>
  </div>
  </div>
<!-- Modal Editar -->

<!-- Modal  Eliminación -->
<div class="modal fade" id="modalEliminar<?= $u['id'] ?>" tabindex="-1">
<div class="modal-dialog modal-lg">
	<div class="modal-content">
    <form method="post"  enctype="multipart/form-data">
      <input type="hidden" name="id" id="idEditar">
	  <div class="modal-header">
		<h5 class="modal-title">Eliminar Aportación Destacada</h5>
		<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
	</div>
      <div class="modal-body">
        ¿Estás seguro de que deseas eliminar la Aportación: <strong><?= $u['descripcion'] ?></strong>?
      </div>
      <div class="modal-footer">
        <button name="eliminar" class="btn btn-sm btn-danger">Eliminar</button>
		<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
		<input type="hidden" name="id" value="<?= $u['id'] ?>"></input>
      </div>
    </form>
  </div>
</div>
</div>
<!-- /Modal  Eliminación -->


								<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
						</div>
					</div>
					</div>
					<!-- /basic table -->

                    <?php if ($estatus_periodo === 'Captura' OR $estatus_periodo === 'Evaluación' OR ($especial)) {?>
						<button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalAgregar">Agregar</button>
                    <?php } ?>
                    <a href="mi_evaluacion.php" class="btn btn-secondary btn-sm">Regresar</a>




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

<div class="modal fade" id="modalAgregar" tabindex="-1">
	<div class="modal-dialog modal-lg">
	<div class="modal-content">
    <form method="post"  enctype="multipart/form-data">
      <input type="hidden" name="id" id="idEditar">
	  <div class="modal-header bg-success text-white">
		<h5 class="modal-title">Agregar Aportación Destacada</h5>
		<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
	</div>
      <div class="modal-body">

	  	<div class="form-group mb-3">
            <label><strong>Descripción de la Aportación Destacada *</strong></label>
			<textarea name="descripcion" class="form-control" rows="4" required placeholder="Describe la aportación destacada realizada..."></textarea>
        </div>

		<div class="form-group mb-3">
			<label class="form-label"><strong>Archivo PDF *</strong></label>
            <input type="file" name="archivo_pdf" class="form-control" required accept="application/pdf">
        </div>

		</div>
      <div class="modal-footer">
        <button name="agregar" class="btn btn-sm btn-success">Agregar</button>
		<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
		<input type="hidden" name="user_id" value="<?= $user_id ?>"></input>
		<input type="hidden" name="periodo"  value="<?= $periodo ?>"></input>
		<input type="hidden" name="estatus"  value="1"></input>
	  </div>
    </form>
  </div>
</div>
<!-- /Modal Agregar -->


</body>
</html>
