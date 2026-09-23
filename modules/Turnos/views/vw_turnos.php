<div class="row mb-3">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <h5 class="mb-0">Turnos de Operarios</h5>
                        <small class="text-muted">Registro de acceso por PIN en el candado de cada máquina</small>
                    </div>
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
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
