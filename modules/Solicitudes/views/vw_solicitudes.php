<div class="row mb-3">
    <div class="col-12">
        <div class="card shadow-sm border-0 ft-cabecera">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">
                        <i class="bi bi-clipboard-check me-2 text-primary"></i>Solicitudes de Repuestos
                    </h5>
                    <small class="text-muted">Gestión y aprobación de solicitudes internas</small>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="ft-meta d-none d-md-block">Operaciones<br>Módulo SOL</span>
                    <button class="btn btn-primary" id="btnNuevaSolicitud">
                        <i class="bi bi-plus-circle me-1"></i> Nueva solicitud
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">

        <!-- Filtros de estado -->
        <div class="d-flex gap-2 mb-3 flex-wrap" id="filtrosEstado">
            <button class="btn btn-sm btn-outline-secondary filtro-estado active" data-estado="">Todas</button>
            <button class="btn btn-sm btn-outline-warning  filtro-estado" data-estado="Pendiente">Pendientes</button>
            <button class="btn btn-sm btn-outline-success  filtro-estado" data-estado="Aprobado">Aprobadas</button>
            <button class="btn btn-sm btn-outline-danger   filtro-estado" data-estado="Rechazado">Rechazadas</button>
            <button class="btn btn-sm btn-outline-secondary filtro-estado" data-estado="Cancelado">Canceladas</button>
        </div>

        <table id="tblSolicitudes" class="table table-hover w-100">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Descripción</th>
                    <th class="col-hide-sm">Tipo</th>
                    <th class="col-hide-xs">Solicitante</th>
                    <th class="text-center col-hide-sm">Máquinas</th>
                    <th class="col-hide-md">Fecha programada</th>
                    <th class="col-hide-md">Creada</th>
                    <th class="text-center">Estado</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>

    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     MODAL — NUEVA SOLICITUD
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalNuevaSolicitud" tabindex="-1"
     aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-clipboard-plus me-2"></i>Nueva Solicitud de Repuestos
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body px-3 px-md-4">

                <!-- ── Datos generales ────────────────────────────── -->
                <div class="row g-3 mb-3">

                    <div class="col-12 col-md-5">
                        <label class="form-label fw-semibold">
                            Descripción general <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="sol_descripcion" rows="2" maxlength="600"
                                  placeholder="¿Qué trabajo se realizará?"></textarea>
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label fw-semibold">
                            Tipo de mantenimiento <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="sol_tipo">
                            <option value="">— Selecciona —</option>
                        </select>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label fw-semibold">
                            Fecha programada <span class="text-danger">*</span>
                        </label>
                        <input type="date" class="form-control" id="sol_fecha">
                    </div>

                    <div class="col-12 col-md-2">
                        <label class="form-label fw-semibold">Técnico asignado</label>
                        <select class="form-select" id="sol_tecnico">
                            <option value="">— Opcional —</option>
                        </select>
                    </div>

                </div>

                <hr class="my-3">

                <!-- ── Máquinas y repuestos ───────────────────────── -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0 text-primary fw-bold">
                        <i class="bi bi-cpu me-1"></i> Máquinas y Repuestos
                    </h6>
                    <button class="btn btn-sm btn-outline-primary" id="btnAgregarMaquina">
                        <i class="bi bi-plus-lg me-1"></i>
                        <span class="d-none d-sm-inline">Agregar</span> máquina
                    </button>
                </div>

                <div id="contenedorMaquinas">
                    <div class="text-center text-muted py-5" id="emptyMaquinas">
                        <i class="bi bi-cpu" style="font-size:2.5rem;opacity:.25"></i>
                        <p class="mt-2 mb-0 small">
                            Aún no agregaste máquinas.<br>
                            Pulsa <strong>Agregar máquina</strong> para comenzar.
                        </p>
                    </div>
                </div>

            </div><!-- /modal-body -->

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary w-100-sm" id="btnEnviarSolicitud">
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
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-sm-down">
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

            <div class="modal-footer flex-wrap gap-2">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button class="btn btn-danger  d-none" id="btnRechazar">
                    <i class="bi bi-x-circle me-1"></i> Rechazar
                </button>
                <button class="btn btn-success d-none" id="btnAprobar">
                    <i class="bi bi-check-circle me-1"></i>
                    <span class="d-none d-sm-inline">Aprobar solicitud</span>
                    <span class="d-sm-none">Aprobar</span>
                </button>
            </div>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     MODAL — MOTIVO DE RECHAZO
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalRechazo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-x-circle me-2 text-danger"></i>Rechazar solicitud
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="rechazo_id">
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        Motivo del rechazo <span class="text-danger">*</span>
                    </label>
                    <textarea class="form-control" id="rechazo_motivo" rows="4" maxlength="600"
                              placeholder="Explica por qué se rechaza la solicitud..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-danger flex-grow-1" id="btnConfirmarRechazo">
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
    <div class="maquina-card card shadow-sm border-0 mb-3" data-temp-id="">

        <!-- Cabecera: select máquina + botón quitar -->
        <div class="card-header d-flex align-items-center gap-2 py-2 bg-primary bg-opacity-10 border-bottom">
            <i class="bi bi-cpu-fill text-primary fs-5 flex-shrink-0"></i>
            <div class="flex-grow-1 maquina-select-wrap">
                <select class="form-select form-select-sm sel-maquina">
                    <option value="">— Selecciona máquina —</option>
                </select>
            </div>
            <button type="button"
                    class="btn btn-sm btn-outline-danger btn-quitar-maquina flex-shrink-0"
                    title="Quitar máquina">
                <i class="bi bi-trash"></i>
            </button>
        </div>

        <!-- Cuerpo: descripción + lista de repuestos -->
        <div class="card-body pb-2 px-2 px-md-3">

            <input type="text" class="form-control form-control-sm mb-3 inp-desc-maquina"
                   maxlength="600"
                   placeholder="Descripción del trabajo en esta máquina (opcional)">

            <!-- Encabezado de columnas — solo desktop -->
            <div class="d-none d-md-flex gap-2 px-2 mb-1 text-muted small fw-semibold border-bottom pb-1">
                <div class="flex-grow-1">Repuesto</div>
                <div style="width:85px">Cant.</div>
                <div style="width:105px" class="text-center">Disponible</div>
                <div style="width:34px"></div>
            </div>

            <!-- Filas de repuesto generadas por JS -->
            <div class="rep-list my-1"></div>

            <button type="button"
                    class="btn btn-sm btn-outline-primary w-100 mt-2 btn-agregar-repuesto">
                <i class="bi bi-plus-lg me-1"></i> Agregar repuesto
            </button>

        </div>
    </div>
