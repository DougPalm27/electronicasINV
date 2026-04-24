<section class="section">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body pt-3">

                    <div class="d-flex justify-content-between align-items-center mb-3 mt-2">
                        <h5 class="card-title mb-0">Divisas y tipos de cambio</h5>
                        <button class="btn btn-primary btn-sm" id="btnNuevaDivisa">
                            <i class="bi bi-plus-circle me-1"></i> Nueva divisa
                        </button>
                    </div>

                    <div class="alert alert-info d-flex align-items-center py-2 mb-3" role="alert">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <span>El tipo de cambio es relativo al <strong>Lempira (L.)</strong> como moneda base.
                        La divisa marcada como <strong>predeterminada</strong> se usa en toda la aplicación para mostrar precios.</span>
                    </div>

                    <div class="table-responsive">
                        <table id="tblDivisas" class="table table-hover table-striped table-sm align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>#</th>
                                    <th>Nombre</th>
                                    <th class="text-center">Código</th>
                                    <th class="text-center">Símbolo</th>
                                    <th class="text-end">Tipo de cambio (vs L.)</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-center">Predeterminada</th>
                                    <th>Actualizado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── Modal Crear / Editar ──────────────────────────────── -->
<div class="modal fade" id="modalDivisa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDivisaTitulo">Nueva divisa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="frmDivisa" novalidate>
                    <input type="hidden" id="d_id">

                    <div class="mb-3">
                        <label for="d_nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="d_nombre"
                               placeholder="Ej: Dólar estadounidense" maxlength="80" required>
                        <div class="invalid-feedback">Ingresa el nombre de la divisa.</div>
                    </div>

                    <div class="row" id="bloque_codigo_simbolo">
                        <div class="col-6 mb-3">
                            <label for="d_codigo" class="form-label">Código ISO <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-uppercase" id="d_codigo"
                                   placeholder="USD" maxlength="10" required>
                            <div class="invalid-feedback">Ingresa el código (USD, HNL…).</div>
                        </div>
                        <div class="col-6 mb-3">
                            <label for="d_simbolo" class="form-label">Símbolo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="d_simbolo"
                                   placeholder="$" maxlength="5" required>
                            <div class="invalid-feedback">Ingresa el símbolo ($, L., €…).</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="d_tipo_cambio" class="form-label">
                            Tipo de cambio <span class="text-danger">*</span>
                            <small class="text-muted ms-1">¿Cuántos Lempiras equivale 1 unidad de esta divisa?</small>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">L.</span>
                            <input type="number" class="form-control" id="d_tipo_cambio"
                                   step="0.0001" min="0.0001" placeholder="25.0000" required>
                            <div class="invalid-feedback">Ingresa un tipo de cambio válido (mayor a 0).</div>
                        </div>
                        <div class="form-text">Para Lempira ingresar 1. Para USD ≈ 25. Para EUR ≈ 27.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary" id="btnGuardarDivisa">
                    <i class="bi bi-save me-1"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .is-invalid ~ .invalid-feedback { display: block !important; }
    .is-invalid { border-color: #dc3545 !important; }
</style>
