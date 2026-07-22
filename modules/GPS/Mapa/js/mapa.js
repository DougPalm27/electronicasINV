/* ══════════════════════════════════════════════════════════════
   Mapa GPS — seguimiento por DESPACHOS
   Armas un despacho con los carros que salen (de cualquier plataforma
   o cuenta), sumas/quitas sobre la marcha y cierras el seguimiento.
════════════════════════════════════════════════════════════════ */
const CTRL_MAPA = './modules/GPS/Mapa/controllers/mapaController.php';

$(document).ready(function () {
    if (!document.getElementById('mapaGPS')) return;

    let mapa, capa, recorridoLayer = null, datos = [], markers = {}, timer = null, cargando = false, ajustado = false, seleccionado = null, recorridoId = null;
    let despachoActual = '';   // '' = todos los activos ; o el id de un despacho
    let estadoDespachos = 'activo';

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
        return fetch(`https://nominatim.openstreetmap.org/reverse?format=json&zoom=16&addressdetails=0&lat=${lat}&lon=${lng}`)
            .then(r => r.json())
            .then(d => { const a = (d && d.display_name) ? d.display_name : null; geoCache[key] = a; return a; })
            .catch(() => null);
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
            <div class="mp-actions">
              <button class="btn btn-sm btn-outline-primary btn-recorrido" type="button" data-id="${v.id_dv}">
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
                const btnQuitar = estadoDespachos === 'cerrado' ? '' :
                    `<button class="mi-quitar" title="Quitar del despacho" data-id="${v.id_dv}">
                         <i class="bi bi-x-lg"></i></button>`;
                $l.append(
                    `<div class="mapa-item ${String(v.id_dv) === String(seleccionado) ? 'activo' : ''}" data-id="${v.id_dv}">
                       <span class="mi-dot" style="background:${SEG[seg] || SEG.sin_senal}"></span>
                       <span class="mi-body" data-id="${v.id_dv}">
                         <span class="mi-placa">${esc(v.placa)}</span><br>
                         <span class="mi-sub">${esc(sub)}</span>
                       </span>
                       <span class="mi-vel">${esc(der)}</span>
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

    function mostrarRecorrido(id) {
        const v = datos.find(x => String(x.id_dv) === String(id));
        $('#mapaStatus').text(`Cargando recorrido de ${v ? v.placa : 'vehículo'}…`);
        $.post(CTRL_MAPA, { accion: 'recorrido', id_dv: id }, function (r) {
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
        mostrarRecorrido($(this).data('id'));
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
    // Quitar carro del despacho
    $('#mapaLista').on('click', '.mi-quitar', function () {
        const id = $(this).data('id');
        const v = datos.find(x => x.id_dv == id);
        Swal.fire({ icon: 'warning', title: '¿Quitar del despacho?',
            text: v ? `${v.placa} dejará de seguirse.` : '', showCancelButton: true,
            confirmButtonText: 'Sí, quitar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545'
        }).then(r => {
            if (!r.isConfirmed) return;
            $.post(CTRL_MAPA, { accion: 'quitarVehiculo', id_dv: id }, function (resp) {
                if (!resp.ok) { Swal.fire({ icon: 'error', title: 'Error', text: resp.mensaje }); return; }
                cargar(false);
            }, 'json');
        });
    });

    // ── Carga de posiciones ────────────────────────────────────
    function pintar(vehiculos) { datos = vehiculos || []; poblarFiltros(); render(); }
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
            if (r && r.ok) { pintar(r.data.vehiculos); if (live && r.data.resumen) avisarErrores(r.data.resumen); }
            else if (r && !r.ok) $('#mapaStatus').text('Error: ' + (r.mensaje || 'desconocido'));
        }, 'json').always(function () {
            cargando = false;
            $('#btnMapaRefrescar').prop('disabled', false)
                .html(historico ? '<i class="bi bi-clock-history me-1"></i> Recargar historial' : '<i class="bi bi-arrow-clockwise me-1"></i> Actualizar');
        });
    }
    let erroresAvisados = '';
    function avisarErrores(resumen) {
        if (!resumen.errores || !resumen.errores.length) { erroresAvisados = ''; return; }
        const firma = resumen.errores.map(e => e.plataforma).join('|');
        if (firma === erroresAvisados) return; erroresAvisados = firma;
        const lst = resumen.errores.map(e => `<li><b>${esc(e.plataforma)}</b> (${esc(e.transporte)}): ${esc(e.mensaje)}</li>`).join('');
        Swal.fire({ icon: 'warning', title: 'Algunas cuentas no respondieron', html: `<ul class="text-start small mb-0">${lst}</ul>` });
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
        $('#btnCerrarDespacho').prop('disabled', !hay || historico);
        $('#btnMapaRefrescar').html(historico ? '<i class="bi bi-clock-history me-1"></i> Recargar historial' : '<i class="bi bi-arrow-clockwise me-1"></i> Actualizar');
        $('#mapaAutoRefresh').prop('disabled', historico);
        if (historico && $('#mapaAutoRefresh').prop('checked')) {
            $('#mapaAutoRefresh').prop('checked', false);
            if (timer) { clearInterval(timer); timer = null; }
        }
    }

    $('#selDespacho').on('change', function () {
        despachoActual = $(this).val() || '';
        aplicarModoDespacho(); ajustado = false; limpiarRecorrido();
        if (estadoDespachos === 'cerrado') cargar(false);
        else cargar(false).always(() => cargar(true));
    });

    $('input[name="modoDespacho"]').on('change', function () {
        estadoDespachos = this.value === 'cerrado' ? 'cerrado' : 'activo';
        despachoActual = ''; seleccionado = null; ajustado = false; limpiarRecorrido();
        $('#fltEstado').val('');
        if (timer) { clearInterval(timer); timer = null; $('#mapaAutoRefresh').prop('checked', false); }
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
            $.post(CTRL_MAPA, { accion: 'cerrarDespacho', id_despacho: despachoActual }, function (resp) {
                if (!resp.ok) { Swal.fire({ icon: 'error', title: 'Error', text: resp.mensaje }); return; }
                Swal.fire({ icon: 'success', title: 'Despacho cerrado', timer: 1400, showConfirmButton: false });
                cargarDespachos('').then(() => { ajustado = false; cargar(false).always(() => cargar(true)); });
            }, 'json');
        });
    });

    // ══════════════════════════════════════════════════════════
    //  SELECTOR — Agregar vehículos al despacho
    // ══════════════════════════════════════════════════════════
    let dispCache = [], cuentaCache = [];
    const modalAgregar = () => bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAgregarVeh'));
    function abrirModalAgregar() {
        if (!despachoActual) return;
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
        dispCache = [];
        actualizarSeleccion();
        renderCuentaInfo();
        if (!id) { $('#dispositivosWrap').html('<div class="text-muted small">Selecciona una cuenta.</div>'); return; }
        $('#dispositivosWrap').html('<div class="text-muted small py-3"><span class="spinner-border spinner-border-sm me-1"></span> Cargando equipos…</div>');
        $.post(CTRL_MAPA, { accion: 'dispositivos', id_cuenta: id, id_despacho: despachoActual }, function (r) {
            if (!r.ok) { $('#dispositivosWrap').html(`<div class="text-danger small">${esc(r.mensaje)}</div>`); return; }
            dispCache = r.data.dispositivos;
            if (r.data.cuenta) {
                const c = cuentaCache.find(x => String(x.id_cuenta) === String(r.data.cuenta.id_cuenta));
                if (c) Object.assign(c, r.data.cuenta);
            }
            renderCuentaInfo(r.data.cuenta || cuentaSeleccionada(), dispCache.length);
            renderDispositivos();
        }, 'json');
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
            cargarDespachos(despachoActual).then(() => { ajustado = false; cargar(false).always(() => cargar(true)); });
        }, 'json');
    });

    // ── Controles ──────────────────────────────────────────────
    $('#btnMapaRefrescar').on('click', () => cargar(estadoDespachos !== 'cerrado'));
    $('#fltTransporte, #fltEstado').on('change', render);
    $('#fltPlaca').on('input', render);
    $('#mapaAutoRefresh').on('change', function () {
        if (estadoDespachos === 'cerrado') { this.checked = false; return; }
        if (this.checked) { cargar(true); timer = setInterval(() => cargar(true), 30000); }
        else if (timer) { clearInterval(timer); timer = null; }
    });

    function esc(s) { return (s == null ? '' : String(s)).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }

    // ── Arranque ───────────────────────────────────────────────
    cargarDespachos().then(() => cargar(false).always(() => cargar(true)));
});
