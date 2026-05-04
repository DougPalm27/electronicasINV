const CTRL_TRANSPORTES = './modules/GPS/Transportes/controllers/transportesController.php';

$(document).ready(function () {
    if (!document.getElementById('tblTransportes')) return;

    // ── DataTable ──────────────────────────────────────────
    const tabla = $('#tblTransportes').DataTable({
        ajax: {
            url:  CTRL_TRANSPORTES,
            type: 'POST',
            data: { accion: 'listar' },
            dataSrc: r => r.ok ? r.data : []
        },
        columns: [
            { data: null, render: (d, t, r, m) => m.row + 1 },
            { data: 'nombre', render: v => `<strong>${v}</strong>` },
            { data: 'contacto', defaultContent: '<span class="text-muted">—</span>' },
            { data: 'telefono', defaultContent: '<span class="text-muted">—</span>' },
            { data: 'fecha_creacion',      render: v => v ? v.substring(0, 10) : '—' },
            { data: 'creado_por_nombre',   defaultContent: '<span class="text-muted">—</span>' },
            { data: 'fecha_actualizacion', render: v => v ? v.substring(0, 10) : '—' },
            { data: 'actualizado_por_nombre', defaultContent: '<span class="text-muted">—</span>' },
            {
                data: 'activo',
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
                    <button class="btn btn-sm btn-warning btn-editar-tr me-1"
                            data-id="${r.id_transporte}"
                            data-nombre="${escHtml(r.nombre)}"
                            data-contacto="${escHtml(r.contacto || '')}"
                            data-telefono="${escHtml(r.telefono || '')}"
                            title="Editar">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm ${r.activo == 1 ? 'btn-danger' : 'btn-success'} btn-toggle-tr"
                            data-id="${r.id_transporte}"
                            title="${r.activo == 1 ? 'Desactivar' : 'Activar'}">
                        <i class="bi bi-${r.activo == 1 ? 'toggle-on' : 'toggle-off'}"></i>
                    </button>`
            }
        ],
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
        order: [[1, 'asc']],
        pageLength: 15,
        scrollX: true
    });

    // ── Nuevo ──────────────────────────────────────────────
    $('#btnNuevoTransporte').on('click', function () {
        resetModalTr('Nueva empresa de transporte');
        abrirModal('#modalTransporte');
    });

    // ── Editar ─────────────────────────────────────────────
    $('#tblTransportes').on('click', '.btn-editar-tr', function () {
        resetModalTr('Editar empresa de transporte');
        $('#tr_id').val($(this).data('id'));
        $('#tr_nombre').val($(this).data('nombre'));
        $('#tr_contacto').val($(this).data('contacto'));
        $('#tr_telefono').val($(this).data('telefono'));
        abrirModal('#modalTransporte');
    });

    // ── Guardar ────────────────────────────────────────────
    $('#btnGuardarTransporte').on('click', function () {
        const id     = $('#tr_id').val();
        const nombre = $('#tr_nombre').val().trim();

        if (!nombre) {
            $('#tr_nombre').addClass('is-invalid');
            return;
        }
        $('#tr_nombre').removeClass('is-invalid');

        $.post(CTRL_TRANSPORTES, {
            accion:        id ? 'editar' : 'crear',
            id_transporte: id,
            nombre,
            contacto: $('#tr_contacto').val().trim(),
            telefono: $('#tr_telefono').val().trim()
        }, function (r) {
            if (!r.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje, confirmButtonColor: '#198754' });
                return;
            }
            cerrarModal('#modalTransporte');
            tabla.ajax.reload();
            Swal.fire({ icon: 'success', title: 'Listo', text: r.mensaje, timer: 1800, showConfirmButton: false });
        }, 'json');
    });

    // ── Toggle activo ──────────────────────────────────────
    $('#tblTransportes').on('click', '.btn-toggle-tr', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: '¿Cambiar estado?',
            icon: 'question', showCancelButton: true,
            confirmButtonText: 'Sí', cancelButtonText: 'No',
            confirmButtonColor: '#198754'
        }).then(res => {
            if (!res.isConfirmed) return;
            $.post(CTRL_TRANSPORTES, { accion: 'toggleActivo', id_transporte: id }, function (r) {
                if (r.ok) tabla.ajax.reload(null, false);
            }, 'json');
        });
    });

    // ── Helpers ────────────────────────────────────────────
    function resetModalTr(titulo) {
        $('#modalTransporteTitulo').text(titulo);
        $('#tr_id').val('');
        $('#tr_nombre').val('').removeClass('is-invalid');
        $('#tr_contacto').val('');
        $('#tr_telefono').val('');
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
