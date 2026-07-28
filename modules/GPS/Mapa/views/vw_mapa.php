<!-- Leaflet (mapa) — se carga antes que mapa.js (footer) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- ══════════════════════════════════════════════════════
     BARRA ÚNICA DE OPERACIÓN
     Solo lo que se usa a diario. Lo demás vive en el menú (⋯)
     y en el panel de configuración (engranaje).
══════════════════════════════════════════════════════ -->
<div class="card shadow-sm border-0 mb-2">
  <div class="card-body py-2 d-flex flex-wrap align-items-center gap-2 barra-op">

    <span class="bo-titulo"><i class="bi bi-geo-alt me-1"></i>Mapa GPS</span>
    <span class="bo-sep d-none d-md-inline"></span>

    <select class="form-select form-select-sm" id="selDespacho" style="max-width:230px">
      <option value="">Todos los activos</option>
    </select>

    <div class="btn-group btn-group-sm" role="group" aria-label="Modo de despacho">
      <input type="radio" class="btn-check" name="modoDespacho" id="modoDespachoActivo" value="activo" checked>
      <label class="btn btn-outline-primary" for="modoDespachoActivo">Activos</label>
      <input type="radio" class="btn-check" name="modoDespacho" id="modoDespachoHistorial" value="cerrado">
      <label class="btn btn-outline-primary" for="modoDespachoHistorial">Historial</label>
    </div>

    <div class="ms-auto d-flex align-items-center gap-2">
      <button class="btn btn-sm bo-campana d-none" id="btnCampanaAlertas" type="button"
              title="Ver alertas activas">
        <i class="bi bi-bell-fill"></i> <span id="campanaConteo">0</span>
      </button>

      <button class="btn btn-sm btn-primary" id="btnMapaRefrescar">
        <i class="bi bi-arrow-clockwise me-1"></i> Actualizar
      </button>

      <div class="dropdown">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
                data-bs-toggle="dropdown" aria-expanded="false" title="Acciones del despacho">
          <i class="bi bi-three-dots-vertical"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
          <li>
            <button class="dropdown-item" type="button" id="btnNuevoDespacho">
              <i class="bi bi-plus-lg me-2 text-success"></i>Preparar despacho
            </button>
          </li>
          <li>
            <button class="dropdown-item" type="button" id="btnAgregarVehiculos" disabled>
              <i class="bi bi-truck me-2 text-primary"></i>Agregar vehículos
            </button>
          </li>
          <li><hr class="dropdown-divider"></li>
          <li>
            <button class="dropdown-item" type="button" id="btnCopiarDirecciones">
              <i class="bi bi-clipboard me-2 text-info"></i>Copiar direcciones
            </button>
          </li>
          <li>
            <button class="dropdown-item" type="button" id="btnReporteDespacho" disabled>
              <i class="bi bi-file-earmark-text me-2 text-secondary"></i>Reporte del despacho
            </button>
          </li>
          <li>
            <button class="dropdown-item" type="button" id="btnHistorialAlertas">
              <i class="bi bi-clock-history me-2 text-warning"></i>Historial de alertas
            </button>
          </li>
          <li><hr class="dropdown-divider"></li>
          <li>
            <button class="dropdown-item text-danger" type="button" id="btnCerrarDespacho" disabled>
              <i class="bi bi-stop-circle me-2"></i>Cerrar despacho
            </button>
          </li>
        </ul>
      </div>

      <button class="btn btn-sm btn-outline-secondary" type="button" title="Configuración"
              data-bs-toggle="offcanvas" data-bs-target="#panelConfig">
        <i class="bi bi-gear"></i>
      </button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     CUERPO — barra lateral con pestañas + mapa
