<div class="row mb-3">
  <div class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div>
          <h5 class="mb-0">Tipos de Vehículo</h5>
          <small class="text-muted">Catálogo de tipos de vehículo</small>
        </div>
        <button class="btn btn-primary" id="btnNuevoTipoV">
          <i class="bi bi-plus-circle me-1"></i> Nuevo tipo
        </button>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm border-0">
  <div class="card-body">
    <table id="tblTiposVehiculo" class="table table-hover w-100">
      <thead>
        <tr>
          <th>Nombre</th><th>Fecha creación</th>
          <th>Creado por</th><th class="text-center">Estado</th>
          <th class="text-center">Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="modalTipoV" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTipoVTitulo">Nuevo tipo de vehículo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="tv_id">
        <div class="mb-3">
          <label for="tv_nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="tv_nombre" maxlength="50"
                 placeholder="Ej.: Cabezal, Pickup, Camión">
          <div class="invalid-feedback">El nombre es obligatorio.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary" id="btnGuardarTipoV">
          <i class="bi bi-save me-1"></i> Guardar
        </button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </div>
  </div>
</div>

