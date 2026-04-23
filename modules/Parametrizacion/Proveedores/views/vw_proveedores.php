<div class="col-lg-12">
    <div class="card">
        <div class="card-body">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">Proveedores</h5>
                <button class="btn btn-primary" id="btnNuevoProveedor">
                    <i class="bi bi-plus-circle"></i> Nuevo Proveedor
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover" id="tablaProveedores">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nombre</th>
                            <th>Teléfono</th>
                            <th>Correo</th>
                            <th>Repuestos</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<!-- MODAL PROVEEDOR -->
<div class="modal fade" id="modalProveedor">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="tituloModalProveedor">Nuevo Proveedor</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form id="formProveedor" novalidate>
                    <input type="hidden" id="id_proveedor">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nombre_proveedor" class="form-label">
                                Nombre <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="nombre_proveedor" class="form-control"
                                   placeholder="Ej: Distribuidora Tecnológica" maxlength="150" autocomplete="off">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="telefono_proveedor" class="form-label">Teléfono</label>
                            <input type="text" id="telefono_proveedor" class="form-control"
                                   placeholder="Ej: 3001234567" maxlength="30">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="correo_proveedor" class="form-label">Correo electrónico</label>
                            <input type="email" id="correo_proveedor" class="form-control"
                                   placeholder="Ej: ventas@proveedor.com" maxlength="100">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="direccion_proveedor" class="form-label">Dirección</label>
                            <input type="text" id="direccion_proveedor" class="form-control"
                                   placeholder="Ej: Calle 10 # 5-20, Bogotá" maxlength="255">
                        </div>
                    </div>

                </form>
            </div>

            <div class="modal-footer">
                <button class="btn btn-primary" id="btnGuardarProveedor">
                    <i class="bi bi-save"></i> Guardar
                </button>
                <button class="btn btn-success d-none" id="btnActualizarProveedor">
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