══════════════════════════════════════════════════════ -->
<div class="card shadow-sm border-0">
  <div class="card-body p-2">
    <div class="row g-2">

      <div class="col-12 col-lg-3">
        <div class="panel-lateral">
          <ul class="nav nav-tabs pl-tabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabCarros" type="button" role="tab">
                Carros <span class="pl-cont" id="contCarros">0</span>
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabAlertas" type="button" role="tab" id="tabBtnAlertas">
                Alertas <span class="pl-cont pl-cont-danger" id="contAlertas">0</span>
              </button>
            </li>
          </ul>

          <div class="tab-content">
            <div class="tab-pane fade show active" id="tabCarros" role="tabpanel">
              <div class="pl-buscar">
                <input type="text" class="form-control form-control-sm" id="fltPlaca" placeholder="Buscar placa">
                <button class="btn btn-sm btn-outline-secondary" type="button" title="Más filtros"
                        data-bs-toggle="collapse" data-bs-target="#filtrosExtra">
                  <i class="bi bi-funnel"></i>
                </button>
              </div>
              <div class="collapse" id="filtrosExtra">
                <div class="pl-filtros">
                  <select class="form-select form-select-sm mb-1" id="fltTransporte"><option value="">Todos los transportes</option></select>
                  <select class="form-select form-select-sm" id="fltEstado">
                    <option value="">Todo el seguimiento</option>
                    <option value="live">En vivo</option>
                    <option value="sin_senal">Sin señal</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="historial">Historial</option>
                  </select>
                </div>
              </div>
              <div class="mapa-lista" id="mapaLista">
                <div class="text-muted small p-3">Cargando…</div>
              </div>
            </div>

            <div class="tab-pane fade" id="tabAlertas" role="tabpanel">
              <div id="alertasPanel" class="mapa-lista"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-9">
        <div id="mapaGPS"></div>
      </div>
    </div>

    <!-- Pie de estado: una sola línea discreta -->
    <div class="pie-estado">
      <span id="mapaStatus">Cargando…</span>
      <span class="worker-status" id="optimusWorkerStatus">
        <span class="worker-dot"></span>
        <span class="worker-text">Optimus</span>
      </span>
      <span id="platformStatus" class="pe-plataformas">Validando plataformas…</span>
      <span class="pe-leyenda ms-auto">
        <span><span class="seg-dot" style="background:#156b45"></span>En vivo</span>
        <span><span class="seg-dot" style="background:#6c757d"></span>Sin señal</span>
        <span><span class="seg-dot" style="background:#d9a300"></span>Pendiente</span>
      </span>
    </div>
  </div>
</div>

<!-- Modal: estado detallado de plataformas -->
<div class="modal fade" id="modalPlataformas" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0"><i class="bi bi-hdd-network me-1"></i> Estado de plataformas</h5>
          <small class="text-muted">Una tarjeta por cuenta con carros en pantalla</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="platform-status" id="platformStatusDetalle">
          <span class="text-muted small">Validando plataformas…</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     PANEL DE CONFIGURACIÓN (engranaje)
══════════════════════════════════════════════════════ -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="panelConfig">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title"><i class="bi bi-gear me-1"></i> Configuración</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body">

    <div class="cfg-grupo">
      <div class="cfg-titulo">Actualización</div>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" id="mapaAutoRefresh" checked disabled>
        <label class="form-check-label small" for="mapaAutoRefresh">Actualizar automáticamente cada 30 s</label>
      </div>
    </div>

    <div class="cfg-grupo">
      <div class="cfg-titulo">Alertas</div>
      <div class="form-check form-switch mb-2">
        <input class="form-check-input" type="checkbox" id="alertasOn" checked>
        <label class="form-check-label small" for="alertasOn">Activar alertas</label>
      </div>

      <label class="form-label small mb-1">Avisar si está detenido más de</label>
      <div class="input-group input-group-sm mb-2">
        <input type="number" class="form-control" id="alertaMinDetenido" min="1" max="240" value="4">
        <span class="input-group-text">minutos</span>
      </div>

      <label class="form-label small mb-1">Avisar si no reporta hace más de</label>
      <div class="input-group input-group-sm mb-2">
        <input type="number" class="form-control" id="alertaMinSinReporte" min="1" max="240" value="15">
        <span class="input-group-text">minutos</span>
      </div>

      <div class="form-check form-switch mb-1">
        <input class="form-check-input" type="checkbox" id="alertaSoloEnRuta" checked>
        <label class="form-check-label small" for="alertaSoloEnRuta">Solo carros con ruta iniciada</label>
      </div>
      <div class="form-text mb-2" style="font-size:.7rem">
        Si lo apagas, alerta de todos los carros del despacho aunque no tengan ruta iniciada.
      </div>

      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" id="alertaSonido" checked>
        <label class="form-check-label small" for="alertaSonido">Sonido al aparecer una alerta</label>
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

