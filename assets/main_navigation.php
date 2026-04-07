<?php $pagina_actual = basename($_SERVER['PHP_SELF']); 
$admin = in_array($pagina_actual, ['admin_variables.php', 'admin_bloqueos.php', 'admin_periodos.php', 'unidades_medida.php', 'admin_adscripciones.php', 'admin_unidades.php', 'admin_calificaciones.php', 'admin_resumen_calificaciones.php', 'admin_capacitacion.php', 'admin_puestos.php', 'admin_usuarios.php', 'admin_calendario.php', 'admin_archivos.php', 'admin_mensajes.php', 'admin_avisos.php', 'admin_log_accesos.php', 'mensajes_redactar.php', 'usuarios_agregar.php', 'usuarios_editar.php', 'puestos_editar.php', 'puestos_agregar.php', 'admin_metas_individuales.php', 'admin_metas_individuales_editar.php', 'admin_metas_individuales_agregar.php','admin_metas_colectivas.php', 'admin_metas_colectivas_editar.php', 'admin_metas_colectivas_agregar.php', 'calificaciones_editar.php', 'admin_categorias.php', 'admin_modalidades.php', 'admin_finalidades.php', 'admin_instrumentos_pnd.php', 'admin_ejes_pnd.php', 'usuarios_importar.php', 'admin_aportaciones_destacadas.php', 'admin_actividades_extraordinarias.php', 'admin_reportes.php', 'admin_evaluadores_directivos.php', 'admin_cedulas_firmadas.php', 'admin_cedulas_captura.php'
]);
$admin2 = in_array($pagina_actual, [ 'admin_periodos.php', 'unidades_medida.php', 'admin_adscripciones.php', 'admin_unidades.php', 'admin_puestos.php', 'admin_usuarios.php', 'usuarios_agregar.php', 'usuarios_editar.php', 'puestos_editar.php', 'puestos_agregar.php', 'admin_categorias.php', 'admin_modalidades.php', 'admin_finalidades.php', 'admin_instrumentos_pnd.php', 'admin_ejes_pnd.php', 'usuarios_importar.php', 
]);
$admin3 = in_array($pagina_actual, ['admin_calificaciones.php', 'admin_resumen_calificaciones.php', 'admin_capacitacion.php', 'admin_metas_individuales.php', 'admin_metas_individuales_editar.php', 'admin_metas_individuales_agregar.php','admin_metas_colectivas.php', 'admin_metas_colectivas_editar.php', 'admin_metas_colectivas_agregar.php', 'calificaciones_editar.php', 'admin_aportaciones_destacadas.php', 'admin_actividades_extraordinarias.php', 'admin_reportes.php', 'admin_evaluadores_directivos.php', 'admin_cedulas_firmadas.php', 'admin_cedulas_captura.php'
]);
$admin4 = in_array($pagina_actual, ['admin_variables.php', 'admin_bloqueos.php', 'admin_avisos.php', 'admin_mensajes.php', 'admin_log_accesos.php', 'mensajes_redactar.php', 'admin_calendario.php', 'admin_archivos.php',
]);
 ?>
