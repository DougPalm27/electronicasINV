const CTRL_SOL = './modules/Solicitudes/controllers/solicitudesController.php';

/* ── Caché de catálogos ──────────────────────────────────── */
let _maquinas  = [];
let _repuestos = [];
let _tipos     = [];
let _tecnicos  = [];

/* ── Estado del constructor de solicitud ─────────────────── */
let _tempIdCounter = 0;

/* ── Helpers de Select2 ──────────────────────────────────── */
// Configuración base; dropdownParent apunta al modal activo
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

$(document).ready(function () {
    if (!document.getElementById('tblSolicitudes')) return;

    const esAdmin  = (window.USUARIO_ROL === 'Administrador');
    const $MODAL   = $('#modalNuevaSolicitud');   // referencia para Select2 dropdownParent

    /* ═══════════════════════════════════════════════════════
       DATATABLE
    ═══════════════════════════════════════════════════════ */
    const tabla = $('#tblSolicitudes').DataTable({
        ajax: {
            url: CTRL_SOL, type: 'POST',
            data: { accion: 'listar' },
            dataSrc: r => r.ok ? r.data : []
        },
        columns: [
            { data: null, render: (d, t, r, m) => m.row + 1 },
            { data: 'descripcion',
              render: v => `<span title="${v}">${v.length > 45 ? v.slice(0,45)+'…' : v}</span>` },
            { data: 'tipo',           className: 'col-hide-sm' },
            { data: 'solicitante',    className: 'col-hide-xs' },
            { data: 'total_maquinas', className: 'text-center col-hide-sm',
              render: v => `<span class="badge bg-secondary">${v}</span>` },
            { data: 'fecha_programada', className: 'col-hide-md' },
            { data: 'fecha_solicitud',  className: 'col-hide-md' },
            { data: 'estado', className: 'text-center', render: badgeEstado },
            { data: null, className: 'text-center', orderable: false,
              render: (d, t, r) => botonesAccion(r, esAdmin) }
        ],
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
        order: [[6, 'desc']],
        pageLength: 15
    });

    /* ── Filtro de estado ───────────────────────────────────── */
    $('#filtrosEstado').on('click', '.filtro-estado', function () {
        $('.filtro-estado').removeClass('active');
        $(this).addClass('active');
        tabla.column(7).search($(this).data('estado')).draw();
    });

    /* ═══════════════════════════════════════════════════════
       CARGAR CATÁLOGOS
    ═══════════════════════════════════════════════════════ */
    function cargarCatalogos(cb) {
        let pendientes = 4;
        const done = () => { if (--pendientes === 0 && cb) cb(); };

        $.post(CTRL_SOL, { accion: 'listarMaquinas'  }, r => { _maquinas  = r.ok ? r.data : []; done(); }, 'json');
        $.post(CTRL_SOL, { accion: 'listarRepuestos' }, r => { _repuestos = r.ok ? r.data : []; done(); }, 'json');
        $.post(CTRL_SOL, { accion: 'listarTipos'     }, r => { _tipos     = r.ok ? r.data : []; done(); }, 'json');
        $.post(CTRL_SOL, { accion: 'listarTecnicos'  }, r => { _tecnicos  = r.ok ? r.data : []; done(); }, 'json');
    }

    /* ═══════════════════════════════════════════════════════
       MODAL — NUEVA SOLICITUD
    ═══════════════════════════════════════════════════════ */
    $('#btnNuevaSolicitud').on('click', function () {
        // Reset cabecera
        $('#sol_descripcion').val('').removeClass('is-invalid');
        $('#sol_tipo').val('').removeClass('is-invalid');
        $('#sol_fecha').val('').removeClass('is-invalid');

        // Destruir Select2 existentes y limpiar máquinas
        $('#contenedorMaquinas .sel-maquina, #contenedorMaquinas .sel-repuesto').each(function () {
            if ($(this).data('select2')) $(this).select2('destroy');
        });
        $('#contenedorMaquinas .maquina-card').remove();
        $('#emptyMaquinas').show();
        _tempIdCounter = 0;

        cargarCatalogos(function () {
            // Poblar select de tipo
            let optsTipo = '<option value="">— Selecciona —</option>';
            _tipos.forEach(t => optsTipo += `<option value="${t.id_tipo}">${t.nombre}</option>`);
            $('#sol_tipo').html(optsTipo);

            // Poblar select de técnico
            let optsTec = '<option value="">— Opcional —</option>';
            _tecnicos.forEach(t => optsTec += `<option value="${t.id_tecnico}">${t.nombre}</option>`);
            $('#sol_tecnico').html(optsTec).prop('disabled', false);

            // Si el usuario logueado es Técnico: pre-seleccionar y bloquear
            if (window.USUARIO_ROL === 'Técnico') {
                $('#sol_tecnico').val(window.USUARIO_ID).prop('disabled', true);
            }

            abrirModal('#modalNuevaSolicitud');
        });
    });

    /* ── Agregar bloque de máquina ──────────────────────────── */
    $('#btnAgregarMaquina').on('click', agregarMaquina);

    function agregarMaquina() {
        const id  = ++_tempIdCounter;
        const tpl = document.getElementById('tplMaquinaCard').content.cloneNode(true);
        const card = tpl.querySelector('.maquina-card');
        card.dataset.tempId = id;

        // Poblar options de la máquina ANTES de append (elemento nativo, no Select2 todavía)
        const sel = card.querySelector('.sel-maquina');
        _maquinas.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.id_maquina;
            opt.textContent = m.nombre;
            sel.appendChild(opt);
        });

        $('#emptyMaquinas').hide();
        $('#contenedorMaquinas').append(card);

        // Inicializar Select2 DESPUÉS de que el elemento esté en el DOM
        const $card = $('#contenedorMaquinas .maquina-card').last();
        $card.find('.sel-maquina').select2(s2Opts('— Selecciona máquina —', $MODAL));
    }

    /* ── Quitar bloque de máquina ───────────────────────────── */
    $('#contenedorMaquinas').on('click', '.btn-quitar-maquina', function () {
        const $card = $(this).closest('.maquina-card');
        // Destruir Select2 antes de remover del DOM
        $card.find('.sel-maquina, .sel-repuesto').each(function () {
            if ($(this).data('select2')) $(this).select2('destroy');
        });
        $card.remove();
        if ($('.maquina-card').length === 0) $('#emptyMaquinas').show();
    });

    /* ── Agregar repuesto a una máquina ─────────────────────── */
    $('#contenedorMaquinas').on('click', '.btn-agregar-repuesto', function () {
        const $repList = $(this).closest('.card-body').find('.rep-list');
        agregarFilaRepuesto($repList);
    });

    function agregarFilaRepuesto($repList) {
        // Construir opciones enriquecidas: Nombre — Marca / Modelo [serie]
        const optsRep = _repuestos.map(r => {
            let detalle = '';
            if (r.marca && r.modelo)   detalle = ` — ${r.marca} / ${r.modelo}`;
            else if (r.marca)          detalle = ` — ${r.marca}`;
            else if (r.modelo)         detalle = ` — ${r.modelo}`;
            const serieTag = r.maneja_serie == 1 ? ' [serie]' : '';

            return `<option value="${r.id_repuesto}"
                            data-stock="${r.stock}"
                            data-serie="${r.maneja_serie}"
                            data-marca="${r.marca  || ''}"
                            data-modelo="${r.modelo || ''}"
                    >${r.nombre}${detalle}${serieTag}</option>`;
        }).join('');

        const $item = $(`
        <div class="rep-item">
            <div class="rep-select-wrap">
                <select class="form-select form-select-sm sel-repuesto">
                    <option value="">— Busca un repuesto —</option>
                    ${optsRep}
                </select>
            </div>
            <div class="rep-actions">
                <input type="number" class="form-control form-control-sm inp-cantidad"
                       min="1" value="1" style="width:80px" title="Cantidad">
                <span class="badge badge-stock-ok td-stock">—</span>
                <button type="button" class="btn btn-sm btn-outline-danger btn-quitar-rep" title="Quitar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>`);

        $repList.append($item);

        // Inicializar Select2 en el nuevo select (ya está en el DOM)
        $item.find('.sel-repuesto').select2(s2Opts('— Busca un repuesto —', $MODAL));
    }

    /* ── Quitar fila de repuesto ─────────────────────────────── */
    $('#contenedorMaquinas').on('click', '.btn-quitar-rep', function () {
        const $item = $(this).closest('.rep-item');
        const $sel  = $item.find('.sel-repuesto');
        if ($sel.data('select2')) $sel.select2('destroy');
        $item.remove();
    });

    /* ── Actualizar badge de stock al cambiar repuesto ──────── */
    $('#contenedorMaquinas').on('change', '.sel-repuesto', function () {
        const opt    = $(this).find(':selected');
        const stock  = parseInt(opt.data('stock') || 0);
        const serie  = parseInt(opt.data('serie')  || 0);
        const $item  = $(this).closest('.rep-item');
        const $badge = $item.find('.td-stock');
        const $inp   = $item.find('.inp-cantidad');

        if (!$(this).val()) {
            $badge.text('—').removeClass().addClass('badge badge-stock-ok td-stock');
            return;
        }
        if (serie) {
            $badge.text(`${stock} disp.`).removeClass()
                  .addClass('badge badge-stock-warn td-stock');
            $inp.attr('max', stock);
        } else if (stock === 0) {
            $badge.text('Sin stock').removeClass()
                  .addClass('badge badge-stock-bad td-stock');
        } else {
            $badge.text(`${stock} disp.`).removeClass()
                  .addClass(`badge ${stock <= 2 ? 'badge-stock-warn' : 'badge-stock-ok'} td-stock`);
            $inp.attr('max', stock);
        }
    });

    /* ── Recalcular color badge cuando cambia cantidad ──────── */
    $('#contenedorMaquinas').on('input', '.inp-cantidad', function () {
        const $item  = $(this).closest('.rep-item');
        const $sel   = $item.find('.sel-repuesto');
        const stock  = parseInt($sel.find(':selected').data('stock') || 0);
        const cant   = parseInt($(this).val() || 0);
        const $badge = $item.find('.td-stock');
        if (!$sel.val()) return;

        if (cant > stock) {
            $badge.text(`${stock} disp. ⚠`).removeClass()
                  .addClass('badge badge-stock-bad td-stock');
        } else if (stock <= 2) {
            $badge.text(`${stock} disp.`).removeClass()
                  .addClass('badge badge-stock-warn td-stock');
        } else {
            $badge.text(`${stock} disp.`).removeClass()
                  .addClass('badge badge-stock-ok td-stock');
        }
    });

    /* ── Enviar solicitud ───────────────────────────────────── */
    $('#btnEnviarSolicitud').on('click', function () {
        let ok = true;
        const descripcion      = $('#sol_descripcion').val().trim();
        const id_tipo          = $('#sol_tipo').val();
        const fecha_programada = $('#sol_fecha').val();

        $('#sol_descripcion').toggleClass('is-invalid', !descripcion);
        $('#sol_tipo').toggleClass('is-invalid', !id_tipo);
        $('#sol_fecha').toggleClass('is-invalid', !fecha_programada);
        if (!descripcion || !id_tipo || !fecha_programada) ok = false;

        // Recolectar máquinas y repuestos
        const maquinas = [];
        let errorMaquinas = '';

        $('.maquina-card').each(function () {
            const id_maquina = $(this).find('.sel-maquina').val();
            if (!id_maquina) { errorMaquinas = 'Selecciona la máquina en todos los bloques.'; return false; }

            const repuestos = [];
            $(this).find('.rep-item').each(function () {
                const id_rep = $(this).find('.sel-repuesto').val();
                const cant   = parseInt($(this).find('.inp-cantidad').val() || 0);
                if (!id_rep) { errorMaquinas = 'Selecciona el repuesto en todas las filas.'; return false; }
                if (cant < 1) { errorMaquinas = 'La cantidad debe ser mayor a 0.'; return false; }
                repuestos.push({ id_repuesto: id_rep, cantidad: cant });
            });
            if (errorMaquinas) return false;

            if (!repuestos.length) { errorMaquinas = 'Agrega al menos un repuesto a cada máquina.'; return false; }
            maquinas.push({
                id_maquina,
                descripcion: $(this).find('.inp-desc-maquina').val().trim(),
                repuestos
            });
        });

        if (!maquinas.length) errorMaquinas = 'Agrega al menos una máquina.';
        if (errorMaquinas)    { Swal.fire({ icon: 'warning', title: 'Atención', text: errorMaquinas }); ok = false; }
        if (!ok) return;

        // Validar stock antes de enviar
        let errorStock = '';
        $('.maquina-card').each(function () {
            $(this).find('.rep-item').each(function () {
                const $sel  = $(this).find('.sel-repuesto');
                if (!$sel.val()) return;
                const opt   = $sel.find('option:selected');
                const serie = parseInt(opt.data('serie') || 0);
                if (serie) return; // serie: no se valida aquí
                const stock = parseInt(opt.data('stock') || 0);
                const cant  = parseInt($(this).find('.inp-cantidad').val() || 0);
                const nombre = opt.text().split(' —')[0].trim();
                if (cant > stock) {
                    errorStock = `Stock insuficiente para "${nombre}". Disponible: ${stock}, solicitado: ${cant}.`;
                    return false;
                }
            });
            if (errorStock) return false;
        });
        if (errorStock) {
            Swal.fire({ icon: 'error', title: 'Stock insuficiente', text: errorStock });
            return;
        }

        // Leer técnico (puede estar disabled)
        const id_tecnico_val = $('#sol_tecnico').prop('disabled')
            ? $('#sol_tecnico option:selected').val()
            : $('#sol_tecnico').val();

        const payload = {
            descripcion,
            id_tipo,
            fecha_programada,
            id_tecnico: id_tecnico_val || null,
            maquinas
        };

        const $btn = $(this).prop('disabled', true)
                            .html('<span class="spinner-border spinner-border-sm me-1"></span> Enviando...');

        $.post(CTRL_SOL, { accion: 'crear', payload: JSON.stringify(payload) }, function (r) {
            $btn.prop('disabled', false)
                .html('<i class="bi bi-send me-1"></i> Enviar solicitud');
            if (!r.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje });
                return;
            }
            cerrarModal('#modalNuevaSolicitud');
            tabla.ajax.reload();
            Swal.fire({ icon: 'success', title: '¡Solicitud enviada!', text: r.mensaje,
                        timer: 2200, showConfirmButton: false });
        }, 'json');
    });

    /* ═══════════════════════════════════════════════════════
       MODAL DETALLE / APROBAR / RECHAZAR
    ═══════════════════════════════════════════════════════ */
    let _idDetalle = 0;

    $('#tblSolicitudes').on('click', '.btn-ver', function () {
        _idDetalle = $(this).data('id');
        $('#detalleSolId').text('#' + String(_idDetalle).padStart(5, '0'));
        $('#detalleBody').html(
            '<div class="text-center py-4"><span class="spinner-border spinner-border-sm me-2"></span> Cargando...</div>'
        );
        $('#btnAprobar, #btnRechazar').addClass('d-none');
        abrirModal('#modalDetalle');

        $.post(CTRL_SOL, { accion: 'detalle', id_solicitud: _idDetalle }, function (r) {
            if (!r.ok) {
                $('#detalleBody').html(`<div class="alert alert-danger">${r.mensaje}</div>`);
                return;
            }
            renderDetalle(r.data);
            if (esAdmin && r.data.estado === 'Pendiente') {
                $('#btnAprobar').removeClass('d-none');
                $('#btnRechazar').removeClass('d-none');
            }
        }, 'json');
    });

    function renderDetalle(d) {
        const estadoBadge = badgeEstado(d.estado);
        let html = `
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6">
                <small class="text-muted d-block">Descripción</small>
                <strong>${d.descripcion}</strong>
            </div>
            <div class="col-6 col-md-3">
                <small class="text-muted d-block">Tipo</small>
                ${d.tipo_nombre}
            </div>
            <div class="col-6 col-md-3">
                <small class="text-muted d-block">Estado</small>
                ${estadoBadge}
            </div>
            <div class="col-6 col-md-3">
                <small class="text-muted d-block">Fecha programada</small>
                <i class="bi bi-calendar2 me-1"></i>${d.fecha_prog_fmt}
            </div>
            <div class="col-6 col-md-3">
                <small class="text-muted d-block">Solicitante</small>
                ${d.solicitante}
            </div>
            <div class="col-6 col-md-3">
                <small class="text-muted d-block">Técnico asignado</small>
                ${d.tecnico_nombre}
            </div>
            <div class="col-6 col-md-3">
                <small class="text-muted d-block">Fecha solicitud</small>
                ${d.fecha_sol_fmt}
            </div>`;

        if (d.estado === 'Rechazado') {
            html += `
            <div class="col-12">
                <div class="alert alert-danger py-2 mb-0">
                    <strong>Motivo del rechazo:</strong> ${d.motivo_rechazo || '—'}
                    <span class="text-muted ms-2 small">— ${d.revisor_nombre}, ${d.fecha_rev_fmt}</span>
                </div>
            </div>`;
        }
        if (d.estado === 'Aprobado') {
            html += `
            <div class="col-12">
                <div class="alert alert-success py-2 mb-0">
                    <i class="bi bi-check-circle me-1"></i>
                    Aprobado por <strong>${d.revisor_nombre}</strong> el ${d.fecha_rev_fmt}
                </div>
            </div>`;
        }

        html += `</div><hr class="my-3">
        <h6 class="text-primary mb-3"><i class="bi bi-cpu me-1"></i>Máquinas y Repuestos</h6>`;

        d.maquinas.forEach(maq => {
            const hasMant = maq.id_mantenimiento_generado;
            html += `
            <div class="card border mb-3 shadow-sm">
                <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center flex-wrap gap-1">
                    <span>
                        <strong><i class="bi bi-cpu me-1 text-primary"></i>${maq.maquina}</strong>
                        ${maq.descripcion ? `<span class="text-muted ms-2 small d-block d-sm-inline">— ${maq.descripcion}</span>` : ''}
                    </span>
                    ${hasMant ? `<a href="?module=mantenimientos" class="badge bg-success text-decoration-none">
                        <i class="bi bi-tools me-1"></i>Mant. #${hasMant}</a>` : ''}
                </div>
                <div class="card-body p-0">`;

            if (maq.repuestos.length) {
                html += `
                <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Repuesto</th>
                            <th class="text-center" style="width:90px">Cant.</th>
                            <th class="text-center" style="width:110px">Stock actual</th>
                            <th class="text-center" style="width:80px">Control</th>
                        </tr>
                    </thead><tbody>`;

                maq.repuestos.forEach(rep => {
                    const stockOk = rep.stock_actual >= rep.cantidad;
                    const stockBadge = rep.stock_actual == 0
                        ? `<span class="badge badge-stock-bad">Sin stock</span>`
                        : stockOk
                            ? `<span class="badge badge-stock-ok">${rep.stock_actual} disp.</span>`
                            : `<span class="badge badge-stock-warn">⚠ ${rep.stock_actual} disp.</span>`;

                    const ctrlBadge = rep.maneja_serie == 1
                        ? '<span class="badge bg-info text-dark">Serie</span>'
                        : '<span class="badge bg-secondary">Cantidad</span>';

                    html += `<tr>
                        <td>${rep.repuesto}</td>
                        <td class="text-center">${rep.cantidad}</td>
                        <td class="text-center">${stockBadge}</td>
                        <td class="text-center">${ctrlBadge}</td>
                    </tr>`;
                });

                html += `</tbody></table></div>`;
            } else {
                html += `<div class="text-muted small p-3">Sin repuestos especificados.</div>`;
            }

            html += `</div></div>`;
        });

        const haySerieEnSol = d.maquinas.some(m => m.repuestos.some(r => r.maneja_serie == 1));
        if (haySerieEnSol) {
            html += `<div class="alert alert-info py-2 mt-2">
                <i class="bi bi-info-circle me-1"></i>
                <strong>Repuestos con control de serie:</strong>
                Al aprobar, se registrarán en el mantenimiento pero la salida de inventario
                deberá procesarse manualmente desde el módulo de Repuestos.
            </div>`;
        }

        $('#detalleBody').html(html);
    }

    /* ── Aprobar ────────────────────────────────────────────── */
    $('#btnAprobar').on('click', function () {
        Swal.fire({
            title: '¿Aprobar solicitud?',
            html: 'Se generará un mantenimiento por cada máquina y se descontarán los repuestos del inventario.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-check-circle me-1"></i> Sí, aprobar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#198754'
        }).then(res => {
            if (!res.isConfirmed) return;
            $.post(CTRL_SOL, { accion: 'aprobar', id_solicitud: _idDetalle }, function (r) {
                cerrarModal('#modalDetalle');
                tabla.ajax.reload();
                Swal.fire({
                    icon: r.ok ? 'success' : 'error',
                    title: r.ok ? '¡Aprobada!' : 'Error',
                    text: r.mensaje,
                    timer: r.ok ? 2500 : undefined,
                    showConfirmButton: !r.ok
                });
            }, 'json');
        });
    });

    /* ── Rechazar ───────────────────────────────────────────── */
    $('#btnRechazar').on('click', function () {
        $('#rechazo_id').val(_idDetalle);
        $('#rechazo_motivo').val('');
        cerrarModal('#modalDetalle');
        abrirModal('#modalRechazo');
    });

    $('#btnConfirmarRechazo').on('click', function () {
        const id     = $('#rechazo_id').val();
        const motivo = $('#rechazo_motivo').val().trim();
        if (!motivo) {
            Swal.fire({ icon: 'warning', title: 'Escribe el motivo', text: 'El motivo es obligatorio.' });
            return;
        }
        $.post(CTRL_SOL, { accion: 'rechazar', id_solicitud: id, motivo }, function (r) {
            cerrarModal('#modalRechazo');
            tabla.ajax.reload();
            Swal.fire({ icon: r.ok ? 'success' : 'error',
                        title: r.ok ? 'Rechazada' : 'Error',
                        text: r.mensaje,
                        timer: r.ok ? 1800 : undefined,
                        showConfirmButton: !r.ok });
        }, 'json');
    });

    /* ── Cancelar (técnico) ─────────────────────────────────── */
    $('#tblSolicitudes').on('click', '.btn-cancelar', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: '¿Cancelar solicitud?',
            text: 'Esta acción no se puede deshacer.',
            icon: 'warning', showCancelButton: true,
            confirmButtonText: 'Sí, cancelar', cancelButtonText: 'No',
            confirmButtonColor: '#dc3545'
        }).then(res => {
            if (!res.isConfirmed) return;
            $.post(CTRL_SOL, { accion: 'cancelar', id_solicitud: id }, function (r) {
                tabla.ajax.reload(null, false);
                if (!r.ok) Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje });
            }, 'json');
        });
    });

    /* ═══════════════════════════════════════════════════════
       HELPERS
    ═══════════════════════════════════════════════════════ */
    function badgeEstado(estado) {
        const map = {
            'Pendiente': 'bg-warning text-dark',
            'Aprobado':  'bg-success',
            'Rechazado': 'bg-danger',
            'Cancelado': 'bg-secondary'
        };
        return `<span class="badge ${map[estado] || 'bg-secondary'}">${estado}</span>`;
    }

    function botonesAccion(r, esAdmin) {
        let btns = `<button class="btn btn-sm btn-info btn-ver me-1" data-id="${r.id_solicitud}" title="Ver detalle">
                        <i class="bi bi-eye"></i></button>`;
        if (esAdmin && r.estado === 'Pendiente') {
            btns += `<button class="btn btn-sm btn-success me-1 btn-ver" data-id="${r.id_solicitud}" title="Revisar">
                         <i class="bi bi-check-circle"></i></button>`;
        }
        if (!esAdmin && r.estado === 'Pendiente') {
            btns += `<button class="btn btn-sm btn-outline-danger btn-cancelar" data-id="${r.id_solicitud}" title="Cancelar">
                         <i class="bi bi-x-circle"></i></button>`;
        }
        return btns;
    }

    function abrirModal(sel) {
        const el = document.querySelector(sel);
        (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show();
    }
    function cerrarModal(sel) {
        const el = document.querySelector(sel);
        const m  = bootstrap.Modal.getInstance(el);
        if (m) m.hide();
    }
});
