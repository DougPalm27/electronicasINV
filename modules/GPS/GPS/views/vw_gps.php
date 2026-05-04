<section class="section">
  <div class="row"><div class="col-12">
    <div class="card">
      <div class="card-body pt-3">
        <div class="d-flex justify-content-between align-items-center mb-3 mt-2">
          <h5 class="card-title mb-0">
            <i class="bi bi-geo-alt-fill me-2 text-success"></i>Credenciales GPS
          </h5>
          <button class="btn btn-success btn-sm" id="btnNuevoGPS">
            <i class="bi bi-plus-circle me-1"></i> Nuevo vehículo
          </button>
        </div>
        <div class="table-responsive">
          <table id="tblGPS" class="table table-hover table-striped table-sm align-middle">
            <thead class="table-success">
              <tr>
                <th>#</th><th>Placa</th><th>Tipo</th><th>Transporte</th>
                <th>Plataforma</th><th>Destino</th><th>Usuario</th><th>Contraseña</th>
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
  </div></div>
</section>

<!-- ── Modal Crear / Editar GPS ────────────────────────────── -->
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
          <!-- Placa -->
          <div class="col-md-4">
            <label for="gps_placa" class="form-label">Placa <span class="text-danger">*</span></label>
            <input type="text" class="form-control text-uppercase" id="gps_placa"
                   maxlength="20" placeholder="Ej.: JAX2805">
            <div class="invalid-feedback">La placa es obligatoria.</div>
          </div>

          <!-- Tipo vehículo -->
          <div class="col-md-4">
            <label for="gps_id_tipo_vehiculo" class="form-label">Tipo de vehículo</label>
            <select class="form-select" id="gps_id_tipo_vehiculo">
              <option value="">— Selecciona —</option>
            </select>
          </div>

          <!-- Destino -->
          <div class="col-md-4">
            <label for="gps_id_destino" class="form-label">Destino</label>
            <select class="form-select" id="gps_id_destino">
              <option value="">— Selecciona —</option>
            </select>
          </div>

          <!-- Transporte (cascada → cuentas) -->
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

          <!-- Info de cuenta (readonly) -->
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
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-success" id="btnGuardarGPS">
          <i class="bi bi-save me-1"></i> Guardar
        </button>
      </div>
    </div>
  </div>
</div>

<style>
  .is-invalid~.invalid-feedback{display:block!important}.is-invalid{border-color:#dc3545!important}
  .pwd-masked{letter-spacing:.15em;color:#6c757d;font-size:.85rem}
  .btn-reveal-pwd{padding:.15rem .4rem;font-size:.78rem;line-height:1}
  #gps_info_plataforma,#gps_info_usuario,#gps_info_contrasena{background:#f8f9fa;color:#495057}
</style>
