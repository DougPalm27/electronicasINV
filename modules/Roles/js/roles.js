const CTRL_ROLES = './modules/Roles/controllers/rolesController.php';

$(document).ready(function () {
    if (!document.getElementById('tblRoles')) return;

    // ── DataTable ──────────────────────────────────────────
    const tabla = $('#tblRoles').DataTable({
        ajax: {
            url:  CTRL_ROLES,
            type: 'POST',
            data: { accion: 'listar' },
            dataSrc: r => r.ok ? r.data : []
        },
        columns: [
            { data: 'nombre', render: v => `<strong>${v}</strong>` },
            { data: 'descripcion', defaultContent: '<span class="text-muted">—</span>' },
            {
                data: 'total_modulos',
                className: 'text-center',
                render: v => `<span class="badge bg-info text-dark">${v} módulo${v != 1 ? 's' : ''}</span>`
            },
            {
                data: 'puede_ejecutar_mantenimiento',
                className: 'text-center',
                render: (v, t, r) => {
                    const on = v == 1;
                    return `<button class="btn btn-sm btn-toggle-ejec-mant
                                    ${on ? 'btn-outline-success' : 'btn-outline-secondary'}"
                                    data-id="${r.id_rol}" title="${on ? 'Quitar permiso' : 'Dar permiso'}">
                                <i class="bi bi-${on ? 'toggle-on text-success' : 'toggle-off text-secondary'}"></i>
                                <span class="ms-1 small">${on ? 'Sí' : 'No'}</span>
                            </button>`;
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
                render: (d, t, r) => `
                    <div class="dropdown">
                        <button class="btn btn-sm btn-primary dropdown-toggle py-1"
                                type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li>
                                <button class="dropdown-item btn-editar-rol" type="button"
                                        data-id="${r.id_rol}"
                                        data-nombre="${r.nombre}"
                                        data-desc="${r.descripcion || ''}">
                                    <i class="bi bi-pencil me-2 text-warning"></i>Editar
                                </button>
                            </li>
                            <li>
                                <button class="dropdown-item btn-permisos" type="button"
                                        data-id="${r.id_rol}" data-nombre="${r.nombre}">
                                    <i class="bi bi-shield-lock me-2 text-primary"></i>Gestionar permisos
                                </button>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <button class="dropdown-item ${r.activo == 1 ? 'text-danger' : 'text-success'} btn-toggle-rol"
                                        type="button" data-id="${r.id_rol}">
                                    <i class="bi bi-${r.activo == 1 ? 'toggle-on' : 'toggle-off'} me-2"></i>
                                    ${r.activo == 1 ? 'Desactivar' : 'Activar'}
                                </button>
                            </li>
                        </ul>
                    </div>`
            }
        ],
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
        order: [[0, 'asc']],
        pageLength: 10
    });

    // ── Nuevo rol ──────────────────────────────────────────
    $('#btnNuevoRol').on('click', function () {
        $('#modalRolTitulo').text('Nuevo rol');
        $('#rol_id').val('');
        $('#rol_nombre').val('').removeClass('is-invalid');
        $('#rol_descripcion').val('');
        abrirModal('#modalRol');
    });

    // ── Editar rol ─────────────────────────────────────────
    $('#tblRoles').on('click', '.btn-editar-rol', function () {
        $('#modalRolTitulo').text('Editar rol');
        $('#rol_id').val($(this).data('id'));
        $('#rol_nombre').val($(this).data('nombre')).removeClass('is-invalid');
        $('#rol_descripcion').val($(this).data('desc'));
        abrirModal('#modalRol');
    });

    // ── Guardar rol (crear/editar) ─────────────────────────
    $('#btnGuardarRol').on('click', function () {
        const id     = $('#rol_id').val();
        const nombre = $('#rol_nombre').val().trim();

        if (!nombre) {
            $('#rol_nombre').addClass('is-invalid');
            return;
        }
        $('#rol_nombre').removeClass('is-invalid');

        const datos = {
            accion:      id ? 'editar' : 'crear',
            id_rol:      id,
            nombre,
            descripcion: $('#rol_descripcion').val().trim()
        };

        $.post(CTRL_ROLES, datos, function (r) {
            if (!r.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje, confirmButtonColor: '#4154f1' });
                return;
            }
            cerrarModal('#modalRol');
            tabla.ajax.reload();
            Swal.fire({ icon: 'success', title: 'Listo', text: r.mensaje, timer: 1800, showConfirmButton: false });
        }, 'json');
    });

    // ── Toggle ejecuta mantenimiento ──────────────────────
    $('#tblRoles').on('click', '.btn-toggle-ejec-mant', function () {
        const id = $(this).data('id');
        $.post(CTRL_ROLES, { accion: 'toggleEjecutaMantenimiento', id_rol: id }, function (r) {
            if (r.ok) tabla.ajax.reload(null, false);
            else Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje });
        }, 'json');
    });

    // ── Toggle activo ──────────────────────────────────────
    $('#tblRoles').on('click', '.btn-toggle-rol', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: '¿Cambiar estado?',
            icon: 'question', showCancelButton: true,
            confirmButtonText: 'Sí', cancelButtonText: 'No',
            confirmButtonColor: '#4154f1'
        }).then(res => {
            if (!res.isConfirmed) return;
            $.post(CTRL_ROLES, { accion: 'toggleActivo', id_rol: id }, function (r) {
                if (r.ok) tabla.ajax.reload(null, false);
            }, 'json');
        });
    });

    // ── Gestionar permisos ─────────────────────────────────
    $('#tblRoles').on('click', '.btn-permisos', function () {
        const id     = $(this).data('id');
        const nombre = $(this).data('nombre');

        $('#permisosRolNombre').text(nombre);
        $('#permisos_id_rol').val(id);
        $('#contenedorPermisos').html(
            '<div class="text-center py-4"><span class="spinner-border spinner-border-sm me-2"></span> Cargando...</div>'
        );

        abrirModal('#modalPermisos');

        $.post(CTRL_ROLES, { accion: 'obtenerPermisos', id_rol: id }, function (r) {
            if (!r.ok) {
                $('#contenedorPermisos').html(`<div class="alert alert-danger">${r.mensaje}</div>`);
                return;
            }
            renderPermisos(r.data.modulos, r.data.asignados);
        }, 'json');
    });

    function renderPermisos(modulos, asignados) {
        // Normalize to numbers so strict equality works regardless of what the server returns
        const asignadosNum = asignados.map(Number);

        // Agrupar por grupo
        const grupos = {};
        modulos.forEach(m => {
            if (!grupos[m.grupo]) grupos[m.grupo] = [];
            grupos[m.grupo].push(m);
        });

        let html = '';
        Object.keys(grupos).forEach(grupo => {
            html += `
            <div class="grupo-modulos">
                <div class="d-flex justify-content-between align-items-center grupo-titulo">
                    <span><i class="bi bi-folder2-open me-1"></i>${grupo}</span>
                    <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input chk-grupo-all" type="checkbox"
                               data-grupo="${grupo}" id="grp_${grupo}"
                               title="Seleccionar / deseleccionar todo el grupo">
                        <label class="form-check-label text-muted small" for="grp_${grupo}">Todos</label>
                    </div>
                </div>
                <div class="row">`;

            grupos[grupo].forEach(m => {
                const checked = asignadosNum.includes(Number(m.id_modulo)) ? 'checked' : '';
                html += `
                    <div class="col-md-6 col-lg-4">
                        <div class="form-check modulo-item">
                            <input class="form-check-input chk-modulo" type="checkbox"
                                   id="mod_${m.id_modulo}" value="${m.id_modulo}"
                                   data-grupo="${grupo}" ${checked}>
                            <label class="form-check-label" for="mod_${m.id_modulo}">
                                <i class="${m.icono}"></i> ${m.nombre}
                            </label>
                        </div>
                    </div>`;
            });

            html += `</div></div>`;
        });

        $('#contenedorPermisos').html(html);

        // Inicializar estado de checkboxes de grupo
        actualizarEstadoGrupos();

        // Sync checkbox de grupo al marcar/desmarcar módulos
        $(document).on('change.permisos', '.chk-modulo', function () {
            actualizarEstadoGrupos();
        });

        // Click en "Todos" del grupo — prop('checked') no dispara 'change', así que actualizamos manualmente
        $(document).on('change.permisos', '.chk-grupo-all', function () {
            const grupo   = $(this).data('grupo');
            const checked = $(this).prop('checked');
            $(`.chk-modulo[data-grupo="${grupo}"]`).prop('checked', checked);
            actualizarEstadoGrupos();
        });
    }

    function actualizarEstadoGrupos() {
        $('.chk-grupo-all').each(function () {
            const grupo  = $(this).data('grupo');
            const total  = $(`.chk-modulo[data-grupo="${grupo}"]`).length;
            const marcados = $(`.chk-modulo[data-grupo="${grupo}"]:checked`).length;
            $(this).prop('checked', total > 0 && marcados === total);
            $(this).prop('indeterminate', marcados > 0 && marcados < total);
        });
    }

    // ── Guardar permisos ───────────────────────────────────
    $('#btnGuardarPermisos').on('click', function () {
        const id_rol = $('#permisos_id_rol').val();
        const ids    = $('.chk-modulo:checked').map((i, el) => el.value).get();

        $.post(CTRL_ROLES, {
            accion:      'guardarPermisos',
            id_rol,
            ids_modulos: ids
        }, function (r) {
            if (!r.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje });
                return;
            }
            // Limpiar listeners para evitar duplicados
            $(document).off('change.permisos');
            cerrarModal('#modalPermisos');
            tabla.ajax.reload(null, false);
            Swal.fire({ icon: 'success', title: 'Listo', text: r.mensaje, timer: 1800, showConfirmButton: false });
        }, 'json');
    });

    // Limpiar listeners al cerrar modal
    document.getElementById('modalPermisos').addEventListener('hidden.bs.modal', function () {
        $(document).off('change.permisos');
    });

    // ── Helpers ────────────────────────────────────────────
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
