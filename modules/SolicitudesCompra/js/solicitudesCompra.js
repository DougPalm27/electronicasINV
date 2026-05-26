/* ═══════════════════════════════════════════════════════════════
   Solicitudes de Compra de Repuestos
   ═══════════════════════════════════════════════════════════════ */
const CTRL_COMPRAS = './modules/SolicitudesCompra/controllers/solicitudesCompraController.php';

/* ── Caché de catálogos ─────────────────────────────────────── */
let _proveedores = [];
let _divisas     = [];
let _repuestos   = [];

/* ── Estado global ──────────────────────────────────────────── */
let _filtroEstado = '';
let _itemIdx      = 0;
let _detalleId    = 0;
let _detalleDatos = null;

/* ── Modales Bootstrap ──────────────────────────────────────── */
const $MODAL_COMPRA    = $('#modalCompra');
const $MODAL_DETALLE   = $('#modalDetalleCompra');
const $MODAL_RECHAZO   = $('#modalRechazoCompra');
const $MODAL_ORDEN     = $('#modalOrdenCompra');
const $MODAL_RECEPCION = $('#modalRecepcion');

/* ══════════════════════════════════════════════════════════════
   HELPERS
══════════════════════════════════════════════════════════════ */
function escHtml(s) {
    return String(s ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function fmtNum(n, dec = 2) {
    return parseFloat(n || 0).toLocaleString('es-HN', {
        minimumFractionDigits: dec, maximumFractionDigits: dec
    });
}

function badgeEstado(estado) {
    const map = {
        'Borrador'        : 'bg-secondary',
        'Pendiente'       : 'bg-warning text-dark',
        'Aprobada'        : 'bg-info text-dark',
        'Ordenada'        : 'bg-primary',
        'Recibida parcial': 'bg-orange text-dark',
        'Recibida'        : 'bg-success',
        'Rechazada'       : 'bg-danger',
        'Cancelada'       : 'bg-dark'
    };
    return `<span class="badge ${map[estado] || 'bg-secondary'}">${escHtml(estado)}</span>`;
}

function s2Opts(placeholder, parent) {
    return {
        dropdownParent: parent || $('body'),
        theme:          'bootstrap-5',
        width:          '100%',
        placeholder,
        allowClear:     false,
        language:       { noResults: () => 'Sin resultados' }
    };
}

function getDivisaSimbolo(idDivisa) {
    const d = _divisas.find(x => String(x.id_divisa) === String(idDivisa));
    return d ? d.simbolo : '';
}

/* ══════════════════════════════════════════════════════════════
   INICIALIZACIÓN
══════════════════════════════════════════════════════════════ */
$(document).ready(function () {
    if (!document.getElementById('tblCompras')) return;

    const esAdmin = (window.USUARIO_ROL === 'Administrador');

    // Carga de catálogos: tres peticiones independientes con contador de finalización
    let _pendientes = 3;
    function _catalogoListo() {
        if (--_pendientes === 0) {
            // Los tres cargaron (o fallaron) → inicializar
            poblarSelectDivisas();
            initDataTable(esAdmin);
        }
    }

    $.post(CTRL_COMPRAS, { accion: 'listarProveedores' }, function (r) {
        _proveedores = (r && r.ok) ? r.data : [];
    }, 'json').fail(function () {
        _proveedores = [];
    }).always(_catalogoListo);

    $.post(CTRL_COMPRAS, { accion: 'listarDivisas' }, function (r) {
        _divisas = (r && r.ok) ? r.data : [];
    }, 'json').fail(function () {
        _divisas = [];
    }).always(_catalogoListo);

    $.post(CTRL_COMPRAS, { accion: 'listarRepuestos' }, function (r) {
        _repuestos = (r && r.ok) ? r.data : [];
    }, 'json').fail(function () {
        _repuestos = [];
    }).always(_catalogoListo);

    bindEventos(esAdmin);
});

/* ══════════════════════════════════════════════════════════════
   SELECT DE DIVISA (cabecera)
══════════════════════════════════════════════════════════════ */
function poblarSelectDivisas() {
    let html = '<option value="">— Selecciona —</option>';
    _divisas.forEach(d => {
        const def = String(d.predeterminada) === '1' ? ' selected' : '';
        html += `<option value="${d.id_divisa}"${def}>${escHtml(d.nombre)} (${escHtml(d.codigo)})</option>`;
    });
    $('#compra_divisa').html(html);
    const sel = $('#compra_divisa').val();
    if (sel) $('#compra_divisa_simbolo').val(getDivisaSimbolo(sel));
}

/* ══════════════════════════════════════════════════════════════
   DATATABLE
══════════════════════════════════════════════════════════════ */
let _tabla;

function initDataTable(esAdmin) {
    _tabla = $('#tblCompras').DataTable({
        ajax: {
            url: CTRL_COMPRAS, type: 'POST',
            data: d => Object.assign(d, { accion: 'listar' }),
            dataSrc: r => {
                if (!r.ok) return [];
                return _filtroEstado
                    ? r.data.filter(x => x.estado === _filtroEstado)
                    : r.data;
            }
        },
        columns: [
            { data: 'codigo',
              render: v => `<span class="badge bg-primary fw-normal font-monospace">${escHtml(v)}</span>` },
            { data: 'descripcion',
              render: v => `<span title="${escHtml(v)}">${escHtml(v.length > 40 ? v.slice(0,40)+'…' : v)}</span>` },
            { data: 'solicitante', className: 'col-hide-xs' },
            { data: 'total_items', className: 'text-center col-hide-sm',
              render: v => `<span class="badge bg-secondary">${v}</span>` },
            { data: null, className: 'text-end col-hide-md',
              render: (d, t, r) => {
                  const tot = parseFloat(r.total_estimado || 0);
                  return tot > 0 ? `${escHtml(r.divisa_simbolo)} ${fmtNum(tot)}` : '—';
              }},
            { data: 'fecha_solicitud', className: 'col-hide-md' },
            { data: 'estado', className: 'text-center', render: badgeEstado },
            { data: null, className: 'text-center', orderable: false,
              render: (d, t, r) => botonesTabla(r, esAdmin) }
        ],
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
        order: [[0, 'desc']],
        pageLength: 15,
        responsive: false
    });
}

function botonesTabla(r, esAdmin) {
    const btnEditar = (r.estado === 'Borrador')
        ? `<li>
               <button class="dropdown-item btn-editar-compra" type="button" data-id="${r.id_solicitud_compra}">
                   <i class="bi bi-pencil me-2 text-warning"></i>Editar
               </button>
           </li>`
        : '';

    return `
        <div class="dropdown">
            <button class="btn btn-sm btn-primary dropdown-toggle py-1"
                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-three-dots-vertical"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li>
                    <button class="dropdown-item btn-ver-compra" type="button" data-id="${r.id_solicitud_compra}">
                        <i class="bi bi-eye me-2 text-info"></i>Ver detalle
                    </button>
                </li>
                ${btnEditar}
            </ul>
        </div>`;
}

/* ══════════════════════════════════════════════════════════════
   FILTRO DE ESTADO
══════════════════════════════════════════════════════════════ */
function bindFiltros() {
    $('#filtrosEstadoCompra').on('click', '.filtro-compra', function () {
        $('#filtrosEstadoCompra .filtro-compra').removeClass('active');
        $(this).addClass('active');
        _filtroEstado = $(this).data('estado');
        if (_tabla) _tabla.ajax.reload();
    });
}

/* ══════════════════════════════════════════════════════════════
   ÍTEMS DE COMPRA
══════════════════════════════════════════════════════════════ */
function opcProveedores() {
    let html = '<option value="">— Proveedor —</option>';
    _proveedores.forEach(p => {
        html += `<option value="${p.id_proveedor}">${escHtml(p.nombre)}</option>`;
    });
    return html;
}

function opcRepuestos() {
    let html = '<option value="">— Busca repuesto —</option>';
    _repuestos.forEach(r => {
        const partes = [r.marca, r.modelo].filter(Boolean).join(' / ');
        const label  = partes ? `${r.nombre} — ${partes}` : r.nombre;
        html += `<option value="${r.id_repuesto}"
                    data-serie="${r.maneja_serie}"
                    data-stock="${r.stock}"
                    data-stock-min="${r.stock_minimo}"
                    data-costo="${r.costo_promedio}">${escHtml(label)}</option>`;
    });
    return html;
}

function actualizarTotal() {
    const simbolo = getDivisaSimbolo($('#compra_divisa').val());
    let total = 0;
    $('#compraItems .compra-item').each(function () {
        const cant  = parseFloat($(this).find('.ci-cantidad').val() || 0);
        const costo = parseFloat($(this).find('.ci-costo').val() || 0);
        const sub   = cant * costo;
        total += sub;
        $(this).find('.ci-subtotal').text(sub > 0 ? `${simbolo} ${fmtNum(sub)}` : '—');
    });
    $('#compra_total').text(total > 0 ? `${simbolo} ${fmtNum(total)}` : '—');
}

function toggleEmptyItems() {
    $('#emptyItems').toggle($('#compraItems .compra-item').length === 0);
}

/* ── Construye la fila del ítem ─────────────────────────────── */
function agregarItem(datos = {}) {
    const idx = ++_itemIdx;

    // Determinar tipo inicial basado en los datos
    const esCatalogo = !datos.nombre_externo;
    const tipoCatalogo = esCatalogo ? 'selected' : '';
    const tipoExterno  = esCatalogo ? '' : 'selected';

    const $item = $(`
        <div class="compra-item" data-idx="${idx}">

            <!-- Selector de tipo -->
            <select class="form-select form-select-sm ci-tipo" style="width:105px;flex-shrink:0" title="Tipo de ítem">
                <option value="catalogo" ${tipoCatalogo}>📦 Catálogo</option>
                <option value="externo"  ${tipoExterno}>🔗 Externo</option>
            </select>

            <!-- Campo catálogo (Select2) -->
            <div class="ci-campo ci-campo-catalogo" style="flex:1 1 200px;min-width:0">
                <select class="form-select form-select-sm ci-repuesto">${opcRepuestos()}</select>
            </div>

            <!-- Campo externo (inputs nombre + enlace) -->
            <div class="ci-campo ci-campo-externo"
                 style="display:none;flex:1 1 200px;min-width:0;flex-direction:column;gap:.25rem">
                <input type="text" class="form-control form-control-sm ci-nombre-externo"
                       placeholder="Nombre del repuesto *" maxlength="300"
                       value="${escHtml(datos.nombre_externo || '')}">
                <input type="text" class="form-control form-control-sm ci-enlace-externo"
                       placeholder="Enlace / URL (opcional)" maxlength="500"
                       value="${escHtml(datos.enlace_externo || '')}">
            </div>

            <!-- Proveedor del ítem -->
            <select class="form-select form-select-sm ci-proveedor" style="width:150px;flex-shrink:0"
                    title="Proveedor de este ítem">${opcProveedores()}</select>

            <!-- Cantidad y costo -->
            <input type="number" class="form-control form-control-sm ci-cantidad"
                   style="width:85px;flex-shrink:0" min="1" value="${datos.cantidad_solicitada || 1}" title="Cantidad">
            <input type="number" class="form-control form-control-sm ci-costo"
                   style="width:120px;flex-shrink:0" min="0" step="0.0001"
                   placeholder="Costo unit." value="${datos.costo_unitario || ''}" title="Costo unitario">
            <div class="ci-subtotal">—</div>

            <div class="ci-actions">
                <button type="button" class="btn btn-sm btn-outline-danger ci-quitar" title="Quitar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>`
    );

    $('#compraItems').append($item);
    toggleEmptyItems();

    // Init Select2 en el select de repuesto
    const $sel = $item.find('.ci-repuesto');
    $sel.select2(s2Opts('Busca repuesto…', $MODAL_COMPRA));

    if (!esCatalogo) {
        aplicarTipo($item, 'externo');
    } else if (datos.id_repuesto) {
        $sel.val(datos.id_repuesto).trigger('change.select2');
    }

    // Pre-seleccionar proveedor del ítem si viene de un borrador
    if (datos.id_proveedor) {
        $item.find('.ci-proveedor').val(datos.id_proveedor);
    }

    actualizarTotal();
    return $item;
}

/* ── Aplica el cambio de tipo al ítem ───────────────────────── */
function aplicarTipo($item, tipo) {
    const $catDiv  = $item.find('.ci-campo-catalogo');
    const $extDiv  = $item.find('.ci-campo-externo');
    const $sel     = $item.find('.ci-repuesto');

    if (tipo === 'externo') {
        $catDiv.hide();
        $extDiv.css('display', 'flex');
    } else {
        $extDiv.hide();
        $catDiv.show();
    }
}

/* ══════════════════════════════════════════════════════════════
   MODAL COMPRA — ABRIR / LIMPIAR
══════════════════════════════════════════════════════════════ */
function abrirModalNueva() {
    limpiarModalCompra();
    $('#modalCompraTitulo').html('<i class="bi bi-cart-plus me-2"></i>Nueva Solicitud de Compra');
    $MODAL_COMPRA.modal('show');
}

function limpiarModalCompra() {
    $('#compra_id').val('');
    $('#compra_descripcion').val('').removeClass('is-invalid');
    $('#compra_notas').val('');

    // Destruir Select2 y limpiar ítems
    $('#compraItems .compra-item').each(function () {
        const $s = $(this).find('.ci-repuesto');
        if ($s.hasClass('select2-hidden-accessible')) $s.select2('destroy');
    });
    $('#compraItems .compra-item').remove();
    toggleEmptyItems();
    actualizarTotal();

    poblarSelectDivisas();
    const sel = $('#compra_divisa').val();
    if (sel) $('#compra_divisa_simbolo').val(getDivisaSimbolo(sel));
}

function cargarBorradorEnModal(id) {
    $.post(CTRL_COMPRAS, { accion: 'detalle', id }, function (r) {
        if (!r.ok) {
            Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje });
            return;
        }
        const d = r.data;
        limpiarModalCompra();
        $('#modalCompraTitulo').html('<i class="bi bi-pencil me-2"></i>Editar Borrador');
        $('#compra_id').val(d.id_solicitud_compra);
        $('#compra_descripcion').val(d.descripcion);
        $('#compra_divisa').val(d.id_divisa).trigger('change');
        $('#compra_notas').val(d.notas || '');
        (d.items || []).forEach(it => agregarItem(it));
        $MODAL_COMPRA.modal('show');
    }, 'json');
}

