/* ═══════════════════════════════════════════════════════════════
   Manual Satake — Catálogo de Tareas + Base de Conocimiento
   ═══════════════════════════════════════════════════════════════ */
const CTRL_SATAKE = './modules/Mantenimiento/Satake/controllers/satakeController.php';

const $MODAL_TAREA  = $('#modalTarea');
const $MODAL_ALERTA = $('#modalAlerta');

const esAdmin = (window.USUARIO_ROL === 'Administrador');

/* ── Catálogos en memoria ────────────────────────────────────── */
let _frecuencias = [];   // [{id_frecuencia, nombre, orden, color_badge}]
let _categorias  = [];   // [{id_categoria, nombre, color_badge}]

/* ── Estado actual del filtro ────────────────────────────────── */
let _filtroIdFrec = 0;

/* ── Helpers ─────────────────────────────────────────────────── */
function escHtml(s) {
    return String(s ?? '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function badgePor(colorBadge, nombre) {
    return `<span class="badge ${escHtml(colorBadge)}">${escHtml(nombre)}</span>`;
}

function frecBadge(idFrec) {
    const f = _frecuencias.find(x => String(x.id_frecuencia) === String(idFrec));
    return f ? badgePor(f.color_badge, f.nombre) : '—';
}

function catBadge(idCat) {
    const c = _categorias.find(x => String(x.id_categoria) === String(idCat));
    return c ? badgePor(c.color_badge, c.nombre) : '—';
}

/* ═══════════════════════════════════════════════════════════════
   INICIALIZACIÓN
═══════════════════════════════════════════════════════════════ */
$(document).ready(function () {
    if (!document.getElementById('tblTareas')) return;

    // Cargar catálogos primero, luego inicializar la tabla y eventos
    let _pendientes = 2;
    function _catalogoListo() {
        if (--_pendientes > 0) return;
        poblarSelectsFrecCat();
        construirFiltrosFrecuencia();
        initTablaTareas();
        cargarAlertas();
        bindEventos();
    }

    $.post(CTRL_SATAKE, { accion: 'listarFrecuencias' }, function (r) {
        _frecuencias = (r && r.ok) ? r.data : [];
    }, 'json').always(_catalogoListo);

    $.post(CTRL_SATAKE, { accion: 'listarCategorias' }, function (r) {
        _categorias = (r && r.ok) ? r.data : [];
    }, 'json').always(_catalogoListo);
});

/* ── Poblar selects del modal con datos de BD ────────────────── */
function poblarSelectsFrecCat() {
    let optsFrec = '<option value="">— Selecciona —</option>';
    _frecuencias.forEach(f => {
        optsFrec += `<option value="${f.id_frecuencia}">${escHtml(f.nombre)}</option>`;
    });
    $('#tarea_id_frecuencia').html(optsFrec);

    let optsCat = '<option value="">— Selecciona —</option>';
    _categorias.forEach(c => {
        optsCat += `<option value="${c.id_categoria}">${escHtml(c.nombre)}</option>`;
    });
    $('#tarea_id_categoria').html(optsCat);
}

/* ── Construir botones de filtro de frecuencia ───────────────── */
function construirFiltrosFrecuencia() {
    const $cont = $('#filtrosFrecuencia');
    // Mantener el botón "Todas" que ya existe en el HTML
    _frecuencias.forEach(f => {
        // Determinar clase outline vs sólido según color_badge de la BD
        const baseClass = f.color_badge.replace('text-dark', '').trim();
        $cont.append(
            `<button class="btn btn-sm ${baseClass} filtro-frec"
                     data-id-frecuencia="${f.id_frecuencia}">
                 ${escHtml(f.nombre)}
             </button>`
        );
    });
}

/* ═══════════════════════════════════════════════════════════════
   DATATABLE DE TAREAS
═══════════════════════════════════════════════════════════════ */
let _tabla;

function initTablaTareas() {
    _tabla = $('#tblTareas').DataTable({
        ajax: {
            url: CTRL_SATAKE, type: 'POST',
            data: d => Object.assign(d, {
                accion:        'listarTareas',
                id_frecuencia: _filtroIdFrec
            }),
            dataSrc: r => (r && r.ok) ? r.data : []
        },
        columns: [
            { data: 'nombre',
              render: (v, t, r) => {
                  let s = `<span class="fw-semibold">${escHtml(v)}</span>`;
                  if (String(r.activo) !== '1')
                      s += ' <span class="badge bg-light text-muted border ms-1 small">Inactiva</span>';
                  if (String(r.solo_tecnico) === '1')
                      s += ' <span class="badge bg-dark ms-1 small"><i class="bi bi-tools me-1"></i>Técnico</span>';
                  return s;
              }
            },
            { data: 'id_frecuencia', className: 'text-center',
              render: (v, t, r) => badgePor(r.frec_badge, r.frecuencia)
            },
            { data: 'id_categoria', className: 'text-center col-hide-sm',
              render: (v, t, r) => badgePor(r.cat_badge, r.categoria)
            },
            { data: 'solo_tecnico', className: 'text-center col-hide-sm',
              render: v => String(v) === '1'
                  ? '<i class="bi bi-check-circle-fill text-dark"></i>'
                  : '<span class="text-muted">—</span>'
            },
            { data: 'activo', className: 'text-center col-hide-sm',
              render: v => String(v) === '1'
                  ? '<span class="badge bg-success">Activa</span>'
                  : '<span class="badge bg-secondary">Inactiva</span>'
            },
            { data: null, className: 'text-center', orderable: false,
              render: (d, t, r) => botonesFilaTarea(r)
            },
        ],
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
        order: [[1, 'asc'], [0, 'asc']],
        pageLength: 25,
        responsive: false,
    });
}

function botonesFilaTarea(r) {
    let b = `<button class="btn btn-sm btn-outline-secondary btn-ver-tarea me-1"
                 data-id="${r.id_tarea}" title="Ver detalle">
                 <i class="bi bi-eye"></i></button>`;
    if (esAdmin) {
        b += `<button class="btn btn-sm btn-outline-primary btn-editar-tarea me-1"
                  data-id="${r.id_tarea}" title="Editar">
                  <i class="bi bi-pencil"></i></button>`;
        const iconToggle = String(r.activo) === '1'
            ? '<i class="bi bi-toggle-on text-success"></i>'
            : '<i class="bi bi-toggle-off text-secondary"></i>';
        b += `<button class="btn btn-sm btn-outline-secondary btn-toggle-tarea"
                  data-id="${r.id_tarea}" title="Activar / Desactivar">
                  ${iconToggle}</button>`;
    }
    return b;
}

/* ═══════════════════════════════════════════════════════════════
   MODAL TAREA
═══════════════════════════════════════════════════════════════ */
function abrirModalNuevaTarea() {
    limpiarModalTarea();
    $('#modalTareaTitulo').html('<i class="bi bi-plus-circle me-2"></i>Nueva Tarea');
    $('#tarea_id').val('');
    if (esAdmin) {
        $('#btnGuardarTarea').removeClass('d-none');
        $('#btnActualizarTarea').addClass('d-none');
    }
    setModalTareaReadonly(false);
    $MODAL_TAREA.modal('show');
}

function abrirModalVerTarea(id) {
    limpiarModalTarea();
    $('#modalTareaTitulo').html('<i class="bi bi-list-check me-2"></i>Detalle de Tarea');
    if (esAdmin) $('#btnGuardarTarea,#btnActualizarTarea').addClass('d-none');
    setModalTareaReadonly(true);
    cargarDatosTarea(id);
    $MODAL_TAREA.modal('show');
}

function abrirModalEditarTarea(id) {
    limpiarModalTarea();
    $('#modalTareaTitulo').html('<i class="bi bi-pencil me-2"></i>Editar Tarea');
    if (esAdmin) {
        $('#btnActualizarTarea').removeClass('d-none');
        $('#btnGuardarTarea').addClass('d-none');
    }
    setModalTareaReadonly(false);
    cargarDatosTarea(id);
    $MODAL_TAREA.modal('show');
}

function setModalTareaReadonly(ro) {
    $('#tarea_nombre,#tarea_id_frecuencia,#tarea_id_categoria,#tarea_por_que,#tarea_si_no_se_hace,#tarea_metodo')
        .prop('disabled', ro);
    $('#tarea_solo_tecnico').prop('disabled', ro);
}

function cargarDatosTarea(id) {
    $.post(CTRL_SATAKE, { accion: 'obtenerTarea', id }, function (r) {
        if (!r.ok) { Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje }); return; }
        const t = r.data;
        $('#tarea_id').val(t.id_tarea);
        $('#tarea_nombre').val(t.nombre);
        $('#tarea_id_frecuencia').val(t.id_frecuencia);
        $('#tarea_id_categoria').val(t.id_categoria);
        $('#tarea_por_que').val(t.por_que);
        $('#tarea_si_no_se_hace').val(t.si_no_se_hace);
        $('#tarea_metodo').val(t.metodo || '');
        $('#tarea_solo_tecnico').prop('checked', String(t.solo_tecnico) === '1');
    }, 'json');
}