<!-- Modal: historial de alertas automáticas -->
<div class="modal fade" id="modalAlertas" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0"><i class="bi bi-bell me-1"></i> Historial de alertas</h5>
          <small class="text-muted" id="alertasHistSub">Detenciones y pérdidas de señal registradas</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-center gap-2 mb-2">
          <select class="form-select form-select-sm" id="fltAlertaEstado" style="max-width:180px">
            <option value="todas">Todas</option>
            <option value="activa">Solo activas</option>
            <option value="resuelta">Solo resueltas</option>
          </select>
          <button class="btn btn-sm btn-outline-secondary" id="btnRefrescarAlertas">
            <i class="bi bi-arrow-clockwise"></i>
          </button>
          <span class="ms-auto small text-muted" id="alertasHistTotal"></span>
        </div>
        <div id="alertasHistWrap">
          <div class="text-muted small py-3">Cargando…</div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-primary" id="btnExportAlertasCsv">
          <i class="bi bi-filetype-csv me-1"></i> Exportar CSV
        </button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: reporte por despacho/equipo -->
<div class="modal fade" id="modalReporteDespacho" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0"><i class="bi bi-file-earmark-text me-1"></i> Reporte del despacho</h5>
          <small class="text-muted" id="reporteDespachoSub">Resumen por equipo</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="reporteDespachoWrap">
          <div class="text-muted small">Selecciona un despacho para generar el reporte.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-primary" id="btnExportReporteCsv" disabled>
          <i class="bi bi-download me-1"></i> Exportar CSV
        </button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: incidencias por vehiculo -->
