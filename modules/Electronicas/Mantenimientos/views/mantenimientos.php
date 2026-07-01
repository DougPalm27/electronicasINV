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
                <?php if (!empty($_SESSION['puede_ejecutar_mantenimiento'])): ?>
                <button class="btn btn-primary" id="btnNuevoMantenimiento">
                    <i class="bi bi-plus-circle me-1"></i> Nuevo mantenimiento
                </button>
                <?php endif; ?>
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
    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-tools me-2"></i><span id="modalTitulo">Registrar Mantenimiento</span>
                </h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form id="formMantenimiento" novalidate>
                    <input type="hidden" id="id_mantenimiento_edit">

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label">
                                Máquina <span class="text-danger">*</span>
                                <i class="bi bi-info-circle text-muted ms-1 info-tip"
                                   data-bs-toggle="tooltip"
                                   title="Selecciona el equipo al que se le realizó la intervención."></i>
                            </label>
                            <select id="id_maquina" class="form-select">
                                <option value="-1">Seleccione</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                Tipo <span class="text-danger">*</span>
                                <i class="bi bi-info-circle text-muted ms-1 info-tip"
                                   data-bs-toggle="tooltip"
                                   title="Indica la naturaleza del mantenimiento. Selecciona uno para ver su descripción."></i>
                            </label>
                            <select id="id_tipo" class="form-select">
                                <option value="-1">Seleccione</option>
                            </select>
                            <div id="desc_tipo" class="form-text text-info mt-1" style="display:none">
                                <i class="bi bi-lightbulb me-1"></i><span id="desc_tipo_texto"></span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                Técnico
                                <i class="bi bi-info-circle text-muted ms-1 info-tip"
                                   data-bs-toggle="tooltip"
                                   title="Persona que realizó la intervención. Si eres técnico, ya apareces seleccionado."></i>
                            </label>
                            <select id="id_tecnico" class="form-select">
                                <option value="-1">Seleccione</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">
                                Fecha <span class="text-danger">*</span>
                                <i class="bi bi-info-circle text-muted ms-1 info-tip"
                                   data-bs-toggle="tooltip"
                                   title="Día en que se realizó el mantenimiento."></i>
                            </label>
                            <input type="date" id="fecha_mantenimiento" class="form-control">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">
                                Próximo mantenimiento
                                <i class="bi bi-info-circle text-muted ms-1 info-tip"
                                   data-bs-toggle="tooltip"
                                   title="Fecha estimada en que se debería realizar la próxima intervención a esta máquina. Es opcional."></i>
                            </label>
                            <input type="date" id="proximo_mantenimiento" class="form-control">
                        </div>

                        <div class="col-12">
                            <label class="form-label">
                                Notas generales
                                <i class="bi bi-info-circle text-muted ms-1 info-tip"
                                   data-bs-toggle="tooltip"
                                   title="Observaciones adicionales sobre el mantenimiento: condiciones encontradas, anomalías, recomendaciones, etc. Es opcional."></i>
                            </label>
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
                            <i class="bi bi-info-circle text-muted ms-1 info-tip"
                               data-bs-toggle="tooltip"
                               data-bs-html="true"
                               title="Lista de trabajos específicos que se hicieron durante esta intervención.<br>Ejemplos: <em>Limpieza de filtros</em>, <em>Calibración de sensor</em>, <em>Cambio de correa</em>.<br>Se requiere al menos una tarea."></i>
                        </h6>
                        <small class="text-muted">Agrega una o más tareas</small>
                    </div>

                    <!-- Select para agregar tarea desde catálogo Satake -->
                    <div class="input-group mb-2">
                        <select id="sel_nueva_tarea" class="form-select">
                            <option value="">— Cargando tareas… —</option>
                        </select>
                        <button type="button" class="btn btn-outline-primary" id="btnAgregarTarea">
                            <i class="bi bi-plus-lg"></i> Agregar
                        </button>
                    </div>

                    <!-- Lista de tareas -->
                    <ul class="list-group" id="listaTareas"></ul>
                    <div id="emptyTareas" class="text-center text-muted py-3" style="display:none">
                        <i class="bi bi-clipboard2 opacity-25" style="font-size:1.5rem"></i>
                        <p class="small mt-1 mb-0">Sin tareas agregadas</p>
                    </div>

                    <!-- ── Repuestos instalados ───────────────────────── -->
                    <hr class="my-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">
                            <i class="bi bi-box-seam me-1 text-success"></i>
                            Repuestos instalados
                            <span class="text-muted fw-normal" style="font-size:.8rem">(opcional)</span>
                            <i class="bi bi-info-circle text-muted ms-1 info-tip"
                               data-bs-toggle="tooltip"
                               data-bs-html="true"
                               title="Piezas nuevas que se colocaron en la máquina durante este mantenimiento.<br>Al guardar, <strong>se descuentan automáticamente del inventario</strong>."></i>
                        </h6>
                        <button type="button" class="btn btn-sm btn-outline-success" id="btnAgregarRepuesto">
                            <i class="bi bi-plus-lg"></i> Agregar
                        </button>
                    </div>
                    <div id="listaRepuestos">
                        <p class="text-muted small fst-italic mb-0" id="emptyRepuestos">
                            Sin repuestos agregados.
                        </p>
                    </div>

                    <!-- ── Piezas retiradas ───────────────────────────── -->
                    <hr class="my-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">
                            <i class="bi bi-arrow-return-left me-1 text-warning"></i>
                            Piezas retiradas
                            <span class="text-muted fw-normal" style="font-size:.8rem">(opcional)</span>
                            <i class="bi bi-info-circle text-muted ms-1 info-tip"
                               data-bs-toggle="tooltip"
                               data-bs-html="true"
                               title="Componentes extraídos de la máquina en este mantenimiento.<br><strong>Devolución:</strong> regresa la pieza al inventario disponible.<br><strong>Baja:</strong> la pieza se descarta definitivamente."></i>
                        </h6>
                        <button type="button" class="btn btn-sm btn-outline-warning" id="btnAgregarRetiro" disabled>
                            <i class="bi bi-plus-lg"></i> Agregar
                        </button>
                    </div>
                    <div id="listaRetiros">
                        <p class="text-muted small fst-italic mb-0" id="emptyRetiros">
                            Selecciona una máquina para habilitar esta sección.
                        </p>
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