function limpiarModalTarea() {
    $('#tarea_id,#tarea_nombre,#tarea_por_que,#tarea_si_no_se_hace,#tarea_metodo').val('');
    $('#tarea_id_frecuencia,#tarea_id_categoria').val('');
    $('#tarea_solo_tecnico').prop('checked', false);
    $('#tarea_nombre,#tarea_id_frecuencia,#tarea_id_categoria,#tarea_por_que,#tarea_si_no_se_hace')
        .removeClass('is-invalid');
}

function recolectarDatosTarea() {
    const nombre        = $('#tarea_nombre').val().trim();
    const id_frecuencia = $('#tarea_id_frecuencia').val();
    const id_categoria  = $('#tarea_id_categoria').val();
    const por_que       = $('#tarea_por_que').val().trim();
    const si_no_se_hace = $('#tarea_si_no_se_hace').val().trim();

    let ok = true;
    $('#tarea_nombre').toggleClass('is-invalid', !nombre);           if (!nombre)        ok = false;
    $('#tarea_id_frecuencia').toggleClass('is-invalid', !id_frecuencia); if (!id_frecuencia) ok = false;
    $('#tarea_id_categoria').toggleClass('is-invalid',  !id_categoria);  if (!id_categoria)  ok = false;
    $('#tarea_por_que').toggleClass('is-invalid', !por_que);         if (!por_que)       ok = false;
    $('#tarea_si_no_se_hace').toggleClass('is-invalid', !si_no_se_hace); if (!si_no_se_hace) ok = false;

    if (!ok) return null;
    return {
        nombre, id_frecuencia, id_categoria, por_que, si_no_se_hace,
        metodo:       $('#tarea_metodo').val().trim() || '',
        solo_tecnico: $('#tarea_solo_tecnico').is(':checked') ? 1 : 0,
    };
}

