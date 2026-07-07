<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/navContext.php';

$nombreUsuario = $_SESSION['nombre']     ?? 'Usuario';
$username      = $_SESSION['usuario']    ?? '';
$rolUsuario    = $_SESSION['nombre_rol'] ?? '';
$fotoUsuario   = $_SESSION['foto']       ?? null;
$seccionHeader = nombreSeccion(moduloActual());
$tituloModulo  = tituloModulo(moduloActual());

// Iniciales para el avatar (máx. 2 letras)
$iniciales = '';
foreach (preg_split('/\s+/', trim($nombreUsuario)) as $palabra) {
    if ($palabra === '') continue;
    $iniciales .= mb_strtoupper(mb_substr($palabra, 0, 1));
    if (mb_strlen($iniciales) >= 2) break;
}
if ($iniciales === '') $iniciales = 'U';
?>
<!-- ======= Header ======= -->
<header id="header" class="header">

    <i class="bi bi-list toggle-sidebar-btn" role="button" aria-label="Alternar menú"></i>

    <div class="hc-breadcrumb d-none d-sm-block">
        <?= htmlspecialchars($seccionHeader) ?>
        <span class="sep">/</span>
        <span class="actual"><?= htmlspecialchars($tituloModulo) ?></span>
    </div>

    <nav class="header-nav ms-auto">
        <ul class="d-flex align-items-center">

            <li class="nav-item dropdown">
                <a class="nav-link nav-profile d-flex align-items-center"
                   href="#" data-bs-toggle="dropdown">
                    <?php if ($fotoUsuario): ?>
                        <img src="./<?= htmlspecialchars($fotoUsuario) ?>" alt="Foto de perfil" class="avatar-foto">
                    <?php else: ?>
                        <span class="avatar-ini"><?= htmlspecialchars($iniciales) ?></span>
                    <?php endif; ?>
                    <span class="d-none d-md-block"><?= htmlspecialchars($nombreUsuario) ?></span>
                    <i class="bi bi-chevron-down d-none d-md-block" style="font-size:.6rem"></i>
                </a>

                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="dropdown-header">
                        <h6><?= htmlspecialchars($nombreUsuario) ?></h6>
                        <span><?= htmlspecialchars($username) ?><?= $rolUsuario ? ' · ' . htmlspecialchars($rolUsuario) : '' ?></span>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center" href="#"
                           id="btnAbrirPerfil">
                            <i class="bi bi-person-circle me-2"></i>
                            <span>Mi perfil</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center" href="#"
                           id="btnAbrirCambiarPassword">
                            <i class="bi bi-key me-2"></i>
                            <span>Cambiar contraseña</span>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center text-danger"
                           href="<?= rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') ?>/logout.php">
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

<!-- ── Modal: Mi perfil (global) ─────────────────────────── -->
<div class="modal fade" id="modalPerfil" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-circle me-2"></i>Mi perfil</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <div class="text-center mb-3">
                    <?php if ($fotoUsuario): ?>
                        <img src="./<?= htmlspecialchars($fotoUsuario) ?>" alt="Foto de perfil" class="perfil-foto">
                    <?php else: ?>
                        <span class="perfil-ini"><?= htmlspecialchars($iniciales) ?></span>
                    <?php endif; ?>
                </div>

                <input type="file" id="perfilInputFoto" class="d-none"
                       accept="image/jpeg, image/png, image/webp">

                <div class="d-flex justify-content-center gap-2 mb-1">
                    <button type="button" class="btn btn-sm btn-primary" id="btnCambiarFoto">
                        <i class="bi bi-camera me-1"></i><?= $fotoUsuario ? 'Cambiar foto' : 'Subir foto' ?>
                    </button>
                    <?php if ($fotoUsuario): ?>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="btnQuitarFoto">
                        <i class="bi bi-trash me-1"></i>Quitar
                    </button>
                    <?php endif; ?>
                </div>
                <p class="text-center text-muted mb-4" style="font-size:.72rem">
                    JPG, PNG o WebP · máx. 2 MB
                </p>

                <div class="border rounded p-3">
                    <div class="row mb-2">
                        <div class="col-4 text-muted small">Nombre</div>
                        <div class="col-8 small fw-semibold"><?= htmlspecialchars($nombreUsuario) ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-4 text-muted small">Usuario</div>
                        <div class="col-8 small"><?= htmlspecialchars($username) ?></div>
                    </div>
                    <div class="row">
                        <div class="col-4 text-muted small">Rol</div>
                        <div class="col-8 small"><?= $rolUsuario ? htmlspecialchars($rolUsuario) : '<span class="text-muted">Sin rol asignado</span>' ?></div>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts de perfil y cambiar contraseña cargados desde scripts.php (después de jQuery) -->
