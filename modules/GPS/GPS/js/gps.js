const CTRL_GPS         = './modules/GPS/GPS/controllers/gpsController.php';
const CTRL_TRANSPORTES = './modules/GPS/Transportes/controllers/transportesController.php';

$(document).ready(function () {
    if (!document.getElementById('tblGPS')) return;

    let transportesCache = [];

    // ── Cargar transportes para el select del modal ────────
    $.post(CTRL_TRANSPORTES, { accion: 'listarActivos' }, function (r) {
        if (!r.ok) return;
        transportesCache = r.data;
        const $sel = $('#gps_id_transporte');
        $sel.find('option:not(:first)').remove();
        r.data.forEach(t => {
            $sel.append(`<option value="${t.id_transporte}">${escHtml(t.nombre)}</option>`);
        });
    }, 'json');

    // ── DataTable ──────────────────────────────────────────
    const tabla = $('#tblGPS').DataTable({
        ajax: {
            url:  CTRL_GPS,
            type: 'POST',
            data: { accion: 'listar' },
            dataSrc: r => r.ok ? r.data : []
        },
        columns: [
            { data: null, render: (d, t, r, m) => m.row + 1 },
            { data: 'placa', render: v => `<strong>${v}</strong>` },
            { data: 'tipo_vehiculo', defaultContent: '<span class="text-muted">—</span>' },
            { data: 'transporte',    defaultContent: '<span class="text-muted">—</span>' },
            {
                data: 'plataforma',
                render: v => v
                    ? `<a href="${escHtml(v)}" target="_blank" rel="noopener"
                          class="text-truncate d-inline-block" style="max-width:160px"
                          title="${escHtml(v)}">
                           <i class="bi bi-link-45deg me-1"></i>${escHtml(v)}
                       </a>`
                    : '<span class="text-muted">—</span>'
            },
            { data: 'destino', defaultContent: '<span class="text-muted">—</span>' },
            { data: 'usuario', defaultContent: '<span class="text-muted">—</span>' },
            {
                data: null,
                render: (d, t, r) => {
                    const pwd = escHtml(r.contrasena || '');
                    return `<span class="pwd-masked" data-pwd="${pwd}">••••••••</span>
                            <button class="btn btn-link btn-reveal-pwd p-0 ms-1"
                                    data-visible="0" title="Mostrar / ocultar contraseña">
                                <i class="bi bi-eye text-secondary"></i>
                            </button>`;
                }
            },
            {
                data: 'estado',
                className: 'text-center',
                render: v => v == 1
                    ? '<span class="badge bg-success">Activo</span>'
                    : '<span class="badge bg-secondary">Inactivo</span>'
            },
            {
                data: null,
                className: 'text-center',
                orderable: false,
                render: (d, t, r) => `
                    <button class="btn btn-sm btn-warning btn-editar-gps me-1"
                            data-id="${r.id_gps}"
                            data-placa="${escHtml(r.placa)}"
                            data-tipo="${escHtml(r.tipo_vehiculo || '')}"
                            data-transporte="${r.id_transporte || ''}"
                            data-plataforma="${escHtml(r.plataforma || '')}"
                            data-destino="${escHtml(r.destino || '')}"
                            data-usuario="${escHtml(r.usuario || '')}"
                            data-contrasena="${escHtml(r.contrasena || '')}"
                            title="Editar">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm ${r.estado == 1 ? 'btn-danger' : 'btn-success'} btn-toggle-gps"
                            data-id="${r.id_gps}"
                            title="${r.estado == 1 ? 'Desactivar' : 'Activar'}">
                        <i class="bi bi-${r.estado == 1 ? 'toggle-on' : 'toggle-off'}"></i>
                    </button>`
            }
        ],
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
        order: [[1, 'asc']],
        pageLength: 15,
        scrollX: true
    });

    // ── Revelar contraseña en tabla ────────────────────────
    $('#tblGPS').on('click', '.btn-reveal-pwd', function () {
        const $btn     = $(this);
        const $masked  = $btn.prev('.pwd-masked');
        const visible  = $btn.data('visible') == 1;
        const pwd      = $masked.data('pwd');

        if (visible) {
            $masked.text('••••••••');
            $btn.data('visible', 0).find('i').removeClass('bi-eye-slash').addClass('bi-eye');
        } else {
            $masked.text(pwd || '(vacía)');
            $btn.data('visible', 1).find('i').removeClass('bi-eye').addClass('bi-eye-slash');
        }
    });

    // ── Nuevo ──────────────────────────────────────────────
    $('#btnNuevoGPS').on('click', function () {
        resetModalGPS('Nuevo registro GPS');
        abrirModal('#modalGPS');
    });

    // ── Editar ─────────────────────────────────────────────
    $('#tblGPS').on('click', '.btn-editar-gps', function () {
        const $btn = $(this);
        resetModalGPS('Editar registro GPS');
        $('#gps_id').val($btn.data('id'));
        $('#gps_placa').val($btn.data('placa'));
        $('#gps_tipo_vehiculo').val($btn.data('tipo'));
        $('#gps_id_transporte').val($btn.data('transporte') || '');
        $('#gps_plataforma').val($btn.data('plataforma'));
        $('#gps_destino').val($btn.data('destino'));
        $('#gps_usuario').val($btn.data('usuario'));
        $('#gps_contrasena').val($btn.data('contrasena'));
        abrirModal('#modalGPS');
    });

    // ── Guardar ────────────────────────────────────────────
    $('#btnGuardarGPS').on('click', function () {
        const id    = $('#gps_id').val();
        const placa = $('#gps_placa').val().trim().toUpperCase();

        if (!placa) {
            $('#gps_placa').addClass('is-invalid');
            return;
        }
        $('#gps_placa').removeClass('is-invalid');

        $.post(CTRL_GPS, {
            accion:        id ? 'editar' : 'crear',
            id_gps:        id,
            placa,
            tipo_vehiculo: $('#gps_tipo_vehiculo').val().trim(),
            id_transporte: $('#gps_id_transporte').val(),
            plataforma:    $('#gps_plataforma').val().trim(),
            destino:       $('#gps_destino').val().trim(),
            usuario:       $('#gps_usuario').val().trim(),
            contrasena:    $('#gps_contrasena').val()
        }, function (r) {
            if (!r.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje, confirmButtonColor: '#198754' });
                return;
            }
            cerrarModal('#modalGPS');
            tabla.ajax.reload();
            Swal.fire({ icon: 'success', title: 'Listo', text: r.mensaje, timer: 1800, showConfirmButton: false });
        }, 'json');
    });

    // ── Toggle estado ──────────────────────────────────────
    $('#tblGPS').on('click', '.btn-toggle-gps', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: '¿Cambiar estado?',
            icon: 'question', showCancelButton: true,
            confirmButtonText: 'Sí', cancelButtonText: 'No',
            confirmButtonColor: '#198754'
        }).then(res => {
            if (!res.isConfirmed) return;
            $.post(CTRL_GPS, { accion: 'toggleEstado', id_gps: id }, function (r) {
                if (r.ok) tabla.ajax.reload(null, false);
            }, 'json');
        });
    });

    // ── Mostrar / ocultar contraseña en modal ──────────────
    $(document).on('click', '.btn-toggle-pwd-modal', function () {
        const $input = $('#gps_contrasena');
        const $icon  = $(this).find('i');
        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $icon.removeClass('bi-eye').addClass('bi-eye-slash');
        } else {
            $input.attr('type', 'password');
            $icon.removeClass('bi-eye-slash').addClass('bi-eye');
        }
    });

    // ── Helpers ────────────────────────────────────────────
    function resetModalGPS(titulo) {
        $('#modalGPSTitulo').text(titulo);
        $('#gps_id').val('');
        $('#gps_placa').val('').removeClass('is-invalid');
        $('#gps_tipo_vehiculo').val('');
        $('#gps_id_transporte').val('');
        $('#gps_plataforma').val('');
        $('#gps_destino').val('');
        $('#gps_usuario').val('');
        $('#gps_contrasena').val('').attr('type', 'password');
        $('#modalGPS .btn-toggle-pwd-modal i').removeClass('bi-eye-slash').addClass('bi-eye');
    }
    function abrirModal(sel) {
        const el = document.querySelector(sel);
        (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show();
    }
    function cerrarModal(sel) {
        const m = bootstrap.Modal.getInstance(document.querySelector(sel));
        if (m) m.hide();
    }
    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;')
                          .replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
});
