<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$nombreUsuario = $_SESSION['nombre']  ?? 'Usuario';
$username      = $_SESSION['usuario'] ?? '';
?>
<!-- ======= Header ======= -->
<header id="header" class="header fixed-top d-flex align-items-center">

    <div class="d-flex align-items-center justify-content-between">
        <a href="inicio.php" class="logo d-flex align-items-center">
            <img src="./assets/img/trazabilidad.png" alt="">
            <span class="d-none d-lg-block">Control de Repuestos</span>
        </a>
        <i class="bi bi-list toggle-sidebar-btn"></i>
    </div>

    <nav class="header-nav ms-auto">
        <ul class="d-flex align-items-center">

            <li class="nav-item dropdown pe-3">
                <a class="nav-link nav-profile d-flex align-items-center pe-0"
                   href="#" data-bs-toggle="dropdown">
                    <img src="./assets/img/usuario.png" alt="Profile" class="rounded-circle">
                    <span class="d-none d-md-block dropdown-toggle ps-2">
                        <?= htmlspecialchars($nombreUsuario) ?>
                    </span>
                </a>

                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
                    <li class="dropdown-header">
                        <h6><?= htmlspecialchars($nombreUsuario) ?></h6>
                        <span><?= htmlspecialchars($username) ?></span>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center" href="#"
                           id="btnAbrirCambiarPassword">
                            <i class="bi bi-key me-2"></i>
                            <span>Cambiar contraseña</span>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center"
                           href="/Electronicas/logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i>
                            <span>Cerrar sesión</span>
                        </a>
                    </li>
                </ul>
            </li>

        </ul>
    </nav>

</header>

<!-- ── Modal: Cambiar contraseña (global) ────────────────── -->
<div class="modal fade" id="modalCambiarPassword" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-key me-2"></i>Cambiar contraseña</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="frmCambiarPassword" novalidate autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label">Contraseña actual <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="cp_actual"
                               placeholder="Tu contraseña actual" required>
                        <div class="invalid-feedback">Ingresa tu contraseña actual.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nueva contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="cp_nueva"
                               placeholder="Mínimo 6 caracteres" minlength="6" required>
                        <div class="invalid-feedback">Mínimo 6 caracteres.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirmar nueva contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="cp_confirmar"
                               placeholder="Repite la nueva contraseña" required>
                        <div class="invalid-feedback">Las contraseñas no coinciden.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary" id="btnGuardarPassword">
                    <i class="bi bi-save me-1"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #modalCambiarPassword .is-invalid ~ .invalid-feedback { display: block !important; }
    #modalCambiarPassword .is-invalid { border-color: #dc3545 !important; }
</style>

<script>
$(function () {
    const CTRL_USR = './modules/Usuarios/controllers/usuariosController.php';

    $('#btnAbrirCambiarPassword').on('click', function (e) {
        e.preventDefault();
        $('#frmCambiarPassword')[0].reset();
        $('#frmCambiarPassword .is-invalid').removeClass('is-invalid');
        const el = document.getElementById('modalCambiarPassword');
        (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show();
    });

    $('#btnGuardarPassword').on('click', function () {
        const actual    = $('#cp_actual').val();
        const nueva     = $('#cp_nueva').val();
        const confirmar = $('#cp_confirmar').val();

        let valido = true;

        $('#cp_actual').toggleClass('is-invalid', !actual);
        if (!actual) valido = false;

        $('#cp_nueva').toggleClass('is-invalid', nueva.length < 6);
        if (nueva.length < 6) valido = false;

        $('#cp_confirmar').toggleClass('is-invalid', nueva !== confirmar);
        if (nueva !== confirmar) valido = false;

        if (!valido) return;

        $('#btnGuardarPassword').prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...'
        );

        $.post(CTRL_USR, {
            accion:           'cambiarPassword',
            password_actual:  actual,
            password_nueva:   nueva,
            confirmar:        confirmar
        }, function (resp) {
            $('#btnGuardarPassword').prop('disabled', false).html('<i class="bi bi-save me-1"></i> Guardar');

            if (!resp.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: resp.mensaje, confirmButtonColor: '#4154f1' });
                return;
            }

            const el = document.getElementById('modalCambiarPassword');
            bootstrap.Modal.getInstance(el).hide();

            Swal.fire({
                icon: 'success', title: '¡Listo!', text: resp.mensaje,
                timer: 2000, showConfirmButton: false
            });
        }, 'json');
    });
});
</script>
