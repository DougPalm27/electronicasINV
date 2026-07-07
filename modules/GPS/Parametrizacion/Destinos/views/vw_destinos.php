<div class="row mb-3">
  <div class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div>
          <h5 class="mb-0">Destinos</h5>
          <small class="text-muted">Puntos de destino para las rutas GPS</small>
        </div>
        <button class="btn btn-primary" id="btnNuevoDestino">
          <i class="bi bi-plus-circle me-1"></i> Nuevo destino
        </button>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm border-0">
  <div class="card-body">
    <table id="tblDestinos" class="table table-hover w-100">
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

<div class="modal fade" id="modalDestino" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalDestinoTitulo">Nuevo destino</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="dest_id">
        <div class="mb-3">
          <label for="dest_nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="dest_nombre" maxlength="100"
                 placeholder="Ej.: ACOPIO, PUERTO, OPC">
          <div class="invalid-feedback">El nombre es obligatorio.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary" id="btnGuardarDestino">
          <i class="bi bi-save me-1"></i> Guardar
        </button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </div>
  </div>
</div>

