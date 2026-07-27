/* ══════════════════════════════════════════════════════════════
   Mapa GPS — seguimiento por DESPACHOS
   Armas un despacho con los carros que salen (de cualquier plataforma
   o cuenta), sumas/quitas sobre la marcha y cierras el seguimiento.
════════════════════════════════════════════════════════════════ */
const CTRL_MAPA = './modules/GPS/Mapa/controllers/mapaController.php';

$(document).ready(function () {
    if (!document.getElementById('mapaGPS')) return;

    let mapa, capa, recorridoLayer = null, datos = [], markers = {}, timer = null, cargando = false, ajustado = false, seleccionado = null, recorridoId = null;
    let timerWorker = null;
    let despachoActual = '';   // '' = todos los activos ; o el id de un despacho
    let estadoDespachos = 'activo';
    let ultimoResumen = null, estadoWorkerOptimus = null, reporteActual = null;

    // ── Mapa base + selector de capas (calles / satélite) ──────
    mapa = L.map('mapaGPS', { zoomControl: true }).setView([15.5, -87.9], 8);

    const baseCalles = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; OpenStreetMap'
    });
    const baseSat = L.tileLayer(
        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19, attribution: 'Imagery &copy; Esri, Maxar, Earthstar Geographics'
    });
    const capaEtiquetas = L.tileLayer(
        'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}',
        { maxZoom: 19 });
    const baseSatEtiquetas = L.layerGroup([baseSat, capaEtiquetas]);

    const BASES = { 'Calles': baseCalles, 'Satélite': baseSat, 'Satélite + etiquetas': baseSatEtiquetas };
    const baseGuardada = localStorage.getItem('mapaGPS_base');
    (BASES[baseGuardada] || baseCalles).addTo(mapa);
    L.control.layers(BASES, null, { collapsed: true, position: 'topright' }).addTo(mapa);
    mapa.on('baselayerchange', e => localStorage.setItem('mapaGPS_base', e.name));

    capa = L.layerGroup().addTo(mapa);

    // ── Dirección bajo demanda (Optimus no la envía) ───────────
    const geoCache = {};
    function geocode(lat, lng) {
        const key = lat.toFixed(4) + ',' + lng.toFixed(4);
        if (geoCache[key] !== undefined) return Promise.resolve(geoCache[key]);
        const ctl = new AbortController();
        const tm = setTimeout(() => ctl.abort(), 6500);
        return fetch(`https://nominatim.openstreetmap.org/reverse?format=json&zoom=16&addressdetails=0&lat=${lat}&lon=${lng}`, { signal: ctl.signal })
            .then(r => r.json())
            .then(d => { const a = (d && d.display_name) ? d.display_name : null; geoCache[key] = a; return a; })
            .catch(() => null)
            .finally(() => clearTimeout(tm));
    }
    mapa.on('popupopen', function (e) {
        const m = e.popup._source; if (!m || !m._veh) return;
        const v = m._veh; if (v.direccion) return;
        const el = e.popup.getElement().querySelector('.mp-ubic');
        if (!el || el.dataset.geo) return;
        geocode(v.lat, v.lng).then(a => { el.textContent = a || 'Dirección no disponible'; el.dataset.geo = '1'; if (a) v.direccion = a; });
    });

    // ── Colores y estado ───────────────────────────────────────
    const SEG = { live: '#156b45', sin_senal: '#6c757d', pendiente: '#d9a300', historial: '#0d6efd' };
    const MOV = { mov: '#156b45', idle: '#d9a300', stop: '#6c757d' };
    const ETI = { mov: 'En movimiento', idle: 'Ralentí', stop: 'Detenido' };
    function movimiento(v) { if ((v.velocidad || 0) > 0) return 'mov'; if (Number(v.encendido) === 1) return 'idle'; return 'stop'; }
    function tienePos(v) { return v.lat != null && v.lng != null; }
    function formatoDuracion(min) {
        if (min == null || isNaN(Number(min))) return '';
        const n = Math.max(0, Number(min));
        const h = Math.floor(n / 60), m = n % 60;
        return h ? `${h}h ${m}m` : `${m}m`;
    }
    function textoTramo(v) {
        if (v.estado_tramo === 'en_ruta') return `En ruta desde ${v.fecha_inicio_tramo || '—'}`;
        if (v.estado_tramo === 'finalizado') return `Finalizado ${formatoDuracion(v.duracion_minutos) || ''}`.trim();
        return 'Ruta sin iniciar';
    }
    function botonesTramo(v, enPopup = false) {
        if (estadoDespachos === 'cerrado') return '';
        const cls = enPopup ? 'btn btn-sm' : 'mi-ruta';
        if (v.estado_tramo === 'en_ruta') {
            return `<button class="${cls} btn-outline-success btn-finalizar-tramo" type="button" title="Finalizar ruta" data-id="${v.id_dv}">
                      <i class="bi bi-flag"></i>${enPopup ? ' Finalizar ruta' : ''}
                    </button>`;
        }
        return `<button class="${cls} btn-outline-primary btn-iniciar-tramo" type="button" title="Iniciar ruta" data-id="${v.id_dv}">
                  <i class="bi bi-play-fill"></i>${enPopup ? ' Iniciar ruta' : ''}
                </button>`;
    }
    function botonesIncidencias(v, enPopup = false) {
        if (enPopup) {
            const registrar = estadoDespachos === 'cerrado' ? '' :
                `<button class="btn btn-sm btn-outline-warning btn-registrar-incidencia" type="button" data-id="${v.id_dv}">
                   <i class="bi bi-exclamation-triangle me-1"></i> Incidencia
                 </button>`;
            return `${registrar}
                <button class="btn btn-sm btn-outline-secondary btn-ver-incidencias" type="button" data-id="${v.id_dv}">
                  <i class="bi bi-list-check me-1"></i> Ver incidencias
                </button>`;
        }
        const abiertas = Number(v.incidencias_abiertas || 0);
        const total = Number(v.incidencias_total || 0);
        const cls = abiertas ? 'btn-outline-danger' : (total ? 'btn-outline-secondary' : 'btn-outline-warning');
        const accion = total ? 'btn-ver-incidencias' : 'btn-registrar-incidencia';
        const titulo = total ? 'Ver incidencias' : 'Registrar incidencia';
        return `<button class="mi-ruta ${accion} ${cls}"
                    type="button" title="${titulo}" data-id="${v.id_dv}">
                  <i class="bi bi-exclamation-triangle"></i>
                </button>`;
    }

    function icono(v, activo = false) {
        const est = movimiento(v), col = MOV[est];
        const rumbo = Number(v.rumbo || 0);
        return L.divIcon({ className: 'mk', iconSize: [38, 28], iconAnchor: [19, 20],
            html: `<div class="mk-veh ${est} ${activo ? 'sel' : ''}" style="--mk-color:${col}; transform:rotate(${rumbo}deg)">
                     <svg viewBox="0 0 76 48" aria-hidden="true">
                       <path class="mk-outline" d="M3 29h6l5-8h17v20H24a8 8 0 0 0-16 0H3zM34 15h38v26h-6a7 7 0 0 0-14 0h-4a7 7 0 0 0-14 0H34z"/>
                       <path class="mk-body" d="M3 29h6l5-8h17v20H24a8 8 0 0 0-16 0H3zM34 15h38v26h-6a7 7 0 0 0-14 0h-4a7 7 0 0 0-14 0H34z"/>
                       <path class="mk-bed" d="M34 8h5l2 3h26l2-3h3v6H34z"/>
                       <path class="mk-window" d="M16 24h11v7H14z"/>
                       <path class="mk-line" d="M34 15h38M15 21h16M3 34h4"/>
                       <circle class="mk-wheel" cx="16" cy="41" r="5"/>
                       <circle class="mk-wheel" cx="44" cy="41" r="5"/>
                       <circle class="mk-wheel" cx="58" cy="41" r="5"/>
                     </svg>
                   </div>` });
    }
    function popup(v) {
        const est = movimiento(v);
        return `<div class="mapa-pop">
            <div class="mp-placa">${esc(v.placa)}</div>
            <div class="mp-row"><span class="mp-lbl">Movimiento</span>
                <span style="color:${MOV[est]};font-weight:600">${ETI[est]}</span>
                ${(v.velocidad || 0) > 0 ? ' · ' + v.velocidad + ' km/h' : ''}</div>
            <div class="mp-row"><span class="mp-lbl">Despacho</span>${esc(v.despacho || '—')}</div>
            <div class="mp-row"><span class="mp-lbl">Plataforma</span>${esc(v.plataforma || '—')}</div>
            <div class="mp-row"><span class="mp-lbl">Transporte</span>${esc(v.transporte || '—')}</div>
            <div class="mp-row"><span class="mp-lbl">Ubicación</span><span class="mp-ubic">${v.direccion ? esc(v.direccion) : 'Buscando dirección…'}</span></div>
            <div class="mp-row"><span class="mp-lbl">Reporte</span>${esc(v.fecha || '—')}</div>
            <div class="mp-row"><span class="mp-lbl">Ruta</span>${esc(textoTramo(v))}</div>
            <div class="mp-row"><span class="mp-lbl">Incidencias</span>${esc(v.incidencias_total || 0)} (${esc(v.incidencias_abiertas || 0)} abiertas)</div>
            <div class="mp-actions">
              ${botonesTramo(v, true)}
              ${botonesIncidencias(v, true)}
              <button class="btn btn-sm btn-outline-primary btn-recorrido" type="button" data-id="${v.id_dv}" data-tramo="${v.id_tramo || ''}">
                <i class="bi bi-signpost-split me-1"></i> Ver recorrido
              </button>
            </div>
        </div>`;
    }

    // ── Filtros ────────────────────────────────────────────────
    function opciones(sel, valores) {
        const $s = $(sel), actual = $s.val();
        $s.find('option:not(:first)').remove();
        valores.filter(Boolean).sort().forEach(v => $s.append(`<option value="${esc(v)}">${esc(v)}</option>`));
        $s.val(actual);
    }
    function poblarFiltros() { opciones('#fltTransporte', [...new Set(datos.map(v => v.transporte))]); }
    function filtrar() {
        const tr = $('#fltTransporte').val(), es = $('#fltEstado').val(), pl = ($('#fltPlaca').val() || '').trim().toUpperCase();
        return datos.filter(v => (!tr || v.transporte === tr) && (!es || v.estado_seg === es) &&
            (!pl || (v.placa || '').toUpperCase().includes(pl)));
    }

    // ── Render ─────────────────────────────────────────────────
    function render() {
        const lista = filtrar();
        capa.clearLayers(); markers = {};
        const bounds = [];
        lista.forEach(v => {
            if (!tienePos(v)) return;
            const activo = String(v.id_dv) === String(seleccionado);
            const m = L.marker([v.lat, v.lng], { icon: icono(v, activo), zIndexOffset: activo ? 1000 : 0 }).bindPopup(popup(v));
            m._veh = v; m.on('click', () => seleccionarVehiculo(v.id_dv, false));
            m.addTo(capa); markers[v.id_dv] = m; bounds.push([v.lat, v.lng]);
        });

        const $l = $('#mapaLista').empty();
        if (!lista.length) {
            const msg = estadoDespachos === 'cerrado'
                ? (despachoActual ? 'Este despacho cerrado no tiene recorrido guardado.' : 'Selecciona un despacho cerrado para revisar su historial.')
                : (despachoActual ? 'Este despacho no tiene carros. Usa “Agregar vehículos”.'
                    : 'No hay despachos activos. Usa “Preparar despacho”.');
            $l.html(`<div class="text-muted small p-3">${msg}</div>`);
        } else {
            lista.forEach(v => {
                const seg = v.estado_seg;
                let der = seg === 'live' ? ((v.velocidad || 0) > 0 ? v.velocidad + ' km/h' : 'Detenido')
                        : seg === 'pendiente' ? 'Pendiente' : seg === 'historial' ? 'Historial' : 'Sin señal';
                const sub = [v.transporte, v.plataforma, estadoDespachos === 'cerrado' && v.fecha_cierre ? 'Cerrado ' + v.fecha_cierre : ''].filter(Boolean).join(' · ');
                const ruta = textoTramo(v);
                const incTxt = Number(v.incidencias_total || 0)
                    ? `<br><span class="mi-inc">${esc(v.incidencias_total)} incidencias · ${esc(v.incidencias_abiertas || 0)} abiertas</span>`
                    : '';
                const btnQuitar = estadoDespachos === 'cerrado' ? '' :
                    `<button class="mi-descartar" title="Descartar (agregado por error)" data-id="${v.id_dv}">
                         <i class="bi bi-slash-circle"></i></button>`;
                $l.append(
                    `<div class="mapa-item ${String(v.id_dv) === String(seleccionado) ? 'activo' : ''}" data-id="${v.id_dv}">
                       <span class="mi-dot" style="background:${SEG[seg] || SEG.sin_senal}"></span>
                       <span class="mi-body" data-id="${v.id_dv}">
                         <span class="mi-placa">${esc(v.placa)}</span><br>
                         <span class="mi-sub">${esc(sub)}</span><br>
                         <span class="mi-tramo">${esc(ruta)}</span>${incTxt}
                       </span>
                       <span class="mi-vel">${esc(der)}</span>
                       ${botonesTramo(v)}
                       ${botonesIncidencias(v)}
                       ${btnQuitar}
                     </div>`);
            });
        }
        if (!ajustado && bounds.length) { mapa.fitBounds(bounds, { padding: [30, 30], maxZoom: 14 }); ajustado = true; }
        estadoTexto(lista);
    }
    function estadoTexto(lista) {
        const vivo = lista.filter(v => v.estado_seg === 'live').length;
        if (estadoDespachos === 'cerrado') {
            $('#mapaStatus').text(`${lista.length} carros · historial · ${new Date().toLocaleTimeString('es-HN')}`);
            return;
        }
        $('#mapaStatus').text(`${lista.length} carros · ${vivo} en vivo · ${new Date().toLocaleTimeString('es-HN')}`);
    }

    function seleccionarVehiculo(id, volar = true) {
        seleccionado = id;
        $('.mapa-item').removeClass('activo');
        $(`.mapa-item[data-id="${id}"]`).addClass('activo');
        Object.keys(markers).forEach(k => {
            const m = markers[k], activo = String(k) === String(id);
            if (m && m._veh) {
                m.setIcon(icono(m._veh, activo));
                m.setZIndexOffset(activo ? 1000 : 0);
            }
        });
        const m = markers[id];
        if (m && volar) mapa.flyTo(m.getLatLng(), Math.max(mapa.getZoom(), 15));
        if (m) m.openPopup();
    }

    function limpiarRecorrido() {
        if (recorridoLayer) {
            mapa.removeLayer(recorridoLayer);
            recorridoLayer = null;
        }
        recorridoId = null;
        $('#recorridoCheckpoint').remove();
    }

    function fechaPunto(p) {
        return p.fecha || p.fecha_captura || 'Sin fecha';
    }

    function popupCheckpoint(p, idx) {
        return `<div class="mapa-pop">
            <div class="mp-placa">Checkpoint ${idx + 1}</div>
            <div class="mp-row"><span class="mp-lbl">Fecha</span>${esc(fechaPunto(p))}</div>
            <div class="mp-row"><span class="mp-lbl">Velocidad</span>${esc(p.velocidad == null ? '—' : p.velocidad + ' km/h')}</div>
            <div class="mp-row"><span class="mp-lbl">Ubicación</span>${esc(p.direccion || 'Sin dirección guardada')}</div>
            <div class="mp-row"><span class="mp-lbl">Coords</span>${Number(p.lat).toFixed(6)}, ${Number(p.lng).toFixed(6)}</div>
        </div>`;
    }

    function mostrarRecorrido(id, idTramo) {
        const v = datos.find(x => String(x.id_dv) === String(id));
        $('#mapaStatus').text(`Cargando recorrido de ${v ? v.placa : 'vehículo'}…`);
        $.post(CTRL_MAPA, { accion: 'recorrido', id_dv: id, id_tramo: idTramo || '' }, function (r) {
            if (!r.ok) {
                Swal.fire({ icon: 'error', title: 'No se pudo cargar', text: r.mensaje || 'Error desconocido.' });
                return;
            }
            const puntos = (r.data.puntos || []).filter(p => p.lat != null && p.lng != null);
            if (puntos.length < 2) {
                Swal.fire({
                    icon: 'info',
                    title: 'Recorrido aún corto',
                    text: 'Se necesitan al menos dos reportes guardados para dibujar la ruta.'
                });
                estadoTexto(filtrar());
                return;
            }
            limpiarRecorrido();
            const coords = puntos.map(p => [Number(p.lat), Number(p.lng)]);
            const linea = L.polyline(coords, {
                color: '#0f5434',
                weight: 4,
                opacity: .9,
                lineJoin: 'round'
            });
            const checkpoints = puntos.map((p, idx) => {
                const esInicio = idx === 0, esFin = idx === puntos.length - 1;
                const marker = L.circleMarker(coords[idx], {
                    radius: esInicio || esFin ? 5 : 3,
                    color: esFin ? '#156b45' : '#0f5434',
                    weight: 2,
                    fillColor: esInicio ? '#fff' : (esFin ? '#156b45' : '#d9a300'),
                    fillOpacity: 1
                }).bindPopup(popupCheckpoint(p, idx));
                marker.on('click', () => $('#recorridoCheckpoint').val(String(idx)));
                return marker;
            });
            recorridoLayer = L.layerGroup([linea, ...checkpoints]).addTo(mapa);
            recorridoId = id;
            mapa.fitBounds(linea.getBounds(), { padding: [35, 35], maxZoom: 15 });
            const opciones = puntos.map((p, idx) => `<option value="${idx}">${idx + 1}. ${esc(fechaPunto(p))}</option>`).join('');
            $('#mapaStatus').html(`${puntos.length} checkpoints ·
                <select class="form-select form-select-sm d-inline-block w-auto ms-1" id="recorridoCheckpoint">${opciones}</select>
                <button class="btn btn-link btn-sm p-0 align-baseline ms-1" id="btnLimpiarRecorrido">ocultar</button>`);
            $('#recorridoCheckpoint').data('puntos', puntos);
        }, 'json');
    }

    // Clic en la ficha → volar al carro
    $('#mapaLista').on('click', '.mi-body', function () {
        seleccionarVehiculo($(this).data('id'));
    });
    $(document).on('click', '.btn-recorrido', function () {
        mostrarRecorrido($(this).data('id'), $(this).data('tramo'));
    });
    $(document).on('click', '.btn-iniciar-tramo', function (e) {
        e.stopPropagation();
        cambiarTramo('iniciarTramo', $(this).data('id'));
    });
    $(document).on('click', '.btn-finalizar-tramo', function (e) {
        e.stopPropagation();
        const id = $(this).data('id');
        const v = datos.find(x => String(x.id_dv) === String(id));
        Swal.fire({
            icon: 'question',
            title: '¿Finalizar ruta?',
            text: v ? `${v.placa} quedará medido hasta su última posición registrada.` : '',
            showCancelButton: true,
            confirmButtonText: 'Finalizar',
            cancelButtonText: 'Cancelar'
        }).then(r => { if (r.isConfirmed) cambiarTramo('finalizarTramo', id); });
    });
    $(document).on('click', '.btn-registrar-incidencia, .btn-ver-incidencias', function (e) {
        e.stopPropagation();
        abrirIncidenciasVehiculo($(this).data('id'), $(this).hasClass('btn-registrar-incidencia'));
    });
    $(document).on('click', '#btnLimpiarRecorrido', function () {
        limpiarRecorrido();
        estadoTexto(filtrar());
    });
    $(document).on('change', '#recorridoCheckpoint', function () {
        const puntos = $(this).data('puntos') || [];
        const p = puntos[Number($(this).val())];
        if (!p) return;
        mapa.flyTo([Number(p.lat), Number(p.lng)], Math.max(mapa.getZoom(), 15));
        if (recorridoLayer) {
            const layers = recorridoLayer.getLayers();
            const marker = layers[Number($(this).val()) + 1]; // +1 porque la linea es el primer layer
            if (marker && marker.openPopup) marker.openPopup();
        }
    });
    // Descartar carro agregado por error (soft-delete con motivo = 'error')
    $('#mapaLista').on('click', '.mi-descartar', function () {
        const id = $(this).data('id');
        const v = datos.find(x => x.id_dv == id);
        Swal.fire({ icon: 'question', title: '¿Agregado por error?',
            text: v ? `${v.placa} se descartará del despacho. No aparecerá en el reporte ni en el histórico.` : '',
            showCancelButton: true, confirmButtonText: 'Sí, fue error', cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d9a300'
        }).then(r => {
            if (!r.isConfirmed) return;
            $.post(CTRL_MAPA, { accion: 'quitarVehiculo', id_dv: id, motivo: 'error' }, function (resp) {
                if (!resp.ok) { Swal.fire({ icon: 'error', title: 'Error', text: resp.mensaje }); return; }
                cargar(false);
            }, 'json');
        });
    });

    const modalIncidencias = () => bootstrap.Modal.getOrCreateInstance(document.getElementById('modalIncidenciasVeh'));
    function abrirIncidenciasVehiculo(id, registrar = false) {
        const v = datos.find(x => String(x.id_dv) === String(id));
        $('#incIdDv').val(id);
        $('#incidenciasSub').text(v ? `${v.placa} · ${v.transporte || ''} · ${v.plataforma || ''}` : 'Seguimiento por vehiculo');
        $('#formNuevaIncidencia').toggleClass('d-none', estadoDespachos === 'cerrado');
        $('#incDescripcion').val('');
        $('#incSeveridad').val('media');
        $('#incTipo').val('Detencion no autorizada');
        $('#incidenciasWrap').html('<div class="text-muted small py-3"><span class="spinner-border spinner-border-sm me-1"></span> Cargando incidencias...</div>');
        modalIncidencias().show();
        cargarIncidenciasVehiculo(id).always(() => {
            if (registrar && estadoDespachos !== 'cerrado') setTimeout(() => $('#incDescripcion').trigger('focus'), 250);
        });
    }
    function badgeIncidencia(i) {
        const sev = String(i.severidad || 'media').toLowerCase();
        const cls = sev === 'alta' ? 'text-danger' : (sev === 'baja' ? 'text-success' : 'text-warning');
        return `<span class="${cls}">${esc(sev.toUpperCase())}</span>`;
    }
    function renderIncidencias(resp) {
        const actuales = resp.actuales || [];
        const historial = resp.historial || [];
        const actualesHtml = actuales.length ? actuales.map(i => `
            <div class="inc-row ${esc(i.severidad || 'media')}">
              <div class="inc-head">
                <span>${esc(i.tipo)}</span>
                ${badgeIncidencia(i)}
                <span class="inc-meta">${esc(i.estado)} · ${esc(i.fecha_incidencia || '')}</span>
                ${i.estado !== 'cerrada' ? `<button class="btn btn-sm btn-outline-success ms-auto btn-cerrar-incidencia" type="button" data-id="${i.id_incidencia}">
                  <i class="bi bi-check2-circle me-1"></i> Cerrar
                </button>` : ''}
              </div>
              ${i.descripcion ? `<div class="inc-desc">${esc(i.descripcion)}</div>` : ''}
              <div class="inc-meta">${esc(i.direccion || 'Sin direccion guardada')}</div>
            </div>`).join('') : '<div class="text-muted small mb-3">Este despacho no tiene incidencias para este equipo.</div>';
        const histHtml = historial.length ? historial.map(i => `
            <div class="inc-row ${esc(i.severidad || 'media')}">
              <div class="inc-head">
                <span>${esc(i.tipo)}</span>
                ${badgeIncidencia(i)}
                <span class="inc-meta">${esc(i.estado)} · ${esc(i.fecha_incidencia || '')}</span>
              </div>
              <div class="inc-meta">${esc(i.despacho || '')}</div>
              ${i.descripcion ? `<div class="inc-desc">${esc(i.descripcion)}</div>` : ''}
            </div>`).join('') : '<div class="text-muted small">Sin historial previo para este equipo.</div>';
        $('#incidenciasWrap').html(`
            <div class="mb-3">
              <h6 class="mb-2">En este despacho</h6>
              ${actualesHtml}
            </div>
            <div>
              <h6 class="mb-2">Historial del equipo</h6>
              ${histHtml}
            </div>`);
    }
    function cargarIncidenciasVehiculo(id) {
        return $.post(CTRL_MAPA, { accion: 'incidenciasVehiculo', id_dv: id }, function (r) {
            if (!r.ok) {
                $('#incidenciasWrap').html(`<div class="text-danger small">${esc(r.mensaje || 'No se pudieron cargar las incidencias.')}</div>`);
                return;
            }
            renderIncidencias(r.data || {});
        }, 'json');
    }
    $('#formNuevaIncidencia').on('submit', function (e) {
        e.preventDefault();
        const id = $('#incIdDv').val();
        if (!id) return;
        $('#btnGuardarIncidencia').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Guardando...');
        $.post(CTRL_MAPA, {
            accion: 'crearIncidencia',
            id_dv: id,
            tipo: $('#incTipo').val(),
            severidad: $('#incSeveridad').val(),
            descripcion: $('#incDescripcion').val()
        }, function (r) {
            if (!r.ok) {
                Swal.fire({ icon: 'error', title: 'No se pudo guardar', text: r.mensaje || 'Error desconocido.' });
                return;
            }
            $('#incDescripcion').val('');
            cargarIncidenciasVehiculo(id);
            cargar(false).always(() => { if (estadoDespachos !== 'cerrado') cargar(true); });
        }, 'json').always(function () {
            $('#btnGuardarIncidencia').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i> Registrar incidencia');
        });
    });
    $('#incidenciasWrap').on('click', '.btn-cerrar-incidencia', function () {
        const idInc = $(this).data('id');
        const idDv = $('#incIdDv').val();
        $.post(CTRL_MAPA, { accion: 'cerrarIncidencia', id_incidencia: idInc }, function (r) {
            if (!r.ok) {
                Swal.fire({ icon: 'error', title: 'No se pudo cerrar', text: r.mensaje || 'Error desconocido.' });
                return;
            }
            cargarIncidenciasVehiculo(idDv);
            cargar(false).always(() => { if (estadoDespachos !== 'cerrado') cargar(true); });
        }, 'json');
    });

    function cambiarTramo(accion, id) {
        $.post(CTRL_MAPA, { accion, id_dv: id }, function (r) {
            if (!r.ok) {
                Swal.fire({ icon: 'error', title: 'No se pudo guardar', text: r.mensaje || 'Error desconocido.' });
                return;
            }
            Swal.fire({ icon: 'success', title: r.mensaje || 'Listo', timer: 1100, showConfirmButton: false });
            cargar(false).always(() => cargar(true));
        }, 'json');
    }

    // ── Carga de posiciones ────────────────────────────────────
    function pintar(vehiculos, resumen = ultimoResumen) {
        datos = vehiculos || [];
        ultimoResumen = resumen || null;
        poblarFiltros();
        render();
        renderPlataformasStatus();
    }
    function cargar(live) {
        if (cargando) return $.Deferred().resolve().promise();
        const historico = estadoDespachos === 'cerrado';
        live = live && !historico;
        if (historico && !despachoActual) {
            pintar([]);
            aplicarModoDespacho();
            return $.Deferred().resolve().promise();
        }
        cargando = true;
        if (live) $('#btnMapaRefrescar').prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-1"></span> Actualizando…');
        return $.post(CTRL_MAPA, {
            accion: live ? 'posiciones' : 'cache',
            id_despacho: despachoActual,
            historico: historico ? 1 : 0
        }, function (r) {
            if (r && r.ok) {
                pintar(r.data.vehiculos, r.data.resumen || null);
                if (live && r.data.resumen) {
                    avisarErrores(r.data.resumen);
                    avisarSinMatch(r.data.resumen);
                }
            }
            else if (r && !r.ok) $('#mapaStatus').text('Error: ' + (r.mensaje || 'desconocido'));
        }, 'json').always(function () {
            cargando = false;
            $('#btnMapaRefrescar').prop('disabled', false)
                .html(historico ? '<i class="bi bi-clock-history me-1"></i> Recargar historial' : '<i class="bi bi-arrow-clockwise me-1"></i> Actualizar');
        });
    }

    function iniciarAutoRefresh() {
        if (estadoDespachos === 'cerrado') return detenerAutoRefresh();
        $('#mapaAutoRefresh').prop('checked', true).prop('disabled', true);
        if (timer) return;
        timer = setInterval(() => cargar(true), 30000);
    }

    function detenerAutoRefresh() {
        if (timer) { clearInterval(timer); timer = null; }
        $('#mapaAutoRefresh').prop('checked', false).prop('disabled', true);
    }

    function sincronizarAutoRefresh() {
        if (estadoDespachos === 'cerrado') detenerAutoRefresh();
        else iniciarAutoRefresh();
    }

    let erroresAvisados = '';
    function avisarErrores(resumen) {
        if (!resumen.errores || !resumen.errores.length) { erroresAvisados = ''; return; }
        const firma = resumen.errores.map(e => e.plataforma).join('|');
        if (firma === erroresAvisados) return; erroresAvisados = firma;
        const lst = resumen.errores.map(e => `<li><b>${esc(e.plataforma)}</b> (${esc(e.transporte)}): ${esc(e.mensaje)}</li>`).join('');
        Swal.fire({ icon: 'warning', title: 'Algunas cuentas no respondieron', html: `<ul class="text-start small mb-0">${lst}</ul>` });
    }
    function avisarSinMatch(resumen) {
        if (!resumen || resumen.en_vivo || !resumen.lecturas || !resumen.sin_match) return;
        Swal.fire({
            icon: 'info',
            title: 'La plataforma respondió, pero no se ubicaron carros',
            text: 'La cuenta devolvió posiciones, pero no coinciden con los IMEI o placas del despacho. Revisa que los carros se hayan agregado desde esa misma cuenta.'
        });
    }

    function renderPlataformasStatus() {
        const $box = $('#platformStatus');
        if (!$box.length) return;
        if (estadoDespachos === 'cerrado') {
            $box.html('<span class="text-muted small">Historial: plataformas sin consulta en vivo.</span>');
            return;
        }
        if (!datos.length) {
            $box.html('<span class="text-muted small">Sin carros para validar plataformas.</span>');
            return;
        }

        const errores = {};
        (ultimoResumen?.errores || []).forEach(e => {
            errores[`${e.plataforma || ''}|${e.transporte || ''}`] = e.mensaje || 'No respondió.';
        });

        const grupos = {};
        datos.forEach(v => {
            const key = `${v.id_cuenta || ''}|${v.plataforma || ''}|${v.transporte || ''}|${v.usuario || ''}`;
            if (!grupos[key]) {
                grupos[key] = {
                    plataforma: v.plataforma || 'Plataforma',
                    transporte: v.transporte || 'Transporte',
                    usuario: v.usuario || '',
                    motor: v.motor || '',
                    total: 0,
                    live: 0,
                    sin: 0,
                    pendiente: 0,
                    sinImei: 0,
                    conPos: 0,
                    error: null
                };
            }
            const g = grupos[key];
            g.total++;
            if (v.estado_seg === 'live') g.live++;
            if (v.estado_seg === 'sin_senal') g.sin++;
            if (v.estado_seg === 'pendiente') g.pendiente++;
            if (!v.imei) g.sinImei++;
            if (tienePos(v)) g.conPos++;
            const errKey = `${v.plataforma || ''}|${v.transporte || ''}`;
            if (errores[errKey]) g.error = errores[errKey];
        });

        const html = Object.values(grupos).map(g => {
            let cls = 'warn', msg = 'Sin señal';
            if (g.error) { cls = 'err'; msg = g.error; }
            else if (g.motor === 'optimus' && estadoWorkerOptimus && !estadoWorkerOptimus.ok) {
                cls = estadoWorkerOptimus.estado === 'sin_tabla' ? 'warn' : 'err';
                msg = estadoWorkerOptimus.detalle || 'Worker Optimus detenido';
            } else if (g.live > 0) {
                cls = 'ok'; msg = `${g.live}/${g.total} en vivo`;
            } else if (g.conPos > 0) {
                msg = `${g.conPos}/${g.total} con posición vieja`;
            } else if (g.sinImei > 0) {
                msg = `${g.sinImei}/${g.total} sin IMEI`;
            } else if (g.pendiente > 0) {
                msg = 'Pendiente';
            }
            const nombre = [g.plataforma, g.usuario ? g.usuario : '', g.motor ? `(${g.motor})` : ''].filter(Boolean).join(' ');
            return `<span class="plat-chip ${cls}" title="${esc(msg)}">
                      <span class="pc-main">${esc(nombre)}</span>
                      <span class="pc-sub">${esc(g.transporte)} · ${esc(msg)}</span>
                    </span>`;
        }).join('');
        $box.html(html);
    }

    function revisarWorkerOptimus() {
        $.post(CTRL_MAPA, { accion: 'workerStatus', worker: 'optimus' }, function (r) {
            const $w = $('#optimusWorkerStatus');
            if (!$w.length) return;
            $w.removeClass('ok warn err');
            if (!r || !r.ok || !r.data || !r.data.worker) {
                $w.addClass('err').attr('title', 'No se pudo validar el worker de Optimus.');
                $w.find('.worker-text').text('Optimus sin validar');
                return;
            }
            const st = r.data.worker;
            estadoWorkerOptimus = st;
            const edad = Number(st.edad_seg || 0);
            if (st.ok) {
                $w.addClass('ok').attr('title', `Worker activo. Ultimo latido hace ${edad}s. ${st.detalle || ''}`);
                $w.find('.worker-text').text('Optimus activo');
            } else if (st.estado === 'sin_tabla') {
                $w.addClass('warn').attr('title', st.detalle || 'Falta migracion de heartbeats.');
                $w.find('.worker-text').text('Optimus sin monitor');
            } else {
                $w.addClass('err').attr('title', `${st.detalle || 'Worker detenido o atrasado.'} Ultimo latido: ${st.fecha_latido || 'sin registro'}`);
                $w.find('.worker-text').text('Optimus detenido');
            }
            renderPlataformasStatus();
        }, 'json').fail(function () {
            estadoWorkerOptimus = { ok: false, detalle: 'No se pudo consultar el estado del worker.' };
            $('#optimusWorkerStatus').removeClass('ok warn').addClass('err')
                .attr('title', 'No se pudo consultar el estado del worker.')
                .find('.worker-text').text('Optimus sin validar');
            renderPlataformasStatus();
        });
    }

    // ══════════════════════════════════════════════════════════
    //  DESPACHOS
    // ══════════════════════════════════════════════════════════
    function cargarDespachos(seleccionar) {
        return $.post(CTRL_MAPA, { accion: 'despachos', estado: estadoDespachos }, function (r) {
            const historico = estadoDespachos === 'cerrado';
            const etiquetaBase = historico ? 'Selecciona un despacho cerrado' : 'Todos los activos';
            const $s = $('#selDespacho').empty().append(`<option value="">${etiquetaBase}</option>`);
            if (r.ok) r.data.despachos.forEach(d =>
                $s.append(`<option value="${d.id_despacho}">${esc(d.nombre)} (${d.carros})${historico && d.fecha_cierre ? ' · ' + esc(d.fecha_cierre) : ''}</option>`));
            if (seleccionar !== undefined) $s.val(String(seleccionar));
            despachoActual = $s.val() || '';
            aplicarModoDespacho();
        }, 'json');
    }
    function aplicarModoDespacho() {
        const hay = despachoActual !== '';
        const historico = estadoDespachos === 'cerrado';
        $('#btnNuevoDespacho').prop('disabled', historico);
        $('#btnAgregarVehiculos').prop('disabled', !hay || historico);
        $('#btnReporteDespacho').prop('disabled', !hay);
        $('#btnCerrarDespacho').prop('disabled', !hay || historico);
        $('#btnMapaRefrescar').html(historico ? '<i class="bi bi-clock-history me-1"></i> Recargar historial' : '<i class="bi bi-arrow-clockwise me-1"></i> Actualizar');
        sincronizarAutoRefresh();
    }

    $('#selDespacho').on('change', function () {
        despachoActual = $(this).val() || '';
        aplicarModoDespacho(); ajustado = false; limpiarRecorrido();
        sincronizarAutoRefresh();
        if (estadoDespachos === 'cerrado') cargar(false);
        else cargar(false).always(() => cargar(true));
    });

    $('input[name="modoDespacho"]').on('change', function () {
        estadoDespachos = this.value === 'cerrado' ? 'cerrado' : 'activo';
        despachoActual = ''; seleccionado = null; ajustado = false; limpiarRecorrido();
        $('#fltEstado').val('');
        sincronizarAutoRefresh();
        cargarDespachos('').then(() => {
            if (estadoDespachos === 'cerrado') cargar(false);
            else cargar(false).always(() => cargar(true));
        });
    });

    $('#btnNuevoDespacho').on('click', function () {
        Swal.fire({
            title: 'Preparar despacho', input: 'text',
            inputLabel: 'Nombre del despacho', inputPlaceholder: 'Ej.: Salida Puerto — 18 jul',
            showCancelButton: true, confirmButtonText: 'Crear', cancelButtonText: 'Cancelar',
            inputValidator: v => (!v || !v.trim()) ? 'Escribe un nombre' : undefined
        }).then(res => {
            if (!res.isConfirmed) return;
            $.post(CTRL_MAPA, { accion: 'crearDespacho', nombre: res.value.trim() }, function (r) {
                if (!r.ok) { Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje }); return; }
                cargarDespachos(r.data.id_despacho).then(() => {
                    ajustado = false;
                    sincronizarAutoRefresh();
                    cargar(false).always(() => cargar(true));
                    abrirModalAgregar(); // arranca agregando carros de una vez
                });
            }, 'json');
        });
    });

    $('#btnCerrarDespacho').on('click', function () {
        if (!despachoActual) return;
        const nombre = $('#selDespacho option:selected').text();
        Swal.fire({ icon: 'warning', title: '¿Cerrar despacho?',
            html: `Se detiene el seguimiento de <b>${esc(nombre)}</b>.<br>Quedará guardado en el historial.`,
            showCancelButton: true, confirmButtonText: 'Sí, cerrar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545'
        }).then(r => {
            if (!r.isConfirmed) return;
            cerrarDespacho(false);
        });
    });

    function cerrarDespacho(cerrarAbiertos) {
        $.post(CTRL_MAPA, {
            accion: 'cerrarDespacho',
            id_despacho: despachoActual,
            cerrar_abiertos: cerrarAbiertos ? 1 : 0
        }, function (resp) {
            if (!resp.ok && resp.data && resp.data.requiere_confirmacion) {
                const abiertos = resp.data.abiertos || [];
                const lista = abiertos.map(t => `<li><b>${esc(t.placa)}</b> desde ${esc(t.fecha_inicio || '—')}</li>`).join('');
                Swal.fire({
                    icon: 'warning',
                    title: 'Hay rutas abiertas',
                    html: `<div class="text-start small">Antes de cerrar, estas rutas deben finalizarse:<ul class="mb-0 mt-2">${lista}</ul></div>`,
                    showCancelButton: true,
                    confirmButtonText: 'Finalizar y cerrar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#dc3545'
                }).then(r => { if (r.isConfirmed) cerrarDespacho(true); });
                return;
            }
            if (!resp.ok) { Swal.fire({ icon: 'error', title: 'Error', text: resp.mensaje }); return; }
            Swal.fire({ icon: 'success', title: 'Despacho cerrado', text: resp.mensaje || '', timer: 1600, showConfirmButton: false });
            detenerAutoRefresh();
            cargarDespachos('').then(() => { ajustado = false; cargar(false).always(() => cargar(true)); });
        }, 'json');
    }

    // ══════════════════════════════════════════════════════════
    //  SELECTOR — Agregar vehículos al despacho
    // ══════════════════════════════════════════════════════════
    const modalReporte = () => bootstrap.Modal.getOrCreateInstance(document.getElementById('modalReporteDespacho'));
    function estadoRutaReporte(e) {
        if (e.estado_tramo === 'en_ruta') return 'En ruta';
        if (e.estado_tramo === 'finalizado') return 'Finalizada';
        return 'Sin iniciar';
    }
    function promedioDuracion(equipos) {
        const nums = equipos.map(e => Number(e.duracion_minutos)).filter(n => !isNaN(n) && n >= 0);
        if (!nums.length) return null;
        return Math.round(nums.reduce((a, b) => a + b, 0) / nums.length);
    }
    function abrirReporteDespacho() {
        if (!despachoActual) return;
        $('#reporteDespachoWrap').html('<div class="text-muted small py-3"><span class="spinner-border spinner-border-sm me-1"></span> Generando reporte...</div>');
        $('#btnExportReporteCsv').prop('disabled', true);
        modalReporte().show();
        $.post(CTRL_MAPA, { accion: 'reporteDespacho', id_despacho: despachoActual }, function (r) {
            if (!r.ok) {
                $('#reporteDespachoWrap').html(`<div class="text-danger small">${esc(r.mensaje || 'No se pudo generar el reporte.')}</div>`);
                return;
            }
            reporteActual = r.data || null;
            renderReporteDespacho(reporteActual);
            $('#btnExportReporteCsv').prop('disabled', !reporteActual || !(reporteActual.equipos || []).length);
        }, 'json');
    }
    function renderReporteDespacho(rep) {
        const d = rep?.despacho || {};
        const equipos = rep?.equipos || [];
        const finalizadas = equipos.filter(e => e.estado_tramo === 'finalizado').length;
        const abiertas = equipos.filter(e => e.estado_tramo === 'en_ruta').length;
        const incTotal = equipos.reduce((n, e) => n + Number(e.incidencias_total || 0), 0);
        const incAbiertas = equipos.reduce((n, e) => n + Number(e.incidencias_abiertas || 0), 0);
        const prom = promedioDuracion(equipos);
        $('#reporteDespachoSub').text(`${d.nombre || 'Despacho'} · ${d.estado || ''}`);
        if (!equipos.length) {
            $('#reporteDespachoWrap').html('<div class="text-muted small">Este despacho no tiene equipos.</div>');
            return;
        }
        const filas = equipos.map(e => `
            <tr>
              <td><span class="rep-code">${esc(e.placa || '')}</span><br><span class="text-muted">${esc(e.imei || e.dispositivo || '')}</span></td>
              <td>${esc(e.transporte || '')}<br><span class="text-muted">${esc(e.plataforma || '')} · ${esc(e.usuario || '')}</span></td>
              <td>${esc(estadoRutaReporte(e))}<br><span class="text-muted">${esc(e.fecha_agregado || '')}${e.activo == 0 ? ' · removido' : ''}</span></td>
              <td>${esc(e.fecha_inicio_tramo || '')}<br><span class="text-muted">${esc(e.direccion_inicio || '')}</span></td>
              <td>${esc(e.fecha_fin_tramo || '')}<br><span class="text-muted">${esc(e.direccion_fin || '')}</span></td>
              <td>${esc(formatoDuracion(e.duracion_minutos) || '')}</td>
              <td>${esc(e.checkpoints || 0)}<br><span class="text-muted">${esc(e.primer_reporte || '')}</span></td>
              <td>${esc(e.incidencias_total || 0)}<br><span class="text-muted">${esc(e.incidencia_severidad_max || '')}${e.incidencias_abiertas ? ' · abiertas ' + esc(e.incidencias_abiertas) : ''}</span></td>
              <td>${esc(e.ultimo_reporte || e.fecha_posicion_actual || '')}<br><span class="text-muted">${esc(e.direccion_actual || '')}</span></td>
            </tr>`).join('');
        $('#reporteDespachoWrap').html(`
            <div class="rep-kpis">
              <div class="rep-kpi"><span class="rk-lbl">Equipos</span><span class="rk-val">${equipos.length}</span></div>
              <div class="rep-kpi"><span class="rk-lbl">Finalizadas</span><span class="rk-val">${finalizadas}</span></div>
              <div class="rep-kpi"><span class="rk-lbl">En ruta</span><span class="rk-val">${abiertas}</span></div>
              <div class="rep-kpi"><span class="rk-lbl">Incidencias</span><span class="rk-val">${incTotal}/${incAbiertas}</span></div>
              <div class="rep-kpi"><span class="rk-lbl">Promedio</span><span class="rk-val">${esc(formatoDuracion(prom) || '-')}</span></div>
            </div>
            <table class="table table-hover w-100 rep-table">
              <thead>
                <tr>
                  <th>Equipo</th><th>Cuenta</th><th>Estado</th><th>Inicio</th>
                  <th>Fin</th><th>Duracion</th><th>Checkpoints</th><th>Incidencias</th><th>Ultimo reporte</th>
                </tr>
              </thead>
              <tbody>${filas}</tbody>
            </table>`);
    }
    function csvVal(v) {
        return `"${String(v == null ? '' : v).replace(/"/g, '""')}"`;
    }
    function exportarReporteCsv() {
        if (!reporteActual) return;
        const d = reporteActual.despacho || {};
        const encabezado = ['Despacho', 'Estado', 'Apertura', 'Cierre', 'Placa', 'IMEI', 'Transporte', 'Plataforma', 'Usuario', 'Ruta', 'Inicio', 'Fin', 'Duracion minutos', 'Checkpoints', 'Primer reporte', 'Incidencias', 'Incidencias abiertas', 'Severidad max', 'Ultima incidencia', 'Detalle incidencias', 'Ultimo reporte'];
        const filas = (reporteActual.equipos || []).map(e => [
            d.nombre, d.estado, d.fecha_apertura, d.fecha_cierre,
            e.placa, e.imei, e.transporte, e.plataforma, e.usuario, estadoRutaReporte(e),
            e.fecha_inicio_tramo, e.fecha_fin_tramo, e.duracion_minutos,
            e.checkpoints, e.primer_reporte, e.incidencias_total, e.incidencias_abiertas,
            e.incidencia_severidad_max, e.ultima_incidencia,
            (e.incidencias_detalle || []).map(i => `${i.fecha_incidencia || ''} ${i.tipo || ''} ${i.severidad || ''} ${i.estado || ''} ${i.descripcion || ''}`).join(' | '),
            e.ultimo_reporte || e.fecha_posicion_actual
        ]);
        const csv = [encabezado, ...filas].map(row => row.map(csvVal).join(';')).join('\r\n');
        const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `reporte_despacho_${d.id_despacho || despachoActual}.csv`;
        document.body.appendChild(a);
        a.click();
        URL.revokeObjectURL(a.href);
        a.remove();
    }
    $('#btnReporteDespacho').on('click', abrirReporteDespacho);
    $('#btnExportReporteCsv').on('click', exportarReporteCsv);

    let dispCache = [], cuentaCache = [], dispositivosReq = null, dispositivosSeq = 0;
    const modalAgregar = () => bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAgregarVeh'));
    function abrirModalAgregar() {
        if (!despachoActual) return;
        detenerAutoRefresh();
        $('#modalAgregarSub').text('Despacho: ' + $('#selDespacho option:selected').text());
        $('#dispositivosWrap').html('<div class="text-muted small">Selecciona una cuenta para ver sus equipos.</div>');
        $('#cuentaInfo').addClass('d-none').empty();
        $('#buscarDispositivo').val(''); $('#selCuentaAgregar').val(''); actualizarSeleccion();
        $.post(CTRL_MAPA, { accion: 'cuentas' }, function (r) {
            cuentaCache = (r.ok && r.data.cuentas) ? r.data.cuentas : [];
            renderCuentasSelector();
        }, 'json');
        modalAgregar().show();
    }
    $('#btnAgregarVehiculos').on('click', abrirModalAgregar);
    $('#modalAgregarVeh').on('hidden.bs.modal', function () {
        if (dispositivosReq && dispositivosReq.readyState !== 4) dispositivosReq.abort();
        dispositivosSeq++;
        sincronizarAutoRefresh();
    });

    function renderCuentasSelector() {
        const $s = $('#selCuentaAgregar').empty().append('<option value="">— Selecciona una cuenta —</option>');
        const grupos = {};
        cuentaCache.forEach(c => {
            const tr = c.transporte || 'Sin transporte';
            if (!grupos[tr]) grupos[tr] = [];
            grupos[tr].push(c);
        });
        Object.keys(grupos).sort().forEach(tr => {
            const $g = $(`<optgroup label="${esc(tr)}"></optgroup>`);
            grupos[tr].sort((a, b) =>
                String(a.plataforma || '').localeCompare(String(b.plataforma || ''), 'es') ||
                String(a.usuario || '').localeCompare(String(b.usuario || ''), 'es')
            ).forEach(c => {
                const label = `${c.plataforma || 'Plataforma'} · ${c.usuario || 'sin usuario'} · ${c.vinculados || 0} vinculados`;
                $g.append(`<option value="${c.id_cuenta}">${esc(label)}</option>`);
            });
            $s.append($g);
        });
    }

    function cuentaSeleccionada() {
        const id = $('#selCuentaAgregar').val();
        return cuentaCache.find(c => String(c.id_cuenta) === String(id)) || null;
    }

    function renderCuentaInfo(cuenta, total) {
        const c = cuenta || cuentaSeleccionada();
        if (!c) { $('#cuentaInfo').addClass('d-none').empty(); return; }
        const totalTxt = total == null ? '—' : total;
        $('#cuentaInfo').removeClass('d-none').html(`
            <div class="ci-title">${esc(c.transporte || 'Sin transporte')}</div>
            <div class="ci-grid">
              <span><span class="ci-lbl">Plataforma</span><span class="ci-val" title="${esc(c.plataforma || '')}">${esc(c.plataforma || '—')}</span></span>
              <span><span class="ci-lbl">Usuario</span><span class="ci-val" title="${esc(c.usuario || '')}">${esc(c.usuario || '—')}</span></span>
              <span><span class="ci-lbl">Motor</span><span class="ci-val">${esc(c.tipo_integracion || c.motor || '—')}</span></span>
              <span><span class="ci-lbl">Equipos</span><span class="ci-val">${esc(totalTxt)} / ${esc(c.vinculados || 0)} vinculados</span></span>
            </div>`);
    }

    $('#selCuentaAgregar').on('change', function () {
        const id = $(this).val();
        if (dispositivosReq && dispositivosReq.readyState !== 4) dispositivosReq.abort();
        const seq = ++dispositivosSeq;
        dispCache = [];
        actualizarSeleccion();
        renderCuentaInfo();
        if (!id) { $('#dispositivosWrap').html('<div class="text-muted small">Selecciona una cuenta.</div>'); return; }
        $('#dispositivosWrap').html('<div class="text-muted small py-3"><span class="spinner-border spinner-border-sm me-1"></span> Cargando equipos…</div>');
        dispositivosReq = $.ajax({
            url: CTRL_MAPA,
            method: 'POST',
            dataType: 'json',
            timeout: 180000,
            data: { accion: 'dispositivos', id_cuenta: id, id_despacho: despachoActual }
        }).done(function (r) {
            if (seq !== dispositivosSeq || String($('#selCuentaAgregar').val() || '') !== String(id)) return;
            if (!r.ok) { $('#dispositivosWrap').html(`<div class="text-danger small">${esc(r.mensaje)}</div>`); return; }
            dispCache = r.data.dispositivos;
            if (r.data.cuenta) {
                const c = cuentaCache.find(x => String(x.id_cuenta) === String(r.data.cuenta.id_cuenta));
                if (c) Object.assign(c, r.data.cuenta);
            }
            renderCuentaInfo(r.data.cuenta || cuentaSeleccionada(), dispCache.length);
            renderDispositivos();
        }).fail(function (xhr, estado) {
            if (estado === 'abort' || seq !== dispositivosSeq) return;
            const msg = estado === 'timeout'
                ? 'La plataforma tardo demasiado en responder. Puedes elegir otra cuenta y volver a intentar.'
                : 'No se pudo cargar la lista de equipos.';
            $('#dispositivosWrap').html(`<div class="text-danger small">${esc(msg)}</div>`);
        });
    });
    function renderDispositivos() {
        const q = ($('#buscarDispositivo').val() || '').trim().toUpperCase();
        const lista = dispCache.filter(d => !q || d.placa.includes(q) || (d.dispositivo || '').toUpperCase().includes(q) || (d.imei || '').toUpperCase().includes(q));
        if (!lista.length) { $('#dispositivosWrap').html('<div class="text-muted small">Sin equipos que coincidan.</div>'); return; }
        $('#dispositivosWrap').html(lista.map(d => {
            const idx = dispCache.indexOf(d);
            return `<div class="disp-row">
                <input class="form-check-input disp-check" type="checkbox" data-idx="${idx}" id="disp${idx}" ${d.ya ? 'checked disabled' : ''}>
                <label for="disp${idx}" class="d-flex flex-column" style="cursor:pointer">
                  <span class="disp-placa">${esc(d.placa)}</span>
                  ${d.dispositivo && d.dispositivo.toUpperCase() !== d.placa ? `<span class="disp-name">${esc(d.dispositivo)}</span>` : ''}
                </label>
                ${d.ya ? '<span class="disp-ya">ya está</span>' : `<span class="disp-imei">${esc(d.imei || '')}</span>`}
              </div>`;
        }).join(''));
        actualizarSeleccion();
    }
    $('#buscarDispositivo').on('input', renderDispositivos);
    $('#dispositivosWrap').on('change', '.disp-check', actualizarSeleccion);
    function seleccionActual() { return $('.disp-check:checked:not(:disabled)').map(function () { return dispCache[$(this).data('idx')]; }).get(); }
    function actualizarSeleccion() {
        const n = seleccionActual().length;
        $('#dispSeleccion').text(n + ' seleccionados'); $('#btnVincularSeleccion').prop('disabled', n === 0);
    }
    $('#btnVincularSeleccion').on('click', function () {
        const items = seleccionActual().map(d => ({ id_cuenta: d.id_cuenta, placa: d.placa, imei: d.imei, dispositivo: d.dispositivo }));
        if (!despachoActual || !items.length) return;
        $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Agregando…');
        $.post(CTRL_MAPA, { accion: 'agregarADespacho', id_despacho: despachoActual, items: JSON.stringify(items) }, function (r) {
            $('#btnVincularSeleccion').html('<i class="bi bi-check-lg me-1"></i> Agregar al despacho');
            if (!r.ok) { Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje }); return; }
            Swal.fire({ icon: 'success', title: '¡Listo!', text: r.mensaje, timer: 1500, showConfirmButton: false });
            modalAgregar().hide();
            cargarDespachos(despachoActual).then(() => { ajustado = false; sincronizarAutoRefresh(); cargar(false).always(() => cargar(true)); });
        }, 'json');
    });

    // ── Controles ──────────────────────────────────────────────
    $('#btnMapaRefrescar').on('click', () => cargar(estadoDespachos !== 'cerrado'));
    $('#fltTransporte, #fltEstado').on('change', render);
    $('#fltPlaca').on('input', render);
    $('#mapaAutoRefresh').on('change', sincronizarAutoRefresh);

    function esc(s) { return (s == null ? '' : String(s)).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }

    // ── Arranque ───────────────────────────────────────────────
    revisarWorkerOptimus();
    timerWorker = setInterval(revisarWorkerOptimus, 60000);
    sincronizarAutoRefresh();
    cargarDespachos().then(() => cargar(false).always(() => cargar(true)));
});
