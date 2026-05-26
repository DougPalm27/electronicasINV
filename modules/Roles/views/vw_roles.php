<div class="row mb-3">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">
                        <i class="bi bi-shield-lock me-2 text-primary"></i>Roles y Permisos
                    </h5>
                    <small class="text-muted">Define qué módulos y acciones puede realizar cada rol</small>
                </div>
                <button class="btn btn-primary" id="btnNuevoRol">
                    <i class="bi bi-plus-circle me-1"></i> Nuevo rol
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <table id="tblRoles" class="table table-hover w-100">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Rol</th>
                    <th>Descripción</th>
                    <th class="text-center">Módulos</th>
                    <th class="text-center">Ejec. Mantenimiento</th>
                    <th class="text-center">Estado</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- ── Modal Crear / Editar Rol ─────────────────────────── -->
<div class="modal fade" id="modalRol" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalRolTitulo">Nuevo rol</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="rol_id">

                <div class="mb-3">
                    <label for="rol_nombre" class="form-label">
                        Nombre del rol <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="rol_nombre" maxlength="80"
                           placeholder="Ej.: Supervisor">
                    <div class="invalid-feedback">El nombre es obligatorio.</div>
                </div>

                <div class="mb-3">
                    <label for="rol_descripcion" class="form-label">Descripción</label>
                    <textarea class="form-control" id="rol_descripcion" rows="2" maxlength="255"
                              placeholder="Descripción breve del rol"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary" id="btnGuardarRol">
                    <i class="bi bi-save me-1"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal Gestionar Permisos ─────────────────────────── -->
<div class="modal fade" id="modalPermisos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-shield-check me-2"></i>
                    Permisos del rol: <strong id="permisosRolNombre"></strong>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Selecciona los módulos que tendrá acceso este rol.
                </p>
                <input type="hidden" id="permisos_id_rol">
                <div id="contenedorPermisos">
                    <div class="text-center py-4">
                        <span class="spinner-border spinner-border-sm me-2"></span> Cargando módulos...
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary" id="btnGuardarPermisos">
                    <i class="bi bi-shield-check me-1"></i> Guardar permisos
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .grupo-modulos { background: #f8f9fa; border-radius: 8px; padding: 12px 16px; margin-bottom: 14px; }
    .grupo-modulos .grupo-titulo { font-weight: 600; color: #4154f1; margin-bottom: 8px; font-size: .85rem; text-transform: uppercase; letter-spacing: .5px; }
    .grupo-modulos .form-check { padding: 3px 0 3px 1.5rem; }
    .grupo-modulos .form-check-input { cursor: pointer; }
    .modulo-item { display: flex; align-items: center; gap: 6px; }
    .modulo-item i { color: #6c757d; font-size: .9rem; }
    .is-invalid ~ .invalid-feedback { display: block !important; }
    .is-invalid { border-color: #dc3545 !important; }
</style>