/* ══════════════════════════════════════════════════════════════
   GUARDAR / ENVIAR
══════════════════════════════════════════════════════════════ */
function recolectarPayload() {
    const desc  = $('#compra_descripcion').val().trim();
    const idDiv = $('#compra_divisa').val();

    $('#compra_descripcion').toggleClass('is-invalid', !desc);
    if (!desc || !idDiv) return null;

    const items = [];
    let   valido = true;

    $('#compraItems .compra-item').each(function () {
        const tipo = $(this).find('.ci-tipo').val();
        const cant = parseInt($(this).find('.ci-cantidad').val() || 0);
        const cost = parseFloat($(this).find('.ci-costo').val() || 0);

        if (cant < 1) { valido = false; return; }

        const idProv = $(this).find('.ci-proveedor').val() || null;

        if (tipo === 'catalogo') {
            const idR = $(this).find('.ci-repuesto').val();
            if (!idR) { valido = false; return; }
            items.push({
                id_repuesto:    idR,
                nombre_externo: null,
                enlace_externo: null,
                id_proveedor:   idProv,
                cantidad:       cant,
                costo_unitario: cost
            });
        } else {
            const nombre = $(this).find('.ci-nombre-externo').val().trim();
            const enlace = $(this).find('.ci-enlace-externo').val().trim();
            if (!nombre) {
                $(this).find('.ci-nombre-externo').addClass('is-invalid');
                valido = false;
                return;
            }
            items.push({
                id_repuesto:    null,
                nombre_externo: nombre,
                enlace_externo: enlace || null,
                id_proveedor:   idProv,
                cantidad:       cant,
                costo_unitario: cost
            });
        }
    });

    if (!valido || items.length === 0) return null;

    return {
        id_solicitud_compra: $('#compra_id').val() || null,
        descripcion:         desc,
        id_proveedor:        null,   // se maneja por ítem
        id_divisa:           idDiv,
        notas:               $('#compra_notas').val().trim() || null,
        items
    };
}

