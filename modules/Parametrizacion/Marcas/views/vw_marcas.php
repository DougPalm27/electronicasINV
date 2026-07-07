<div class="row mb-3">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">Marcas</h5>
                    <small class="text-muted">Catálogo de marcas de equipos y repuestos</small>
                </div>
                <button class="btn btn-primary" id="btnNuevaMarca">
                    <i class="bi bi-plus-circle"></i> Nueva Marca
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <table class="table table-hover w-100" id="tablaMarcas">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Modelos</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- MODAL MARCA -->
<div class="modal fade" id="modalMarca">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="tituloModalMarca">Nueva Marca</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form id="formMarca" novalidate>
                    <input type="hidden" id="id_marca">

                    <div class="mb-3">
                        <label for="nombre_marca" class="form-label">
                            Nombre <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="nombre_marca" class="form-control"
                               placeholder="Ej: Samsung" maxlength="100" autocomplete="off">
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button class="btn btn-primary" id="btnGuardarMarca">
                    <i class="bi bi-save"></i> Guardar
                </button>
                <button class="btn btn-success d-none" id="btnActualizarMarca">
                    <i class="bi bi-pencil-square"></i> Actualizar
                </button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            </div>

        </div>
    </div>
</div>