/* ═══════════════════════════════════════════════════════════════
   ALERTAS
═══════════════════════════════════════════════════════════════ */
function cargarAlertas() {
    $('#acordeonAlertas').html(
        '<div class="text-center py-3"><span class="spinner-border spinner-border-sm me-2"></span> Cargando...</div>'
    );
    $.post(CTRL_SATAKE, { accion: 'listarAlertas' }, function (r) {
        if (!r.ok || !r.data.length) {
            $('#acordeonAlertas').html('<div class="alert alert-secondary">Sin alertas registradas.</div>');
            return;
        }
        renderAlertas(r.data);
    }, 'json');
}

function renderAlertas(alertas) {
    let html = '';
    alertas.forEach(a => {
        const inact = String(a.activo) !== '1' ? ' inactiva' : '';
        const btns  = esAdmin
            ? `<div class="d-flex gap-1 mt-1 flex-shrink-0">
                 <button class="btn btn-sm btn-outline-warning btn-editar-alerta"
                         data-id="${a.id_alerta}" title="Editar">
                     <i class="bi bi-pencil"></i>
                 </button>
                 <button class="btn btn-sm btn-outline-secondary btn-toggle-alerta"
                         data-id="${a.id_alerta}" title="Activar / Desactivar">
                     ${String(a.activo) === '1'
                         ? '<i class="bi bi-toggle-on text-success"></i>'
                         : '<i class="bi bi-toggle-off text-secondary"></i>'}
                 </button>
               </div>`
            : '';

        html += `
        <div class="alerta-card${inact}" data-id-alerta="${a.id_alerta}">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div class="flex-grow-1">
                    <p class="alerta-senal mb-1">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        ${escHtml(a.senal)}
                    </p>
                    <p class="small mb-1">
                        <span class="text-muted fw-semibold">Causa:</span> ${escHtml(a.causa)}
                    </p>
                    <p class="small mb-0">
                        <span class="text-muted fw-semibold">Acción:</span>
                        <span class="text-success fw-semibold">${escHtml(a.accion)}</span>
                    </p>
                </div>
                ${btns}
            </div>
        </div>`;
    });
    $('#acordeonAlertas').html(html);
}