function guardarCompra(accion) {
    const payload = recolectarPayload();

    if (!payload) {
        Swal.fire({
            icon: 'warning', title: 'Atención',
            text: 'Completa los campos requeridos y agrega al menos un ítem válido.'
        });
        return;
    }

    const $btn = accion === 'guardar' ? $('#btnGuardarBorrador') : $('#btnGuardarEnviar');
    $btn.prop('disabled', true).prepend('<span class="spinner-border spinner-border-sm me-1"></span>');

    $.post(CTRL_COMPRAS, { accion, payload: JSON.stringify(payload) }, function (r) {
        $btn.prop('disabled', false).find('.spinner-border').remove();
        if (!r.ok) { Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje }); return; }
        $MODAL_COMPRA.modal('hide');
        _tabla.ajax.reload(null, false);
        Swal.fire({ icon: 'success', title: '¡Listo!', text: r.mensaje,
                    timer: 2000, showConfirmButton: false });
    }, 'json').fail(() => {
        $btn.prop('disabled', false).find('.spinner-border').remove();
        Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo conectar al servidor.' });
    });
}

/* ══════════════════════════════════════════════════════════════
   MODAL DETALLE
══════════════════════════════════════════════════════════════ */
function abrirDetalle(id) {
    _detalleId    = id;
    _detalleDatos = null;

    $('#detalleCompraId').text('…');
    $('#detalleCompraBody').html(
        '<div class="text-center py-4"><span class="spinner-border spinner-border-sm me-2"></span> Cargando...</div>'
    );
    $('#btnEditarBorrador,#btnEnviarBorrador,#btnAprobarCompra,#btnRechazarCompra,' +
      '#btnOrdenarCompra,#btnRecibirCompra,#btnCerrarRecepcion,#btnCancelarCompra').addClass('d-none');

    $MODAL_DETALLE.modal('show');

    $.post(CTRL_COMPRAS, { accion: 'detalle', id }, function (r) {
        if (!r.ok) {
            $('#detalleCompraBody').html(
                `<div class="alert alert-danger m-3">${escHtml(r.mensaje)}</div>`
            );
            return;
        }
        _detalleDatos = r.data;
        // Mostrar código en el título del modal
        const cod = r.data.codigo || `#${id}`;
        $('#detalleCompraId').html(
            `<span class="badge bg-primary fw-normal font-monospace ms-1">${escHtml(cod)}</span>`
        );
        renderDetalle(r.data);
        mostrarBotonesDetalle(r.data, window.USUARIO_ROL === 'Administrador');
    }, 'json');
}

