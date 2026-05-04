const CTRL_GPS  = './modules/GPS/GPS/controllers/gpsController.php';
const CTRL_CU   = './modules/GPS/CuentasGPS/controllers/cuentasGPSController.php';
const CTRL_TV   = './modules/GPS/Parametrizacion/TiposVehiculo/controllers/tiposVehiculoController.php';
const CTRL_DEST = './modules/GPS/Parametrizacion/Destinos/controllers/destinosController.php';
const CTRL_TR   = './modules/GPS/Transportes/controllers/transportesController.php';

$(document).ready(function () {
    if (!document.getElementById('tblGPS')) return;

    let cuentasCache = [];

    // ── Poblar selects fijos del modal ─────────────────────
    $.post(CTRL_TV,   { accion: 'listarActivos' },  r => poblar('#gps_id_tipo_vehiculo', r.data, 'id_tipo_vehiculo', 'nombre'), 'json');
    $.post(CTRL_DEST, { accion: 'listarActivos' },  r => poblar('#gps_id_destino',       r.data, 'id_destino',       'nombre'), 'json');
    $.post(CTRL_TR,   { accion: 'listarActivos' },  r => poblar('#gps_id_transporte',    r.data, 'id_transporte',    'nombre'), 'json');

    // ── Cascada Transporte → Cuentas GPS ───────────────────
    $('#gps_id_transporte').on('change', function () {
        const idTr = $(this).val();
        const $cu  = $('#gps_id_cuenta');
        $cu.prop('disabled', true)
           .find('option:not(:first)').remove();
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
            { data: null, render: (d, t, r, m) => m.row + 1 },
            { data: 'placa', render: v => `<strong>${v}</strong>` },
            { data: 'tipo_vehiculo', defaultContent: '<span class="text-muted">—</span>' },
            { data: 'transporte',   defaultContent: '<span class="text-muted">—</span>' },
            { data: 'plataforma',   defaultContent: '<span class="text-muted">—</span>' },
            { data: 'destino',      defaultContent: '<span class="text-muted">—</span>' },
            { data: 'usuario',      defaultContent: '<span class="text-muted">—</span>' },
            { data: null, render: (d, t, r) => {
                const pwd = esc(r.contrasena || '');
                return `<span class="pwd-masked" data-pwd="${pwd}">••••••••</span>
                        <button class="btn btn-link btn-reveal-pwd p-0 ms-1" data-visible="0" title="Mostrar / ocultar">
                            <i class="bi bi-eye text-secondary"></i></button>`;
            }},
            { data: 'fecha_creacion',      render: v => v ? v.substring(0, 10) : '—' },
            { data: 'creado_por_nombre',   defaultContent: '<span class="text-muted">—</span>' },
            { data: 'fecha_actualizacion', render: v => v ? v.substring(0, 10) : '—' },
            { data: 'actualizado_por_nombre', defaultContent: '<span class="text-muted">—</span>' },
            { data: 'estado', className: 'text-center',
              render: v => v == 1 ? '<span class="badge bg-success">Activo</span>'
                                  : '<span class="badge bg-secondary">Inactivo</span>' },
            { data: null, className: 'text-center', orderable: false,
              render: (d, t, r) => `
                <button class="btn btn-sm btn-warning btn-editar-gps me-1"
                        data-id="${r.id_gps}"
                        data-placa="${esc(r.placa)}"
                        data-id-tipo="${r.id_tipo_vehiculo || ''}"
                        data-id-destino="${r.id_destino || ''}"
                        data-id-transporte="${r.id_transporte || ''}"
                        data-id-cuenta="${r.id_cuenta || ''}"
                        data-plataforma="${esc(r.plataforma || '')}"
                        data-usuario="${esc(r.usuario || '')}"
                        data-contrasena="${esc(r.contrasena || '')}"
                        title="Editar"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm ${r.estado == 1 ? 'btn-danger' : 'btn-success'} btn-toggle-gps"
                        data-id="${r.id_gps}" title="${r.estado == 1 ? 'Desactivar' : 'Activar'}">
                    <i class="bi bi-${r.estado == 1 ? 'toggle-on' : 'toggle-off'}"></i></button>` }
        ],
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
        order: [[1, 'asc']], pageLength: 15, scrollX: true
    });

    // ── Revelar contraseña en tabla ────────────────────────
    $('#tblGPS').on('click', '.btn-reveal-pwd', function () {
        const $btn = $(this), $m = $btn.prev('.pwd-masked'), vis = $btn.data('visible') == 1;
        $m.text(vis ? '••••••••' : ($m.data('pwd') || '(vacía)'));
        $btn.data('visible', vis ? 0 : 1).find('i').toggleClass('bi-eye', vis).toggleClass('bi-eye-slash', !vis);
    });

    // ── Nuevo ──────────────────────────────────────────────
    $('#btnNuevoGPS').on('click', () => { reset('Nuevo vehículo GPS'); abrir('#modalGPS'); });

    // ── Editar ─────────────────────────────────────────────
    $('#tblGPS').on('click', '.btn-editar-gps', function () {
        const $b = $(this);
        reset('Editar vehículo GPS');
        $('#gps_id').val($b.data('id'));
        $('#gps_placa').val($b.data('placa'));
        $('#gps_id_tipo_vehiculo').val($b.data('id-tipo'));
        $('#gps_id_destino').val($b.data('id-destino'));

        const idTr    = $b.data('id-transporte');
        const idCuenta = $b.data('id-cuenta');

        if (idTr) {
            $('#gps_id_transporte').val(idTr);
            // Load cuentas then select the right one
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
            // Fallback: show stored info even without cascading
            $('#gps_info_plataforma').val($b.data('plataforma'));
            $('#gps_info_usuario').val($b.data('usuario'));
            $('#gps_info_contrasena').val($b.data('contrasena'));
        }

        abrir('#modalGPS');
    });

    // ── Guardar ────────────────────────────────────────────
    $('#btnGuardarGPS').on('click', function () {
        const id       = $('#gps_id').val();
        const placa    = $('#gps_placa').val().trim().toUpperCase();
        const idCuenta = $('#gps_id_cuenta').val();
        let ok = true;

        $('#gps_placa').toggleClass('is-invalid', !placa);       if (!placa)    ok = false;
        $('#gps_id_cuenta').toggleClass('is-invalid', !idCuenta); if (!idCuenta) ok = false;
        if (!ok) return;

        $.post(CTRL_GPS, {
            accion:           id ? 'editar' : 'crear',
            id_gps:           id,
            placa,
            id_cuenta:        idCuenta,
            id_tipo_vehiculo: $('#gps_id_tipo_vehiculo').val(),
            id_destino:       $('#gps_id_destino').val()
        }, function (r) {
            if (!r.ok) return Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje });
            cerrar('#modalGPS'); tabla.ajax.reload();
            Swal.fire({ icon: 'success', title: 'Listo', text: r.mensaje, timer: 1800, showConfirmButton: false });
        }, 'json');
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
        $('#gps_placa').val('').removeClass('is-invalid');
        $('#gps_id_tipo_vehiculo, #gps_id_destino, #gps_id_transporte').val('');
        $('#gps_id_cuenta').val('').prop('disabled', true)
            .find('option:first').text('— Primero selecciona transporte —');
        $('#gps_id_cuenta').find('option:not(:first)').remove();
        $('#gps_id_cuenta').removeClass('is-invalid');
        cuentasCache = [];
        limpiarInfoCuenta();
    }

    function abrir(sel) { const el = document.querySelector(sel); (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show(); }
    function cerrar(sel) { const m = bootstrap.Modal.getInstance(document.querySelector(sel)); if (m) m.hide(); }
    function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
});