/* ── Modal alerta ────────────────────────────────────────────── */
function abrirModalNuevaAlerta() {
    limpiarModalAlerta();
    $('#modalAlertaTitulo').html('<i class="bi bi-plus-circle me-2"></i>Nueva Alerta');
    $('#alerta_id').val('');
    $('#btnGuardarAlerta').removeClass('d-none');
    $('#btnActualizarAlerta').addClass('d-none');
    setModalAlertaReadonly(false);
    $MODAL_ALERTA.modal('show');
}

function abrirModalEditarAlerta(id) {
    limpiarModalAlerta();
    $('#modalAlertaTitulo').html('<i class="bi bi-pencil me-2"></i>Editar Alerta');
    $('#btnActualizarAlerta').removeClass('d-none');
    $('#btnGuardarAlerta').addClass('d-none');
    setModalAlertaReadonly(false);
    $.post(CTRL_SATAKE, { accion: 'obtenerAlerta', id }, function (r) {
        if (!r.ok) { Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje }); return; }
        const a = r.data;
        $('#alerta_id').val(a.id_alerta);
        $('#alerta_senal').val(a.senal);
        $('#alerta_causa').val(a.causa);
        $('#alerta_accion').val(a.accion);
    }, 'json');
    $MODAL_ALERTA.modal('show');
}

function setModalAlertaReadonly(ro) {
    $('#alerta_senal,#alerta_causa,#alerta_accion').prop('disabled', ro);
}

function limpiarModalAlerta() {
    $('#alerta_id,#alerta_senal,#alerta_causa,#alerta_accion').val('');
    $('#alerta_senal,#alerta_causa,#alerta_accion').removeClass('is-invalid');
}

function recolectarDatosAlerta() {
    const senal  = $('#alerta_senal').val().trim();
    const causa  = $('#alerta_causa').val().trim();
    const accion = $('#alerta_accion').val().trim();

    let ok = true;
    $('#alerta_senal').toggleClass('is-invalid',  !senal);  if (!senal)  ok = false;
    $('#alerta_causa').toggleClass('is-invalid',  !causa);  if (!causa)  ok = false;
    $('#alerta_accion').toggleClass('is-invalid', !accion); if (!accion) ok = false;

    return ok ? { senal, causa, accion_alerta: accion } : null;
}

