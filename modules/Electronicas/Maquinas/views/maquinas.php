
<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="card-title mb-0">Máquinas</h5>
                <small class="text-muted">Registro general del área de electrónicas</small>
            </div>
            <button class="btn btn-primary" id="btnNuevaMaquina" data-bs-toggle="modal" data-bs-target="#modalMaquina">
                <i class="bi bi-plus-circle"></i> Nueva Máquina
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle" id="tablaMaquinas" width="100%">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Serie</th>
                        <th>Año</th>
                        <th>Ubicación</th>
                        <th>Costo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalMaquina" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Registrar Máquina</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form id="formMaquina" class="row g-3">
                    <input type="hidden" id="id_maquina">

                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="nombre" placeholder="Nombre">
                            <label for="nombre">Nombre</label>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="serie" placeholder="Serie">
                            <label for="serie">Serie</label>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-floating">
                            <select class="form-select" id="id_marca">
                                <option value="-1">Seleccione una marca</option>
                            </select>
                            <label for="id_marca">Marca</label>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-floating">
                            <select class="form-select" id="id_modelo">
                                <option value="-1">Seleccione un modelo</option>
                            </select>
                            <label for="id_modelo">Modelo</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="number" class="form-control" id="costo" placeholder="Costo" step="0.01">
                            <label for="costo">Costo</label>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="number" class="form-control" id="anio" placeholder="Año" min="1900" max="2100">
                            <label for="anio">Año de fabricación</label>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-floating">
                            <select class="form-select" id="ubicacion">
                                <option value="-1">Seleccione una ubicación</option>
                                <option value="Planta 1">Planta 1</option>
                                <option value="Planta 2">Planta 2</option>
                                <option value="Planta 3 (HOSCO)">Planta 3 (HOSCO)</option>
                            </select>
                            <label for="ubicacion">Ubicación</label>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="form-floating">
                            <select class="form-select" id="id_estado">
                                <option value="-1">Seleccione un estado</option>
                            </select>
                            <label for="id_estado">Estado</label>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="form-floating">
                            <textarea class="form-control" id="comentarios" style="height: 100px;" placeholder="Comentarios"></textarea>
                            <label for="comentarios">Comentarios</label>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="btnGuardarMaquina">Guardar</button>
                <button type="button" class="btn btn-success" id="btnEditarMaquina" style="display:none;">Editar</button>
                <button type="button" class="btn btn-secondary" onclick="limpiarModalMaquina()">Limpiar</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: Historial de mantenimientos ─────────────────── -->
<div class="modal fade" id="modalHistorialMaquina" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-clock-history me-2"></i>
                    Historial — <span id="histMaquinaNombre" class="fw-bold"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="contenidoHistorial" style="min-height:200px">
                <div class="text-center py-5">
                    <span class="spinner-border text-primary"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" id="btnImprimirHistorial" onclick="imprimirHistorial()">
                    <i class="bi bi-printer me-1"></i>Imprimir reporte
                </button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRepuestosMaquina">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5>Repuestos de la máquina</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <table class="table table-sm" id="tablaRepuestosMaquina">
                    <thead>
                        <tr>
                            <th>Repuesto</th>
                            <th>Estado</th>
                            <th>Costo</th>
                            <th>Último mantenimiento</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

        </div>
    </div>
</div>