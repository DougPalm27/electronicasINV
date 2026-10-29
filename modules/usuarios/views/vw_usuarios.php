<div class="row mb-3">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">Gestión de Usuarios</h5>
                    <small class="text-muted">Cuentas de acceso y roles asignados</small>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary" id="btnClavesDelvis">
                        <i class="bi bi-shield-lock me-1"></i> Contraseñas de Delvis
                    </button>
                    <button class="btn btn-primary" id="btnNuevoUsuario">
                        <i class="bi bi-person-plus me-1"></i> Nuevo usuario
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <table id="tblUsuarios" class="table table-hover w-100">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th class="text-center">Estado</th>
                    <th class="text-center">PIN</th>
                    <th>Registro</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- ── Modal Crear / Editar ──────────────────────────────── -->
<div class="modal fade" id="modalUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalUsuarioTitulo">Nuevo usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="frmUsuario" novalidate>
                    <input type="hidden" id="u_id">

                    <div class="mb-3">
                        <label for="u_nombre" class="form-label">Nombre completo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="u_nombre" maxlength="150" required>
                        <div class="invalid-feedback">Ingresa el nombre completo.</div>
                    </div>

                    <div class="mb-3">
                        <label for="u_email" class="form-label">Correo electrónico</label>
                        <input type="email" class="form-control" id="u_email" maxlength="150"
                               placeholder="usuario@empresa.com">
                        <div class="form-text text-muted">
                            <i class="bi bi-envelope me-1"></i>
                            Se usa para enviar notificaciones del sistema.
                        </div>
                        <div class="invalid-feedback">Ingresa un correo válido.</div>
                    </div>

                    <!-- Rol -->
                    <div class="mb-3">
                        <label for="u_rol" class="form-label">Rol</label>
                        <select class="form-select" id="u_rol">
                            <option value="">— Sin rol asignado —</option>
                        </select>
                        <div class="form-text text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Define qué módulos podrá ver este usuario.
                        </div>
                    </div>

                    <div id="bloque_username">
                        <div class="mb-3">
                            <label for="u_username" class="form-label">Nombre de usuario <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="u_username" maxlength="50" required>
                            <div class="invalid-feedback">Ingresa el nombre de usuario.</div>
                        </div>

                        <div class="mb-3">
                            <label for="u_password" class="form-label">Contraseña <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="u_password" minlength="6" required>
                            <div class="invalid-feedback">Mínimo 6 caracteres.</div>
                        </div>

                        <div class="mb-3">
                            <label for="u_confirmar" class="form-label">Confirmar contraseña <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="u_confirmar" minlength="6" required>
                            <div class="invalid-feedback">Las contraseñas no coinciden.</div>
                        </div>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary"   id="btnGuardarUsuario">
                    <i class="bi bi-save me-1"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal Código de bloqueo (PIN del kiosco de planta) ── -->
<div class="modal fade" id="modalClavesDelvis" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Contraseñas de nivel de Delvis</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Las mismas en todas las máquinas. El candado las escribe en Delvis según el nivel de cada
                    usuario (Código de bloqueo → Nivel en Delvis). Se guardan cifradas y nunca se vuelven a mostrar.
                    Solo se usan si coinciden con las que tiene Delvis en esa máquina.
                </p>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="cd_misma" checked>
                    <label class="form-check-label" for="cd_misma">La misma para los 3 niveles</label>
                </div>
                <div id="cd_filas"></div>
                <div class="form-text text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    En blanco = se deja la que está. Llegan a las máquinas con la próxima sincronización.
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button class="btn btn-outline-danger" id="btnQuitarClavesDelvis">
                    <i class="bi bi-x-circle me-1"></i> Quitar todas
                </button>
                <div>
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" id="btnGuardarClavesDelvis">
                        <i class="bi bi-save me-1"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalPin" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Código de bloqueo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Usuario: <strong id="pinNombreUsuario"></strong>
                </p>
                <input type="hidden" id="pin_id">
                <input type="hidden" id="pin_tiene" value="0">
                <div class="mb-3">
                    <label class="form-label">Código (4 a 6 dígitos) <span class="text-danger" id="pinObligatorio">*</span></label>
                    <input type="text" class="form-control" id="pin_valor" inputmode="numeric"
                           maxlength="6" placeholder="Ej. 4821">
                    <div class="form-text text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Es el código que el operario marca en el candado de la máquina. No es su contraseña.
                        <span id="pinYaTiene" class="d-none">En blanco = conserva el código actual.</span>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="pin_nivel">Nivel en Delvis</label>
                    <select class="form-select" id="pin_nivel">
                        <option value="0">Ninguno (escribe la contraseña a mano)</option>
                        <option value="1">Nivel 1</option>
                        <option value="2">Nivel 2</option>
                        <option value="3">Nivel 3</option>
                    </select>
                    <div class="form-text text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Cuando Delvis pida la contraseña de este nivel o uno menor, el candado la escribe sola.
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button class="btn btn-outline-danger" id="btnQuitarPin">
                    <i class="bi bi-x-circle me-1"></i> Quitar código
                </button>
                <div>
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" id="btnGuardarPin">
                        <i class="bi bi-save me-1"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal Reset Contraseña (admin) ───────────────────── -->
<div class="modal fade" id="modalReset" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Restablecer contraseña</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Usuario: <strong id="resetNombreUsuario"></strong>
                </p>
                <input type="hidden" id="reset_id">
                <div class="mb-3">
                    <label class="form-label">Nueva contraseña <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="reset_password" minlength="6"
                           placeholder="Mínimo 6 caracteres">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-warning"   id="btnConfirmarReset">
                    <i class="bi bi-key me-1"></i> Restablecer
                </button>
            </div>
        </div>
    </div>
</div>
