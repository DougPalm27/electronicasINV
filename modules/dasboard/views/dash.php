<section class="section dashboard">
    <div class="row">

        <!-- Left side columns -->
        <div class="col-lg-12">
            <div class="row">

                <!-- lineas móviles -->
                <div class="col-xxl-4 col-md-4">
                    <div class="card info-card sales-card">

                        <div class="filter">
                            <a class="icon" href="#" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></a>
                            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                                <li class="dropdown-header text-start">
                                    <h6>Filter</h6>
                                </li>

                                <li><a class="dropdown-item" href="#">Today</a></li>
                                <li><a class="dropdown-item" href="#">This Month</a></li>
                                <li><a class="dropdown-item" href="#">This Year</a></li>
                            </ul>
                        </div>

                        <div class="card-body">
                            <h5 class="card-title">Cantidad de Repuestos</h5>

                            <div class="d-flex align-items-center">
                                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="bi bi-phone"></i>
                                </div>
                                <div class="ps-3">
                                    <h6 id="cantidadLinea">145</h6>
                                    <!-- <span class="text-success small pt-1 fw-bold">12%</span> <span class="text-muted small pt-2 ps-1">increase</span> -->

                                </div>
                            </div>
                        </div>

                    </div>
                </div>
                <!-- End lineas móviles -->

                <!-- equipos IT -->
                <div class="col-xxl-4 col-md-4">
                    <div class="card info-card revenue-card">

                        <div class="filter">
                            <a class="icon" href="#" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></a>
                            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                                <li class="dropdown-header text-start">
                                    <h6>Filter</h6>
                                </li>

                                <li><a class="dropdown-item" href="#">Today</a></li>
                                <li><a class="dropdown-item" href="#">This Month</a></li>
                                <li><a class="dropdown-item" href="#">This Year</a></li>
                            </ul>
                        </div>

                        <div class="card-body">
                            <h5 class="card-title">Cantidad de Maquinas </h5>

                            <div class="d-flex align-items-center">
                                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="bi bi-pc"></i>
                                </div>
                                <div class="ps-3">
                                    <h6 id="cantidadEquipo">243</h6>
                                    <!-- <span class="text-success small pt-1 fw-bold">8%</span> <span class="text-muted small pt-2 ps-1">increase</span> -->

                                </div>
                            </div>
                        </div>

                    </div>
                </div>
                <!-- End equipos IT -->

                <!-- Kit de agroinumos -->
                <div class="col-xxl-4 col-md-4">

                    <div class="card info-card customers-card">

                        <div class="filter">
                            <a class="icon" href="#" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></a>
                            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                                <li class="dropdown-header text-start">
                                    <h6>Filter</h6>
                                </li>

                                <li><a class="dropdown-item" href="#">Today</a></li>
                                <li><a class="dropdown-item" href="#">This Month</a></li>
                                <li><a class="dropdown-item" href="#">This Year</a></li>
                            </ul>
                        </div>

                        <div class="card-body">
                            <h5 class="card-title">Cantidad de Mantenimientos</h5>

                            <div class="d-flex align-items-center">
                                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="bi bi-people"></i>
                                </div>
                                <div class="ps-3">
                                    <h6 id="cantidadKit">150</h6>
                                    <!-- <span class="text-danger small pt-1 fw-bold">12%</span> <span class="text-muted small pt-2 ps-1">decrease</span> -->

                                </div>
                            </div>

                        </div>
                    </div>

                </div>
                <!-- End Kit de agroinumos -->



                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Reporte de mantenimientos</h5>

                            <!-- Bar Chart -->
                            <div class="col-md-12">
                                <div class="form-floating floating-select2">
                                    <select class="form-select" id="project" name="project" aria-label="State">
                                        <option selected value="-1">Selecciona una planta</option>
                                    </select>
                                    <label for="floatingSelect">Planta:</label>
                                </div>
                                <div class="d-grid gap-2 mt-3">
                                <button id="btnReport" type="button" class="btn btn-primary" disabled>Descargar reporte</button>
                                </div>
                            </div>
                            <!-- End Bar Chart -->

                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Maquinas por planta</h5>

                            <!-- Bar Chart -->
                            <div id="barChart"></div>

                            <script>

                            </script>
                            <!-- End Bar Chart -->

                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Repuestos usados</h5>

                            <!-- Donut Chart -->
                            <div id="donutChart"></div>

                            <script>

                            </script>
                            <!-- End Donut Chart -->

                        </div>
                    </div>
                </div>



            </div>
        </div><!-- End Left side columns -->



    </div>
</section>