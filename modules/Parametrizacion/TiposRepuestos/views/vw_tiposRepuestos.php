<div class="row mb-3">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">Tipos de Repuesto</h5>
                    <small class="text-muted">Clasificación de repuestos por categoría</small>
                </div>
                <button class="btn btn-primary" id="btnNuevoTipo">
                    <i class="bi bi-plus-circle"></i> Nuevo Tipo
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <table class="table table-hover w-100" id="tablaTipos">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Repuestos</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- MODAL TIPO REPUESTO -->
<div class="modal fade" id="modalTipo">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="tituloModalTipo">Nuevo Tipo de Repuesto</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form id="formTipo" novalidate>
                    <input type="hidden" id="id_tipo_repuesto">

                    <div class="mb-3">
                        <label for="nombre_tipo" class="form-label">
                            Nombre <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="nombre_tipo" class="form-control"
                               placeholder="Ej: Electrónico, Mecánico" maxlength="100" autocomplete="off">
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button class="btn btn-primary" id="btnGuardarTipo">
                    <i class="bi bi-save"></i> Guardar
                </button>
                <button class="btn btn-success d-none" id="btnActualizarTipo">
                    <i class="bi bi-pencil-square"></i> Actualizar
                </button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            </div>

        </div>
    </div>
</div>

<style>
    .form-control.is-invalid {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.2) !important;
    }
    .invalid-feedback {
        display: block !important;
        color: #dc3545;
        font-size: 0.8rem;
        margin-top: 4px;
    }
</style>
