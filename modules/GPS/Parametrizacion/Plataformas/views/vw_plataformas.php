<section class="section">
  <div class="row"><div class="col-12">
    <div class="card">
      <div class="card-body pt-3">
        <div class="d-flex justify-content-between align-items-center mb-3 mt-2">
          <h5 class="card-title mb-0">
            <i class="bi bi-broadcast me-2 text-success"></i>Plataformas GPS
          </h5>
          <button class="btn btn-success btn-sm" id="btnNuevaPlataforma">
            <i class="bi bi-plus-circle me-1"></i> Nueva plataforma
          </button>
        </div>
        <div class="table-responsive">
          <table id="tblPlataformas" class="table table-hover table-striped table-sm align-middle">
            <thead class="table-success">
              <tr>
                <th>#</th><th>Nombre</th><th>URL base</th><th>Fecha creación</th>
                <th>Creado por</th><th class="text-center">Estado</th>
                <th class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </div></div>
</section>

<div class="modal fade" id="modalPlataforma" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalPlataformaTitulo">Nueva plataforma GPS</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="plat_id">
        <div class="mb-3">
          <label for="plat_nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="plat_nombre" maxlength="100"
                 placeholder="Ej.: TrackSolid Pro">
          <div class="invalid-feedback">El nombre es obligatorio.</div>
        </div>
        <div class="mb-3">
          <label for="plat_url" class="form-label">URL base</label>
          <input type="text" class="form-control" id="plat_url" maxlength="500"
                 placeholder="https://...">
          <div class="form-text">URL de acceso a la plataforma (opcional).</div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-success" id="btnGuardarPlataforma">
          <i class="bi bi-save me-1"></i> Guardar
        </button>
      </div>
    </div>
  </div>
</div>
<style>.is-invalid~.invalid-feedback{display:block!important}.is-invalid{border-color:#dc3545!important}</style>
