
<div class="row mb-3">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0">Máquinas</h5>
                    <small class="text-muted">Registro general del área de electrónicas</small>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-success" id="btnVerTablaCompleta">
                        <i class="bi bi-table me-1"></i> Ver tabla completa
                    </button>
                    <button class="btn btn-primary" id="btnNuevaMaquina" data-bs-toggle="modal" data-bs-target="#modalMaquina">
                        <i class="bi bi-plus-circle"></i> Nueva Máquina
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Vista de tarjetas por modelo ────────────────────────── -->
<div id="seccionCards">
    <div class="d-flex justify-content-end mb-3">
        <input type="search" class="form-control" id="buscarModelo"
               placeholder="Buscar modelo, marca o tipo…" style="max-width:280px">
    </div>
    <div class="row g-3" id="gridModelos">
        <div class="col-12 text-center py-5">
            <span class="spinner-border text-primary"></span>
        </div>
    </div>
</div>

<!-- ── Vista de detalle (máquinas del modelo) ──────────────── -->
<div id="seccionDetalle" class="d-none">
    <div class="row mb-3">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body d-flex align-items-center gap-3 py-2">
                    <button class="btn btn-sm btn-secondary" id="btnVolverCards">
                        <i class="bi bi-arrow-left me-1"></i>Volver
                    </button>
                    <div>
                        <h6 class="mb-0" id="detalleTitulo"></h6>
                        <small class="text-muted" id="detalleSubtitulo"></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <table class="table table-hover w-100" id="tablaMaquinas">
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
                <table class="table table-hover w-100" id="tablaRepuestosMaquina">
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

<!-- ── Modal: Componentes de la máquina (estados) ──────────── -->
<div class="modal fade" id="modalComponentesMaquina" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0">
                        <i class="bi bi-diagram-3 me-2"></i>
                        Componentes — <span id="cmMaquinaNombre" class="fw-bold"></span>
                    </h5>
                    <div class="d-flex gap-3 flex-wrap mt-2 small">
                        <span><span class="ey-legend bg-success"></span> OK</span>
                        <span><span class="ey-legend bg-danger"></span> Falla</span>
                        <span><span class="ey-legend bg-warning"></span> Advertencia</span>
                        <span><span class="ey-legend bg-secondary"></span> Apagado</span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div id="cmContenido" style="min-height:180px">
                    <div class="text-center py-5"><span class="spinner-border text-primary"></span></div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline-secondary d-none" id="btnHistorialComponentes">
                    <i class="bi bi-clock-history me-1"></i>Historial
                </button>
                <button class="btn btn-outline-success d-none" id="btnVerBarraEyectores">
                    <i class="bi bi-grid-3x3-gap me-1"></i>Ver barra de eyectores
                </button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: Historial de componentes de la máquina ───────── -->
<div class="modal fade" id="modalHistorialComponentes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title mb-0">
                    <i class="bi bi-clock-history me-2"></i>
                    Historial de componentes — <span id="cmHistMaquinaNombre" class="fw-bold"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" class="form-control form-control-sm mb-3" id="cmHistFiltro"
                       placeholder="Filtrar por componente, estado o usuario">
                <div id="cmHistContenido" style="min-height:150px">
                    <div class="text-center py-5"><span class="spinner-border text-primary"></span></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: Componentes — Barra de eyectores ─────────────── -->
<div class="modal fade" id="modalEyectores" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0">
                        <i class="bi bi-grid-3x3-gap me-2"></i>
                        Eyectores — <span id="eyMaquinaNombre" class="fw-bold"></span>
                    </h5>
                    <div class="d-flex gap-3 flex-wrap mt-2 small">
                        <span><span class="ey-legend bg-success"></span> OK</span>
                        <span><span class="ey-legend bg-danger"></span> Falla</span>
                        <span><span class="ey-legend bg-warning"></span> Advertencia</span>
                        <span><span class="ey-legend bg-secondary"></span> Apagado</span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div id="eyContenido" style="min-height:200px">
                    <div class="text-center py-5"><span class="spinner-border text-primary"></span></div>
                </div>

                <div class="row g-2 mt-3 d-none" id="eyStats">
                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-sm text-center">
                            <div class="card-body py-2">
                                <span class="d-block fs-4 fw-bold text-success" id="eyOk">0</span>
                                <small class="text-muted">OK</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-sm text-center">
                            <div class="card-body py-2">
                                <span class="d-block fs-4 fw-bold text-danger" id="eyFalla">0</span>
                                <small class="text-muted">Fallas</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-sm text-center">
                            <div class="card-body py-2">
                                <span class="d-block fs-4 fw-bold text-warning" id="eyAdv">0</span>
                                <small class="text-muted">Advertencias</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-sm text-center">
                            <div class="card-body py-2">
                                <span class="d-block fs-4 fw-bold text-secondary" id="eyOff">0</span>
                                <small class="text-muted">Apagados</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline-secondary d-none" id="btnHistorialEyectores">
                    <i class="bi bi-clock-history me-1"></i>Historial
                </button>
                <button class="btn btn-success d-none" id="btnInicializarBarra">
                    <i class="bi bi-magic me-1"></i>Inicializar barra
                </button>
                <button class="btn btn-outline-primary d-none" id="btnModoSeleccion">
                    <i class="bi bi-check2-square me-1"></i>Seleccionar varios
                </button>
                <button class="btn btn-outline-secondary d-none" id="btnLimpiarSeleccion">
                    Limpiar
                </button>
                <button class="btn btn-primary d-none" id="btnAplicarSeleccion" disabled>
                    <i class="bi bi-pencil-square me-1"></i>Cambiar estado (<span id="eySelCount">0</span>)
                </button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: Historial de la barra de eyectores ────────────── -->
