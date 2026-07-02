const CTRL_CUSTODIOS = './modules/GPS/Custodios/controllers/custodiosController.php';

$(document).ready(function () {
    if (!document.getElementById('tblCustodios')) return;

    let editingId = null; // id_custodio de la fila en edición inline (o null)

    function esFilaEditada(r) {
        return editingId !== null && String(editingId) === String(r.id_custodio);
    }

    // ── DataTable ──────────────────────────────────────────
    const tabla = $('#tblCustodios').DataTable({
        ajax: {
            url:  CTRL_CUSTODIOS,
            type: 'POST',
            data: { accion: 'listar' },
            dataSrc: r => r.ok ? r.data : []
        },
        columns: [
            {
                data: 'nombre',
                render: function (v, type, r) {
                    if (type !== 'display') return v;
                    if (esFilaEditada(r)) {
                        return `<input type="text" class="form-control form-control-sm inp-edit-nombre"
                                       value="${escC(v)}" maxlength="150">`;
                    }
                    return `<strong>${escC(v)}</strong>`;
                }
            },
            {
                data: 'telefono',
                render: function (v, type, r) {
                    if (type !== 'display') return v;
                    if (esFilaEditada(r)) {
                        return `<input type="text" class="form-control form-control-sm inp-edit-telefono"
                                       value="${escC(v || '')}" maxlength="30">`;
                    }
                    if (!v) return '<span class="text-muted">—</span>';
                    const limpio = String(v).replace(/[^0-9+]/g, '');
                    return `
                        <span class="me-2">${escC(v)}</span>
                        <a href="tel:${escC(limpio)}" class="btn btn-sm btn-outline-secondary py-0 px-1"
                           title="Llamar"><i class="bi bi-telephone"></i></a>
                        <a href="https://wa.me/${escC(limpio.replace(/\D/g, ''))}" target="_blank"
                           rel="noopener noreferrer" class="btn btn-sm btn-outline-success py-0 px-1"
                           title="WhatsApp"><i class="bi bi-whatsapp"></i></a>`;
                }
            },
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
                render: function (d, t, r) {
                    if (esFilaEditada(r)) {
                        return `
                            <button class="btn btn-sm btn-success btn-guardar-inline" type="button"
                                    data-id="${r.id_custodio}" title="Guardar">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            <button class="btn btn-sm btn-secondary btn-cancelar-inline" type="button" title="Cancelar">
                                <i class="bi bi-x-lg"></i>
                            </button>`;
                    }
                    return `
                        <div class="dropdown">
                            <button class="btn btn-sm btn-primary dropdown-toggle py-1"
                                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li>
                                    <button class="dropdown-item btn-editar-cus" type="button" data-id="${r.id_custodio}">
                                        <i class="bi bi-pencil me-2 text-warning"></i>Editar
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item ${r.activo == 1 ? 'text-danger' : 'text-success'} btn-toggle-cus"
                                            type="button" data-id="${r.id_custodio}">
                                        <i class="bi bi-${r.activo == 1 ? 'toggle-on' : 'toggle-off'} me-2"></i>
                                        ${r.activo == 1 ? 'Desactivar' : 'Activar'}
                                    </button>
                                </li>
                            </ul>
                        </div>`;
                }
            }
        ],
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
        order: [[0, 'asc']],
        pageLength: 10,
        autoWidth: false
    });

    // ── Abrir directorio (modal único, sin relevo) ──────────
    $('#btnAbrirCustodios').on('click', function () {
        abrirModalCus('#modalCustodiosDir');
    });

    // DataTables mide mal el ancho si nace oculto: recalcular al mostrarse
    $('#modalCustodiosDir').on('shown.bs.modal', function () {
        tabla.columns.adjust();
    });

    // Al cerrar el directorio, resetear cualquier estado pendiente
    $('#modalCustodiosDir').on('hidden.bs.modal', function () {
        $('#panelNuevoCustodio').hide();
        if (editingId !== null) {
            editingId = null;
            tabla.rows().invalidate('data').draw(false);
        }
    });

    // ── Nuevo custodio (panel inline, dentro del mismo modal) ──
    $('#btnNuevoCustodio').on('click', function () {
        $('#cus_new_nombre').val('').removeClass('is-invalid');
        $('#cus_new_telefono').val('');
        $('#panelNuevoCustodio').show();
        $('#cus_new_nombre').trigger('focus');
    });

    $('#btnCancelarNuevoCustodio').on('click', function () {
        $('#panelNuevoCustodio').hide();
    });

    $('#btnGuardarNuevoCustodio').on('click', function () {
        const nombre = $('#cus_new_nombre').val().trim();

        if (!nombre) {
            $('#cus_new_nombre').addClass('is-invalid');
            return;
        }
        $('#cus_new_nombre').removeClass('is-invalid');

        $.post(CTRL_CUSTODIOS, {
            accion: 'crear',
            nombre,
            telefono: $('#cus_new_telefono').val().trim()
        }, function (r) {
            if (!r.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje });
                return;
            }
            $('#panelNuevoCustodio').hide();
            tabla.ajax.reload(null, false);
            Swal.fire({ icon: 'success', title: 'Listo', text: r.mensaje, timer: 1500, showConfirmButton: false });
        }, 'json');
    });

    $('#cus_new_nombre, #cus_new_telefono').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $('#btnGuardarNuevoCustodio').trigger('click');
        } else if (e.key === 'Escape') {
            $('#btnCancelarNuevoCustodio').trigger('click');
        }
    });

    // ── Edición inline en la misma tabla ────────────────────
    function activarEdicion(id) {
        editingId = id;
        tabla.rows().invalidate('data').draw(false);
        if (id !== null) {
            setTimeout(() => $('#tblCustodios .inp-edit-nombre').trigger('focus'), 0);
        }
    }

    $('#tblCustodios').on('click', '.btn-editar-cus', function () {
        activarEdicion($(this).data('id'));
    });

    $('#tblCustodios').on('click', '.btn-cancelar-inline', function () {
        activarEdicion(null);
    });

    $('#tblCustodios').on('click', '.btn-guardar-inline', function () {
        const $tr      = $(this).closest('tr');
        const id       = $(this).data('id');
        const nombre   = $tr.find('.inp-edit-nombre').val().trim();
        const telefono = $tr.find('.inp-edit-telefono').val().trim();

        if (!nombre) {
            $tr.find('.inp-edit-nombre').addClass('is-invalid');
            return;
        }

        $.post(CTRL_CUSTODIOS, {
            accion:      'editar',
            id_custodio: id,
            nombre,
            telefono
        }, function (r) {
            if (!r.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje });
                return;
            }
            editingId = null;
            tabla.ajax.reload(null, false);
            Swal.fire({ icon: 'success', title: 'Listo', text: r.mensaje, timer: 1500, showConfirmButton: false });
        }, 'json');
    });

    // Enter = guardar, Escape = cancelar
    $('#tblCustodios').on('keydown', '.inp-edit-nombre, .inp-edit-telefono', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $(this).closest('tr').find('.btn-guardar-inline').trigger('click');
        } else if (e.key === 'Escape') {
            activarEdicion(null);
        }
    });

    // ── Toggle activo ──────────────────────────────────────
    $('#tblCustodios').on('click', '.btn-toggle-cus', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: '¿Cambiar estado?',
            icon: 'question', showCancelButton: true,
            confirmButtonText: 'Sí', cancelButtonText: 'No',
            confirmButtonColor: '#198754'
        }).then(res => {
            if (!res.isConfirmed) return;
            $.post(CTRL_CUSTODIOS, { accion: 'toggleActivo', id_custodio: id }, function (r) {
                if (r.ok) tabla.ajax.reload(null, false);
            }, 'json');
        });
    });

    // ── Helpers ────────────────────────────────────────────
    function abrirModalCus(sel) {
        const el = document.querySelector(sel);
        (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show();
    }
    function escC(str) {
        return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;')
                          .replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
});