</template>


<style>
/* ── Filtros de estado: estilo segmentado global en app.css ── */

/* Fechas de la tabla en monoespaciada (columnas 6 y 7) */
#tblSolicitudes tbody td:nth-child(6),
#tblSolicitudes tbody td:nth-child(7) {
    font-family: 'IBM Plex Mono', ui-monospace, Consolas, monospace;
    font-size: .72rem;
}

/* ── Tarjeta de máquina ───────────────────────────────── */
.maquina-card         { border-radius:10px; overflow:hidden; }
.maquina-card .card-header { border-radius:10px 10px 0 0; }

/* ── Ítem de repuesto (flex row, responsive) ─────────── */
.rep-item {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .5rem;
    padding: .5rem .6rem;
    border-radius: 8px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    margin-bottom: .45rem;
}
.rep-item .rep-select-wrap {
    flex: 1 1 200px;   /* crece, pero mínimo 200px → en móvil ocupa toda la fila */
    min-width: 0;
}
.rep-item .rep-actions {
    display: flex;
    align-items: center;
    gap: .4rem;
    flex-shrink: 0;
}

/* ── Badges de stock ──────────────────────────────────── */
.badge-stock-ok   { background:#d1fae5; color:#065f46; }
.badge-stock-warn { background:#fef3c7; color:#92400e; }
.badge-stock-bad  { background:#fee2e2; color:#991b1b; }
.td-stock { min-width:85px; text-align:center; font-size:.78rem; }

/* ── Select2 sm dentro de las tarjetas de máquina ────── */
/* Corrige el height:38px global que viene de links.php  */
.maquina-card .select2-container--bootstrap-5 .select2-selection--single,
.rep-item     .select2-container--bootstrap-5 .select2-selection--single {
    height: calc(1.5em + .5rem + 2px) !important;  /* tamaño form-select-sm */
    padding: .25rem 2rem .25rem .5rem;
    font-size: .875rem;
    line-height: 1.5;
}
.maquina-card .select2-container--bootstrap-5 .select2-selection__rendered,
.rep-item     .select2-container--bootstrap-5 .select2-selection__rendered {
    padding: 0;
    line-height: calc(1.5em + .5rem);
    font-size: .875rem;
}
.maquina-card .select2-container--bootstrap-5 .select2-selection__arrow,
.rep-item     .select2-container--bootstrap-5 .select2-selection__arrow {
    height: calc(1.5em + .5rem) !important;
}
/* Opciones del dropdown */
.select2-results__option {
    padding: .4rem .65rem;
    font-size: .875rem;
    line-height: 1.4;
}
.select2-search--dropdown .select2-search__field {
    font-size: .875rem !important;
    padding: .35rem .5rem;
}

/* ── Columnas ocultas en tabla (responsive) ───────────── */
@media (max-width: 991px) {
    #tblSolicitudes .col-hide-md,
    #tblSolicitudes .col-hide-md + td { display: none; }
    th.col-hide-md { display: none; }
}
@media (max-width: 767px) {
    #tblSolicitudes th.col-hide-sm,
    #tblSolicitudes td:nth-child(4),   /* Tipo */
    #tblSolicitudes td:nth-child(6) {  /* Máquinas */
        display: none;
    }
}
@media (max-width: 575px) {
    #tblSolicitudes th.col-hide-xs,
    #tblSolicitudes td:nth-child(5) {  /* Solicitante */
        display: none;
    }
    /* footer buttons full-width en mobile */
    .w-100-sm { width: 100%; }
    .modal-footer { flex-direction: column-reverse; }
    .modal-footer .btn { width: 100%; }
}
</style>
