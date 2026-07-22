<!-- Leaflet (mapa) — se carga antes que mapa.js (footer) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Tarjeta 1: cabecera -->
<div class="row mb-3">
  <div class="col-12">
    <div class="card shadow-sm border-0 ft-cabecera">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div>
          <h5 class="mb-0">Mapa GPS</h5>
          <small class="text-muted">Seguimiento por despacho — arma tu lista de carros que salen</small>
        </div>
        <div class="d-flex align-items-center gap-3">
          <span class="ft-meta d-none d-md-block">GPS<br>Despachos</span>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" id="mapaAutoRefresh">
            <label class="form-check-label small" for="mapaAutoRefresh">Auto</label>
          </div>
          <button class="btn btn-primary" id="btnMapaRefrescar">
            <i class="bi bi-arrow-clockwise me-1"></i> Actualizar
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Barra de despacho -->
<div class="card shadow-sm border-0 mb-3">
  <div class="card-body py-2 d-flex flex-wrap align-items-center gap-2">
    <span class="small fw-semibold me-1"><i class="bi bi-clipboard-check me-1"></i>Despacho:</span>
    <select class="form-select form-select-sm" id="selDespacho" style="max-width:280px">
      <option value="">Todos los activos</option>
    </select>
    <button class="btn btn-sm btn-success" id="btnNuevoDespacho">
      <i class="bi bi-plus-lg me-1"></i> Preparar despacho
    </button>
    <button class="btn btn-sm btn-outline-primary" id="btnAgregarVehiculos" disabled>
      <i class="bi bi-truck me-1"></i> Agregar vehículos
    </button>
    <button class="btn btn-sm btn-outline-danger ms-auto" id="btnCerrarDespacho" disabled>
      <i class="bi bi-stop-circle me-1"></i> Cerrar despacho
    </button>
  </div>
</div>

<!-- Tarjeta 2: filtros + lista + mapa -->
<div class="card shadow-sm border-0">
  <div class="card-body">

    <div class="row g-2 mb-2 align-items-end">
      <div class="col-6 col-md-3">
        <label class="form-label small mb-1">Transporte</label>
        <select class="form-select form-select-sm" id="fltTransporte"><option value="">Todos</option></select>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small mb-1">Seguimiento</label>
        <select class="form-select form-select-sm" id="fltEstado">
          <option value="">Todos</option>
          <option value="live">En vivo</option>
          <option value="sin_senal">Sin señal</option>
          <option value="pendiente">Pendiente</option>
        </select>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small mb-1">Buscar placa</label>
        <input type="text" class="form-control form-control-sm" id="fltPlaca" placeholder="Ej.: JDF0364">
      </div>
      <div class="col-6 col-md-3 text-md-end">
        <span class="ft-status small text-muted" id="mapaStatus">Cargando…</span>
      </div>
    </div>

    <div class="d-flex flex-wrap gap-3 mb-3 small text-muted">
      <span><span class="seg-dot" style="background:#156b45"></span> En vivo</span>
      <span><span class="seg-dot" style="background:#6c757d"></span> Sin señal</span>
      <span><span class="seg-dot" style="background:#d9a300"></span> Pendiente</span>
    </div>

    <div class="row g-3">
      <div class="col-12 col-lg-3">
        <div class="mapa-lista border" id="mapaLista">
          <div class="text-muted small p-3">Cargando…</div>
        </div>
      </div>
      <div class="col-12 col-lg-9">
        <div id="mapaGPS"></div>
      </div>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL — AGREGAR VEHÍCULOS AL DESPACHO
