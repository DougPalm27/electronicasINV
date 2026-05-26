const CTRL_GPS  = './modules/GPS/GPS/controllers/gpsController.php';
const CTRL_CU   = './modules/GPS/CuentasGPS/controllers/cuentasGPSController.php';
const CTRL_TV   = './modules/GPS/Parametrizacion/TiposVehiculo/controllers/tiposVehiculoController.php';
const CTRL_DEST = './modules/GPS/Parametrizacion/Destinos/controllers/destinosController.php';
const CTRL_TR   = './modules/GPS/Transportes/controllers/transportesController.php';

$(document).ready(function () {
    if (!document.getElementById('tblGPS')) return;

    let cuentasCache = [];
    let modoEdicion  = false;

    // ── Poblar selects fijos del modal ─────────────────────
    $.post(CTRL_TV,   { accion: 'listarActivos' },  r => poblar('#gps_id_tipo_vehiculo', r.data, 'id_tipo_vehiculo', 'nombre'), 'json');
    $.post(CTRL_DEST, { accion: 'listarActivos' },  r => poblar('#gps_id_destino',       r.data, 'id_destino',       'nombre'), 'json');
    $.post(CTRL_TR,   { accion: 'listarActivos' },  r => poblar('#gps_id_transporte',    r.data, 'id_transporte',    'nombre'), 'json');

    // ── Cascada Transporte → Cuentas GPS ───────────────────
    $('#gps_id_transporte').on('change', function () {
        const idTr = $(this).val();
        const $cu  = $('#gps_id_cuenta');
        $cu.prop('disabled', true).find('option:not(:first)').remove();
        $('#gps_id_cuenta option:first').text('— Primero selecciona transporte —');
        limpiarInfoCuenta();
        if (!idTr) return;

        $.post(CTRL_CU, { accion: 'listarPorTransporte', id_transporte: idTr }, function (r) {
            if (!r.ok || !r.data.length) {
                $('#gps_id_cuenta option:first').text('Sin cuentas para este transporte');
                return;
            }
            cuentasCache = r.data;
            $('#gps_id_cuenta option:first').text('— Selecciona cuenta —');
            r.data.forEach(c => {
                $cu.append(`<option value="${c.id_cuenta}">${esc(c.plataforma)} · ${esc(c.usuario || 'sin usuario')}</option>`);
            });
            $cu.prop('disabled', false);
        }, 'json');
    });

    // ── Autocomplete info al elegir cuenta ─────────────────
    $('#gps_id_cuenta').on('change', function () {
        const id = parseInt($(this).val());
        const c  = cuentasCache.find(x => x.id_cuenta == id);
        if (c) {
            $('#gps_info_plataforma').val(c.plataforma || '');
            $('#gps_info_usuario').val(c.usuario     || '');
            $('#gps_info_contrasena').val(c.contrasena  || '');
        } else {
            limpiarInfoCuenta();
        }
    });

    // ── DataTable ──────────────────────────────────────────
    const tabla = $('#tblGPS').DataTable({
        ajax: {
            url:     CTRL_GPS,
            type:    'POST',
            data:    { accion: 'listar' },
            dataSrc: r => r.ok ? r.data : []
        },
        columns: [
            { data: 'placa', render: v => `
                <div class="placa-hn placa-copiable" data-placa="${esc(v)}" title="Clic para copiar">
                    <div class="ph-top"><span class="ph-flag">🇭🇳</span> HONDURAS</div>
                    <div class="ph-num">${esc(v)}</div>
                    <div class="ph-bot">CENTROAMÉRICA</div>
                    <div class="ph-copied">¡Copiado!</div>
                </div>` },
            { data: 'tipo_vehiculo', defaultContent: '<span class="text-muted">—</span>' },
            { data: 'transporte',    defaultContent: '<span class="text-muted">—</span>' },
            { data: 'plataforma',    defaultContent: '<span class="text-muted">—</span>' },
            { data: 'destino',       defaultContent: '<span class="text-muted">—</span>' },
            { data: 'estado', className: 'text-center',
              render: v => v == 1 ? '<span class="badge bg-success">Activo</span>'
                                  : '<span class="badge bg-secondary">Inactivo</span>' },
            { data: null, className: 'text-center', orderable: false,
              render: (d, t, r) => `
                <div class="dropdown">
                    <button class="btn btn-sm btn-primary dropdown-toggle py-1"
                            type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li>
                            <button class="dropdown-item btn-acceso-rapido" type="button"
                                    data-placa="${esc(r.placa)}"
                                    data-usuario="${esc(r.usuario || '')}"
                                    data-contrasena="${esc(r.contrasena || '')}"
                                    data-url="${esc(r.url_base || '')}"
                                    data-plataforma="${esc(r.plataforma || '')}">
                                <i class="bi bi-key me-2 text-success"></i>Acceso rápido
                            </button>
                        </li>
                        <li>
                            <button class="dropdown-item btn-editar-gps" type="button"
                                    data-id="${r.id_gps}"
                                    data-placa="${esc(r.placa)}"
                                    data-id-tipo="${r.id_tipo_vehiculo || ''}"
                                    data-id-destino="${r.id_destino || ''}"
                                    data-id-transporte="${r.id_transporte || ''}"
                                    data-id-cuenta="${r.id_cuenta || ''}"
                                    data-plataforma="${esc(r.plataforma || '')}"
                                    data-usuario="${esc(r.usuario || '')}"
                                    data-contrasena="${esc(r.contrasena || '')}">
                                <i class="bi bi-pencil me-2 text-warning"></i>Editar
                            </button>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <button class="dropdown-item ${r.estado==1?'text-danger':'text-success'} btn-toggle-gps"
                                    type="button" data-id="${r.id_gps}">
                                <i class="bi bi-${r.estado==1?'toggle-on':'toggle-off'} me-2"></i>
                                ${r.estado==1?'Desactivar':'Activar'}
                            </button>
                        </li>
                    </ul>
                </div>` }
        ],
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
        order: [[0, 'asc']], pageLength: 15, scrollX: true, autoWidth: false
    });

    // ── Agregar fila de placa ──────────────────────────────
    function agregarFilaPlaca(valor = '', soloLectura = false) {
        const $lista  = $('#gps_placas_lista');
        const index   = $lista.find('.placa-row').length;
        const roAttr  = soloLectura ? 'readonly' : '';
        const $fila   = $(`
            <div class="input-group mb-2 placa-row">
                <span class="input-group-text">${index + 1}</span>
                <input type="text" class="form-control inp-placa text-uppercase"
                       placeholder="Ej. ABC-123" maxlength="20" ${roAttr}
                       value="${esc(valor)}">
                ${soloLectura ? '' : '<button type="button" class="btn btn-outline-danger btn-quitar-placa" title="Quitar"><i class="bi bi-trash"></i></button>'}
            </div>`);
        $lista.append($fila);
    }

    // ── Botón agregar placa ────────────────────────────────
    $('#btnAgregarPlaca').on('click', () => agregarFilaPlaca());

    // ── Quitar fila de placa (delegado) ───────────────────
    $('#gps_placas_lista').on('click', '.btn-quitar-placa', function () {
        const $lista = $('#gps_placas_lista');
        if ($lista.find('.placa-row').length <= 1) return;
        $(this).closest('.placa-row').remove();
        // Renumerar
        $lista.find('.placa-row').each(function (i) {
            $(this).find('.input-group-text').text(i + 1);
        });
    });

    // ── Nuevo ──────────────────────────────────────────────
    $('#btnNuevoGPS').on('click', function () {
        modoEdicion = false;
        reset('Nuevo vehículo GPS');
        agregarFilaPlaca();
        abrir('#modalGPS');
    });

    // ── Editar ─────────────────────────────────────────────
    $('#tblGPS').on('click', '.btn-editar-gps', function () {
        const $b = $(this);
        modoEdicion = true;
        reset('Editar vehículo GPS');

        $('#gps_id').val($b.data('id'));
        $('#gps_id_tipo_vehiculo').val($b.data('id-tipo'));
        $('#gps_id_destino').val($b.data('id-destino'));

        agregarFilaPlaca($b.data('placa'), true);

        const idTr     = $b.data('id-transporte');
        const idCuenta = $b.data('id-cuenta');

        if (idTr) {
            $('#gps_id_transporte').val(idTr);
            $.post(CTRL_CU, { accion: 'listarPorTransporte', id_transporte: idTr }, function (r) {
                const $cu = $('#gps_id_cuenta');
                $cu.find('option:not(:first)').remove();
                $('#gps_id_cuenta option:first').text('— Selecciona cuenta —');
                if (r.ok && r.data.length) {
                    cuentasCache = r.data;
                    r.data.forEach(c => {
                        $cu.append(`<option value="${c.id_cuenta}">${esc(c.plataforma)} · ${esc(c.usuario || 'sin usuario')}</option>`);
                    });
                    $cu.prop('disabled', false).val(idCuenta);
                    const c = r.data.find(x => x.id_cuenta == idCuenta);
                    if (c) {
                        $('#gps_info_plataforma').val(c.plataforma || '');
                        $('#gps_info_usuario').val(c.usuario     || '');
                        $('#gps_info_contrasena').val(c.contrasena  || '');
                    }
                }
            }, 'json');
        } else {
            $('#gps_info_plataforma').val($b.data('plataforma'));
            $('#gps_info_usuario').val($b.data('usuario'));
            $('#gps_info_contrasena').val($b.data('contrasena'));
        }

        abrir('#modalGPS');
    });

    // ── Guardar ────────────────────────────────────────────
    $('#btnGuardarGPS').on('click', function () {
        const id       = $('#gps_id').val();
        const idCuenta = $('#gps_id_cuenta').val();
        let ok = true;

        $('#gps_id_cuenta').toggleClass('is-invalid', !idCuenta);
        if (!idCuenta) ok = false;

        // Recolectar placas
        const placas = [];
        let placaVacia = false;
        $('#gps_placas_lista .inp-placa').each(function () {
            const v = $(this).val().trim().toUpperCase();
            if (!v) { placaVacia = true; $(this).addClass('is-invalid'); }
            else      { $(this).removeClass('is-invalid'); placas.push(v); }
        });
        if (placaVacia) ok = false;

        // Duplicados dentro del formulario
        if (ok && !modoEdicion && placas.length !== new Set(placas).size) {
            $('#gps_placas_error').text('Hay placas repetidas en el listado.').show();
            ok = false;
        } else {
            $('#gps_placas_error').hide();
        }

        if (!ok) return;

        const datos = {
            accion:           modoEdicion ? 'editar' : 'crear',
            id_cuenta:        idCuenta,
            id_tipo_vehiculo: $('#gps_id_tipo_vehiculo').val(),
            id_destino:       $('#gps_id_destino').val(),
        };

        if (modoEdicion) {
            datos.id_gps = id;
            datos.placa  = placas[0];
        } else {
            datos.placas = JSON.stringify(placas);
        }

        $.post(CTRL_GPS, datos, function (r) {
            if (!r.ok) return Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje });
            cerrar('#modalGPS');
            tabla.ajax.reload();
            Swal.fire({ icon: 'success', title: 'Listo', text: r.mensaje, timer: 2200, showConfirmButton: false });
        }, 'json');
    });

    // ── Copiar placa al portapapeles ──────────────────────
    $('#tblGPS').on('click', '.placa-copiable', function () {
        const placa = $(this).data('placa');
        const $el   = $(this);
        navigator.clipboard.writeText(placa).then(() => {
            $el.addClass('placa-copiada');
            setTimeout(() => $el.removeClass('placa-copiada'), 1500);
        }).catch(() => {
            // fallback
            const $tmp = $('<input>').val(placa).appendTo('body').select();
            document.execCommand('copy');
            $tmp.remove();
            $el.addClass('placa-copiada');
            setTimeout(() => $el.removeClass('placa-copiada'), 1500);
        });
    });

    // ── Acceso rápido — abrir modal ───────────────────────
    $('#tblGPS').on('click', '.btn-acceso-rapido', function () {
        const $b = $(this);
        $('#arTitulo').text($b.data('plataforma') || 'Acceso rápido');
        $('#arPlaca').text('Placa: ' + $b.data('placa'));
        $('#arUsuario').val($b.data('usuario'));
        $('#arContrasena').val($b.data('contrasena')).attr('type', 'password');
        $('#arEnlace').attr('href', $b.data('url') || '#')
                      .toggleClass('disabled', !$b.data('url'));
        $('#arToast').hide();
        $('#modalAccesoRapido .btn-ver-pwd i').removeClass('bi-eye-slash').addClass('bi-eye');
        abrir('#modalAccesoRapido');
    });

    // ── Acceso rápido — mostrar/ocultar contraseña ────────
    $('#modalAccesoRapido').on('click', '.btn-ver-pwd', function () {
        const $inp = $('#arContrasena');
        const visible = $inp.attr('type') === 'text';
        $inp.attr('type', visible ? 'password' : 'text');
        $(this).find('i').toggleClass('bi-eye', visible).toggleClass('bi-eye-slash', !visible);
    });

    // ── Acceso rápido — copiar al portapapeles ────────────
    $('#modalAccesoRapido').on('click', '.btn-copiar', function () {
        const campo  = $(this).data('target');
        const valor  = $('#' + campo).val();
        const etiq   = campo === 'arUsuario' ? 'Usuario' : 'Contraseña';
        if (!valor) return;

        navigator.clipboard.writeText(valor).then(() => {
            $('#arToastMsg').text(etiq + ' copiado al portapapeles');
            $('#arToast').stop(true).fadeIn(150).delay(2000).fadeOut(400);
            // Feedback visual en el botón
            const $icn = $(this).find('i');
            $icn.removeClass('bi-clipboard').addClass('bi-clipboard-check');
            setTimeout(() => $icn.removeClass('bi-clipboard-check').addClass('bi-clipboard'), 2000);
        }).catch(() => {
            // Fallback para navegadores sin soporte Clipboard API
            const $inp = $('#' + campo);
            $inp.attr('type', 'text').select();
            document.execCommand('copy');
            if (campo === 'arContrasena') $inp.attr('type', 'password');
            $('#arToastMsg').text(etiq + ' copiado al portapapeles');
            $('#arToast').stop(true).fadeIn(150).delay(2000).fadeOut(400);
        });
    });

    // ── Toggle estado ──────────────────────────────────────
    $('#tblGPS').on('click', '.btn-toggle-gps', function () {
        const id = $(this).data('id');
        Swal.fire({ title: '¿Cambiar estado?', icon: 'question', showCancelButton: true,
            confirmButtonText: 'Sí', cancelButtonText: 'No', confirmButtonColor: '#198754'
        }).then(res => {
            if (!res.isConfirmed) return;
            $.post(CTRL_GPS, { accion: 'toggleEstado', id_gps: id }, r => {
                if (r.ok) tabla.ajax.reload(null, false);
            }, 'json');
        });
    });

    // ── Helpers ────────────────────────────────────────────
    function poblar(sel, data, valKey, txtKey) {
        const $s = $(sel);
        $s.find('option:not(:first)').remove();
        (data || []).forEach(d => $s.append(`<option value="${d[valKey]}">${esc(d[txtKey])}</option>`));
    }

    function limpiarInfoCuenta() {
        $('#gps_info_plataforma, #gps_info_usuario, #gps_info_contrasena').val('');
    }

    function reset(titulo) {
        $('#modalGPSTitulo').text(titulo);
        $('#gps_id').val('');
        $('#gps_id_tipo_vehiculo, #gps_id_destino, #gps_id_transporte').val('');
        $('#gps_id_cuenta').val('').prop('disabled', true).removeClass('is-invalid')
            .find('option:first').text('— Primero selecciona transporte —');
        $('#gps_id_cuenta').find('option:not(:first)').remove();
        $('#gps_placas_lista').empty();
        $('#gps_placas_error').hide();
        $('#btnAgregarPlaca').toggle(!modoEdicion);
        cuentasCache = [];
        limpiarInfoCuenta();
    }

    function abrir(sel)  { const el = document.querySelector(sel); (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show(); }
    function cerrar(sel) { const m = bootstrap.Modal.getInstance(document.querySelector(sel)); if (m) m.hide(); }
    function esc(s)      { return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
});
