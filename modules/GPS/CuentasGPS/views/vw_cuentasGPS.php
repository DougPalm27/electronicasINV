<div class="col-lg-12">
  <div class="card">
    <div class="card-body">

      <h5 class="card-title">Cuentas GPS</h5>

      <div class="d-flex justify-content-between mb-3">
        <button class="btn btn-primary" id="btnNuevaCuenta">
          <i class="bi bi-plus-circle me-1"></i> Nueva cuenta
        </button>
      </div>

      <div class="table-responsive">
        <table id="tblCuentasGPS" class="table table-striped">
          <thead>
            <tr>
              <th>#</th><th>Transporte</th><th>Plataforma</th><th>URL</th>
              <th>Usuario</th><th>Contraseña</th>
              <th>Fecha creación</th><th>Creado por</th>
              <th>Últ. actualización</th><th>Actualizado por</th>
              <th class="text-center">Estado</th><th class="text-center">Acciones</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="modalCuenta" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalCuentaTitulo">Nueva cuenta GPS</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="cu_id">
        <div class="row g-3">
          <div class="col-md-6">
            <label for="cu_id_transporte" class="form-label">Transporte <span class="text-danger">*</span></label>
            <select class="form-select" id="cu_id_transporte">
              <option value="">— Selecciona —</option>
            </select>
            <div class="invalid-feedback">Selecciona un transporte.</div>
          </div>
          <div class="col-md-6">
            <label for="cu_id_plataforma" class="form-label">Plataforma <span class="text-danger">*</span></label>
            <select class="form-select" id="cu_id_plataforma">
              <option value="">— Selecciona —</option>
            </select>
            <div class="invalid-feedback">Selecciona una plataforma.</div>
          </div>
          <div class="col-md-6">
            <label for="cu_usuario" class="form-label">Usuario</label>
            <input type="text" class="form-control" id="cu_usuario" maxlength="100" autocomplete="off">
          </div>
          <div class="col-md-6">
            <label for="cu_contrasena" class="form-label">Contraseña</label>
            <div class="input-group">
              <input type="password" class="form-control" id="cu_contrasena"
                     maxlength="150" autocomplete="new-password">
              <button class="btn btn-outline-secondary btn-toggle-pwd-cu" type="button" tabindex="-1">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary" id="btnGuardarCuenta">
          <i class="bi bi-save me-1"></i> Guardar
        </button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </div>
  </div>
</div>

<style>
  .is-invalid ~ .invalid-feedback { display: block !important; }
  .form-control.is-invalid,
  .form-select.is-invalid { border-color: #dc3545 !important; box-shadow: 0 0 0 0.2rem rgba(220,53,69,.2) !important; }
  .pwd-masked { letter-spacing: .15em; color: #6c757d; font-size: .85rem; }
  .btn-reveal-pwd { padding: .15rem .4rem; font-size: .78rem; line-height: 1; }
</style>
