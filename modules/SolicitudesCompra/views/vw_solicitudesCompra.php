<section class="section">
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-body pt-3">

    <div class="d-flex justify-content-between align-items-center mb-3 mt-2 flex-wrap gap-2">
        <h5 class="card-title mb-0">
            <i class="bi bi-cart-check me-2 text-primary"></i>Solicitudes de Compra
        </h5>
        <button class="btn btn-primary btn-sm" id="btnNuevaCompra">
            <i class="bi bi-plus-circle me-1"></i> Nueva solicitud
        </button>
    </div>

    <!-- Filtros de estado -->
    <div class="d-flex gap-2 mb-3 flex-wrap" id="filtrosEstadoCompra">
        <button class="btn btn-sm btn-outline-secondary filtro-compra active" data-estado="">Todas</button>
        <button class="btn btn-sm btn-outline-secondary filtro-compra" data-estado="Borrador">Borrador</button>
        <button class="btn btn-sm btn-outline-warning  filtro-compra" data-estado="Pendiente">Pendientes</button>
        <button class="btn btn-sm btn-outline-info     filtro-compra" data-estado="Aprobada">Aprobadas</button>
        <button class="btn btn-sm btn-outline-primary  filtro-compra" data-estado="Ordenada">Ordenadas</button>
        <button class="btn btn-sm btn-outline-warning  filtro-compra" data-estado="Recibida parcial">Parciales</button>
        <button class="btn btn-sm btn-outline-success  filtro-compra" data-estado="Recibida">Recibidas</button>
        <button class="btn btn-sm btn-outline-danger   filtro-compra" data-estado="Rechazada">Rechazadas</button>
    </div>

    <div class="table-responsive">
        <table id="tblCompras" class="table table-hover table-striped table-sm align-middle">
            <thead class="table-primary">
                <tr>
                    <th>#</th>
                    <th>Descripción</th>
                    <th class="col-hide-xs">Solicitante</th>
                    <th class="text-center col-hide-sm">Ítems</th>
                    <th class="col-hide-md text-end">Total estimado</th>
                    <th class="col-hide-md">Creada</th>
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
     MODAL — NUEVA / EDITAR SOLICITUD
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalCompra" tabindex="-1"
     aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalCompraTitulo">
                    <i class="bi bi-cart-plus me-2"></i>Nueva Solicitud de Compra
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body px-3 px-md-4">
                <input type="hidden" id="compra_id">

                <!-- Datos generales -->
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-7">
                        <label class="form-label fw-semibold">
                            Descripción <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="compra_descripcion" rows="2"
                                  maxlength="600" placeholder="¿Qué se va a comprar?"></textarea>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label fw-semibold">
                            Divisa <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="compra_divisa">
                            <option value="">— Selecciona —</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label fw-semibold">Símbolo</label>
                        <input type="text" id="compra_divisa_simbolo" class="form-control" readonly
                               placeholder="—" tabindex="-1">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Notas adicionales</label>
                        <input type="text" class="form-control" id="compra_notas"
                               maxlength="1000" placeholder="Observaciones, condiciones de compra, etc. (opcional)">
                    </div>
                </div>

                <hr class="my-3">

                <!-- Ítems -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 text-primary fw-bold">
                        <i class="bi bi-box-seam me-1"></i> Repuestos a comprar
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnAgregarItem">
                        <i class="bi bi-plus-lg me-1"></i> Agregar repuesto
                    </button>
                </div>

                <!-- Encabezado columnas — desktop -->
                <div class="d-none d-md-flex gap-2 px-2 mb-1 text-muted small fw-semibold border-bottom pb-1">
                    <div style="width:105px;flex-shrink:0">Tipo</div>
                    <div class="flex-grow-1">Repuesto</div>
                    <div style="width:150px;flex-shrink:0">Proveedor</div>
                    <div style="width:85px;flex-shrink:0">Cantidad</div>
                    <div style="width:120px;flex-shrink:0">Costo unit.</div>
                    <div style="width:100px;flex-shrink:0" class="text-end">Subtotal</div>
                    <div style="width:34px;flex-shrink:0"></div>
                </div>

                <div id="compraItems">
                    <div id="emptyItems" class="text-center text-muted py-4">
                        <i class="bi bi-box-seam opacity-25" style="font-size:2rem"></i>
                        <p class="mt-2 small mb-0">Agrega al menos un repuesto.</p>
                    </div>
                </div>

                <!-- Total estimado -->
                <div class="d-flex justify-content-end mt-3 me-1">
                    <div class="text-end">
                        <span class="text-muted small">Total estimado:</span>
                        <span class="fw-bold ms-2 fs-6" id="compra_total">—</span>
                    </div>
                </div>
            </div>

            <div class="modal-footer flex-wrap gap-2">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-outline-primary" id="btnGuardarBorrador">
                    <i class="bi bi-floppy me-1"></i> Guardar borrador
                </button>
                <button class="btn btn-primary" id="btnGuardarEnviar">
                    <i class="bi bi-send me-1"></i> Enviar solicitud
                </button>
            </div>

        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     MODAL — DETALLE
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalDetalleCompra" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-cart-check me-2"></i>
                    Solicitud de Compra <span id="detalleCompraId"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body" id="detalleCompraBody">
                <div class="text-center py-4">
                    <span class="spinner-border spinner-border-sm me-2"></span> Cargando...
                </div>
            </div>

            <div class="modal-footer flex-wrap gap-2" id="detalleCompraFooter">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button class="btn btn-outline-primary  d-none" id="btnEditarBorrador">
                    <i class="bi bi-pencil me-1"></i> Editar
                </button>
                <button class="btn btn-primary          d-none" id="btnEnviarBorrador">
                    <i class="bi bi-send me-1"></i> Enviar
                </button>
                <button class="btn btn-danger           d-none" id="btnRechazarCompra">
                    <i class="bi bi-x-circle me-1"></i> Rechazar
                </button>
                <button class="btn btn-success          d-none" id="btnAprobarCompra">
                    <i class="bi bi-check-circle me-1"></i> Aprobar
                </button>
                <button class="btn btn-outline-primary  d-none" id="btnOrdenarCompra">
                    <i class="bi bi-truck me-1"></i> Registrar orden
                </button>
                <button class="btn btn-success          d-none" id="btnRecibirCompra">
                    <i class="bi bi-box-arrow-in-down me-1"></i> Registrar recepción
                </button>
                <button class="btn btn-outline-success  d-none" id="btnCerrarRecepcion">
                    <i class="bi bi-check2-all me-1"></i> Cerrar recepción
                </button>
                <button class="btn btn-outline-danger   d-none" id="btnCancelarCompra">
                    <i class="bi bi-slash-circle me-1"></i> Cancelar
                </button>
            </div>

        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     MODAL — MOTIVO DE RECHAZO
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalRechazoCompra" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-x-circle me-2 text-danger"></i>Rechazar solicitud
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label fw-semibold">
                    Motivo <span class="text-danger">*</span>
                </label>
                <textarea class="form-control" id="rechazoCompraMotivo" rows="4"
                          maxlength="600" placeholder="Explica el motivo del rechazo..."></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-danger flex-grow-1" id="btnConfirmarRechazoCompra">
                    <i class="bi bi-x-circle me-1"></i> Confirmar rechazo
                </button>
            </div>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     MODAL — REGISTRAR ORDEN AL PROVEEDOR
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalOrdenCompra" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-truck me-2 text-primary"></i>Registrar Orden al Proveedor
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Número de orden / referencia</label>
                    <input type="text" class="form-control" id="ordenNumero"
                           maxlength="100" placeholder="Ej: PO-2025-001 (opcional)">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Fecha estimada de entrega</label>
                    <input type="date" class="form-control" id="ordenFechaEntrega">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary flex-grow-1" id="btnConfirmarOrden">
                    <i class="bi bi-truck me-1"></i> Registrar orden
                </button>
            </div>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     MODAL — REGISTRAR RECEPCIÓN
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalRecepcion" tabindex="-1"
     aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">

            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-box-arrow-in-down me-2"></i>Registrar Recepción de Mercancía
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body px-3">
                <div class="alert alert-info py-2 mb-3 small">
                    <i class="bi bi-info-circle me-1"></i>
                    Confirma las cantidades recibidas. Para repuestos con número de serie,
                    ingresa cada serial individualmente.
                </div>
                <div id="recepcionItems"></div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-success" id="btnConfirmarRecepcion">
                    <i class="bi bi-check-circle me-1"></i> Confirmar recepción
                </button>
            </div>

        </div>
    </div>
