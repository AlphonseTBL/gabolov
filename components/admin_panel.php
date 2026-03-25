<?php if (!empty($showAdminPanel)): ?>
<section class="py-5">
    <div class="container">
        <div class="glass p-4 p-md-4 rounded-4" data-aos="fade-up">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <h3 class="text-white fw-bold mb-1">Panel de administración</h3>
                    <p class="text-white-50 mb-0">Navega por modulos desde la barra lateral.</p>
                </div>
                <a href="index.php" class="btn btn-outline-light rounded-pill px-4">Volver al inicio</a>
            </div>

            <div class="row g-4 admin-layout">
                <div class="col-12 col-lg-3">
                    <aside class="admin-sidebar p-3 rounded-4">
                        <p class="small text-uppercase text-white-50 mb-3">Modulos</p>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-admin-nav active js-admin-nav-link" data-target="dashboard">Dashboard</button>
                            <button type="button" class="btn btn-admin-nav js-admin-nav-link" data-target="usuarios">Usuarios</button>
                            <button type="button" class="btn btn-admin-nav js-admin-nav-link" data-target="alumnos">Alumnos</button>
                            <button type="button" class="btn btn-admin-nav js-admin-nav-link" data-target="maestros">Maestros</button>
                            <button type="button" class="btn btn-admin-nav js-admin-nav-link" data-target="bicicletas">Bicicletas</button>
                            <button type="button" class="btn btn-admin-nav js-admin-nav-link" data-target="viajes">Viajes</button>
                            <button type="button" class="btn btn-admin-nav js-admin-nav-link" data-target="reportes">Reportes</button>
                        </div>
                    </aside>
                </div>

                <div class="col-12 col-lg-9">
                    <div class="admin-content">
                        <section class="admin-section is-active" data-section="dashboard">
                            <h5 class="mb-3">Dashboard</h5>
                            <div class="row g-3">
                                <div class="col-12 col-md-6 col-xl-4">
                                    <div class="card bg-dark-subtle border-0 h-100"><div class="card-body"><p class="text-uppercase text-muted small mb-1">Usuarios</p><h4 class="mb-0"><?php echo (int) ($adminSummary['total_usuarios'] ?? 0); ?></h4></div></div>
                                </div>
                                <div class="col-12 col-md-6 col-xl-4">
                                    <div class="card bg-dark-subtle border-0 h-100"><div class="card-body"><p class="text-uppercase text-muted small mb-1">Administradores</p><h4 class="mb-0"><?php echo (int) ($adminSummary['total_administradores'] ?? 0); ?></h4></div></div>
                                </div>
                                <div class="col-12 col-md-6 col-xl-4">
                                    <div class="card bg-dark-subtle border-0 h-100"><div class="card-body"><p class="text-uppercase text-muted small mb-1">Miembros</p><h4 class="mb-0"><?php echo (int) ($adminSummary['total_miembros'] ?? 0); ?></h4></div></div>
                                </div>
                                <div class="col-12 col-md-6 col-xl-4">
                                    <div class="card bg-dark-subtle border-0 h-100"><div class="card-body"><p class="text-uppercase text-muted small mb-1">Usuarios activos</p><h4 class="mb-0"><?php echo (int) ($adminSummary['usuarios_activos'] ?? 0); ?></h4></div></div>
                                </div>
                                <div class="col-12 col-md-6 col-xl-4">
                                    <div class="card bg-dark-subtle border-0 h-100"><div class="card-body"><p class="text-uppercase text-muted small mb-1">Prestamos activos</p><h4 class="mb-0"><?php echo (int) ($adminSummary['prestamos_activos'] ?? 0); ?></h4></div></div>
                                </div>
                                <div class="col-12 col-md-6 col-xl-4">
                                    <div class="card bg-dark-subtle border-0 h-100"><div class="card-body"><p class="text-uppercase text-muted small mb-1">Bicicletas disponibles</p><h4 class="mb-0"><?php echo (int) ($adminSummary['bicicletas_disponibles'] ?? 0); ?> / <?php echo (int) ($adminSummary['bicicletas_totales'] ?? 0); ?></h4></div></div>
                                </div>
                            </div>
                        </section>

                        <section class="admin-section" data-section="usuarios">
                            <h5 class="mb-3">Usuarios</h5>
                            <div class="card bg-dark-subtle border-0 mb-3">
                                <div class="card-body">
                                    <h6 class="mb-3">Crear nuevo administrador</h6>
                                    <form method="post" action="index.php?action=admin&admin_section=usuarios" class="row g-2">
                                        <div class="col-12 col-md-4">
                                            <input type="text" class="form-control" name="admin_user_name" maxlength="80" placeholder="Nombre completo" required>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <input type="email" class="form-control" name="admin_user_email" maxlength="120" placeholder="Correo" required>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <input type="password" class="form-control" name="admin_user_password" minlength="6" placeholder="Contraseña" required>
                                        </div>
                                        <div class="col-12 col-md-1 d-grid">
                                            <button type="submit" class="btn btn-utn" name="admin_create_admin_user" value="1">Crear</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-dark table-striped align-middle">
                                    <thead><tr><th>ID</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Activo</th><th>Ultimo uso</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($adminUsers)): ?><tr><td colspan="6" class="text-center text-white-50">Sin usuarios registrados.</td></tr><?php endif; ?>
                                        <?php foreach ($adminUsers as $user): ?>
                                            <tr>
                                                <td><?php echo (int) $user['id']; ?></td>
                                                <td><?php echo htmlspecialchars($user['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars(ucfirst($user['rol']), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo ((int) $user['activo'] === 1) ? 'Si' : 'No'; ?></td>
                                                <td><?php echo htmlspecialchars($user['ultimo_uso'] !== '' ? $user['ultimo_uso'] : 'N/D', ENT_QUOTES, 'UTF-8'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="admin-section" data-section="alumnos">
                            <h5 class="mb-3">Alumnos</h5>
                            <div class="row g-4 mb-3">
                                <div class="col-12 col-lg-6"><div class="card bg-dark-subtle border-0 h-100"><div class="card-body"><h6 class="mb-2">Carga masiva</h6><p class="text-muted small mb-3">Formato: ID|Nombre|Carrera</p><form method="post" action="index.php?action=admin" class="d-grid gap-2"><textarea name="bulk_alumnos_data" class="form-control" rows="6" placeholder="1003|Laura Perez|Ingenieria Civil&#10;1004|Diego Ruiz|Mecatronica" required></textarea><button type="submit" class="btn btn-utn" name="admin_bulk_alumnos" value="1">Procesar alumnos</button></form></div></div></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-dark table-striped align-middle">
                                    <thead><tr><th style="width:120px;">ID</th><th>Nombre</th><th>Carrera</th><th style="width:120px;">Usuario</th><th style="width:220px;">Acciones</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($adminAlumnos)): ?><tr><td colspan="5" class="text-center text-white-50">Sin alumnos registrados.</td></tr><?php endif; ?>
                                        <?php foreach ($adminAlumnos as $alumno): ?>
                                            <tr><form method="post" action="index.php?action=admin"><td><input type="number" class="form-control form-control-sm" name="alumno_id" value="<?php echo (int) $alumno['id']; ?>" min="1" readonly></td><td><input type="text" class="form-control form-control-sm" name="alumno_nombre" value="<?php echo htmlspecialchars($alumno['nombre'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="50" required></td><td><input type="text" class="form-control form-control-sm" name="alumno_carrera" value="<?php echo htmlspecialchars($alumno['campo'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="50" required></td><td><?php echo $alumno['usuario_id'] !== null ? (int) $alumno['usuario_id'] : '-'; ?></td><td><div class="d-flex gap-2"><button type="submit" class="btn btn-sm btn-success" name="admin_update_alumno" value="1">Guardar</button><button type="submit" class="btn btn-sm btn-danger" name="admin_delete_alumno" value="1" onclick="return confirm('¿Eliminar alumno?');">Eliminar</button></div></td></form></tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="admin-section" data-section="maestros">
                            <h5 class="mb-3">Maestros</h5>
                            <div class="row g-4 mb-3">
                                <div class="col-12 col-lg-6"><div class="card bg-dark-subtle border-0 h-100"><div class="card-body"><h6 class="mb-2">Carga masiva</h6><p class="text-muted small mb-3">Formato: ID|Nombre|Departamento</p><form method="post" action="index.php?action=admin" class="d-grid gap-2"><textarea name="bulk_maestros_data" class="form-control" rows="6" placeholder="5003|Patricia Luna|Ciencias Basicas&#10;5004|Rafael Nieto|Ingenieria" required></textarea><button type="submit" class="btn btn-utn" name="admin_bulk_maestros" value="1">Procesar maestros</button></form></div></div></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-dark table-striped align-middle">
                                    <thead><tr><th style="width:120px;">ID</th><th>Nombre</th><th>Departamento</th><th style="width:120px;">Usuario</th><th style="width:220px;">Acciones</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($adminMaestros)): ?><tr><td colspan="5" class="text-center text-white-50">Sin maestros registrados.</td></tr><?php endif; ?>
                                        <?php foreach ($adminMaestros as $maestro): ?>
                                            <tr><form method="post" action="index.php?action=admin"><td><input type="number" class="form-control form-control-sm" name="maestro_id" value="<?php echo (int) $maestro['id']; ?>" min="1" readonly></td><td><input type="text" class="form-control form-control-sm" name="maestro_nombre" value="<?php echo htmlspecialchars($maestro['nombre'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="60" required></td><td><input type="text" class="form-control form-control-sm" name="maestro_departamento" value="<?php echo htmlspecialchars($maestro['campo'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="50" required></td><td><?php echo $maestro['usuario_id'] !== null ? (int) $maestro['usuario_id'] : '-'; ?></td><td><div class="d-flex gap-2"><button type="submit" class="btn btn-sm btn-success" name="admin_update_maestro" value="1">Guardar</button><button type="submit" class="btn btn-sm btn-danger" name="admin_delete_maestro" value="1" onclick="return confirm('¿Eliminar maestro?');">Eliminar</button></div></td></form></tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="admin-section" data-section="bicicletas">
                            <h5 class="mb-3">Bicicletas</h5>
                            <div class="card bg-dark-subtle border-0 mb-3"><div class="card-body"><form method="post" action="index.php?action=admin" class="row g-2"><div class="col-12 col-md-3"><input type="text" class="form-control" name="bike_modelo" maxlength="50" placeholder="Modelo" required></div><div class="col-12 col-md-5"><input type="url" class="form-control" name="bike_imagen_url" maxlength="500" placeholder="URL de imagen" required></div><div class="col-12 col-md-3"><input type="text" class="form-control" name="bike_comentarios" maxlength="255" placeholder="Comentarios" required></div><div class="col-12 col-md-1 d-grid"><button type="submit" class="btn btn-utn" name="admin_create_bike" value="1">Crear</button></div></form></div></div>
                            <div class="table-responsive">
                                <table class="table table-dark table-striped align-middle">
                                    <thead><tr><th>ID</th><th>Modelo</th><th>Estado</th><th>Imagen URL</th><th>Comentarios</th><th>Ultimo uso</th><th>Acciones</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($adminBikes)): ?><tr><td colspan="7" class="text-center text-white-50">Sin bicicletas registradas.</td></tr><?php endif; ?>
                                        <?php foreach ($adminBikes as $bike): ?>
                                            <tr><form method="post" action="index.php?action=admin"><td><input type="number" class="form-control form-control-sm" name="bike_id" value="<?php echo (int) $bike['id']; ?>" readonly></td><td><input type="text" class="form-control form-control-sm" name="bike_modelo" value="<?php echo htmlspecialchars($bike['modelo'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="50" required></td><td><select class="form-select form-select-sm" name="bike_estado" required><?php foreach (['Disponible', 'Ocupada', 'En Mantenimiento'] as $estado): ?><option value="<?php echo $estado; ?>" <?php echo ($bike['estado'] === $estado) ? 'selected' : ''; ?>><?php echo $estado; ?></option><?php endforeach; ?></select></td><td><input type="url" class="form-control form-control-sm" name="bike_imagen_url" value="<?php echo htmlspecialchars($bike['imagen_url'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="500" required></td><td><input type="text" class="form-control form-control-sm" name="bike_comentarios" value="<?php echo htmlspecialchars($bike['comentarios'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="255" required></td><td><?php echo htmlspecialchars($bike['ultimo_uso'] !== '' ? $bike['ultimo_uso'] : 'N/D', ENT_QUOTES, 'UTF-8'); ?></td><td><div class="d-flex gap-2"><button type="submit" class="btn btn-sm btn-success" name="admin_update_bike" value="1">Guardar</button><button type="submit" class="btn btn-sm btn-danger" name="admin_delete_bike" value="1" onclick="return confirm('¿Eliminar bicicleta?');">Eliminar</button></div></td></form></tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="admin-section" data-section="viajes">
                            <h5 class="mb-3">Historial global de viajes</h5>
                            <div class="table-responsive">
                                <table class="table table-dark table-striped align-middle">
                                    <thead><tr><th>Prestamo</th><th>Usuario</th><th>Nombre</th><th>Bici</th><th>Modelo</th><th>Fecha</th><th>Estado</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($adminTrips)): ?><tr><td colspan="7" class="text-center text-white-50">No hay viajes registrados.</td></tr><?php endif; ?>
                                        <?php foreach ($adminTrips as $trip): ?>
                                            <tr><td>#<?php echo (int) $trip['prestamo_id']; ?></td><td><?php echo (int) $trip['usuario_id']; ?></td><td><?php echo htmlspecialchars($trip['nombre_usuario'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo (int) $trip['bicicleta_id']; ?></td><td><?php echo htmlspecialchars($trip['modelo'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($trip['dia_uso'] !== '' ? $trip['dia_uso'] : 'N/D', ENT_QUOTES, 'UTF-8'); ?></td><td class="<?php echo ((int) $trip['estado_viaje'] === 1) ? 'text-success' : 'text-warning'; ?>"><?php echo ((int) $trip['estado_viaje'] === 1) ? 'Finalizado' : 'En curso'; ?></td></tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="admin-section" data-section="reportes">
                            <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-3">
                                <div>
                                    <h5 class="mb-1">Reportes</h5>
                                    <p class="text-white-50 mb-0">Analitica mensual con exportacion en PDF.</p>
                                </div>
                                <form method="get" action="index.php" class="d-flex align-items-end gap-2">
                                    <input type="hidden" name="action" value="admin">
                                    <input type="hidden" name="admin_section" value="reportes">
                                    <div>
                                        <label for="adminReportMonth" class="form-label small text-white-50 mb-1">Mes</label>
                                        <input type="month" id="adminReportMonth" name="admin_report_month" class="form-control form-control-sm" value="<?php echo htmlspecialchars($adminReportMonth, ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-outline-light">Filtrar</button>
                                </form>
                            </div>

                            <div id="adminReportExportArea" data-month="<?php echo htmlspecialchars($adminReportMonth, ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="row g-3 mb-4">
                                    <div class="col-12 col-md-6 col-xl-3"><div class="card bg-dark-subtle border-0 h-100"><div class="card-body"><p class="text-uppercase text-muted small mb-1">Total viajes</p><h4 class="mb-0"><?php echo (int) ($adminReports['total_viajes'] ?? 0); ?></h4></div></div></div>
                                    <div class="col-12 col-md-6 col-xl-3"><div class="card bg-dark-subtle border-0 h-100"><div class="card-body"><p class="text-uppercase text-muted small mb-1">En curso</p><h4 class="mb-0"><?php echo (int) ($adminReports['viajes_en_curso'] ?? 0); ?></h4></div></div></div>
                                    <div class="col-12 col-md-6 col-xl-3"><div class="card bg-dark-subtle border-0 h-100"><div class="card-body"><p class="text-uppercase text-muted small mb-1">Finalizados</p><h4 class="mb-0"><?php echo (int) ($adminReports['viajes_finalizados'] ?? 0); ?></h4></div></div></div>
                                    <div class="col-12 col-md-6 col-xl-3"><div class="card bg-dark-subtle border-0 h-100"><div class="card-body"><p class="text-uppercase text-muted small mb-1">Uso de flotilla</p><h4 class="mb-0"><?php echo htmlspecialchars((string) ($adminReports['uso_flotilla_porcentaje'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>%</h4></div></div></div>
                                </div>

                                <div class="row g-4 mb-4">
                                    <div class="col-12 col-xl-6">
                                        <div class="card bg-dark-subtle border-0 h-100">
                                            <div class="card-body">
                                                <h6 class="mb-3">Uso por modelo</h6>
                                                <div class="report-chart-wrap">
                                                    <canvas id="adminModelUsageChart"></canvas>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-xl-6">
                                        <div class="card bg-dark-subtle border-0 h-100">
                                            <div class="card-body">
                                                <h6 class="mb-3">Uso por usuario</h6>
                                                <div class="report-chart-wrap">
                                                    <canvas id="adminUserUsageChart"></canvas>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-4">
                                    <div class="col-12 col-xl-6">
                                        <h6 class="mb-2">Top modelos mas utilizados</h6>
                                        <div class="table-responsive"><table class="table table-dark table-striped align-middle"><thead><tr><th>Modelo</th><th>Viajes</th></tr></thead><tbody><?php if (empty($adminReports['top_modelos'])): ?><tr><td colspan="2" class="text-center text-white-50">Sin datos.</td></tr><?php endif; ?><?php foreach (($adminReports['top_modelos'] ?? []) as $item): ?><tr><td><?php echo htmlspecialchars($item['modelo'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo (int) $item['viajes']; ?></td></tr><?php endforeach; ?></tbody></table></div>
                                    </div>
                                    <div class="col-12 col-xl-6">
                                        <h6 class="mb-2">Top usuarios por viajes</h6>
                                        <div class="table-responsive"><table class="table table-dark table-striped align-middle"><thead><tr><th>Usuario</th><th>Nombre</th><th>Viajes</th></tr></thead><tbody><?php if (empty($adminReports['top_usuarios'])): ?><tr><td colspan="3" class="text-center text-white-50">Sin datos.</td></tr><?php endif; ?><?php foreach (($adminReports['top_usuarios'] ?? []) as $item): ?><tr><td><?php echo (int) $item['usuario_id']; ?></td><td><?php echo htmlspecialchars($item['nombre'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo (int) $item['viajes']; ?></td></tr><?php endforeach; ?></tbody></table></div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 d-flex justify-content-end">
                                <button type="button" class="btn btn-sm btn-warning" id="adminReportExportBtn">Exportar PDF con graficos</button>
                            </div>

                            <script type="application/json" id="adminReportTopModelsData"><?php echo json_encode($adminReports['top_modelos'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
                            <script type="application/json" id="adminReportTopUsersData"><?php echo json_encode($adminReports['top_usuarios'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>