<!-- Main sidebar -->
		<div class="sidebar sidebar-dark sidebar-main sidebar-expand-lg <?php if ($_SESSION['sidebar_resized'] == 1) {echo 'sidebar-main-resized';} ?>">

			<!-- Sidebar content -->
			<div class="sidebar-content">

				<!-- Sidebar header -->
				<div class="sidebar-section">
					<div class="sidebar-section-body d-flex justify-content-center">
						<h5 class="sidebar-resize-hide flex-grow-1 my-auto">Navegación</h5>

						<div>
							<a href="sidebar_resized.php?url=<?php echo $pagina_actual; ?>" id="sidebar_resized" class="btn btn-flat-white btn-icon btn-sm rounded-pill border-transparent">
								<i class="ph-arrows-left-right"></i>
							</a>

							<a href="#" id="sidebar_resized" class="btn btn-flat-white btn-icon btn-sm rounded-pill border-transparent sidebar-mobile-main-toggle d-lg-none">
								<i class="ph-x"></i>
							</a>
						</div>
					</div>
				</div>
				<!-- /sidebar header -->


				<!-- Main navigation -->
				<div class="sidebar-section">
					<ul class="nav nav-sidebar" data-nav-type="accordion">

					<?php if ($_SESSION['tipo_usuario'] == 1 OR $_SESSION['tipo_usuario'] == 2): ?>

						<!-- Main -->
						<li class="nav-item">
							<a href="index.php" class="nav-link <?= ($pagina_actual == 'index.php' OR $pagina_actual == 'archivos_descargar.php') ? 'active' : '' ?>">
								<i class="ph-house"></i>
								<span>Inicio</span>
							</a>
						</li>
						

							<li class="nav-item">
							<a href="mis_metas_individuales.php" class="nav-link  <?= ($pagina_actual == 'mis_metas_individuales.php' OR $pagina_actual == 'metas_individuales.php' OR $pagina_actual == 'meta_editar.php' OR $pagina_actual == 'meta_nueva.php') ? 'active' : '' ?>">
								<i class="ph-list"></i>
								<span>Metas Individuales</span>
							</a>
						</li>

						<?php if (isset($_SESSION['permite_metas_colectivas']) && $_SESSION['permite_metas_colectivas'] == 1): ?>
						<li class="nav-item">
							<a href="mis_metas_colectivas.php" class="nav-link  <?= ($pagina_actual == 'mis_metas_colectivas.php' OR $pagina_actual == 'metas_colectivas.php' OR $pagina_actual == 'meta_colectiva_editar.php' OR $pagina_actual == 'meta_colectiva_nueva.php') ? 'active' : '' ?>">
								<i class="ph-list-checks"></i>
								<span>Metas Colectivas</span>
							</a>
						</li>
						<?php endif; ?>

						<li class="nav-item">
							<a href="mi_evaluacion.php" class="nav-link <?= ($pagina_actual == 'mi_evaluacion.php' OR $pagina_actual == 'mis_actividades_extraordinarias.php' OR $pagina_actual == 'mi_autoevaluacion.php'  OR $pagina_actual == 'mis_aportaciones_destacadas.php' OR $pagina_actual == 'mis_metas_colectivas') ? 'active' : '' ?>">
								<i class="ph-user-list"></i>
								<span>Mi evaluación</span>
							</a>
						</li>
						
						<li class="nav-item">
							<a href="mis_colaboradores.php" class="nav-link <?= ($pagina_actual == 'mis_colaboradores.php' OR $pagina_actual == 'evaluar_individuales.php' OR $pagina_actual == 'meta_evaluar.php' OR $pagina_actual == 'cols_aportaciones_destacadas.php' OR $pagina_actual == 'cols_actividades_extraordinarias.php' OR $pagina_actual == 'evaluar_competencias_colaborador.php') ? 'active' : '' ?>">
								<i class="ph-users"></i>
								<span>Mis colaboradores</span>
							</a>
						</li>
						
						<?php endif; ?>

						<li class="nav-item">
							<a href="mi_capacitacion.php" class="nav-link  <?= ($pagina_actual == 'mi_capacitacion.php') ? 'active' : '' ?>">
								<i class="ph-student"></i>
								<span>Mi capacitación</span>
							</a>
						</li>

						<?php if ($_SESSION['role'] == 3): ?>


							<li class="nav-item nav-item-submenu <?= $admin ? 'nav-item-expanded nav-item-open' : '' ?>">
							<a href="#" class="nav-link"><i class="icon icon-cog3"></i> <span>Administración</span></a>
							<ul class="nav-group-sub collapse <?= $admin ? 'show' : '' ?>">
								<li class="nav-item nav-item-submenu <?= $admin2 ? 'nav-item-expanded nav-item-open' : '' ?>" >
									<a href="#" class="nav-link">Catálogos</a>
									<ul class="nav-group-sub collapse  <?= $admin2 ? 'show' : '' ?>">
									<li class="nav-item"><a href="admin_unidades.php" class="nav-link <?= ($pagina_actual == 'admin_unidades.php') ? 'active' : '' ?>">Unidades Administrativas</a></li>
									<li class="nav-item"><a href="admin_adscripciones.php" class="nav-link <?= ($pagina_actual == 'admin_adscripciones.php') ? 'active' : '' ?>">Adscripciones</a></li>
									<li class="nav-item"><a href="unidades_medida.php" class="nav-link <?= ($pagina_actual == 'unidades_medida.php') ? 'active' : '' ?>">Unidades de Medida</a></li>
									<li class="nav-item"><a href="admin_categorias.php" class="nav-link <?= ($pagina_actual == 'admin_categorias.php') ? 'active' : '' ?>">Categorias Capacitación</a></li>
									<li class="nav-item"><a href="admin_finalidades.php" class="nav-link <?= ($pagina_actual == 'admin_finalidades.php') ? 'active' : '' ?>">Finalidades Capacitación</a></li>
									<li class="nav-item"><a href="admin_modalidades.php" class="nav-link <?= ($pagina_actual == 'admin_modalidades.php') ? 'active' : '' ?>">Modalidades Capacitación</a></li>
									<li class="nav-item"><a href="admin_instrumentos_pnd.php" class="nav-link <?= ($pagina_actual == 'admin_instrumentos_pnd.php') ? 'active' : '' ?>">Instrumentos</a></li>
									<li class="nav-item"><a href="admin_ejes_pnd.php" class="nav-link <?= ($pagina_actual == 'admin_ejes_pnd.php') ? 'active' : '' ?>">Ejes P.N.D.</a></li>
									<li class="nav-item"><a href="admin_periodos.php" class="nav-link <?= ($pagina_actual == 'admin_periodos.php') ? 'active' : '' ?>">Periodos de Evaluación</a></li>
									<li class="nav-item"><a href="admin_puestos.php" class="nav-link <?= ($pagina_actual == 'admin_puestos.php' OR $pagina_actual == 'puestos_editar.php' OR $pagina_actual == 'puestos_agregar.php') ? 'active' : '' ?>">Puestos</a></li>
									<li class="nav-item"><a href="admin_usuarios.php" class="nav-link <?= ($pagina_actual == 'admin_usuarios.php' OR $pagina_actual == 'usuarios_agregar.php'  OR $pagina_actual == 'usuarios_editar.php' Or $pagina_actual == 'usuarios_importar.php') ? 'active' : '' ?>">Usuarios</a></li>
								</ul>	
								</li>
								<li class="nav-item nav-item-submenu <?= $admin3 ? 'nav-item-expanded nav-item-open' : '' ?>" >
									<a href="#" class="nav-link">Evaluaciones</a>
									<ul class="nav-group-sub collapse  <?= $admin3 ? 'show' : '' ?>">
									<li class="nav-item"><a href="admin_evaluadores_directivos.php" class="nav-link <?= ($pagina_actual == 'admin_evaluadores_directivos.php') ? 'active' : '' ?>">Evaluadores Directivos</a></li>
									<li class="nav-item"><a href="admin_metas_colectivas.php" class="nav-link <?= ($pagina_actual == 'admin_metas_colectivas.php' OR $pagina_actual == 'admin_metas_colectivas_editar.php' OR $pagina_actual == 'admin_metas_colectivas_agregar.php') ? 'active' : '' ?>">Metas Colectivas</a></li>									<li class="nav-item"><a href="admin_metas_individuales.php" class="nav-link <?= ($pagina_actual == 'admin_metas_individuales.php' OR $pagina_actual == 'admin_meta_editar.php' OR $pagina_actual == 'admin_metas_individuales_agregar.php') ? 'active' : '' ?>">Metas Individuales</a></li>
									<li class="nav-item"><a href="admin_aportaciones_destacadas.php" class="nav-link <?= ($pagina_actual == 'admin_aportaciones_destacadas.php') ? 'active' : '' ?>">Aportaciones Destacadas</a></li>
									<li class="nav-item"><a href="admin_actividades_extraordinarias.php" class="nav-link <?= ($pagina_actual == 'admin_actividades_extraordinarias.php') ? 'active' : '' ?>">Actividades Extraordinarias</a></li>
									<li class="nav-item"><a href="admin_capacitacion.php" class="nav-link <?= ($pagina_actual == 'admin_capacitacion.php') ? 'active' : '' ?>">Capacitacion</a></li>
									<li class="nav-item"><a href="admin_cedulas_firmadas.php" class="nav-link <?= ($pagina_actual == 'admin_cedulas_firmadas.php') ? 'active' : '' ?>">Cédulas Finales</a></li>
									<li class="nav-item"><a href="admin_cedulas_captura.php" class="nav-link <?= ($pagina_actual == 'admin_cedulas_captura.php') ? 'active' : '' ?>">Cédulas Captura</a></li>
									<li class="nav-item"><a href="admin_calificaciones.php" class="nav-link <?= ($pagina_actual == 'admin_calificaciones.php') ? 'active' : '' ?>">Estatus Calificaciones</a></li>
									<li class="nav-item"><a href="admin_resumen_calificaciones.php" class="nav-link <?= ($pagina_actual == 'admin_resumen_calificaciones.php') ? 'active' : '' ?>">Resumen de Calificaciones</a></li>
									<li class="nav-item"><a href="admin_reportes.php" class="nav-link <?= ($pagina_actual == 'admin_reportes.php') ? 'active' : '' ?>">Descarga de Reportes</a></li>
									</ul>
								</li>
								<li class="nav-item nav-item-submenu <?= $admin4 ? 'nav-item-expanded nav-item-open' : '' ?>" >
									<a href="#" class="nav-link">Sistema</a>
									<ul class="nav-group-sub collapse  <?= $admin4 ? 'show' : '' ?>">
									<li class="nav-item"><a href="admin_avisos.php" class="nav-link <?= ($pagina_actual == 'admin_avisos.php') ? 'active' : '' ?>">Avisos</a></li>
									<li class="nav-item"><a href="admin_mensajes.php" class="nav-link <?= ($pagina_actual == 'admin_mensajes.php' OR $pagina_actual == 'mensajes_redactar.php') ? 'active' : '' ?>">Mensajes</a></li>
									<li class="nav-item"><a href="admin_archivos.php" class="nav-link <?= ($pagina_actual == 'admin_archivos.php') ? 'active' : '' ?>">Archivos</a></li>
									<li class="nav-item"><a href="admin_calendario.php" class="nav-link <?= ($pagina_actual == 'admin_calendario.php') ? 'active' : '' ?>">Calendario</a></li>
									<li class="nav-item"><a href="admin_log_accesos.php" class="nav-link <?= ($pagina_actual == 'admin_log_accesos.php') ? 'active' : '' ?>">Log de Accesos</a></li>
									<li class="nav-item"><a href="admin_bloqueos.php" class="nav-link <?= ($pagina_actual == 'admin_bloqueos.php') ? 'active' : '' ?>">Bloqueos de Captura</a></li>
									<li class="nav-item"><a href="admin_variables.php" class="nav-link <?= ($pagina_actual == 'admin_variables.php') ? 'active' : '' ?>">Variables del Sistema</a></li>
									</ul>
								</li>
							</ul>
						</li>

					
						<?php endif; ?>
						<!-- /page kits -->

					</ul>
				</div>
				<!-- /main navigation -->

				
			</div>
			<!-- /sidebar content -->
			
		</div>
		<!-- /main sidebar -->

