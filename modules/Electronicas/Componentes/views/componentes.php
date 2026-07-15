
<div class="row mb-3">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0">Catálogo de componentes</h5>
                    <small class="text-muted">Radiografía y componentes por modelo de máquina</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Vista de tarjetas por modelo ────────────────────────── -->
<div id="ccSeccionCards">
    <div class="d-flex justify-content-end mb-3">
        <input type="search" class="form-control" id="ccBuscarModelo"
               placeholder="Buscar modelo, marca o tipo…" style="max-width:280px">
    </div>
    <div class="row g-3" id="ccGridModelos">
        <div class="col-12 text-center py-5">
            <span class="spinner-border text-primary"></span>
        </div>
    </div>
</div>

<!-- ── Ficha del modelo ────────────────────────────────────── -->
<div id="ccSeccionFicha" class="d-none">
    <div class="row mb-3">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <button class="btn btn-sm btn-secondary" id="ccBtnVolver">
                            <i class="bi bi-arrow-left me-1"></i>Volver
                        </button>
                        <div>
                            <h5 class="mb-0" id="ccFichaTitulo"></h5>
                            <small class="text-muted" id="ccFichaSubtitulo"></small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="ft-meta" id="ccFichaMeta"></div>
                        <button class="btn btn-primary d-none" id="ccBtnAgregar">
                            <i class="bi bi-plus-lg me-1"></i> Agregar componente
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Radiografía -->
        <div class="col-12 col-lg-5">
            <div class="card shadow-sm border-0" id="ccRadioCard">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0 cc-panel-titulo">Radiografía del modelo</h6>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-success d-none" id="ccBtnSubirEsquema">
                                <i class="bi bi-image me-1"></i>Cambiar imagen
                            </button>
                            <button class="btn btn-sm btn-outline-success d-none" id="ccBtnEditarPines">
                                <i class="bi bi-pencil me-1"></i>Editar pines
                            </button>
                        </div>
                    </div>
                    <input type="file" id="ccInputEsquema" accept="image/png,image/jpeg,image/webp,image/svg+xml" hidden>

                    <div class="cc-hint d-none" id="ccHintEdicion">
                        Arrastrá los pines a su posición y presioná Guardar
                    </div>

                    <div id="ccLienzo" class="cc-lienzo">
                        <div class="text-center text-muted py-5" id="ccSinEsquema">
                            <i class="bi bi-image" style="font-size:2rem"></i>
                            <p class="small mb-0 mt-2">Este modelo aún no tiene imagen de radiografía.</p>
                        </div>
                        <img id="ccEsquemaImg" class="d-none" alt="Radiografía del modelo">
                        <div id="ccPines"></div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-2 d-none" id="ccAccionesEdicion">
                        <button class="btn btn-sm btn-secondary" id="ccBtnCancelarPines">Cancelar</button>
                        <button class="btn btn-sm btn-primary" id="ccBtnGuardarPines">
                            <i class="bi bi-check-lg me-1"></i>Guardar posiciones
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de componentes -->
        <div class="col-12 col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <table class="table table-hover w-100" id="ccTablaComponentes">
                        <thead>
                            <tr>
                                <th style="width:40px">N°</th>
                                <th>Componente</th>
                                <th style="width:60px">Cant.</th>
                                <th style="width:200px">Repuesto vinculado</th>
                                <th style="width:60px"></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: agregar / editar componente ──────────────────── -->
