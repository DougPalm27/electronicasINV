<div class="col-lg-12">
  <div class="card">
    <div class="card-body">

      <h5 class="card-title">Credenciales GPS</h5>

      <div class="d-flex justify-content-between mb-3">
        <button class="btn btn-primary" id="btnNuevoGPS">
          <i class="bi bi-plus-circle me-1"></i> Nuevo vehículo
        </button>
      </div>

      <div>
        <table id="tblGPS" class="table table-striped w-100">
          <thead>
            <tr>
              <th>#</th><th>Placa</th><th>Tipo</th><th>Transporte</th>
              <th>Plataforma</th><th>Destino</th><th>Usuario</th><th>Contraseña</th>
              <th class="text-center">Enlace</th>
              <th class="text-center">Estado</th><th class="text-center">Acciones</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="modalGPS" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalGPSTitulo">Nuevo vehículo GPS</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="gps_id">

        <div class="row g-3">
          <!-- Tipo vehículo -->
          <div class="col-md-6">
            <label for="gps_id_tipo_vehiculo" class="form-label">Tipo de vehículo</label>
            <select class="form-select" id="gps_id_tipo_vehiculo">
              <option value="">— Selecciona —</option>
            </select>
          </div>

          <!-- Destino -->
          <div class="col-md-6">
            <label for="gps_id_destino" class="form-label">Destino</label>
            <select class="form-select" id="gps_id_destino">
              <option value="">— Selecciona —</option>
            </select>
          </div>

          <!-- Transporte -->
          <div class="col-md-6">
            <label for="gps_id_transporte" class="form-label">Empresa de transporte <span class="text-danger">*</span></label>
            <select class="form-select" id="gps_id_transporte">
              <option value="">— Selecciona —</option>
            </select>
          </div>

          <!-- Cuenta GPS -->
          <div class="col-md-6">
            <label for="gps_id_cuenta" class="form-label">Cuenta GPS <span class="text-danger">*</span></label>
            <select class="form-select" id="gps_id_cuenta" disabled>
              <option value="">— Primero selecciona transporte —</option>
            </select>
            <div class="invalid-feedback">Selecciona una cuenta GPS.</div>
          </div>

          <!-- Info readonly -->
          <div class="col-md-6">
            <label class="form-label">Plataforma</label>
            <input type="text" class="form-control" id="gps_info_plataforma" readonly
                   placeholder="Se carga al seleccionar cuenta">
          </div>
          <div class="col-md-3">
            <label class="form-label">Usuario</label>
            <input type="text" class="form-control" id="gps_info_usuario" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label">Contraseña</label>
            <input type="text" class="form-control" id="gps_info_contrasena" readonly>
          </div>

          <!-- Sección placas -->
          <div class="col-12">
            <hr class="my-1">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <label class="form-label mb-0 fw-semibold">
                Placas <span class="text-danger">*</span>
              </label>
              <button type="button" class="btn btn-sm btn-outline-primary" id="btnAgregarPlaca">
                <i class="bi bi-plus-lg me-1"></i> Agregar placa
              </button>
            </div>
            <div id="gps_placas_lista"></div>
            <div class="invalid-feedback" id="gps_placas_error"></div>
          </div>
        </div>

      </div>
      <div class="modal-footer">
        <button class="btn btn-primary" id="btnGuardarGPS">
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
  #gps_info_plataforma, #gps_info_usuario, #gps_info_contrasena { background: #f8f9fa; color: #495057; }
  .placa-row input { text-transform: uppercase; }
</style>
