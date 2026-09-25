<div class="row mb-3">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <h5 class="mb-0">Turnos de Operarios</h5>
                        <small class="text-muted">Registro de acceso por PIN en el candado de cada máquina</small>
                    </div>
                    <a class="btn btn-outline-primary" href="./candado.php" target="_blank" rel="noopener">
                        <i class="bi bi-download me-1"></i> Instalador para PCs de planta
                    </a>
                </div>
                <div id="estacionesActivas" class="d-flex flex-wrap gap-2 mt-2">
                    <span class="text-muted small">Cargando estaciones…</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <table id="tblTurnos" class="table table-hover w-100">
            <thead>
                <tr>
                    <th>Estación</th>
                    <th>Operario</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th class="text-center">Duración</th>
                    <th class="text-center">Fallas</th>
                    <th class="text-center">Ajustes</th>
                    <th class="text-center">Cierres</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- ── Modal: qué pasó durante el turno ─────────────────── -->
<div class="modal fade" id="modalEventos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0">Detalle del turno</h5>
                    <small class="text-muted" id="eventosResumen"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:150px">Hora</th>
                                <th style="width:150px">Tipo</th>
                                <th>Descripción</th>
                                <th style="width:260px">Cambio</th>
                            </tr>
                        </thead>
                        <tbody id="eventosCuerpo"></tbody>
                    </table>
                </div>
                <div id="eventosVacio" class="text-center text-muted py-4 d-none">
                    <i class="bi bi-check-circle me-1"></i>
                    No se registró ninguna falla, ajuste ni cierre inesperado durante este turno.
                </div>
                <div class="form-text mt-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Se toma de las bitácoras y la configuración de Delvis en la PC de la máquina.
                    Si la PC estuvo sin conexión, los eventos aparecen cuando vuelve a sincronizar.
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