<div class="modal fade" id="ccModalItem" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ccModalTitulo">Agregar componente</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="ccFormItem">
                    <input type="hidden" id="ccItemId">

                    <div class="mb-3">
                        <label class="form-label">Componente</label>
                        <div class="d-flex gap-2">
                            <select class="form-select" id="ccItemComponente"></select>
                            <button class="btn btn-outline-success flex-shrink-0" type="button"
                                    id="ccBtnNuevoComponente" title="Crear componente nuevo en el catálogo">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-4 mb-3">
                            <label class="form-label">Cantidad</label>
                            <input type="number" class="form-control" id="ccItemCantidad" min="1" value="1">
                        </div>
                        <div class="col-8 mb-3">
                            <label class="form-label">Parte de (opcional)</label>
                            <select class="form-select" id="ccItemPadre">
                                <option value="">— Ninguno —</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Especificación (opcional)</label>
                        <input type="text" class="form-control" id="ccItemEspec"
                               maxlength="255" placeholder="Ej. 2 lados · 92 + 92 boquillas">
                    </div>

                    <div class="mb-1">
                        <label class="form-label">Repuesto vinculado (opcional)</label>
                        <select class="form-select" id="ccItemRepuesto">
                            <option value="">— Sin vincular —</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary" id="ccBtnGuardarItem">
                    <i class="bi bi-check-lg me-1"></i>Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    /* ── Cards por modelo (mismo lenguaje que Máquinas) ── */
    .cc-card-modelo {
        cursor: pointer;
        transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
    }
    .cc-card-modelo:hover {
        transform: translateY(-2px);
        border-color: #156b45 !important;
        box-shadow: 0 8px 20px rgba(28, 33, 40, .08);
    }
    .cc-img {
        height: 132px;
        background: #f6f7f9;
        border-bottom: 1px solid #eef0f3;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .cc-img img { width: 100%; height: 100%; object-fit: contain; padding: .5rem; }
    .cc-img .cc-placeholder { font-size: 2.2rem; color: #c6ccd4; }

    .cc-panel-titulo {
        font-family: var(--hc-mono);
        font-size: .68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .09em;
        color: #40483f;
    }

    /* ── Lienzo de la radiografía ── */
    .cc-lienzo {
        position: relative;
        border: 1px solid var(--hc-borde-suave);
        background: #fff;
        min-height: 180px;
    }
    .cc-lienzo img { display: block; width: 100%; height: auto; }

    .cc-pin {
        position: absolute;
        transform: translate(-50%, -50%);
        width: 22px;
        height: 22px;
        border-radius: 50%;
        border: 1.4px solid var(--hc-tinta);
        background: var(--hc-superficie);
        color: var(--hc-tinta);
        font-family: var(--hc-mono);
        font-size: .66rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: default;
        padding: 0;
        user-select: none;
        touch-action: none;
    }
    .cc-pin.activo, .cc-pin:hover {
        background: var(--hc-verde);
        border-color: var(--hc-verde-hover);
        color: #fff;
        z-index: 3;
    }
    #ccRadioCard.editando .cc-pin {
        cursor: grab;
        border-color: var(--hc-verde);
        border-style: dashed;
    }
    .cc-pin.arrastrando { cursor: grabbing !important; z-index: 4; }

    .cc-hint {
        font-family: var(--hc-mono);
        font-size: .62rem;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: var(--hc-verde);
        background: var(--hc-verde-tinte);
        border: 1px dashed var(--hc-verde);
        padding: .35rem .6rem;
        margin-bottom: .6rem;
    }

    /* ── Tabla: jerarquía y chips ── */
    #ccTablaComponentes tbody tr.activo td { background: #f0f3ef; }
    #ccTablaComponentes .cc-ref {
        font-family: var(--hc-mono);
        font-size: .62rem;
        font-weight: 700;
        width: 18px; height: 18px;
        border: 1.2px solid var(--hc-tinta);
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    #ccTablaComponentes tr:hover .cc-ref,
    #ccTablaComponentes tr.activo .cc-ref {
        background: var(--hc-verde);
        border-color: var(--hc-verde-hover);
        color: #fff;
    }
    .cc-categoria {
        font-family: var(--hc-mono);
        font-size: .56rem;
        letter-spacing: .07em;
        text-transform: uppercase;
        color: var(--hc-texto-3);
        display: block;
        margin-top: .1rem;
    }
    .cc-espec { color: var(--hc-texto-2); font-size: .78rem; display: block; }
    tr.cc-hija td { background: #fafbf9; }
    tr.cc-hija .cc-nombre { padding-left: 1.15rem; position: relative; font-weight: 500; }
    tr.cc-hija .cc-nombre::before {
        content: "";
        position: absolute;
        left: .25rem;
        top: -0.6rem;
        bottom: 45%;
        width: .55rem;
        border-left: 1px solid var(--hc-borde);
        border-bottom: 1px solid var(--hc-borde);
    }
    .cc-chip {
        font-family: var(--hc-mono);
        font-size: .6rem;
        font-weight: 600;
        letter-spacing: .04em;
        padding: .14rem .45rem;
        border: 1px solid;
        white-space: nowrap;
    }
    .cc-chip-ok   { color: var(--hc-verde); background: var(--hc-verde-tinte); border-color: #c8ddd2; }
    .cc-chip-bajo { color: #9a6b00; background: #fdf3dd; border-color: #e8d5a8; }
    .cc-chip-cero { color: #a13333; background: #faecec; border-color: #e3c2c2; }
    .cc-rep-nombre { display: block; font-size: .74rem; color: var(--hc-texto-2); margin-bottom: .18rem; }
</style>
