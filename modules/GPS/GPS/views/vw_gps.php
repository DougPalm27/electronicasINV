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
              <th>Plataforma</th><th>Destino</th>
              <th class="text-center">Acceso</th><th class="text-center">Estado</th>
              <th class="text-center">Acciones</th>
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

<!-- ══════════════════════════════════════════════════════
     MODAL — ACCESO RÁPIDO
══════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalAccesoRapido" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header pb-2">
        <div>
          <h6 class="modal-title mb-0" id="arTitulo">Acceso rápido</h6>
          <small class="text-muted" id="arPlaca"></small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <!-- Usuario -->
        <label class="form-label small fw-semibold mb-1">Usuario</label>
        <div class="input-group mb-3">
          <input type="text" id="arUsuario" class="form-control form-control-sm" readonly>
          <button class="btn btn-outline-secondary btn-copiar" data-target="arUsuario" title="Copiar usuario">
            <i class="bi bi-clipboard"></i>
          </button>
        </div>

        <!-- Contraseña -->
        <label class="form-label small fw-semibold mb-1">Contraseña</label>
        <div class="input-group mb-3">
          <input type="password" id="arContrasena" class="form-control form-control-sm" readonly>
          <button class="btn btn-outline-secondary btn-ver-pwd" title="Mostrar / ocultar">
            <i class="bi bi-eye"></i>
          </button>
          <button class="btn btn-outline-secondary btn-copiar" data-target="arContrasena" title="Copiar contraseña">
            <i class="bi bi-clipboard"></i>
          </button>
        </div>

        <!-- Toast de feedback -->
        <div id="arToast" class="alert alert-success py-1 px-2 small mb-0" style="display:none">
          <i class="bi bi-check2 me-1"></i><span id="arToastMsg"></span>
        </div>

      </div>

      <div class="modal-footer pt-2">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
        <a id="arEnlace" href="#" target="_blank" rel="noopener noreferrer"
           class="btn btn-primary btn-sm">
          <i class="bi bi-box-arrow-up-right me-1"></i> Ir a la plataforma
        </a>
      </div>

    </div>
  </div>
</div>

<style>
  .is-invalid ~ .invalid-feedback { display: block !important; }
  .form-control.is-invalid,
  .form-select.is-invalid { border-color: #dc3545 !important; box-shadow: 0 0 0 0.2rem rgba(220,53,69,.2) !important; }
  #gps_info_plataforma, #gps_info_usuario, #gps_info_contrasena { background: #f8f9fa; color: #495057; }
  .placa-row input { text-transform: uppercase; }

  /* ── Placa hondureña ── */
  .placa-hn {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    background: #fff;
    border: 2.5px solid #0d1d8c;
    border-radius: 5px;
    min-width: 108px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,.3);
    line-height: 1;
    font-family: 'Arial Black', 'Arial Bold', Arial, sans-serif;
    user-select: none;
  }
  .placa-hn .ph-top {
    width: 100%;
    background: #0d1d8c;
    color: #fff;
    font-size: .48rem;
    font-weight: 800;
    letter-spacing: .18em;
    text-align: center;
    padding: 2px 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 3px;
  }
  .placa-hn .ph-top .ph-flag {
    font-size: .6rem;
    line-height: 1;
  }
  .placa-hn .ph-num {
    color: #000;
    font-size: 1.15rem;
    font-weight: 900;
    letter-spacing: .06em;
    padding: 1px 8px 0;
    text-align: center;
  }
  .placa-hn .ph-bot {
    width: 100%;
    color: #0d1d8c;
    font-size: .42rem;
    font-weight: 800;
    letter-spacing: .16em;
    text-align: center;
    padding: 1px 4px 3px;
  }
</style>