function renderDetalle(d) {
    const simbolo = escHtml(d.divisa_simbolo || '');

    let html = `<div class="row g-3 mb-3">
        <div class="col-6 col-md-2">
            <p class="mb-1 text-muted small">Código</p>
            <p class="mb-0"><span class="badge bg-primary fw-normal font-monospace">${escHtml(d.codigo || '—')}</span></p>
        </div>
        <div class="col-6 col-md-1">
            <p class="mb-1 text-muted small">ID interno</p>
            <p class="mb-0 text-muted small">${escHtml(String(d.id_solicitud_compra))}</p>
        </div>
        <div class="col-12 col-md-5">
            <p class="mb-1 text-muted small">Descripción</p>
            <p class="fw-semibold mb-0">${escHtml(d.descripcion)}</p>
        </div>
        <div class="col-6 col-md-2">
            <p class="mb-1 text-muted small">Estado</p>
            <p class="mb-0">${badgeEstado(d.estado)}</p>
        </div>
        <div class="col-6 col-md-2">
            <p class="mb-1 text-muted small">Divisa</p>
            <p class="mb-0">${escHtml(d.divisa_nombre)} (${simbolo})</p>
        </div>
        <div class="col-6 col-md-3">
            <p class="mb-1 text-muted small">Solicitante</p>
            <p class="mb-0">${escHtml(d.solicitante)}</p>
        </div>
        <div class="col-6 col-md-3">
            <p class="mb-1 text-muted small">Creada</p>
            <p class="mb-0">${escHtml(d.fecha_sol_fmt || '—')}</p>
        </div>`;

    if (['Aprobada','Ordenada','Recibida'].includes(d.estado)) {
        html += `<div class="col-6 col-md-3">
            <p class="mb-1 text-muted small">Aprobada por</p>
            <p class="mb-0">${escHtml(d.aprobador_nombre)}</p>
        </div>
        <div class="col-6 col-md-3">
            <p class="mb-1 text-muted small">Fecha aprobación</p>
            <p class="mb-0">${escHtml(d.fecha_apr_fmt || '—')}</p>
        </div>`;
    }
    if (d.estado === 'Rechazada') {
        html += `<div class="col-12">
            <p class="mb-1 text-muted small">Motivo de rechazo</p>
            <p class="mb-0 text-danger">${escHtml(d.motivo_rechazo || '—')}</p>
        </div>`;
    }
    if (['Ordenada','Recibida parcial','Recibida'].includes(d.estado)) {
        html += `<div class="col-6 col-md-3">
            <p class="mb-1 text-muted small">Nº Orden</p>
            <p class="mb-0">${escHtml(d.numero_orden || '—')}</p>
        </div>`;
    }
    if (d.estado === 'Recibida') {
        html += `<div class="col-6 col-md-3">
            <p class="mb-1 text-muted small">Recibida por</p>
            <p class="mb-0">${escHtml(d.receptor_nombre)}</p>
        </div>
        <div class="col-6 col-md-3">
            <p class="mb-1 text-muted small">Fecha recepción</p>
            <p class="mb-0">${escHtml(d.fecha_rec_fmt || '—')}</p>
        </div>`;
    }
    if (d.notas) {
        html += `<div class="col-12">
            <p class="mb-1 text-muted small">Notas</p>
            <p class="mb-0 fst-italic text-secondary">${escHtml(d.notas)}</p>
        </div>`;
    }
    html += '</div><hr class="my-3">';

    // Tabla de ítems
    html += `<h6 class="text-primary fw-bold mb-2">
        <i class="bi bi-box-seam me-1"></i> Ítems solicitados
    </h6>
    <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>Repuesto</th>
                <th class="col-hide-sm">Proveedor</th>
                <th class="text-center" style="width:80px">Solicitado</th>
                <th class="text-center" style="width:80px">Recibido</th>
                <th class="col-hide-sm text-center" style="width:105px">Entrega est.</th>
                <th class="text-end"   style="width:115px">Costo unit.</th>
                <th class="text-end"   style="width:115px">Subtotal</th>
            </tr>
        </thead>
        <tbody>`;

    let total = 0;
    (d.items || []).forEach(it => {
        const esExterno = !it.id_repuesto;

        // Etiqueta del repuesto
        let label;
        if (esExterno) {
            label = `<span class="fw-semibold">${escHtml(it.repuesto)}</span>
                     <span class="badge bg-warning text-dark ms-1 small">Externo</span>`;
            if (it.enlace_externo) {
                label += ` <a href="${escHtml(it.enlace_externo)}" target="_blank" rel="noopener"
                             class="ms-1 small" title="Ver enlace">
                             <i class="bi bi-box-arrow-up-right"></i>
                           </a>`;
            }
        } else {
            const extra = [it.marca, it.modelo].filter(Boolean).join(' / ');
            label = escHtml(it.repuesto);
            if (extra) label += ` <small class="text-muted">— ${escHtml(extra)}</small>`;
            if (String(it.maneja_serie) === '1') {
                label += ' <span class="badge bg-secondary ms-1 small">Serie</span>';
            }
        }

        const sub = parseFloat(it.cantidad_solicitada) * parseFloat(it.costo_unitario || 0);
        total += sub;

        const recCls = parseInt(it.cantidad_recibida) >= parseInt(it.cantidad_solicitada)
            ? 'text-success fw-semibold'
            : (parseInt(it.cantidad_recibida) > 0 ? 'text-warning' : '');

        const provItem = it.proveedor_item
            ? escHtml(it.proveedor_item)
            : '<span class="text-muted small">—</span>';

        html += `<tr>
            <td>${label}</td>
            <td class="col-hide-sm small">${provItem}</td>
            <td class="text-center">${it.cantidad_solicitada}</td>
            <td class="text-center ${recCls}">${it.cantidad_recibida}</td>
            <td class="col-hide-sm text-center small">${escHtml(it.fecha_ent_fmt || '—')}</td>
            <td class="text-end">${it.costo_unitario > 0 ? `${simbolo} ${fmtNum(it.costo_unitario)}` : '—'}</td>
            <td class="text-end">${sub > 0 ? `${simbolo} ${fmtNum(sub)}` : '—'}</td>
        </tr>`;
    });

    html += `</tbody>
        <tfoot><tr class="table-light">
            <td colspan="6" class="text-end fw-bold">Total estimado:</td>
            <td class="text-end fw-bold">${total > 0 ? `${simbolo} ${fmtNum(total)}` : '—'}</td>
        </tr></tfoot>
    </table></div>`;

    $('#detalleCompraBody').html(html);
}

