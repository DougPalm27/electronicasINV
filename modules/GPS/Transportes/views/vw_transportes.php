<section class="section">
    <div class="row">
        <div class="col-12">

            <div class="card">
                <div class="card-body pt-3">

                    <div class="d-flex justify-content-between align-items-center mb-3 mt-2">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-truck me-2 text-success"></i>Empresas de Transporte
                        </h5>
                        <button class="btn btn-success btn-sm" id="btnNuevoTransporte">
                            <i class="bi bi-plus-circle me-1"></i> Nueva empresa
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table id="tblTransportes" class="table table-hover table-striped table-sm align-middle">
                            <thead class="table-success">
                                <tr>
                                    <th>#</th>
                                    <th>Empresa</th>
                                    <th>Contacto</th>
                                    <th>Teléfono</th>
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

<!-- ── Modal Crear / Editar Transporte ──────────────────────── -->
<div class="modal fade" id="modalTransporte" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTransporteTitulo">Nueva empresa de transporte</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="tr_id">

                <div class="mb-3">
                    <label for="tr_nombre" class="form-label">
                        Nombre <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="tr_nombre" maxlength="150"
                           placeholder="Ej.: Transportes Honduras S.A.">
                    <div class="invalid-feedback">El nombre es obligatorio.</div>
                </div>

                <div class="mb-3">
                    <label for="tr_contacto" class="form-label">Contacto</label>
                    <input type="text" class="form-control" id="tr_contacto" maxlength="100"
                           placeholder="Nombre del contacto">
                </div>

                <div class="mb-3">
                    <label for="tr_telefono" class="form-label">Teléfono</label>
                    <input type="text" class="form-control" id="tr_telefono" maxlength="20"
                           placeholder="Ej.: +504 9999-9999">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-success" id="btnGuardarTransporte">
                    <i class="bi bi-save me-1"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .is-invalid ~ .invalid-feedback { display: block !important; }
    .is-invalid { border-color: #dc3545 !important; }
</style>