<div class="modal fade" id="modalIncidenciasVeh" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0"><i class="bi bi-exclamation-triangle me-1"></i> Incidencias del equipo</h5>
          <small class="text-muted" id="incidenciasSub">Seguimiento por vehiculo</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="formNuevaIncidencia" class="inc-form mb-3">
          <input type="hidden" id="incIdDv">
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label small mb-1">Tipo</label>
              <select class="form-select form-select-sm" id="incTipo">
                <option value="Detencion no autorizada">Detencion no autorizada</option>
                <option value="Desvio de ruta">Desvio de ruta</option>
                <option value="Perdida de senal">Perdida de senal</option>
                <option value="Retraso">Retraso</option>
                <option value="Falla mecanica">Falla mecanica</option>
                <option value="Cliente no recibe">Cliente no recibe</option>
                <option value="Accidente">Accidente</option>
                <option value="Cambio de unidad">Cambio de unidad</option>
                <option value="Observacion">Observacion</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small mb-1">Severidad</label>
              <select class="form-select form-select-sm" id="incSeveridad">
                <option value="media">Media</option>
                <option value="baja">Baja</option>
                <option value="alta">Alta</option>
              </select>
            </div>
            <div class="col-md-5">
              <label class="form-label small mb-1">Comentario</label>
              <input type="text" class="form-control form-control-sm" id="incDescripcion" maxlength="800"
                     placeholder="Detalle breve de lo ocurrido">
            </div>
          </div>
          <div class="text-end mt-2">
            <button class="btn btn-sm btn-primary" id="btnGuardarIncidencia" type="submit">
              <i class="bi bi-check-lg me-1"></i> Registrar incidencia
            </button>
          </div>
        </form>
        <div id="incidenciasWrap">
          <div class="text-muted small">Selecciona un equipo para ver sus incidencias.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<style>
  /* El alto real lo calcula mapa.js según el espacio libre (ajustarAlturaMapa).
     Estos valores son solo el punto de partida antes de que corra el JS. */
  #mapaGPS { height: 460px; border: 1px solid var(--hc-banda, #d7ddd9); }
  .panel-lateral { display:flex; flex-direction:column; height:460px; }
  .panel-lateral .tab-content { flex:1 1 auto; min-height:0; display:flex; }
  .panel-lateral .tab-pane.active { display:flex; flex-direction:column; width:100%; min-height:0; }
  .mapa-lista { flex:1 1 auto; min-height:0; overflow-y:auto; background:#fff; }
  .seg-dot { display:inline-block; width:9px; height:9px; border-radius:50%; margin-right:3px; vertical-align:middle; }

  /* ── Barra única de operación ── */
  .barra-op .bo-titulo { font-family:'IBM Plex Mono', monospace; font-weight:700; font-size:.82rem; white-space:nowrap; }
  .barra-op .bo-sep { width:1px; height:22px; background:var(--hc-banda, #d7ddd9); }
  .barra-op .bo-campana { background:#fdf6f6; border:1px solid #e3c2c2; color:#b02a37; font-family:'IBM Plex Mono', monospace;
                          font-weight:700; font-size:.74rem; padding:.2rem .5rem; }
  .barra-op .bo-campana:hover { background:#f9eded; color:#7a1d26; }
  .barra-op .bo-campana i { animation:alertaPulso 1.4s ease-in-out infinite; }

  /* ── Panel lateral con pestañas ── */
  .panel-lateral { border:1px solid var(--hc-banda, #d7ddd9); background:#fff; }
  .panel-lateral .pl-tabs, .panel-lateral .pl-buscar, .panel-lateral .collapse { flex:0 0 auto; }
  .pl-tabs { border-bottom:1px solid var(--hc-banda, #d7ddd9); flex-wrap:nowrap; }
  .pl-tabs .nav-link { border:none; border-bottom:2px solid transparent; border-radius:0; padding:.4rem .6rem;
                       font-size:.75rem; color:#6c757d; white-space:nowrap; }
  .pl-tabs .nav-link.active { border-bottom-color:var(--hc-tinta, #156b45); color:var(--hc-tinta, #156b45); font-weight:600; background:none; }
  .pl-cont { display:inline-block; min-width:18px; padding:0 4px; background:#eef1ef; color:#5a615c;
             font-family:'IBM Plex Mono', monospace; font-size:.68rem; font-weight:700; text-align:center; }
  .pl-cont-danger { background:#fdf6f6; color:#b02a37; }
  .pl-buscar { display:flex; gap:.25rem; padding:.35rem; border-bottom:1px solid #eef1ef; }
  .pl-filtros { padding:.35rem; border-bottom:1px solid #eef1ef; background:#fafbfa; }

  /* ── Pie de estado ── */
  .pie-estado { display:flex; flex-wrap:wrap; align-items:center; gap:.9rem; margin-top:.5rem; padding:.35rem .25rem 0;
                border-top:1px solid #eef1ef; font-size:.68rem; color:#8a919c; }
  .pie-estado .pe-leyenda { display:flex; gap:.7rem; }
  .pie-estado .form-select-sm { font-size:.7rem; padding:.05rem 1.2rem .05rem .3rem; }
  .pie-estado .btn { font-size:.68rem; }
  .pie-estado .pe-plataformas { cursor:pointer; }
  .pie-estado .pe-link { border-bottom:1px dotted #b9c0c7; }
  .pie-estado .pe-plataformas:hover .pe-link { color:var(--hc-tinta, #156b45); border-bottom-color:var(--hc-tinta, #156b45); }
  .pie-estado .pe-link.err { color:#b02a37; border-bottom-color:#b02a37; }

  /* ── Panel de configuración ── */
  .cfg-grupo { padding-bottom:1rem; margin-bottom:1rem; border-bottom:1px solid #eef1ef; }
  .cfg-grupo:last-child { border-bottom:none; }
  .cfg-titulo { font-family:'IBM Plex Mono', monospace; font-weight:700; font-size:.72rem; text-transform:uppercase;
                letter-spacing:.04em; color:var(--hc-tinta, #156b45); margin-bottom:.5rem; }

  #alertasPanel { background:#fff; }
  #alertasPanel .ap-vacio { padding:1.2rem .6rem; color:#8a919c; font-size:.72rem; text-align:center; }
  #alertasPanel .ap-head { display:flex; align-items:center; gap:.4rem; padding:.35rem .55rem; border-bottom:1px solid #f0dcdc;
                           background:#fdf6f6; font-family:'IBM Plex Mono', monospace; font-size:.72rem; font-weight:700; color:#b02a37; }
  #alertasPanel .ap-item { display:flex; align-items:center; gap:.5rem; padding:.4rem .55rem; border-bottom:1px solid #f6eaea;
                           border-left:3px solid #b02a37; background:#fdf6f6; cursor:pointer; }
  #alertasPanel .ap-item:last-child { border-bottom:none; }
  #alertasPanel .ap-item:hover { background:#f9eded; }
  #alertasPanel .ap-placa { font-family:'IBM Plex Mono', monospace; font-weight:600; font-size:.76rem; }
  #alertasPanel .ap-txt { font-size:.68rem; color:#8a6b6b; flex:1 1 auto; }
  #alertasPanel .ap-inc { border:none; background:none; color:#b02a37; font-size:.8rem; padding:1px 3px; }
  #alertasPanel .ap-inc:hover { color:#7a1d26; }
  .mapa-item.alerta { background:#fdf6f6; border-left-color:#b02a37; }
  .mapa-item .mi-alerta { display:block; font-size:.66rem; color:#b02a37; font-weight:600; }
  @keyframes alertaPulso { 0%,100% { opacity:1; } 50% { opacity:.45; } }
  #alertasPanel .ap-head i { animation:alertaPulso 1.4s ease-in-out infinite; }
  /* Aro rojo que late alrededor del carro con alerta */
  @keyframes mkAro { 0% { transform:scale(.7); opacity:.85; } 100% { transform:scale(1.9); opacity:0; } }
  .mk-veh.alertado::before { content:''; position:absolute; left:50%; top:50%; width:30px; height:30px;
      margin:-15px 0 0 -15px; border-radius:50%; border:2.5px solid #b02a37;
      animation:mkAro 1.5s ease-out infinite; pointer-events:none; }
  .al-table { font-size:.76rem; }
  .al-table th { white-space:nowrap; }
  .al-table td { vertical-align:top; }
  .al-placa { font-family:'IBM Plex Mono', monospace; font-weight:600; }
  .al-dir { color:#8a919c; font-size:.68rem; display:block; max-width:280px; }
  .worker-status { align-items:center; gap:.35rem; font-family:'IBM Plex Mono', ui-monospace, monospace; font-size:.68rem; color:#8a919c; }
  .worker-dot { width:9px; height:9px; border-radius:50%; background:#adb5bd; box-shadow:0 0 0 2px rgba(0,0,0,.05); }
  .worker-status.ok .worker-dot { background:#156b45; }
  .worker-status.warn .worker-dot { background:#d9a300; }
  .worker-status.err .worker-dot { background:#dc3545; }
  .platform-status { display:flex; flex-wrap:wrap; gap:.45rem; }
  .plat-chip { display:inline-flex; align-items:center; gap:.4rem; border:1px solid #d7ddd9; border-left-width:4px; background:#fff; padding:.32rem .5rem; font-family:'IBM Plex Mono', ui-monospace, monospace; font-size:.68rem; }
  .plat-chip.ok { border-left-color:#156b45; }
  .plat-chip.warn { border-left-color:#d9a300; }
  .plat-chip.err { border-left-color:#dc3545; }
  .plat-chip .pc-main { font-weight:700; color:#17221c; }
  .plat-chip .pc-sub { color:#8a919c; }
  .mapa-item { display:flex; align-items:center; gap:.6rem; padding:.5rem .6rem; border-bottom:1px solid #eef1ef; border-left:3px solid transparent; }
  .mapa-item:hover { background:#f4f8f6; }
  .mapa-item.activo { background:#eaf3ee; border-left-color:var(--hc-tinta, #156b45); }
  .mapa-item .mi-dot { width:11px; height:11px; border-radius:50%; flex:0 0 auto; box-shadow:0 0 0 2px rgba(0,0,0,.06); }
  .mapa-item .mi-body { flex:1 1 auto; cursor:pointer; min-width:0; }
  .mapa-item .mi-placa { font-family:'IBM Plex Mono', ui-monospace, monospace; font-weight:600; font-size:.82rem; line-height:1.1; }
  .mapa-item .mi-sub { font-size:.68rem; color:#8a919c; line-height:1.1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .mapa-item .mi-tramo { font-size:.66rem; color:#156b45; line-height:1.1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .mapa-item .mi-vel { font-size:.72rem; color:#6c757d; white-space:nowrap; }
  .mapa-item .mi-ruta { border:1px solid #d7ddd9; background:#fff; color:#156b45; font-size:.85rem; line-height:1; padding:3px 5px; cursor:pointer; }
  .mapa-item .mi-ruta:hover { background:#eaf3ee; border-color:#156b45; }
  .mapa-item .mi-ruta.btn-outline-success { color:#0f7a3a; border-color:#b9d8c5; }
  .mapa-item .mi-inc { font-size:.66rem; color:#dc3545; font-family:'IBM Plex Mono', ui-monospace, monospace; white-space:nowrap; }
  .mapa-item .mi-descartar { border:none; background:none; color:#c0c4c9; font-size:.95rem; line-height:1; padding:2px 4px; cursor:pointer; }
  .mapa-item .mi-descartar:hover { color:#d9a300; }

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
  .rep-kpis { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:.5rem; margin-bottom:.75rem; }
  .rep-kpi { border:1px solid #d7ddd9; border-left:4px solid #156b45; padding:.5rem .65rem; background:#fff; }
  .rep-kpi .rk-lbl { display:block; color:#8a919c; font-size:.65rem; font-family:'IBM Plex Mono', ui-monospace, monospace; }
  .rep-kpi .rk-val { display:block; font-size:1rem; font-weight:700; font-family:'IBM Plex Mono', ui-monospace, monospace; }
  .rep-table th, .rep-table td { font-size:.74rem; vertical-align:top; }
  .rep-table .rep-code { font-family:'IBM Plex Mono', ui-monospace, monospace; font-weight:700; }
  .inc-form { border:1px solid #d7ddd9; border-left:4px solid #156b45; padding:.65rem; background:#f8faf9; }
  .inc-row { border:1px solid #d7ddd9; border-left:4px solid #d9a300; padding:.55rem .65rem; margin-bottom:.45rem; background:#fff; }
  .inc-row.alta { border-left-color:#dc3545; }
  .inc-row.media { border-left-color:#d9a300; }
  .inc-row.baja { border-left-color:#156b45; }
  .inc-head { display:flex; flex-wrap:wrap; gap:.45rem; align-items:center; font-family:'IBM Plex Mono', ui-monospace, monospace; font-size:.74rem; font-weight:700; }
  .inc-meta { color:#8a919c; font-size:.68rem; }
  .inc-desc { font-size:.76rem; margin-top:.25rem; }
  @media (max-width: 767.98px) { .cuenta-info .ci-grid { grid-template-columns:repeat(2, minmax(0,1fr)); } }
  @media (max-width: 767.98px) { .rep-kpis { grid-template-columns:repeat(2, minmax(0,1fr)); } }
</style>