function mostrarBotonesDetalle(d, esAdmin) {
    const estado   = d.estado;
    const esPropio = Number(d.id_usuario) === Number(window.USUARIO_ID);

    if (estado === 'Borrador' && (esAdmin || esPropio)) {
        $('#btnEditarBorrador,#btnEnviarBorrador,#btnCancelarCompra').removeClass('d-none');
    }
    if (estado === 'Pendiente') {
        if (esAdmin) $('#btnAprobarCompra,#btnRechazarCompra').removeClass('d-none');
        if (esAdmin || esPropio) $('#btnCancelarCompra').removeClass('d-none');
    }
    if (estado === 'Aprobada' && esAdmin) {
        $('#btnOrdenarCompra,#btnCancelarCompra').removeClass('d-none');
    }
    if (estado === 'Ordenada' && esAdmin) {
        $('#btnRecibirCompra').removeClass('d-none');
    }
    if (estado === 'Recibida parcial' && esAdmin) {
        $('#btnRecibirCompra').removeClass('d-none');
        $('#btnCerrarRecepcion').removeClass('d-none');
        $('#btnCancelarCompra').removeClass('d-none');
    }
}

/* ══════════════════════════════════════════════════════════════
   MODAL RECEPCIÓN
══════════════════════════════════════════════════════════════ */
function abrirModalRecepcion(items) {
    let html = '';

    items.forEach(it => {
        const esExterno = !it.id_repuesto;
        const esSerie   = !esExterno && String(it.maneja_serie) === '1';
        const pendiente = Math.max(0, parseInt(it.cantidad_solicitada) - parseInt(it.cantidad_recibida));

        // Skip si ya fue completamente recibido (solo para no-serie y no-externos)
        if (!esSerie && pendiente === 0) return;

        const extra = [it.marca, it.modelo].filter(Boolean).join(' / ');
        const labelSub = extra ? ` <small class="text-muted">— ${escHtml(extra)}</small>` : '';
        const labelNom = `<strong>${escHtml(it.repuesto)}</strong>${labelSub}`;

        // Etiqueta de progreso para mostrar cuánto falta
        const progreso = parseInt(it.cantidad_recibida) > 0
            ? `<span class="badge bg-secondary ms-1 small">${it.cantidad_recibida}/${it.cantidad_solicitada} recibidos</span>`
            : '';

        if (esExterno) {
            const enlaceBtn = it.enlace_externo
                ? `<a href="${escHtml(it.enlace_externo)}" target="_blank" rel="noopener"
                      class="btn btn-sm btn-outline-info ms-2">
                      <i class="bi bi-box-arrow-up-right me-1"></i>Ver enlace
                   </a>`
                : '';
            html += `
            <div class="rec-item border-warning" data-id-detalle="${it.id_detalle}" data-serie="0" data-externo="1">
                <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-1">
                    <div>${labelNom}
                        <span class="badge bg-warning text-dark ms-1">Externo</span>
                        ${progreso}${enlaceBtn}
                    </div>
                </div>
                <div class="alert alert-warning py-1 px-2 small mb-2">
                    <i class="bi bi-info-circle me-1"></i>
                    Este repuesto no está en el catálogo. Al recibirlo no se actualizará el inventario.
                </div>
                <div class="row g-2 align-items-end">
                    <div class="col-6 col-md-4">
                        <label class="form-label small mb-1">
                            Cant. a recibir ahora
                            <span class="text-muted">(pendiente: ${pendiente})</span>
                        </label>
                        <input type="number" class="form-control form-control-sm rec-cant"
                               min="0" max="${pendiente}" value="${pendiente}">
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small mb-1">Costo unitario</label>
                        <input type="number" class="form-control form-control-sm rec-costo"
                               min="0" step="0.0001" placeholder="Costo real"
                               value="${it.costo_unitario || ''}">
                    </div>
                </div>
            </div>`;

        } else if (!esSerie) {
            html += `
            <div class="rec-item" data-id-detalle="${it.id_detalle}" data-serie="0" data-externo="0">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>${labelNom}${progreso}</div>
                    <span class="badge bg-secondary small">Por cantidad</span>
                </div>
                <div class="row g-2 align-items-end">
                    <div class="col-6 col-md-4">
                        <label class="form-label small mb-1">
                            Cant. a recibir ahora
                            <span class="text-muted">(pendiente: ${pendiente})</span>
                        </label>
                        <input type="number" class="form-control form-control-sm rec-cant"
                               min="0" max="${pendiente}" value="${pendiente}">
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small mb-1">Costo unitario real</label>
                        <input type="number" class="form-control form-control-sm rec-costo"
                               min="0" step="0.0001" placeholder="Costo confirmado"
                               value="${it.costo_unitario || ''}">
                    </div>
                </div>
            </div>`;

        } else {
            // Serie: mostrar solo los pendientes
            let seriesHtml = '';
            for (let i = 0; i < pendiente; i++) {
                seriesHtml += `<input type="text" class="form-control form-control-sm rec-serie-input"
                                      placeholder="Serie ${parseInt(it.cantidad_recibida) + i + 1}"
                                      maxlength="100">`;
            }
            html += `
            <div class="rec-item rec-serie" data-id-detalle="${it.id_detalle}" data-serie="1" data-externo="0">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>${labelNom}${progreso}</div>
                    <span class="badge bg-warning text-dark small">Serie · ${pendiente} pendientes</span>
                </div>
                <label class="form-label small mb-1">Números de serie a recibir:</label>
                <div class="rec-seriales">${seriesHtml}</div>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-2 btn-add-serie">
                    <i class="bi bi-plus-circle me-1"></i> Agregar serie extra
                </button>
            </div>`;
        }
    });

    if (!html) {
        html = '<div class="alert alert-success">Todos los ítems ya han sido recibidos.</div>';
        $('#btnConfirmarRecepcion').prop('disabled', true);
    } else {
        $('#btnConfirmarRecepcion').prop('disabled', false);
    }

    $('#recepcionItems').html(html);
    $MODAL_RECEPCION.modal('show');
}