<div class="modal fade" id="modalHistorialEyectores" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title mb-0">
                    <i class="bi bi-clock-history me-2"></i>
                    Historial de eyectores — <span id="eyHistMaquinaNombre" class="fw-bold"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" class="form-control form-control-sm mb-3" id="eyHistFiltro"
                       placeholder="Filtrar por eyector, estado o usuario (ej. Izq #12)">
                <div id="eyHistContenido" style="min-height:150px">
                    <div class="text-center py-5"><span class="spinner-border text-primary"></span></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<style>
    /* ── Componentes por máquina: radiografía y estados ── */
    .cm-lienzo {
        position: relative;
        border: 1px solid var(--hc-borde-suave);
        background: #fff;
        max-width: 420px;
        margin: 0 auto 1rem;
    }
    .cm-lienzo img { display: block; width: 100%; height: auto; }
    .cm-pin {
        position: absolute;
        transform: translate(-50%, -50%);
        width: 24px;
        height: 24px;
        border-radius: 50%;
        border: 1.6px solid rgba(0, 0, 0, .35);
        color: #fff;
        font-family: var(--hc-mono);
        font-size: .68rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        user-select: none;
    }
    .cm-pin.bg-warning { color: #1c2128; }
    .cm-pin.activo, .cm-pin:hover { z-index: 3; box-shadow: 0 0 0 3px var(--hc-verde-tinte); }

    .cm-item { cursor: pointer; }
    .cm-item.activo, .cm-item:hover { background: #f0f3ef; }
    .cm-item .cm-num {
        font-family: var(--hc-mono);
        font-size: .62rem;
        font-weight: 700;
        width: 18px; height: 18px;
        border: 1.2px solid var(--hc-tinta);
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .cm-item .cm-espec { color: var(--hc-texto-2); font-size: .78rem; }
    .cm-item .cm-obs   { color: var(--hc-texto-3); font-size: .74rem; }

    /* ── Tarjetas por modelo ── */
    .modelo-card {
        cursor: pointer;
        transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
    }
    .modelo-card:hover {
        transform: translateY(-2px);
        border-color: #156b45 !important;
        box-shadow: 0 8px 20px rgba(28, 33, 40, .08);
    }
    .mc-img {
        height: 132px;
        background: #f6f7f9;
        border-bottom: 1px solid #eef0f3;
        border-radius: 10px 10px 0 0;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .mc-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .mc-img .mc-placeholder {
        font-size: 2.4rem;
        color: #c2cdc7;
    }
    .mc-badges .badge { margin-right: .25rem; }

    .ey-legend {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        vertical-align: middle;
    }
    .ey-bar {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        user-select: none;
    }
    .ey-side {
        border: 1px solid #dee2e6;
        border-radius: 12px;
        padding: 14px;
        background: #f8f9fa;
    }
    .ey-side-title {
        text-align: center;
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 12px;
        color: #495057;
    }
    .ey-row {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
    }
    .ey-fila {
        width: 48px;
        font-size: 12px;
        color: #6c757d;
        flex-shrink: 0;
    }
    .ey-dots {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        flex: 1;
    }
    .ey-dot {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        cursor: pointer;
        border: 1px solid rgba(0, 0, 0, 0.15);
        transition: transform 0.12s ease;
    }
    .ey-dot:hover {
        transform: scale(1.6);
        z-index: 3;
    }
    .ey-dot.ey-selected {
        outline: 2px solid #0d6efd;
        outline-offset: 1px;
        transform: scale(1.25);
    }
    .ey-tooltip {
        position: fixed;
        z-index: 2000;
        background: #212529;
        color: #fff;
        font-size: 12px;
        line-height: 1.2;
        padding: 4px 8px;
        border-radius: 6px;
        pointer-events: none;
        white-space: nowrap;
        transform: translate(-50%, -100%);
        opacity: 0;
        transition: opacity 0.1s ease;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
    }
    .ey-tooltip.show {
        opacity: 1;
    }
    @media (max-width: 768px) {
        .ey-bar { grid-template-columns: 1fr; }
    }
</style>