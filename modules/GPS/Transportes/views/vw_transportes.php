<div class="row mb-3">
  <div class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div>
          <h5 class="mb-0">Empresas de Transporte</h5>
          <small class="text-muted">Catálogo de empresas de transporte</small>
        </div>
        <button class="btn btn-primary" id="btnNuevoTransporte">
          <i class="bi bi-plus-circle me-1"></i> Nueva empresa
        </button>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm border-0">
  <div class="card-body">
    <table id="tblTransportes" class="table table-hover w-100">
      <thead>
        <tr>
          <th>Empresa</th>
          <th>Contacto</th>
          <th>Teléfono</th>
          <th>Fecha creación</th>
          <th>Creado por</th>
          <th>Últ. actualización</th>
          <th>Actualizado por</th>
          <th class="text-center">Estado</th>
          <th class="text-center">Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="modalTransporte" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTransporteTitulo">Nueva empresa de transporte</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="tr_id">
        <div class="mb-3">
          <label for="tr_nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="tr_nombre" maxlength="150"
                 placeholder="Ej.: Transportes Honduras S.A.">
          <div class="invalid-feedback">El nombre es obligatorio.</div>
        </div>
        <div class="mb-3">
          <label for="tr_contacto" class="form-label">Contacto</label>
          <input type="text" class="form-control" id="tr_contacto" maxlength="100"
                 placeholder="Nombre del contacto">
        </div>
        <div class="mb-3">
          <label for="tr_telefono" class="form-label">Teléfono</label>
          <input type="text" class="form-control" id="tr_telefono" maxlength="20"
                 placeholder="Ej.: +504 9999-9999">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary" id="btnGuardarTransporte">
          <i class="bi bi-save me-1"></i> Guardar
        </button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </div>
  </div>
</div>