══════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalAgregarVeh" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0"><i class="bi bi-truck me-1"></i> Agregar vehículos al despacho</h5>
          <small class="text-muted" id="modalAgregarSub">Elige una cuenta y marca los carros que salen</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2 align-items-end mb-3">
          <div class="col-md-8">
            <label class="form-label small mb-1">Cuenta (transporte · plataforma · usuario)</label>
            <select class="form-select form-select-sm" id="selCuentaAgregar">
              <option value="">— Selecciona una cuenta —</option>
            </select>
          </div>
          <div class="col-md-4">
            <input type="text" class="form-control form-control-sm" id="buscarDispositivo"
                   placeholder="Filtrar por placa/nombre">
          </div>
        </div>
        <div class="cuenta-info mb-3 d-none" id="cuentaInfo"></div>
        <div id="dispositivosWrap">
          <div class="text-muted small">Selecciona una cuenta para ver sus equipos.</div>
        </div>
      </div>
      <div class="modal-footer">
        <span class="me-auto small text-muted" id="dispSeleccion">0 seleccionados</span>
        <button class="btn btn-primary" id="btnVincularSeleccion" disabled>
          <i class="bi bi-check-lg me-1"></i> Agregar al despacho
        </button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<style>
  #mapaGPS { height: calc(100vh - 360px); min-height: 440px; border: 1px solid var(--hc-banda, #d7ddd9); }
  .mapa-lista { height: calc(100vh - 360px); min-height: 440px; overflow-y: auto; background: #fff; }
  .seg-dot { display:inline-block; width:11px; height:11px; border-radius:50%; margin-right:3px; vertical-align:middle; }
  .mapa-item { display:flex; align-items:center; gap:.6rem; padding:.5rem .6rem; border-bottom:1px solid #eef1ef; border-left:3px solid transparent; }
  .mapa-item:hover { background:#f4f8f6; }
  .mapa-item.activo { background:#eaf3ee; border-left-color:var(--hc-tinta, #156b45); }
  .mapa-item .mi-dot { width:11px; height:11px; border-radius:50%; flex:0 0 auto; box-shadow:0 0 0 2px rgba(0,0,0,.06); }
  .mapa-item .mi-body { flex:1 1 auto; cursor:pointer; min-width:0; }
  .mapa-item .mi-placa { font-family:'IBM Plex Mono', ui-monospace, monospace; font-weight:600; font-size:.82rem; line-height:1.1; }
  .mapa-item .mi-sub { font-size:.68rem; color:#8a919c; line-height:1.1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .mapa-item .mi-vel { font-size:.72rem; color:#6c757d; white-space:nowrap; }
  .mapa-item .mi-quitar { border:none; background:none; color:#c0c4c9; font-size:.95rem; line-height:1; padding:2px 4px; cursor:pointer; }
  .mapa-item .mi-quitar:hover { color:#dc3545; }

  .mk { background:none; border:none; }
  .mk-veh { position:relative; width:38px; height:28px; transform-origin:50% 50%; filter:drop-shadow(0 1px 2px rgba(0,0,0,.32)); }
  .mk-veh.sel { filter:drop-shadow(0 1px 2px rgba(0,0,0,.32)) drop-shadow(0 0 5px var(--mk-color)); }
  .mk-veh svg { position:relative; display:block; width:38px; height:28px; }
  .mk-outline { fill:none; stroke:#fff; stroke-width:5; stroke-linejoin:round; stroke-linecap:round; }
  .mk-body, .mk-bed { fill:var(--mk-color); stroke:#17221c; stroke-width:1.4; stroke-linejoin:round; }
  .mk-veh.sel .mk-body, .mk-veh.sel .mk-bed { stroke:#fff; stroke-width:2; }
  .mk-window { fill:rgba(255,255,255,.78); stroke:#17221c; stroke-width:1.2; stroke-linejoin:round; }
  .mk-line { fill:none; stroke:#17221c; stroke-width:1.2; stroke-linecap:round; }
  .mk-wheel { fill:#17221c; stroke:#fff; stroke-width:1.2; }
  .mapa-pop .mp-placa { font-family:'IBM Plex Mono', ui-monospace, monospace; font-weight:700; font-size:.95rem; }
  .mapa-pop .mp-row { font-size:.78rem; margin-top:2px; }
  .mapa-pop .mp-lbl { color:#8a919c; display:inline-block; min-width:74px; }
  .mapa-pop .mp-actions { margin-top:.5rem; }
  .leaflet-popup-content { margin:.6rem .8rem; }

  .disp-row { display:flex; align-items:center; gap:.6rem; padding:.4rem .5rem; border-bottom:1px solid #eef1ef; }
  .disp-placa { font-family:'IBM Plex Mono', ui-monospace, monospace; font-weight:600; font-size:.82rem; }
  .disp-name { font-size:.72rem; color:#8a919c; }
  .disp-imei { margin-left:auto; font-size:.68rem; color:#adb5bd; font-family:'IBM Plex Mono', monospace; }
  .disp-ya { font-size:.66rem; color:#156b45; font-weight:600; }
  .cuenta-info { border:1px solid #d7ddd9; border-left:4px solid #156b45; background:#f8faf9; padding:.55rem .7rem; }
  .cuenta-info .ci-title { font-family:'IBM Plex Mono', ui-monospace, monospace; font-weight:700; font-size:.78rem; }
  .cuenta-info .ci-grid { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:.5rem; margin-top:.35rem; }
  .cuenta-info .ci-lbl { display:block; color:#8a919c; font-size:.62rem; line-height:1.1; }
  .cuenta-info .ci-val { display:block; font-family:'IBM Plex Mono', ui-monospace, monospace; font-size:.72rem; line-height:1.2; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  @media (max-width: 767.98px) { .cuenta-info .ci-grid { grid-template-columns:repeat(2, minmax(0,1fr)); } }
</style>
