<section class="section">
    <div class="row">
        <div class="col-12">

            <div class="card">
                <div class="card-body pt-3">

                    <div class="d-flex justify-content-between align-items-center mb-3 mt-2">
                        <h5 class="card-title mb-0">Gestión de Usuarios</h5>
                        <button class="btn btn-primary btn-sm" id="btnNuevoUsuario">
                            <i class="bi bi-person-plus me-1"></i> Nuevo usuario
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table id="tblUsuarios" class="table table-hover table-striped table-sm align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>#</th>
                                    <th>Usuario</th>
                                    <th>Nombre</th>
                                    <th class="text-center">Estado</th>
                                    <th>Registro</th>
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

<!-- ── Modal Crear / Editar ──────────────────────────────── -->
<div class="modal fade" id="modalUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalUsuarioTitulo">Nuevo usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="frmUsuario" novalidate>
                    <input type="hidden" id="u_id">

                    <div class="mb-3">
                        <label for="u_nombre" class="form-label">Nombre completo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="u_nombre" maxlength="150" required>
                        <div class="invalid-feedback">Ingresa el nombre completo.</div>
                    </div>

                    <div id="bloque_username">
                        <div class="mb-3">
                            <label for="u_username" class="form-label">Nombre de usuario <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="u_username" maxlength="50" required>
                            <div class="invalid-feedback">Ingresa el nombre de usuario.</div>
                        </div>

                        <div class="mb-3">
                            <label for="u_password" class="form-label">Contraseña <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="u_password" minlength="6" required>
                            <div class="invalid-feedback">Mínimo 6 caracteres.</div>
                        </div>

                        <div class="mb-3">
                            <label for="u_confirmar" class="form-label">Confirmar contraseña <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="u_confirmar" minlength="6" required>
                            <div class="invalid-feedback">Las contraseñas no coinciden.</div>
                        </div>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary"   id="btnGuardarUsuario">
                    <i class="bi bi-save me-1"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal Reset Contraseña (admin) ───────────────────── -->
<div class="modal fade" id="modalReset" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Restablecer contraseña</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Usuario: <strong id="resetNombreUsuario"></strong>
                </p>
                <input type="hidden" id="reset_id">
                <div class="mb-3">
                    <label class="form-label">Nueva contraseña <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="reset_password" minlength="6"
                           placeholder="Mínimo 6 caracteres">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-warning"   id="btnConfirmarReset">
                    <i class="bi bi-key me-1"></i> Restablecer
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .is-invalid ~ .invalid-feedback { display: block !important; }
    .is-invalid { border-color: #dc3545 !important; }
</style>
