<section class="section">
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-body pt-3">

    <div class="d-flex justify-content-between align-items-center mb-3 mt-2">
        <h5 class="card-title mb-0">
            <i class="bi bi-clipboard-check me-2 text-primary"></i>Solicitudes de Repuestos
        </h5>
        <button class="btn btn-primary btn-sm" id="btnNuevaSolicitud">
            <i class="bi bi-plus-circle me-1"></i> Nueva solicitud
        </button>
    </div>

    <!-- Filtro de estado -->
    <div class="d-flex gap-2 mb-3 flex-wrap" id="filtrosEstado">
        <button class="btn btn-sm btn-outline-secondary filtro-estado active" data-estado="">Todas</button>
        <button class="btn btn-sm btn-outline-warning  filtro-estado" data-estado="Pendiente">Pendientes</button>
        <button class="btn btn-sm btn-outline-success  filtro-estado" data-estado="Aprobado">Aprobadas</button>
        <button class="btn btn-sm btn-outline-danger   filtro-estado" data-estado="Rechazado">Rechazadas</button>
        <button class="btn btn-sm btn-outline-secondary filtro-estado" data-estado="Cancelado">Canceladas</button>
    </div>

    <div class="table-responsive">
        <table id="tblSolicitudes" class="table table-hover table-striped table-sm align-middle">
            <thead class="table-primary">
                <tr>
                    <th>#</th>
                    <th>Descripción</th>
                    <th>Tipo</th>
                    <th>Solicitante</th>
                    <th class="text-center">Máquinas</th>
                    <th>Fecha programada</th>
                    <th>Creada</th>
                    <th class="text-center">Estado</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

</div>
</div>
</div>
</div>
</section>

<!-- ══════════════════════════════════════════════════════════
     MODAL — NUEVA SOLICITUD
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalNuevaSolicitud" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-clipboard-plus me-2"></i>Nueva Solicitud de Repuestos</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <!-- Datos generales -->
                <div class="row g-3 mb-4">
                    <div class="col-md-5">
                        <label class="form-label">Descripción general <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="sol_descripcion" rows="2" maxlength="600"
                                  placeholder="¿Qué trabajo se realizará?"></textarea>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipo de mantenimiento <span class="text-danger">*</span></label>
                        <select class="form-select" id="sol_tipo">
                            <option value="">— Selecciona —</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Fecha programada <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="sol_fecha">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Técnico asignado</label>
                        <select class="form-select" id="sol_tecnico">
                            <option value="">— Opcional —</option>
                        </select>
                    </div>
                </div>

                <hr class="my-3">

                <!-- Bloque de máquinas dinámico -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0 text-primary">
                        <i class="bi bi-cpu me-1"></i> Máquinas y Repuestos
                    </h6>
                    <button class="btn btn-sm btn-outline-primary" id="btnAgregarMaquina">
                        <i class="bi bi-plus-lg me-1"></i> Agregar máquina
                    </button>
                </div>

                <div id="contenedorMaquinas">
                    <div class="text-center text-muted py-4" id="emptyMaquinas">
                        <i class="bi bi-cpu" style="font-size:2rem;opacity:.3"></i>
                        <p class="mt-2 mb-0">Aún no has agregado máquinas.<br>
                           Haz clic en <strong>Agregar máquina</strong> para comenzar.</p>
                    </div>
                </div>

            </div><!-- /modal-body -->

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary" id="btnEnviarSolicitud">
                    <i class="bi bi-send me-1"></i> Enviar solicitud
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL — VER DETALLE / APROBAR / RECHAZAR
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header" id="detalleHeader">
                <h5 class="modal-title">
                    <i class="bi bi-clipboard-data me-2"></i>
                    Solicitud <span id="detalleSolId"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body" id="detalleBody">
                <div class="text-center py-4">
                    <span class="spinner-border spinner-border-sm me-2"></span> Cargando...
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <!-- Botones visibles solo en Pendiente + Admin -->
                <button class="btn btn-danger  d-none" id="btnRechazar">
                    <i class="bi bi-x-circle me-1"></i> Rechazar
                </button>
                <button class="btn btn-success d-none" id="btnAprobar">
                    <i class="bi bi-check-circle me-1"></i> Aprobar y generar mantenimientos
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL — MOTIVO DE RECHAZO
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalRechazo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-x-circle me-2 text-danger"></i>Rechazar solicitud</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="rechazo_id">
                <div class="mb-3">
                    <label class="form-label">Motivo del rechazo <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="rechazo_motivo" rows="3" maxlength="600"
                              placeholder="Explica por qué se rechaza la solicitud..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-danger" id="btnConfirmarRechazo">
                    <i class="bi bi-x-circle me-1"></i> Confirmar rechazo
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     TEMPLATE — tarjeta de máquina (clonado por JS)
══════════════════════════════════════════════════════════ -->
<template id="tplMaquinaCard">
    <div class="maquina-card card border mb-3" data-temp-id="">
        <div class="card-header d-flex justify-content-between align-items-center py-2 bg-light">
            <div class="d-flex align-items-center gap-2 flex-grow-1 me-3">
                <i class="bi bi-cpu text-primary"></i>
                <select class="form-select form-select-sm sel-maquina" style="max-width:280px">
                    <option value="">— Selecciona máquina —</option>
                </select>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger btn-quitar-maquina" title="Quitar máquina">
                <i class="bi bi-trash"></i>
            </button>
        </div>
        <div class="card-body pb-2">
            <div class="mb-2">
                <input type="text" class="form-control form-control-sm inp-desc-maquina"
                       maxlength="600" placeholder="Descripción del trabajo en esta máquina (opcional)">
            </div>

            <!-- Tabla de repuestos -->
            <table class="table table-sm table-bordered mb-2 tbl-repuestos">
                <thead class="table-light">
                    <tr>
                        <th>Repuesto</th>
                        <th style="width:110px">Cantidad</th>
                        <th style="width:90px" class="text-center">Stock</th>
                        <th style="width:40px"></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>

            <button type="button" class="btn btn-sm btn-outline-secondary btn-agregar-repuesto">
                <i class="bi bi-plus me-1"></i> Agregar repuesto
            </button>
        </div>
    </div>
</template>

<style>
    /* filtros de estado */
    .filtro-estado.active { opacity: 1; font-weight: 600; }
    .filtro-estado:not(.active) { opacity: .7; }

    /* tarjetas de máquinas */
    .maquina-card { border-radius: 8px; }
    .maquina-card .card-header { border-radius: 8px 8px 0 0; }

    /* badge de stock */
    .badge-stock-ok   { background: #d1fae5; color:#065f46; }
    .badge-stock-warn { background: #fef3c7; color:#92400e; }
    .badge-stock-bad  { background: #fee2e2; color:#991b1b; }

    /* scrollable select in repuesto row */
    .sel-repuesto { min-width: 0; }
</style>
