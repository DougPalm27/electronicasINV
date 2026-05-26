<div class="col-lg-12">
  <div class="card">
    <div class="card-body">

      <h5 class="card-title">Destinos</h5>

      <div class="d-flex justify-content-between mb-3">
        <button class="btn btn-primary" id="btnNuevoDestino">
          <i class="bi bi-plus-circle me-1"></i> Nuevo destino
        </button>
      </div>

      <div class="table-responsive">
        <table id="tblDestinos" class="table table-striped">
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

<style>
  .is-invalid ~ .invalid-feedback { display: block !important; }
  .form-control.is-invalid,
  .form-select.is-invalid { border-color: #dc3545 !important; box-shadow: 0 0 0 0.2rem rgba(220,53,69,.2) !important; }
</style>
