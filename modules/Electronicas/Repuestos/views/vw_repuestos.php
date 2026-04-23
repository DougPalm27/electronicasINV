<div class="col-lg-12">
    <div class="card">
        <div class="card-body">

            <h5 class="card-title">Inventario de Repuestos</h5>

            <div class="d-flex justify-content-between mb-3">
                <button id="btnNuevoRepuesto" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Nuevo Repuesto
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-striped" id="tablaRepuestos">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>N° Parte</th>
                            <th>Proveedor</th>
                            <th>Stock</th>
                            <th>Costo</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<!-- ============================= -->
<!-- MODAL REPUESTO -->
<!-- ============================= -->

<div class="modal fade" id="modalRepuesto">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Gestión de Repuesto</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form id="formRepuesto" novalidate>

                    <input type="hidden" id="id_repuesto">

                    <!-- INFORMACIÓN GENERAL -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nombre" class="form-label">Nombre del repuesto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombre" placeholder="Ej: Fuente de poder">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="numero_parte" class="form-label">Número de parte</label>
                            <input type="text" class="form-control" id="numero_parte" placeholder="Ej: PSU-450W">
                        </div>
                    </div>

                    <!-- CLASIFICACIÓN -->
                    <div class="border rounded p-3 mb-3">
                        <h6 class="text-muted mb-3">Clasificación</h6>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="id_tipo" class="form-label">Tipo</label>
                                <select class="form-select select2" id="id_tipo"></select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="id_marca" class="form-label">Marca</label>
                                <select class="form-select select2" id="id_marca"></select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="id_modelo" class="form-label">Modelo</label>
                                <select class="form-select select2" id="id_modelo"></select>
                            </div>
                        </div>
                    </div>

                    <!-- CONFIGURACIÓN -->
                    <div class="border rounded p-3 mb-3">
                        <h6 class="text-muted mb-3">Configuración</h6>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="id_proveedor" class="form-label">Proveedor <span class="text-danger">*</span></label>
                                <select class="form-select select2" id="id_proveedor"></select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="maneja_serie" class="form-label">Tipo de control</label>
                                <select class="form-select" id="maneja_serie">
                                    <option value="0">Por cantidad</option>
                                    <option value="1">Por serie</option>
                                </select>
                            </div>
                        </div>

                        <!-- BLOQUE STOCK -->
                        <div class="row" id="bloqueStock">
                            <div class="col-md-6 mb-3">
                                <label for="stock_minimo" class="form-label">Stock mínimo</label>
                                <input type="number" class="form-control" id="stock_minimo" placeholder="Ej: 5" min="0">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="costo" class="form-label">Costo promedio</label>
                                <input type="number" step="0.01" class="form-control" id="costo" placeholder="0.00" min="0">
                            </div>
                        </div>

                        <!-- BLOQUE SERIE -->
                        <div id="bloqueSerie" style="display:none;">
                            <div class="alert alert-info mb-0">
                                <i class="bi bi-info-circle"></i>
                                Este repuesto se manejará por <strong>número de serie individual</strong>.
                                El control de inventario será por unidades únicas.
                            </div>
                        </div>

                    </div>

                    <!-- COMENTARIOS -->
                    <div class="mb-3">
                        <label for="comentarios" class="form-label">Comentarios</label>
                        <textarea class="form-control" id="comentarios" rows="2"></textarea>
                    </div>

                </form>
            </div>

            <div class="modal-footer">
                <button class="btn btn-primary" id="btnGuardarRepuesto">
                    <i class="bi bi-save"></i> Guardar
                </button>
                <button class="btn btn-success d-none" id="btnEditarRepuesto">
                    <i class="bi bi-pencil-square"></i> Actualizar
                </button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">
                    Cancelar
                </button>
            </div>

        </div>
    </div>
</div>

<!-- ============================= -->
<!-- MODAL ENTRADA -->
<!-- ============================= -->

<div class="modal fade" id="modalEntrada">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5>Entrada de Inventario</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <input type="hidden" id="id_repuesto_mov">
                <input type="hidden" id="maneja_serie_mov">

                <!-- MODO STOCK -->
                <div id="entradaStock">
                    <div class="mb-3">
                        <label for="cantidad_mov" class="form-label">Cantidad <span class="text-danger">*</span></label>
                        <input type="number" id="cantidad_mov" class="form-control" min="1" placeholder="Ej: 10">
                    </div>
                    <div class="mb-3">
                        <label for="costo_mov" class="form-label">Costo unitario <span class="text-danger">*</span></label>
                        <input type="number" id="costo_mov" class="form-control" step="0.01" min="0" placeholder="0.00">
                    </div>
                </div>

                <!-- MODO SERIE -->
                <div id="entradaSerie" style="display:none;">
                    <div class="mb-3">
                        <label for="series_input" class="form-label">Series (una por línea) <span class="text-danger">*</span></label>
                        <textarea id="series_input" class="form-control" rows="5"
                            placeholder="SERIE001&#10;SERIE002&#10;SERIE003"></textarea>
                        <div class="form-text text-muted">Cada línea representa un equipo individual.</div>
                    </div>
                </div>

            </div>

            <div class="modal-footer">
                <button class="btn btn-success" onclick="guardarEntrada()">
                    <i class="bi bi-save"></i> Guardar
                </button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            </div>

        </div>
    </div>
