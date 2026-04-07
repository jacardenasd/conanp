<?php 
$user_id = $_SESSION['user_id']; 
$periodo = $_SESSION['periodo'];
$pagina_actual = basename($_SERVER['PHP_SELF']); 

$stmt = $pdo->prepare("SELECT m.*, u.nombre, u.apellido_paterno, u.apellido_materno FROM mensajes m JOIN usuarios u ON m.remitente_id = u.user_id WHERE m.destinatario_id = ? AND m.leido = 0 ORDER BY m.fecha DESC");
$stmt->execute([$user_id]);
$mensajes_usuario = $stmt->fetchAll();

// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT *  FROM usuarios WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$usuario_ = $stmt->fetch();


// Cargar periodos
$periodos = $pdo->query("SELECT id, anio FROM periodos ORDER BY anio")->fetchAll(PDO::FETCH_ASSOC);
?>
<!-- Main navbar -->
<div class="navbar navbar-dark navbar-expand-lg navbar-static border-bottom border-bottom-white border-opacity-10">
	<div class="container-fluid">
		<div class="d-flex d-lg-none me-2">
			<button type="button" class="navbar-toggler sidebar-mobile-main-toggle rounded-pill">
				<i class="ph-list"></i>
			</button>
		</div>
			<div class="navbar-brand flex-1 flex-lg-0">
			<a href="index.php" class="d-inline-flex align-items-center">
				<img src="assets/images/logo_icon.png" alt="">
				<img src="assets/images/logo_text_light.png" class="d-none d-sm-inline-block h-16px ms-3" alt="">
			</a>
		</div>
  										
		<ul class="nav flex-row justify-content-end order-1 order-lg-2">
				<li class="nav-item">
					<a href="mensajes.php" class="navbar-nav-link navbar-nav-link-icon rounded-pill">
						<i class="ph-envelope"></i>
						<span class="badge bg-yellow text-black position-absolute top-0 end-0 translate-middle-top zindex-1 rounded-pill mt-1 me-1"><?php if (count($mensajes_usuario) == 0){ ?>0<?php } else { echo count($mensajes_usuario);} ?></span>
					</a>
				</li>
				<li class="nav-item">
					<a href="#" class="navbar-nav-link navbar-nav-link-icon rounded-pill" data-bs-toggle="offcanvas" data-bs-target="#notifications">
						<i class="icon-calendar2"></i>
						<span class="badge bg-danger text-white position-absolute top-0 end-0 translate-middle-top zindex-1 rounded-pill mt-1 me-1"><?php echo $periodo ?></span>
					</a>
				</li>

					<li class="nav-item nav-item-dropdown-lg dropdown ms-lg-2">
				<a href="#" class="navbar-nav-link align-items-center rounded-pill p-1" data-bs-toggle="dropdown">
					<div class="status-indicator-container">
					<img src="fotos/<?= htmlspecialchars($usuario_['foto'] ?? 'default.png') ?>" class="w-32px h-32px rounded-pill" alt="">
						<span class="status-indicator bg-success"></span>
					</div>
					<span class="d-none d-lg-inline-block mx-lg-2"><?php echo $_SESSION['nombre']; ?></span>
				</a>
					<div class="dropdown-menu dropdown-menu-end">
					<a href="mis_datos.php" class="dropdown-item"><i class="ph-user-circle me-2"></i>Mis datos</a>

					<div class="dropdown-divider"></div>
					<a href="logout.php" class="dropdown-item"><i class="ph-sign-out me-2"></i>Salir</a>
				</div>
			</li>
		</ul>

	</div>
</div>
<!-- /main navbar -->

	<!-- Notifications -->
	<div class="offcanvas offcanvas-end" tabindex="-1" id="notifications">
		<div class="offcanvas-header py-0">
			<h5 class="offcanvas-title py-3">Periodo de Evaluación</h5>
			<button type="button" class="btn btn-light btn-sm btn-icon border-transparent rounded-pill" data-bs-dismiss="offcanvas">
				<i class="ph-x"></i>
			</button>
		</div>

		<div class="offcanvas-body p-0">
			<div class="p-3">
				<div class="d-flex align-items-start mb-3">
					<div class="flex-fill">
					<p>El periodo actual es <a href="#" class="fw-semibold"><?= $periodo ?></a>.</p>
					<p>El estatus del periodo es: <a href="#" class="fw-semibold"><?= $estatus_periodo ?></a>.</p>
					</div>
				</div>
			</div>

			<div class="p-3">
				<div class="d-flex align-items-start mb-3">
					<div class="flex-fill">

					<p>Selecciona para cambiar el periodo:</p>
					<!-- Subscription form -->
						<form method="post" action="cambio_periodo.php?url=<?php echo $pagina_actual; ?>">
									<div class="mb-3">
										<select class="form-select" name="periodo">
											<option value="0" disabled selected>Selecciona el Periodo</option>
							<?php foreach ($periodos as $u): ?>
								<option value="<?= $u['anio'] ?>" <?= ($periodo == $u['anio'] ? 'selected' : '') ?>><?= $u['anio'] ?></option>
							<?php endforeach; ?>
										</select>
									</div>
									<div class="d-flex align-items-center">
                      <button type="submit" name="periodo_guardar" class="btn btn-sm btn-primary ms-auto">Seleccionar</button>
			            </div>
					</form>
					<!-- /subscription form -->

					</div>
				</div>
			</div>

		</div>
	</div>
	<!-- /notifications -->