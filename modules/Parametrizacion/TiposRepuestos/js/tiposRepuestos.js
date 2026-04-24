$(document).ready(function () {

    // ── DataTable ──────────────────────────────────────────────────────────────
    const tabla = $('#tablaTipos').DataTable({
        ajax: {
            url: './modules/Parametrizacion/TiposRepuestos/controllers/tiposRepuestosController.php',
            type: 'POST',
            data: { accion: 'listar' },
            dataSrc: function (json) {
                return json.ok ? json.data : [];
            }
        },
        columns: [
            { data: null, render: (_, __, ___, meta) => meta.row + 1, orderable: false, width: '40px' },
            { data: 'nombre' },
            {
                data: 'total_repuestos',
                className: 'text-center',
                render: n => `<span class="badge bg-${n > 0 ? 'primary' : 'secondary'}">${n}</span>`
            },
            {
                data: null,
                className: 'text-center',
                orderable: false,
                render: row => `
                    <button class="btn btn-sm btn-warning btn-editar me-1"
                            data-id="${row.id_tipo_repuesto}"
                            data-nombre="${row.nombre}"
                            title="Editar">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-danger btn-eliminar"
                            data-id="${row.id_tipo_repuesto}"
                            data-nombre="${row.nombre}"
                            data-repuestos="${row.total_repuestos}"
                            title="Eliminar">
                        <i class="bi bi-trash"></i>
                    </button>`
            }
        ],
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
        order: [[1, 'asc']],
        pageLength: 10
    });

    // ── Helpers ────────────────────────────────────────────────────────────────
    function cerrarModal() {
        const el = document.getElementById('modalTipo');
        (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).hide();
    }

    function limpiarModal() {
        $('#formTipo')[0].reset();
        $('#id_tipo_repuesto').val('');
        $('#btnGuardarTipo').removeClass('d-none');
        $('#btnActualizarTipo').addClass('d-none');
        $('#tituloModalTipo').text('Nuevo Tipo de Repuesto');
        limpiarErrores();
    }

    function marcarInvalido(selector, msg) {
        const el = $(selector);
        el.addClass('is-invalid');
        if (el.next('.invalid-feedback').length === 0) {
            el.after(`<div class="invalid-feedback">${msg}</div>`);
        } else {
            el.next('.invalid-feedback').text(msg);
        }
    }

    function limpiarErrores() {
        $('#formTipo .is-invalid').removeClass('is-invalid');
        $('#formTipo .invalid-feedback').remove();
    }

    function validarForm() {
        limpiarErrores();
        const nombre = $('#nombre_tipo').val().trim();
        if (!nombre) {
            marcarInvalido('#nombre_tipo', 'El nombre es requerido');
            return false;
        }
        return true;
    }

    // ── Abrir modal nuevo ──────────────────────────────────────────────────────
    $('#btnNuevoTipo').on('click', function () {
        limpiarModal();
        new bootstrap.Modal(document.getElementById('modalTipo')).show();
    });

    // ── Limpiar al cerrar ──────────────────────────────────────────────────────
    document.getElementById('modalTipo').addEventListener('hidden.bs.modal', limpiarModal);

    // ── Guardar ────────────────────────────────────────────────────────────────
    $('#btnGuardarTipo').on('click', guardar);
    $('#formTipo').on('keydown', function (e) {
        if (e.key === 'Enter' && !$('#btnGuardarTipo').hasClass('d-none')) guardar();
    });

    function guardar() {
        if (!validarForm()) return;

        $.post('./modules/Parametrizacion/TiposRepuestos/controllers/tiposRepuestosController.php', {
            accion:  'guardar',
            nombre:  $('#nombre_tipo').val().trim()
        }, function (res) {
            if (!res.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: res.mensaje });
                return;
            }
            cerrarModal();
            tabla.ajax.reload(null, false);
            Swal.fire({ icon: 'success', title: res.mensaje, timer: 1800, showConfirmButton: false });
        }, 'json').fail(() => Swal.fire({ icon: 'error', title: 'Error de conexión' }));
    }

    // ── Editar — abrir ─────────────────────────────────────────────────────────
    $('#tablaTipos').on('click', '.btn-editar', function () {
        const d = $(this).data();
        limpiarModal();
        $('#tituloModalTipo').text('Editar Tipo de Repuesto');
        $('#id_tipo_repuesto').val(d.id);
        $('#nombre_tipo').val(d.nombre);
        $('#btnGuardarTipo').addClass('d-none');
        $('#btnActualizarTipo').removeClass('d-none');
        new bootstrap.Modal(document.getElementById('modalTipo')).show();
    });

    // ── Actualizar ─────────────────────────────────────────────────────────────
    $('#btnActualizarTipo').on('click', actualizar);
    $('#formTipo').on('keydown', function (e) {
        if (e.key === 'Enter' && !$('#btnActualizarTipo').hasClass('d-none')) actualizar();
    });

    function actualizar() {
        if (!validarForm()) return;

        $.post('./modules/Parametrizacion/TiposRepuestos/controllers/tiposRepuestosController.php', {
            accion:           'editar',
            id_tipo_repuesto: $('#id_tipo_repuesto').val(),
            nombre:           $('#nombre_tipo').val().trim()
        }, function (res) {
            if (!res.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: res.mensaje });
                return;
            }
            cerrarModal();
            tabla.ajax.reload(null, false);
            Swal.fire({ icon: 'success', title: res.mensaje, timer: 1800, showConfirmButton: false });
        }, 'json').fail(() => Swal.fire({ icon: 'error', title: 'Error de conexión' }));
    }

    // ── Eliminar ───────────────────────────────────────────────────────────────
    $('#tablaTipos').on('click', '.btn-eliminar', function () {
        const d = $(this).data();

        if (parseInt(d.repuestos) > 0) {
            Swal.fire({
                icon: 'warning',
                title: 'No se puede eliminar',
                text: `"${d.nombre}" tiene ${d.repuestos} repuesto(s) activo(s) asociado(s).`
            });
            return;
        }

        Swal.fire({
            title: '¿Eliminar tipo?',
            text: `Se eliminará "${d.nombre}".`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545'
        }).then(result => {
            if (!result.isConfirmed) return;

            $.post('./modules/Parametrizacion/TiposRepuestos/controllers/tiposRepuestosController.php', {
                accion:           'eliminar',
                id_tipo_repuesto: d.id
            }, function (res) {
                if (!res.ok) {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.mensaje });
                    return;
                }
                tabla.ajax.reload(null, false);
                Swal.fire({ icon: 'success', title: res.mensaje, timer: 1800, showConfirmButton: false });
            }, 'json').fail(() => Swal.fire({ icon: 'error', title: 'Error de conexión' }));
        });
    });

});
