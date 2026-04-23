<div class="col-lg-12">
    <div class="card">
        <div class="card-body">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">Modelos</h5>
                <button class="btn btn-primary" id="btnNuevoModelo">
                    <i class="bi bi-plus-circle"></i> Nuevo Modelo
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover" id="tablaModelos">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nombre</th>
                            <th>Marca</th>
                            <th>Tipo</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<!-- MODAL MODELO -->
<div class="modal fade" id="modalModelo">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="tituloModalModelo">Nuevo Modelo</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form id="formModelo" novalidate>
                    <input type="hidden" id="id_modelo">

                    <div class="mb-3">
                        <label for="nombre_modelo" class="form-label">
                            Nombre <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="nombre_modelo" class="form-control"
                               placeholder="Ej: Galaxy A54" maxlength="100" autocomplete="off">
                    </div>

                    <div class="mb-3">
                        <label for="id_marca_modelo" class="form-label">
                            Marca <span class="text-danger">*</span>
                        </label>
                        <select id="id_marca_modelo" class="form-select">
                            <option value="">-- Seleccione --</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="id_tipo_modelo" class="form-label">Tipo</label>
                        <select id="id_tipo_modelo" class="form-select">
                            <option value="">-- Seleccione --</option>
                        </select>
                    </div>

                </form>
            </div>

            <div class="modal-footer">
                <button class="btn btn-primary" id="btnGuardarModelo">
                    <i class="bi bi-save"></i> Guardar
                </button>
                <button class="btn btn-success d-none" id="btnActualizarModelo">
                    <i class="bi bi-pencil-square"></i> Actualizar
                </button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            </div>

        </div>
    </div>
</div>

<style>
    .form-control.is-invalid,
    .form-select.is-invalid {
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
