$(document).ready(function () {

    // ── DataTable ──────────────────────────────────────────────────────────────
    const tabla = $('#tablaProveedores').DataTable({
        ajax: {
            url: './modules/Parametrizacion/Proveedores/controllers/proveedoresController.php',
            type: 'POST',
            data: { accion: 'listar' },
            dataSrc: function (json) {
                return json.ok ? json.data : [];
            }
        },
        columns: [
            { data: null, render: (_, __, ___, meta) => meta.row + 1, orderable: false, width: '40px' },
            { data: 'nombre' },
            { data: 'telefono', defaultContent: '<span class="text-muted">—</span>' },
            { data: 'correo',   defaultContent: '<span class="text-muted">—</span>' },
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
                            data-id="${row.id_proveedor}"
                            data-nombre="${row.nombre}"
                            data-telefono="${row.telefono ?? ''}"
                            data-correo="${row.correo ?? ''}"
                            data-direccion="${row.direccion ?? ''}"
                            title="Editar">
                        <i class="bi bi-pencil-square"></i>
                    </button>
                    <button class="btn btn-sm btn-danger btn-eliminar"
                            data-id="${row.id_proveedor}"
                            data-nombre="${row.nombre}"
                            data-repuestos="${row.total_repuestos}"
                            title="Eliminar">
                        <i class="bi bi-trash3"></i>
                    </button>`
            }
        ],
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
        order: [[1, 'asc']],
        pageLength: 10
    });

    // ── Helpers ────────────────────────────────────────────────────────────────
    function cerrarModal() {
        const el = document.getElementById('modalProveedor');
        (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).hide();
    }

    function limpiarModal() {
        $('#formProveedor')[0].reset();
        $('#id_proveedor').val('');
        $('#btnGuardarProveedor').removeClass('d-none');
        $('#btnActualizarProveedor').addClass('d-none');
        $('#tituloModalProveedor').text('Nuevo Proveedor');
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
        $('#formProveedor .is-invalid').removeClass('is-invalid');
        $('#formProveedor .invalid-feedback').remove();
    }

    function validarForm() {
        limpiarErrores();
        let ok = true;

        const nombre = $('#nombre_proveedor').val().trim();
        if (!nombre) {
            marcarInvalido('#nombre_proveedor', 'El nombre es requerido');
            ok = false;
        }

        const correo = $('#correo_proveedor').val().trim();
        if (correo) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!re.test(correo)) {
                marcarInvalido('#correo_proveedor', 'Formato de correo inválido');
                ok = false;
            }
        }

        return ok;
    }

    // ── Abrir modal nuevo ──────────────────────────────────────────────────────
    $('#btnNuevoProveedor').on('click', function () {
        limpiarModal();
        new bootstrap.Modal(document.getElementById('modalProveedor')).show();
    });

    // ── Limpiar al cerrar ──────────────────────────────────────────────────────
    document.getElementById('modalProveedor').addEventListener('hidden.bs.modal', limpiarModal);

    // ── Guardar ────────────────────────────────────────────────────────────────
    $('#btnGuardarProveedor').on('click', guardar);
    $('#formProveedor').on('keydown', function (e) {
        if (e.key === 'Enter' && !$('#btnGuardarProveedor').hasClass('d-none')) guardar();
    });

    function guardar() {
        if (!validarForm()) return;

        $.post('./modules/Parametrizacion/Proveedores/controllers/proveedoresController.php', {
            accion:    'guardar',
            nombre:    $('#nombre_proveedor').val().trim(),
            telefono:  $('#telefono_proveedor').val().trim(),
            correo:    $('#correo_proveedor').val().trim(),
            direccion: $('#direccion_proveedor').val().trim()
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
    $('#tablaProveedores').on('click', '.btn-editar', function () {
        const d = $(this).data();
        limpiarModal();
        $('#tituloModalProveedor').text('Editar Proveedor');
        $('#id_proveedor').val(d.id);
        $('#nombre_proveedor').val(d.nombre);
        $('#telefono_proveedor').val(d.telefono);
        $('#correo_proveedor').val(d.correo);
        $('#direccion_proveedor').val(d.direccion);
        $('#btnGuardarProveedor').addClass('d-none');
        $('#btnActualizarProveedor').removeClass('d-none');
        new bootstrap.Modal(document.getElementById('modalProveedor')).show();
    });

    // ── Actualizar ─────────────────────────────────────────────────────────────
    $('#btnActualizarProveedor').on('click', actualizar);
    $('#formProveedor').on('keydown', function (e) {
        if (e.key === 'Enter' && !$('#btnActualizarProveedor').hasClass('d-none')) actualizar();
    });

    function actualizar() {
        if (!validarForm()) return;

        $.post('./modules/Parametrizacion/Proveedores/controllers/proveedoresController.php', {
            accion:       'editar',
            id_proveedor: $('#id_proveedor').val(),
            nombre:       $('#nombre_proveedor').val().trim(),
            telefono:     $('#telefono_proveedor').val().trim(),
            correo:       $('#correo_proveedor').val().trim(),
            direccion:    $('#direccion_proveedor').val().trim()
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
    $('#tablaProveedores').on('click', '.btn-eliminar', function () {
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
            title: '¿Eliminar proveedor?',
            text: `Se eliminará "${d.nombre}".`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545'
        }).then(result => {
            if (!result.isConfirmed) return;

            $.post('./modules/Parametrizacion/Proveedores/controllers/proveedoresController.php', {
                accion: 'eliminar',
                id_proveedor: d.id
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