/* ══════════════════════════════════════════════════════════════
   EVENTOS
══════════════════════════════════════════════════════════════ */
function bindEventos(esAdmin) {

    bindFiltros();

    // ── Nueva solicitud ──────────────────────────────────────
    $('#btnNuevaCompra').on('click', abrirModalNueva);

    // ── Cambio de divisa ─────────────────────────────────────
    $('#compra_divisa').on('change', function () {
        $('#compra_divisa_simbolo').val(getDivisaSimbolo($(this).val()));
        actualizarTotal();
    });

    // ── Agregar ítem ─────────────────────────────────────────
    $('#btnAgregarItem').on('click', () => agregarItem());

    // ── Cambio de tipo (Catálogo / Externo) ──────────────────
    $('#compraItems').on('change', '.ci-tipo', function () {
        aplicarTipo($(this).closest('.compra-item'), $(this).val());
        actualizarTotal();
    });

    // ── Quitar ítem ──────────────────────────────────────────
    $('#compraItems').on('click', '.ci-quitar', function () {
        const $item = $(this).closest('.compra-item');
        const $sel  = $item.find('.ci-repuesto');
        if ($sel.hasClass('select2-hidden-accessible')) $sel.select2('destroy');
        $item.remove();
        toggleEmptyItems();
        actualizarTotal();
    });

    // ── Recalcular total ─────────────────────────────────────
    $('#compraItems').on('input change', '.ci-cantidad, .ci-costo', actualizarTotal);

    // ── Pre-rellenar costo promedio al seleccionar repuesto ──
    $('#compraItems').on('change', '.ci-repuesto', function () {
        const $item = $(this).closest('.compra-item');
        const costo = parseFloat($(this).find(':selected').data('costo') || 0);
        const $c    = $item.find('.ci-costo');
        if (!$c.val() || parseFloat($c.val()) === 0) {
            $c.val(costo > 0 ? costo.toFixed(4) : '');
        }
        actualizarTotal();
    });

    // ── Limpiar is-invalid al escribir ──────────────────────
    $('#compraItems').on('input', '.ci-nombre-externo', function () {
        $(this).removeClass('is-invalid');
    });
    $('#compra_descripcion').on('input', function () {
        $(this).removeClass('is-invalid');
    });

    // ── Guardar borrador / Enviar ────────────────────────────
    $('#btnGuardarBorrador').on('click', () => guardarCompra('guardar'));
    $('#btnGuardarEnviar').on('click',   () => guardarCompra('guardarEnviar'));

    // ── Tabla: ver / editar ──────────────────────────────────
    $('#tblCompras').on('click', '.btn-ver-compra', function () {
        abrirDetalle(Number($(this).data('id')));
    });
    $('#tblCompras').on('click', '.btn-editar-compra', function () {
        cargarBorradorEnModal(Number($(this).data('id')));
    });

    // ── Detalle: editar borrador ─────────────────────────────
    $('#btnEditarBorrador').on('click', function () {
        $MODAL_DETALLE.modal('hide');
        cargarBorradorEnModal(_detalleId);
    });

    // ── Detalle: enviar ──────────────────────────────────────
    $('#btnEnviarBorrador').on('click', function () {
        Swal.fire({
            icon: 'question', title: '¿Enviar solicitud?',
            text: 'Pasará a estado Pendiente y no podrás editarla.',
            showCancelButton: true, confirmButtonText: 'Sí, enviar',
            cancelButtonText: 'Cancelar', confirmButtonColor: '#0d6efd'
        }).then(res => {
            if (!res.isConfirmed) return;
            $.post(CTRL_COMPRAS, { accion: 'enviar', id: _detalleId }, function (r) {
                if (!r.ok) { Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje }); return; }
                if (r.data?.mail_error) console.warn('[MAIL] Error al notificar:', r.data.mail_error);
                else console.log('[MAIL] Notificación enviada correctamente.');
                $MODAL_DETALLE.modal('hide');
                _tabla.ajax.reload(null, false);
                Swal.fire({ icon: 'success', title: '¡Enviada!', text: r.mensaje,
                            timer: 2000, showConfirmButton: false });
            }, 'json');
        });
    });

    // ── Aprobar ──────────────────────────────────────────────
    $('#btnAprobarCompra').on('click', function () {
        Swal.fire({
            icon: 'question', title: '¿Aprobar esta solicitud?',
            showCancelButton: true, confirmButtonText: 'Aprobar',
            cancelButtonText: 'Cancelar', confirmButtonColor: '#198754'
        }).then(res => {
            if (!res.isConfirmed) return;
            $.post(CTRL_COMPRAS, { accion: 'aprobar', id: _detalleId }, function (r) {
                if (!r.ok) { Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje }); return; }
                if (r.data?.mail_error) console.warn('[MAIL] Error al notificar:', r.data.mail_error);
                else console.log('[MAIL] Notificación enviada correctamente.');
                $MODAL_DETALLE.modal('hide');
                _tabla.ajax.reload(null, false);
                Swal.fire({ icon: 'success', title: '¡Aprobada!', text: r.mensaje,
                            timer: 2000, showConfirmButton: false });
            }, 'json');
        });
    });

    // ── Rechazar ─────────────────────────────────────────────
    $('#btnRechazarCompra').on('click', function () {
        $('#rechazoCompraMotivo').val('').removeClass('is-invalid');
        $MODAL_RECHAZO.modal('show');
    });
    $('#rechazoCompraMotivo').on('input', function () { $(this).removeClass('is-invalid'); });
    $('#btnConfirmarRechazoCompra').on('click', function () {
        const motivo = $('#rechazoCompraMotivo').val().trim();
        if (!motivo) { $('#rechazoCompraMotivo').addClass('is-invalid'); return; }
        $(this).prop('disabled', true);
        $.post(CTRL_COMPRAS, { accion: 'rechazar', id: _detalleId, motivo }, function (r) {
            $('#btnConfirmarRechazoCompra').prop('disabled', false);
            if (!r.ok) { Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje }); return; }
            if (r.data?.mail_error) console.warn('[MAIL] Error al notificar:', r.data.mail_error);
            else console.log('[MAIL] Notificación enviada correctamente.');
            $MODAL_RECHAZO.modal('hide');
            $MODAL_DETALLE.modal('hide');
            _tabla.ajax.reload(null, false);
            Swal.fire({ icon: 'success', title: 'Rechazada', text: r.mensaje,
                        timer: 2000, showConfirmButton: false });
        }, 'json');
    });

    // ── Registrar orden ──────────────────────────────────────
    $('#btnOrdenarCompra').on('click', function () {
        $('#ordenNumero').val('');

        // Generar una fila de fecha por ítem
        let html = '';
        if (_detalleDatos && _detalleDatos.items && _detalleDatos.items.length) {
            _detalleDatos.items.forEach(it => {
                const extra = [it.marca, it.modelo].filter(Boolean).join(' / ');
                const label = extra
                    ? `${escHtml(it.repuesto)} <small class="text-muted">— ${escHtml(extra)}</small>`
                    : escHtml(it.repuesto);
                html += `
                <div class="mb-3 border rounded p-2 bg-light">
                    <div class="small fw-semibold mb-1">${label}</div>
                    <label class="form-label small mb-1 text-muted">Fecha estimada de entrega <span class="fw-normal">(opcional)</span></label>
                    <input type="date" class="form-control form-control-sm orden-fecha-item"
                           data-id-detalle="${it.id_detalle}">
                </div>`;
            });
        }
        $('#ordenItemsFechas').html(html);
        $MODAL_ORDEN.modal('show');
    });
    $('#btnConfirmarOrden').on('click', function () {
        // Recolectar fecha estimada por ítem
        const fechas_items = [];
        $('#ordenItemsFechas .orden-fecha-item').each(function () {
            fechas_items.push({
                id_detalle:        $(this).data('id-detalle'),
                fecha_entrega_est: $(this).val() || null
            });
        });

        $(this).prop('disabled', true);
        $.post(CTRL_COMPRAS, {
            accion:       'registrarOrden',
            id:           _detalleId,
            numero_orden: $('#ordenNumero').val().trim(),
            fechas_items: JSON.stringify(fechas_items)
        }, function (r) {
            $('#btnConfirmarOrden').prop('disabled', false);
            if (!r.ok) { Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje }); return; }
            $MODAL_ORDEN.modal('hide');
            $MODAL_DETALLE.modal('hide');
            _tabla.ajax.reload(null, false);
            Swal.fire({ icon: 'success', title: '¡Orden registrada!', text: r.mensaje,
                        timer: 2000, showConfirmButton: false });
        }, 'json');
    });

    // ── Recibir mercancía ────────────────────────────────────
    $('#btnRecibirCompra').on('click', function () {
        if (!_detalleDatos || !_detalleDatos.items) return;
        abrirModalRecepcion(_detalleDatos.items);
    });

    // ── Agregar serie extra ──────────────────────────────────
    $('#recepcionItems').on('click', '.btn-add-serie', function () {
        const $s = $(this).closest('.rec-item').find('.rec-seriales');
        const n  = $s.find('input').length + 1;
        $s.append(`<input type="text" class="form-control form-control-sm rec-serie-input"
                          placeholder="Serie ${n}" maxlength="100">`);
    });

    // ── Confirmar recepción ──────────────────────────────────
    $('#btnConfirmarRecepcion').on('click', function () {
        const recepciones = [];

        $('#recepcionItems .rec-item').each(function () {
            const idDetalle = $(this).data('id-detalle');
            const esSerie   = String($(this).data('serie'))   === '1';

            if (!esSerie) {
                recepciones.push({
                    id_detalle:        idDetalle,
                    maneja_serie:      0,
                    cantidad_recibida: parseInt($(this).find('.rec-cant').val()  || 0),
                    costo_recibido:    parseFloat($(this).find('.rec-costo').val() || 0)
                });
            } else {
                const series = [];
                $(this).find('.rec-serie-input').each(function () {
                    const v = $(this).val().trim();
                    if (v) series.push(v);
                });
                recepciones.push({ id_detalle: idDetalle, maneja_serie: 1, series });
            }
        });

        if (!recepciones.length) {
            Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay ítems a registrar.' });
            return;
        }

        $(this).prop('disabled', true);
        $.post(CTRL_COMPRAS, {
            accion:      'registrarRecepcion',
            id:          _detalleId,
            recepciones: JSON.stringify(recepciones)
        }, function (r) {
            $('#btnConfirmarRecepcion').prop('disabled', false);
            if (!r.ok) { Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje }); return; }
            if (r.data?.mail_error) console.warn('[MAIL] Error al notificar:', r.data.mail_error);
            else console.log('[MAIL] Notificación enviada correctamente.');
            $MODAL_RECEPCION.modal('hide');
            $MODAL_DETALLE.modal('hide');
            _tabla.ajax.reload(null, false);

            const esParcial = (r.estado === 'Recibida parcial');
            Swal.fire({
                icon: esParcial ? 'info' : 'success',
                title: esParcial ? 'Recepción parcial registrada' : '¡Todo recibido!',
                html: (esParcial
                    ? '<p class="mb-2">Quedan ítems pendientes por recibir.<br>La solicitud queda en estado <strong>Recibida parcial</strong>.</p>'
                    : '<p class="mb-2">Todos los ítems han sido recibidos correctamente.</p>')
                    + (r.mensaje && r.mensaje !== 'Recepción registrada. Inventario actualizado.'
                        ? `<div class="small text-muted">${escHtml(r.mensaje).replace(/ Avisos: /g,'<br><strong>Avisos:</strong><br>').replace(/\|/g,'<br>')}</div>`
                        : ''),
                confirmButtonText: 'Aceptar'
            });
        }, 'json');
    });

    // ── Cerrar recepción parcial ─────────────────────────────
    $('#btnCerrarRecepcion').on('click', function () {
        Swal.fire({
            icon: 'warning',
            title: '¿Cerrar la recepción?',
            html: 'Los ítems que aún no han llegado <strong>quedarán pendientes sin registrar</strong>.<br>' +
                  'El inventario ya actualizado <strong>no se verá afectado</strong>.<br><br>' +
                  'La solicitud pasará a estado <strong>Recibida</strong>.',
            showCancelButton: true,
            confirmButtonText: 'Sí, cerrar recepción',
            cancelButtonText:  'Cancelar',
            confirmButtonColor: '#198754'
        }).then(res => {
            if (!res.isConfirmed) return;
            $.post(CTRL_COMPRAS, { accion: 'cerrarRecepcion', id: _detalleId }, function (r) {
                if (!r.ok) { Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje }); return; }
                $MODAL_DETALLE.modal('hide');
                _tabla.ajax.reload(null, false);
                Swal.fire({ icon: 'success', title: '¡Cerrada!', text: r.mensaje,
                            timer: 2500, showConfirmButton: false });
            }, 'json');
        });
    });

    // ── Cancelar solicitud ───────────────────────────────────
    $('#btnCancelarCompra').on('click', function () {
        Swal.fire({
            icon: 'warning', title: '¿Cancelar solicitud?',
            text: 'Esta acción no se puede deshacer.',
            showCancelButton: true, confirmButtonText: 'Sí, cancelar',
            cancelButtonText: 'No', confirmButtonColor: '#dc3545'
        }).then(res => {
            if (!res.isConfirmed) return;
            $.post(CTRL_COMPRAS, { accion: 'cancelar', id: _detalleId }, function (r) {
                if (!r.ok) { Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje }); return; }
                $MODAL_DETALLE.modal('hide');
                _tabla.ajax.reload(null, false);
                Swal.fire({ icon: 'success', title: 'Cancelada', text: r.mensaje,
                            timer: 2000, showConfirmButton: false });
            }, 'json');
        });
    });
}
