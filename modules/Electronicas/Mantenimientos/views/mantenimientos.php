<!-- ══════════════════════════════════════════════════════════
     CABECERA + TABLA PRINCIPAL
══════════════════════════════════════════════════════════ -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">Mantenimientos</h5>
                    <small class="text-muted">Historial y control de máquinas</small>
                </div>
                <button class="btn btn-primary" id="btnNuevoMantenimiento">
                    <i class="bi bi-plus-circle me-1"></i> Nuevo mantenimiento
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
<div class="modal fade" id="modalMantenimiento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-tools me-2"></i>Registrar Mantenimiento
                </h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form id="formMantenimiento" novalidate>

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label">Máquina <span class="text-danger">*</span></label>
                            <select id="id_maquina" class="form-select">
                                <option value="-1">Seleccione</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select id="id_tipo" class="form-select">
                                <option value="-1">Seleccione</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Técnico</label>
                            <select id="id_tecnico" class="form-select">
                                <option value="-1">Seleccione</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Fecha <span class="text-danger">*</span></label>
                            <input type="date" id="fecha_mantenimiento" class="form-control">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Próximo mantenimiento</label>
                            <input type="date" id="proximo_mantenimiento" class="form-control">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Notas generales</label>
                            <textarea id="descripcion" class="form-control" rows="2"
                                      placeholder="Observaciones adicionales (opcional)"></textarea>
                        </div>

                    </div>

                    <!-- ── Tareas realizadas ───────────────────────── -->
                    <hr class="my-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">
                            <i class="bi bi-list-check me-1 text-primary"></i>
                            Tareas realizadas <span class="text-danger">*</span>
                        </h6>
                        <small class="text-muted">Agrega una o más tareas</small>
                    </div>

                    <!-- Input para agregar tarea -->
                    <div class="input-group mb-2">
                        <input type="text" id="inp_nueva_tarea" class="form-control"
                               placeholder="Ej: Limpieza de filtros internos" maxlength="500">
                        <button type="button" class="btn btn-outline-primary" id="btnAgregarTarea">
                            <i class="bi bi-plus-lg"></i> Agregar
                        </button>
                    </div>

                    <!-- Lista de tareas -->
                    <ul class="list-group" id="listaTareas">
                        <!-- Las tareas se renderizan aquí -->
                    </ul>
                    <div id="emptyTareas" class="text-center text-muted py-3" style="display:none">
                        <i class="bi bi-clipboard2 opacity-25" style="font-size:1.5rem"></i>
                        <p class="small mt-1 mb-0">Sin tareas agregadas</p>
                    </div>

                    <div class="alert alert-info py-2 mt-3 mb-0" style="font-size:.85rem">
                        <i class="bi bi-info-circle me-1"></i>
                        Para incluir repuestos en un mantenimiento, crea una
                        <a href="?module=solicitudes" class="alert-link">Solicitud de Repuestos</a>.
                    </div>

                </form>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary" id="btnGuardarMantenimiento">
                    <i class="bi bi-save me-1"></i> Guardar
                </button>
            </div>

        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     MODAL — DETALLE DE UN MANTENIMIENTO
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header bg-info bg-opacity-10">
                <h5 class="modal-title" id="modalDetalleLabel">
                    <i class="bi bi-tools me-2"></i>Detalle del mantenimiento
                </h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body" id="detalleContenido">
                <div class="text-center py-4">
                    <span class="spinner-border spinner-border-sm me-2"></span> Cargando...
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>

        </div>
    </div>
</div>

<style>
    #listaTareas .list-group-item {
        display: flex;
        align-items: center;
        gap: .5rem;
        padding: .45rem .75rem;
    }
    #listaTareas .tarea-numero {
        font-size: .75rem;
        font-weight: 600;
        color: #6c757d;
        min-width: 1.4rem;
    }
    #listaTareas .tarea-texto {
        flex: 1;
        font-size: .9rem;
    }
    #listaTareas .btn-quitar-tarea {
        padding: .1rem .35rem;
        line-height: 1;
    }
</style>