</div>

<!-- ============================= -->
<!-- MODAL SALIDA -->
<!-- ============================= -->

<div class="modal fade" id="modalSalida">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Salida de Repuesto</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <input type="hidden" id="id_repuesto_salida">
                <input type="hidden" id="maneja_serie_salida">

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="id_maquina_salida" class="form-label">ID Máquina destino <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="id_maquina_salida" placeholder="Ej: 3">
                    </div>
                    <div class="col-md-6">
                        <label for="referencia_salida" class="form-label">Referencia</label>
                        <input type="text" class="form-control" id="referencia_salida" value="MANTENIMIENTO">
                    </div>
                </div>

                <div id="bloqueSalidaCantidad">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cantidad_salida" class="form-label">Cantidad <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="cantidad_salida" min="1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="costo_salida" class="form-label">Costo unitario</label>
                            <input type="number" class="form-control" id="costo_salida" step="0.01" min="0">
                        </div>
                    </div>
                </div>

                <div id="bloqueSalidaSerie" style="display:none;">
                    <label for="series_salida" class="form-label">Series disponibles <span class="text-danger">*</span></label>
                    <select id="series_salida" class="form-select select2" multiple></select>
                    <div class="form-text text-muted">Selecciona una o varias series disponibles.</div>
                </div>

            </div>

            <div class="modal-footer">
                <button class="btn btn-primary" onclick="guardarSalida()">
                    <i class="bi bi-arrow-up-circle"></i> Registrar salida
                </button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            </div>

        </div>
    </div>
</div>

<!-- ============================= -->
<!-- MODAL KARDEX -->
<!-- ============================= -->

<div class="modal fade" id="modalKardex">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5>Kardex del Repuesto</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <table class="table table-sm table-striped" id="tablaKardex">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Cantidad</th>
                            <th>Stock Antes</th>
                            <th>Stock Después</th>
                            <th>Costo</th>
                            <th>Referencia</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>

        </div>
    </div>
</div>

<!-- ============================= -->
<!-- MODAL DETALLE (SERIE) -->
<!-- ============================= -->

<div class="modal fade" id="modalDetalle">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5>Detalle por Serie</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <table class="table table-sm table-striped" id="tablaDetalle">
                    <thead>
                        <tr>
                            <th>Serie</th>
                            <th>Estado</th>
                            <th>Máquina</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>

        </div>
    </div>
</div>

<!-- ============================= -->
<!-- MODAL EDITAR DETALLE -->
<!-- ============================= -->

<div class="modal fade" id="modalEditarDetalle">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5>Editar Detalle</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <input type="hidden" id="id_detalle_repuesto">

                <div class="mb-3">
                    <label for="serie_detalle" class="form-label">Serie <span class="text-danger">*</span></label>
                    <input type="text" id="serie_detalle" class="form-control">
                </div>

                <div class="mb-3">
                    <label for="estado_detalle" class="form-label">Estado</label>
                    <select id="estado_detalle" class="form-select">
                        <!-- cargado dinámicamente desde EstadoRepuestos -->
                    </select>
                </div>

                <div class="mb-3">
                    <label for="maquina_detalle" class="form-label">ID Máquina</label>
                    <input type="number" id="maquina_detalle" class="form-control">
                </div>

            </div>

            <div class="modal-footer">
                <button class="btn btn-success" onclick="guardarDetalle()">
                    <i class="bi bi-save"></i> Guardar
                </button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            </div>

        </div>
    </div>
</div>

<style>
    .select2-container--bootstrap-5 .select2-selection {
        height: 38px;
        display: flex;
        align-items: center;
        padding-left: 10px;
    }

    .select2-container {
        width: 100% !important;
    }

    .border h6 {
        font-weight: 600;
    }

    /* Validación visual — forzamos sobre los estilos de NiceAdmin */
    .form-control.is-invalid,
    .form-select.is-invalid {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.2) !important;
    }

    .form-control.is-invalid:focus,
    .form-select.is-invalid:focus {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.35) !important;
    }

    /* Select2 inválido */
    .select2-container .select2-selection.border.border-danger {
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