/* ═══════════════════════════════════════════════════════════════
   EVENTOS
═══════════════════════════════════════════════════════════════ */
function bindEventos() {

    // ── Filtros de frecuencia ────────────────────────────────
    $('#filtrosFrecuencia').on('click', '.filtro-frec', function () {
        $('#filtrosFrecuencia .filtro-frec').removeClass('active');
        $(this).addClass('active');
        _filtroIdFrec = parseInt($(this).data('id-frecuencia') || 0);
        if (_tabla) _tabla.ajax.reload();
    });

    // ── Nueva tarea ──────────────────────────────────────────
    $('#btnNuevaTarea').on('click', abrirModalNuevaTarea);

    // ── Tabla: ver / editar / toggle ─────────────────────────
    $('#tblTareas').on('click', '.btn-ver-tarea', function () {
        abrirModalVerTarea(Number($(this).data('id')));
    });
    $('#tblTareas').on('click', '.btn-editar-tarea', function () {
        abrirModalEditarTarea(Number($(this).data('id')));
    });
    $('#tblTareas').on('click', '.btn-toggle-tarea', function () {
        const id = Number($(this).data('id'));
        $.post(CTRL_SATAKE, { accion: 'toggleActivoTarea', id }, function (r) {
            if (!r.ok) { Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje }); return; }
            _tabla.ajax.reload(null, false);
        }, 'json');
    });

    // ── Guardar nueva tarea ──────────────────────────────────
    $('#btnGuardarTarea').on('click', function () {
        const d = recolectarDatosTarea();
        if (!d) return;
        $(this).prop('disabled', true);
        $.post(CTRL_SATAKE, Object.assign({ accion: 'guardarTarea' }, d), function (r) {
            $('#btnGuardarTarea').prop('disabled', false);
            if (!r.ok) { Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje }); return; }
            $MODAL_TAREA.modal('hide');
            _tabla.ajax.reload(null, false);
            Swal.fire({ icon: 'success', title: '¡Creada!', text: r.mensaje,
                        timer: 2000, showConfirmButton: false });
        }, 'json');
    });

    // ── Actualizar tarea ─────────────────────────────────────
    $('#btnActualizarTarea').on('click', function () {
        const d = recolectarDatosTarea();
        if (!d) return;
        const id = parseInt($('#tarea_id').val() || 0);
        if (!id) return;
        $(this).prop('disabled', true);
        $.post(CTRL_SATAKE, Object.assign({ accion: 'actualizarTarea', id }, d), function (r) {
            $('#btnActualizarTarea').prop('disabled', false);
            if (!r.ok) { Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje }); return; }
            $MODAL_TAREA.modal('hide');
            _tabla.ajax.reload(null, false);
            Swal.fire({ icon: 'success', title: '¡Actualizada!', text: r.mensaje,
                        timer: 2000, showConfirmButton: false });
        }, 'json');
    });

    // ── Limpiar is-invalid en tarea ──────────────────────────
    $('#tarea_nombre,#tarea_por_que,#tarea_si_no_se_hace').on('input', function () {
        $(this).removeClass('is-invalid');
    });
    $('#tarea_id_frecuencia,#tarea_id_categoria').on('change', function () {
        $(this).removeClass('is-invalid');
    });

    // ── Cargar alertas al activar su tab ─────────────────────
    $('#tab-alertas-btn').on('shown.bs.tab', cargarAlertas);

    // ── Nueva alerta ─────────────────────────────────────────
    $('#btnNuevaAlerta').on('click', abrirModalNuevaAlerta);

    // ── Editar / toggle alerta ───────────────────────────────
    $('#acordeonAlertas').on('click', '.btn-editar-alerta', function () {
        abrirModalEditarAlerta(Number($(this).data('id')));
    });
    $('#acordeonAlertas').on('click', '.btn-toggle-alerta', function () {
        const id = Number($(this).data('id'));
        $.post(CTRL_SATAKE, { accion: 'toggleActivoAlerta', id }, function (r) {
            if (!r.ok) { Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje }); return; }
            cargarAlertas();
        }, 'json');
    });

    // ── Guardar nueva alerta ─────────────────────────────────
    $('#btnGuardarAlerta').on('click', function () {
        const d = recolectarDatosAlerta();
        if (!d) return;
        $(this).prop('disabled', true);
        $.post(CTRL_SATAKE, Object.assign({ accion: 'guardarAlerta' }, d), function (r) {
            $('#btnGuardarAlerta').prop('disabled', false);
            if (!r.ok) { Swal.fire({ icon: 'error', title: 'Error', text: r.manera }); return; }
            $MODAL_ALERTA.modal('hide');
            cargarAlertas();
            Swal.fire({ icon: 'success', title: '¡Creada!', text: r.mensaje,
                        timer: 2000, showConfirmButton: false });
        }, 'json');
    });

    // ── Actualizar alerta ────────────────────────────────────
    $('#btnActualizarAlerta').on('click', function () {
        const d = recolectarDatosAlerta();
        if (!d) return;
        const id = parseInt($('#alerta_id').val() || 0);
        if (!id) return;
        $(this).prop('disabled', true);
        $.post(CTRL_SATAKE, Object.assign({ accion: 'actualizarAlerta', id }, d), function (r) {
            $('#btnActualizarAlerta').prop('disabled', false);
            if (!r.ok) { Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje }); return; }
            $MODAL_ALERTA.modal('hide');
            cargarAlertas();
            Swal.fire({ icon: 'success', title: '¡Actualizada!', text: r.mensaje,
                        timer: 2000, showConfirmButton: false });
        }, 'json');
    });

    // ── Limpiar is-invalid en alerta ─────────────────────────
    $('#alerta_senal,#alerta_causa,#alerta_accion').on('input', function () {
        $(this).removeClass('is-invalid');
    });
}