</div>


<style>
/* ── Badge color naranja (Recibida parcial) ───────────── */
.badge.bg-orange { background-color: #fd7e14 !important; }

/* ── Filtros ──────────────────────────────────────────── */
.filtro-compra.active  { opacity:1; font-weight:600; }
.filtro-compra:not(.active) { opacity:.65; }

/* ── Ítem de compra (flex, responsive) ───────────────── */
.compra-item {
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
.compra-item .ci-select { flex: 1 1 200px; min-width: 0; }
.compra-item .ci-actions { display:flex; align-items:center; gap:.4rem; flex-shrink:0; }
.compra-item .ci-subtotal {
    width:100px; text-align:right; font-size:.82rem;
    color:#495057; font-weight:600;
}

/* ── Ítem de recepción ────────────────────────────────── */
.rec-item {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: .75rem;
    margin-bottom: .75rem;
    background: #fff;
}
.rec-item.rec-serie { background: #fffbea; border-color: #fcd34d; }
.rec-seriales { display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.5rem; }
.rec-seriales input { width:150px; font-size:.82rem; }

/* ── Columnas ocultas responsive ─────────────────────── */
/* Col idx: 1=#  2=Descripción  3=Solicitante  4=Ítems  5=Total  6=Creada  7=Estado  8=Acciones */
@media (max-width:991px) {
    #tblCompras th.col-hide-md,
    #tblCompras td:nth-child(5),
    #tblCompras td:nth-child(6) { display:none; }
    th.col-hide-md { display:none; }
}
@media (max-width:767px) {
    #tblCompras th.col-hide-sm,
    #tblCompras td:nth-child(4) { display:none; }
}
@media (max-width:575px) {
    #tblCompras th.col-hide-xs,
    #tblCompras td:nth-child(3) { display:none; }
    .modal-footer { flex-direction:column-reverse; }
    .modal-footer .btn { width:100%; }
}

/* ── Select2 sm en ítems de compra ───────────────────── */
.compra-item .select2-container--bootstrap-5 .select2-selection--single,
.compra-item .select2-container--bootstrap-5 .select2-selection--single {
    height: calc(1.5em + .5rem + 2px) !important;
    padding: .25rem 2rem .25rem .5rem;
    font-size: .875rem;
}
.compra-item .select2-container--bootstrap-5 .select2-selection__rendered {
    padding: 0;
    line-height: calc(1.5em + .5rem);
    font-size: .875rem;
}
.compra-item .select2-container--bootstrap-5 .select2-selection__arrow {
    height: calc(1.5em + .5rem) !important;
}
</style>
