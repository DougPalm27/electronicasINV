const CTRL_USR = './modules/usuarios/controllers/usuariosController.php';

$(document).ready(function () {
    if (!document.getElementById('tblUsuarios')) return;

    // ── Cargar roles en el select ──────────────────────────
    function cargarRoles(valorActual = '') {
        $.post(CTRL_USR, { accion: 'listarRoles' }, function (r) {
            if (!r.ok) return;
            let opts = '<option value="">— Sin rol asignado —</option>';
            r.data.forEach(rol => {
                const sel = String(rol.id_rol) === String(valorActual) ? 'selected' : '';
                opts += `<option value="${rol.id_rol}" ${sel}>${rol.nombre}</option>`;
            });
            $('#u_rol').html(opts);
        }, 'json');
    }

    // ── DataTable ─────────────────────────────────────────
    const tabla = $('#tblUsuarios').DataTable({
        ajax: {
            url: CTRL_USR,
            type: 'POST',
            data: { accion: 'listar' },
            dataSrc: function (resp) { return resp.ok ? resp.data : []; }
        },
        columns: [
            { data: null, render: (d, t, r, m) => m.row + 1 },
            { data: 'username' },
            { data: 'nombre' },
            {
                data: 'email',
                render: v => v
                    ? `<a href="mailto:${v}" class="text-decoration-none small">${v}</a>`
                    : '<span class="text-muted small">—</span>'
            },
            {
                data: 'nombre_rol',
                render: v => v
                    ? `<span class="badge bg-primary">${v}</span>`
                    : '<span class="text-muted small">Sin rol</span>'
            },
            {
                data: 'activo',
                className: 'text-center',
                render: v => v == 1
                    ? '<span class="badge bg-success">Activo</span>'
                    : '<span class="badge bg-secondary">Inactivo</span>'
            },
            { data: 'fecha_registro' },
            {
                data: null,
                className: 'text-center',
                orderable: false,
                render: (d, t, r) => `
                    <button class="btn btn-sm btn-warning btn-editar me-1"
                            data-id="${r.id_usuario}"
                            data-nombre="${r.nombre}"
                            data-email="${r.email || ''}"
                            data-rol="${r.id_rol || ''}"
                            title="Editar usuario">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-primary btn-reset me-1"
                            data-id="${r.id_usuario}" data-nombre="${r.nombre}"
                            title="Restablecer contraseña">
                        <i class="bi bi-key"></i>
                    </button>
                    <button class="btn btn-sm ${r.activo == 1 ? 'btn-danger' : 'btn-success'} btn-toggle"
                            data-id="${r.id_usuario}"
                            title="${r.activo == 1 ? 'Desactivar' : 'Activar'}">
                        <i class="bi bi-${r.activo == 1 ? 'person-slash' : 'person-check'}"></i>
                    </button>`
            }
        ],
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
        order: [[2, 'asc']],
        pageLength: 10
    });

    // ── Abrir modal nuevo usuario ─────────────────────────
    $('#btnNuevoUsuario').on('click', function () {
        $('#modalUsuarioTitulo').text('Nuevo usuario');
        $('#frmUsuario')[0].reset();
        $('#frmUsuario .is-invalid').removeClass('is-invalid');
        $('#u_id').val('');
        $('#u_email').val('');
        $('#bloque_username').show();
        $('#u_username, #u_password, #u_confirmar').prop('required', true);
        cargarRoles('');
        abrirModal('#modalUsuario');
    });

    // ── Abrir modal editar ────────────────────────────────
    $('#tblUsuarios').on('click', '.btn-editar', function () {
        const id     = $(this).data('id');
        const nombre = $(this).data('nombre');
        const email  = $(this).data('email');
        const rol    = $(this).data('rol');

        $('#modalUsuarioTitulo').text('Editar usuario');
        $('#frmUsuario')[0].reset();
        $('#frmUsuario .is-invalid').removeClass('is-invalid');
        $('#u_id').val(id);
        $('#u_nombre').val(nombre);
        $('#u_email').val(email || '');
        $('#bloque_username').hide();
        $('#u_username, #u_password, #u_confirmar').prop('required', false);
        cargarRoles(rol);
        abrirModal('#modalUsuario');
    });

    // ── Guardar (crear o editar) ──────────────────────────
    $('#btnGuardarUsuario').on('click', function () {
        const id        = $('#u_id').val();
        const esNuevo   = !id;
        const nombre    = $('#u_nombre').val().trim();
        const email     = $('#u_email').val().trim();
        const username  = $('#u_username').val().trim();
        const password  = $('#u_password').val();
        const confirmar = $('#u_confirmar').val();
        const id_rol    = $('#u_rol').val();

        let valido = true;

        toggleInvalid('#u_nombre', !nombre);
        if (!nombre) valido = false;

        // Validar email si se ingresó
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const emailInvalido = email !== '' && !emailRegex.test(email);
        toggleInvalid('#u_email', emailInvalido);
        if (emailInvalido) valido = false;

        if (esNuevo) {
            toggleInvalid('#u_username', !username);
            if (!username) valido = false;

            toggleInvalid('#u_password', password.length < 6);
            if (password.length < 6) valido = false;

            const noCoinciden = password !== confirmar;
            toggleInvalid('#u_confirmar', noCoinciden);
            if (noCoinciden) valido = false;
        }

        if (!valido) return;

        const accion = esNuevo ? 'crear' : 'editarNombre';
        const datos  = esNuevo
            ? { accion, nombre, email, username, password, id_rol }
            : { accion, id_usuario: id, nombre, email, id_rol };

        $.post(CTRL_USR, datos, function (resp) {
            if (!resp.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: resp.mensaje, confirmButtonColor: '#4154f1' });
                return;
            }
            cerrarModal('#modalUsuario');
            tabla.ajax.reload();
            Swal.fire({
                icon: 'success', title: 'Listo', text: resp.mensaje,
                timer: 1800, showConfirmButton: false
            });
        }, 'json');
    });

    // ── Reset contraseña ──────────────────────────────────
    $('#tblUsuarios').on('click', '.btn-reset', function () {
        $('#reset_id').val($(this).data('id'));
        $('#resetNombreUsuario').text($(this).data('nombre'));
        $('#reset_password').val('');
        abrirModal('#modalReset');
    });

    $('#btnConfirmarReset').on('click', function () {
        const id  = $('#reset_id').val();
        const pwd = $('#reset_password').val();

        if (pwd.length < 6) {
            Swal.fire({ icon: 'warning', title: 'Contraseña muy corta', text: 'Mínimo 6 caracteres.', confirmButtonColor: '#4154f1' });
            return;
        }

        Swal.fire({
            title: '¿Restablecer contraseña?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, restablecer',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#f59e0b'
        }).then(res => {
            if (!res.isConfirmed) return;
            $.post(CTRL_USR, { accion: 'resetPassword', id_usuario: id, password: pwd }, function (resp) {
                cerrarModal('#modalReset');
                Swal.fire({
                    icon: resp.ok ? 'success' : 'error',
                    title: resp.ok ? 'Listo' : 'Error',
                    text: resp.mensaje,
                    timer: resp.ok ? 1800 : undefined,
                    showConfirmButton: !resp.ok
                });
            }, 'json');
        });
    });

    // ── Toggle activo / inactivo ──────────────────────────
    $('#tblUsuarios').on('click', '.btn-toggle', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: '¿Cambiar estado?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí',
            cancelButtonText: 'No',
            confirmButtonColor: '#4154f1'
        }).then(res => {
            if (!res.isConfirmed) return;
            $.post(CTRL_USR, { accion: 'toggleActivo', id_usuario: id }, function (resp) {
                if (resp.ok) tabla.ajax.reload(null, false);
            }, 'json');
        });
    });

    // ── Helpers ───────────────────────────────────────────
    function toggleInvalid(selector, condicion) {
        $(selector).toggleClass('is-invalid', condicion);
    }

    function abrirModal(selector) {
        const el = document.querySelector(selector);
        (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show();
    }

    function cerrarModal(selector) {
        const el = document.querySelector(selector);
        const m  = bootstrap.Modal.getInstance(el);
        if (m) m.hide();
    }
});
