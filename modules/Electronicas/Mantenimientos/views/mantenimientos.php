<!-- ══════════════════════════════════════════════════════════
     CABECERA + TABLA PRINCIPAL
     ══════════════════════════════════════════════════════════ -->
<div class="row mb-3">
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">Mantenimientos</h5>
                    <small class="text-muted">Historial y control de máquinas</small>
                </div>
                <button class="btn btn-primary" id="btnNuevoMantenimiento">
                    <i class="bi bi-plus-circle"></i> Nuevo mantenimiento
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <table id="tablaMantenimientos" class="table table-hover w-100"></table>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     MODAL — REGISTRAR MANTENIMIENTO
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalMantenimiento">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Registrar Mantenimiento</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form id="formMantenimiento">
                    <input type="hidden" id="id_mantenimiento">

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label">Máquina</label>
                            <select id="id_maquina" class="form-select"></select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Tipo</label>
                            <select id="id_tipo" class="form-select"></select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Técnico</label>
                            <select id="id_tecnico" class="form-select"></select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Fecha</label>
                            <input type="date" id="fecha_mantenimiento" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Próximo mantenimiento</label>
                            <input type="date" id="proximo_mantenimiento" class="form-control">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Descripción</label>
                            <textarea id="descripcion" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="col-12"><hr></div>
                        <h6 class="mb-0">Repuestos usados</h6>

                        <!-- Fila agregar repuesto -->
                        <div class="col-12">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-5">
                                    <label class="form-label">Repuesto</label>
                                    <select id="select_repuesto" class="form-select"></select>
                                </div>

                                <div class="col-md-2" id="bloqueCantidadRepuesto">
                                    <label class="form-label">Cant.</label>
                                    <input type="number" id="cantidad_repuesto" class="form-control"
                                           placeholder="Cant." value="1" min="1">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Costo unit.</label>
                                    <input type="number" id="costo_unitario" class="form-control"
                                           placeholder="0.00" step="0.01" min="0">
                                </div>

                                <div class="col-md-2">
                                    <button type="button" class="btn btn-success w-100" id="btnAgregarRepuesto">
                                        <i class="bi bi-plus-lg"></i> Agregar
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Tabla detalle repuestos -->
                        <div class="col-12">
                            <table class="table table-sm table-bordered" id="tablaDetalleRepuestos">
                                <thead class="table-light">
                                    <tr>
                                        <th>Repuesto</th>
                                        <th>Uso</th>
                                        <th>Costo unit.</th>
                                        <th style="width:60px"></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary" id="btnGuardarMantenimiento">
                    <i class="bi bi-save"></i> Guardar
                </button>
            </div>

        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     MODAL — DETALLE DE REPUESTOS DE UN MANTENIMIENTO
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalDetalle">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header bg-info bg-opacity-10">
                <h5 class="modal-title" id="modalDetalleLabel">
                    <i class="bi bi-tools me-2"></i>Detalle de repuestos
                </h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-0">
                <table class="table table-hover table-bordered mb-0" id="tablaDetalleModal">
                    <thead class="table-light">
                        <tr>
                            <th>Repuesto</th>
                            <th class="text-center" style="width:80px">Cant.</th>
                            <th class="text-end"    style="width:110px">Costo unit.</th>
                            <th class="text-end"    style="width:110px">Subtotal</th>
                            <th class="text-center" style="width:130px">N° Serie</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="5" class="text-center text-muted">—</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>

        </div>
    </div>
</div>
