<section class="section">
    <div class="row">
        <div class="col-12">

            <div class="card">
                <div class="card-body pt-3">

                    <div class="d-flex justify-content-between align-items-center mb-3 mt-2">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-geo-alt-fill me-2 text-success"></i>Credenciales GPS
                        </h5>
                        <button class="btn btn-success btn-sm" id="btnNuevoGPS">
                            <i class="bi bi-plus-circle me-1"></i> Nuevo registro
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table id="tblGPS" class="table table-hover table-striped table-sm align-middle">
                            <thead class="table-success">
                                <tr>
                                    <th>#</th>
                                    <th>Placa</th>
                                    <th>Tipo Vehículo</th>
                                    <th>Transporte</th>
                                    <th>Plataforma</th>
                                    <th>Destino</th>
                                    <th>Usuario</th>
                                    <th>Contraseña</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                </div>
            </div>

        </div>
    </div>
</section>

<!-- ── Modal Crear / Editar GPS ────────────────────────────── -->
<div class="modal fade" id="modalGPS" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalGPSTitulo">Nuevo registro GPS</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="gps_id">

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="gps_placa" class="form-label">
                            Placa <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control text-uppercase" id="gps_placa"
                               maxlength="20" placeholder="Ej.: HND-1234">
                        <div class="invalid-feedback">La placa es obligatoria.</div>
                    </div>

                    <div class="col-md-4">
                        <label for="gps_tipo_vehiculo" class="form-label">Tipo de vehículo</label>
                        <input type="text" class="form-control" id="gps_tipo_vehiculo"
                               maxlength="50" placeholder="Ej.: Camión, Pickup">
                    </div>

                    <div class="col-md-4">
                        <label for="gps_id_transporte" class="form-label">Empresa de transporte</label>
                        <select class="form-select" id="gps_id_transporte">
                            <option value="">— Sin asignar —</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="gps_plataforma" class="form-label">URL de plataforma GPS</label>
                        <input type="url" class="form-control" id="gps_plataforma"
                               maxlength="500" placeholder="https://...">
                    </div>

                    <div class="col-md-6">
                        <label for="gps_destino" class="form-label">Destino / Ruta</label>
                        <input type="text" class="form-control" id="gps_destino"
                               maxlength="150" placeholder="Ej.: San Pedro Sula">
                    </div>

                    <div class="col-md-6">
                        <label for="gps_usuario" class="form-label">Usuario de plataforma</label>
                        <input type="text" class="form-control" id="gps_usuario"
                               maxlength="100" autocomplete="off">
                    </div>

                    <div class="col-md-6">
                        <label for="gps_contrasena" class="form-label">Contraseña</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="gps_contrasena"
                                   maxlength="150" autocomplete="new-password">
                            <button class="btn btn-outline-secondary btn-toggle-pwd-modal" type="button"
                                    tabindex="-1" title="Mostrar / ocultar">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-success" id="btnGuardarGPS">
                    <i class="bi bi-save me-1"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .is-invalid ~ .invalid-feedback { display: block !important; }
    .is-invalid { border-color: #dc3545 !important; }
    .pwd-masked { letter-spacing: .15em; color: #6c757d; font-size: .85rem; }
    .btn-reveal-pwd { padding: .15rem .4rem; font-size: .78rem; line-height: 1; }
</style>
