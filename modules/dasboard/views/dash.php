<section class="section dashboard">
    <div class="row g-3">

        <!-- ── KPI: Repuestos ────────────────────────────── -->
        <div class="col-xxl-3 col-md-6">
            <div class="card info-card sales-card h-100">
                <div class="card-body">
                    <h5 class="card-title">Repuestos activos</h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <div class="ps-3">
                            <h6 id="kpi_repuestos"><span class="spinner-border spinner-border-sm"></span></h6>
                            <span class="text-muted small">en inventario</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── KPI: Máquinas ─────────────────────────────── -->
        <div class="col-xxl-3 col-md-6">
            <div class="card info-card revenue-card h-100">
                <div class="card-body">
                    <h5 class="card-title">Máquinas activas</h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-cpu"></i>
                        </div>
                        <div class="ps-3">
                            <h6 id="kpi_maquinas"><span class="spinner-border spinner-border-sm"></span></h6>
                            <span class="text-muted small">en operación</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── KPI: Mantenimientos del mes ───────────────── -->
        <div class="col-xxl-3 col-md-6">
            <div class="card info-card customers-card h-100">
                <div class="card-body">
                    <h5 class="card-title">Mantenimientos</h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-tools"></i>
                        </div>
                        <div class="ps-3">
                            <h6 id="kpi_mantenimientos"><span class="spinner-border spinner-border-sm"></span></h6>
                            <span class="text-muted small">este mes</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── KPI: Stock bajo ───────────────────────────── -->
        <div class="col-xxl-3 col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Stock bajo</h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center"
                             style="background:#fff3cd">
                            <i class="bi bi-exclamation-triangle" style="color:#f59e0b"></i>
                        </div>
                        <div class="ps-3">
                            <h6 id="kpi_stock_bajo"><span class="spinner-border spinner-border-sm"></span></h6>
                            <span class="text-muted small">repuestos bajo mínimo</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Gráfico: Mantenimientos por mes ───────────── -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Mantenimientos — últimos 6 meses</h5>
                    <div id="chartMantenimientos"></div>
                </div>
            </div>
        </div>

        <!-- ── Gráfico: Repuestos por tipo ───────────────── -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Repuestos por tipo</h5>
                    <div id="chartTipos"></div>
                </div>
            </div>
        </div>

        <!-- ── Tabla: Últimos mantenimientos ─────────────── -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Últimos mantenimientos</h5>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Máquina</th>
                                    <th>Tipo</th>
                                    <th>Técnico</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyUltimos">
                                <tr><td colspan="4" class="text-center">
                                    <span class="spinner-border spinner-border-sm"></span>
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Tabla: Repuestos stock bajo ───────────────── -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
                        Repuestos con stock bajo
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Repuesto</th>
                                    <th class="text-center">Stock</th>
                                    <th class="text-center">Mínimo</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyStockBajo">
                                <tr><td colspan="3" class="text-center">
                                    <span class="spinner-border spinner-border-sm"></span>
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>
