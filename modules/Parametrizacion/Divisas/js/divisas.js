const CTRL_DIV = './modules/Parametrizacion/Divisas/controllers/divisasController.php';

$(document).ready(function () {
    if (!document.getElementById('tblDivisas')) return;

    // ── DataTable ─────────────────────────────────────────
    const tabla = $('#tblDivisas').DataTable({
        ajax: {
            url: CTRL_DIV,
            type: 'POST',
            data: { accion: 'listar' },
            dataSrc: r => r.ok ? r.data : []
        },
        columns: [
            { data: null, render: (d, t, r, m) => m.row + 1, orderable: false, width: '40px' },
            { data: 'nombre' },
            { data: 'codigo',  className: 'text-center', render: v => `<code>${v}</code>` },
            { data: 'simbolo', className: 'text-center fw-bold' },
            {
                data: 'tipo_cambio',
                className: 'text-end',
                render: v => `L. ${parseFloat(v).toFixed(4)}`
            },
            {
                data: 'activo',
                className: 'text-center',
                render: v => v == 1
                    ? '<span class="badge bg-success">Activa</span>'
                    : '<span class="badge bg-secondary">Inactiva</span>'
            },
            {
                data: 'predeterminada',
                className: 'text-center',
                render: v => v == 1
                    ? '<span class="badge bg-primary"><i class="bi bi-star-fill me-1"></i>Sí</span>'
                    : '<span class="text-muted">—</span>'
            },
            { data: 'fecha_actualizacion', className: 'text-muted small' },
            {
                data: null,
                className: 'text-center',
                orderable: false,
                render: (d, t, r) => `
                    <button class="btn btn-sm btn-warning btn-editar me-1"
                            data-id="${r.id_divisa}"
                            data-nombre="${r.nombre}"
                            data-simbolo="${r.simbolo}"
                            data-tipo="${r.tipo_cambio}"
                            title="Editar">
                        <i class="bi bi-pencil"></i>
                    </button>
                    ${r.predeterminada != 1 ? `
                    <button class="btn btn-sm btn-primary btn-pred me-1"
                            data-id="${r.id_divisa}"
                            data-nombre="${r.nombre}"
                            title="Establecer como predeterminada">
                        <i class="bi bi-star"></i>
                    </button>
                    <button class="btn btn-sm ${r.activo == 1 ? 'btn-danger' : 'btn-success'} btn-toggle"
                            data-id="${r.id_divisa}"
                            title="${r.activo == 1 ? 'Desactivar' : 'Activar'}">
                        <i class="bi bi-${r.activo == 1 ? 'slash-circle' : 'check-circle'}"></i>
                    </button>` : '<span class="text-muted small">Predeterminada</span>'}`
            }
        ],
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
        order: [[6, 'desc'], [1, 'asc']],
        pageLength: 25,
        paging: false,
        info: false,
        searching: false
    });

    // ── Nueva divisa ──────────────────────────────────────
    $('#btnNuevaDivisa').on('click', function () {
        $('#modalDivisaTitulo').text('Nueva divisa');
        $('#frmDivisa')[0].reset();
        $('#frmDivisa .is-invalid').removeClass('is-invalid');
        $('#d_id').val('');
        $('#bloque_codigo_simbolo').show();
        $('#d_codigo').prop('readonly', false).prop('required', true);
        abrirModal('#modalDivisa');
    });

    // ── Editar ────────────────────────────────────────────
    $('#tblDivisas').on('click', '.btn-editar', function () {
        $('#modalDivisaTitulo').text('Editar divisa');
        $('#frmDivisa')[0].reset();
        $('#frmDivisa .is-invalid').removeClass('is-invalid');
        $('#d_id').val($(this).data('id'));
        $('#d_nombre').val($(this).data('nombre'));
        $('#d_simbolo').val($(this).data('simbolo'));
        $('#d_tipo_cambio').val(parseFloat($(this).data('tipo')).toFixed(4));
        // En edición no se cambia el código
        $('#bloque_codigo_simbolo input#d_codigo').prop('readonly', true).prop('required', false);
        abrirModal('#modalDivisa');
    });

    // ── Guardar ───────────────────────────────────────────
    $('#btnGuardarDivisa').on('click', function () {
        const id         = $('#d_id').val();
        const esNueva    = !id;
        const nombre     = $('#d_nombre').val().trim();
        const codigo     = $('#d_codigo').val().trim();
        const simbolo    = $('#d_simbolo').val().trim();
        const tipoCambio = parseFloat($('#d_tipo_cambio').val());

        let valido = true;
        $('#d_nombre').toggleClass('is-invalid', !nombre);           if (!nombre)            valido = false;
        $('#d_simbolo').toggleClass('is-invalid', !simbolo);         if (!simbolo)           valido = false;
        $('#d_tipo_cambio').toggleClass('is-invalid', !(tipoCambio > 0)); if (!(tipoCambio > 0)) valido = false;
        if (esNueva) {
            $('#d_codigo').toggleClass('is-invalid', !codigo);
            if (!codigo) valido = false;
        }
        if (!valido) return;

        const datos = esNueva
            ? { accion: 'crear',      nombre, codigo, simbolo, tipo_cambio: tipoCambio }
            : { accion: 'actualizar', id_divisa: id, nombre, simbolo, tipo_cambio: tipoCambio };

        $.post(CTRL_DIV, datos, function (resp) {
            if (!resp.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: resp.mensaje, confirmButtonColor: '#4154f1' });
                return;
            }
            cerrarModal('#modalDivisa');
            tabla.ajax.reload();
            Swal.fire({ icon: 'success', title: 'Listo', text: resp.mensaje, timer: 1800, showConfirmButton: false });
        }, 'json');
    });

    // ── Establecer predeterminada ─────────────────────────
    $('#tblDivisas').on('click', '.btn-pred', function () {
        const id     = $(this).data('id');
        const nombre = $(this).data('nombre');
        Swal.fire({
            title: `¿Usar ${nombre} como predeterminada?`,
            text: 'Los precios del sistema se mostrarán con esta divisa.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, establecer',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#4154f1'
        }).then(res => {
            if (!res.isConfirmed) return;
            $.post(CTRL_DIV, { accion: 'setPredeterminada', id_divisa: id }, function (resp) {
                if (resp.ok) {
                    tabla.ajax.reload();
                    Swal.fire({ icon: 'success', title: 'Listo', text: resp.mensaje, timer: 1800, showConfirmButton: false });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: resp.mensaje });
                }
            }, 'json');
        });
    });

    // ── Toggle activo ─────────────────────────────────────
    $('#tblDivisas').on('click', '.btn-toggle', function () {
        const id = $(this).data('id');
        $.post(CTRL_DIV, { accion: 'toggleActivo', id_divisa: id }, function (resp) {
            if (resp.ok) {
                tabla.ajax.reload(null, false);
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: resp.mensaje });
            }
        }, 'json');
    });

    // ── Helpers ───────────────────────────────────────────
    function abrirModal(sel) {
        const el = document.querySelector(sel);
        (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show();
    }
    function cerrarModal(sel) {
        const m = bootstrap.Modal.getInstance(document.querySelector(sel));
        if (m) m.hide();
    }
});
