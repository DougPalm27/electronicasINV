<div class="col-lg-12">
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Inventario General de Equipo</h5>
            <br>
            <div class="btn-group" role="group" aria-label="Basic mixed styles example">
                <a data-bs-toggle="modal" data-bs-target="#nuevoEquipo" id="btnNuevoEquipo" name="btnNuevoEquipo" type="button" class="btn btn-primary"><i class="fas fa-save "></i>Nuevo Kit</a>
                <a data-bs-toggle="modal" data-bs-target="#asignarEquipoModal" id="btnAsignarEquipo" name="btnAsignarEquipo" type="button" class="btn btn-success"><i class="bi bi-chevron-bar-right"></i>Asignar Kit</a>
                <a id="btnPrint" name="btnPrint" type="button" class="btn btn-warning"><i class="fas fa-print"></i>Imprimir Reporte</a>
            </div>
            <br>
            <hr>

            <!-- Default Tabs -->
            <ul class="nav nav-tabs d-flex" id="myTabjustified" role="tablist">
                <li class="nav-item flex-fill" role="presentation">
                    <button class="nav-link w-100 active" id="equipoAsignado" data-bs-toggle="tab" data-bs-target="#ss" type="button" role="tab" aria-controls="home" aria-selected="true">Equipo Asignado</button>
                </li>
                <li class="nav-item flex-fill" role="presentation">
                    <button class="nav-link w-100" id="equipoDisponible" data-bs-toggle="tab" data-bs-target="#disponible" type="button" role="tab" aria-controls="profile" aria-selected="false">Equipo disponible</button>
                </li>
                <li class="nav-item flex-fill" role="presentation">
                    <button class="nav-link w-100" id="original" data-bs-toggle="tab" data-bs-target="#dtodo" type="button" role="tab" aria-controls="contact" aria-selected="false">Todo el equipo</button>
                </li>
                <li class="nav-item flex-fill" role="presentation">
                    <button class="nav-link w-100" id="original" data-bs-toggle="tab" data-bs-target="#asignarKitCompleto" type="button" role="tab" aria-controls="contact" aria-selected="false">Kit</button>
                </li>
            </ul>

            <div class="tab-content pt-2" id="myTabjustifiedContent">
                <div class="tab-pane fade show active" id="ss" role="tabpanel" aria-labelledby="home-tab">
                    <div class="col-md-12 table-responsive">
                        <table class="table align-items-center table-flush table-striped" id="TablaKit1" name="TablaAsignaciones">
                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col">Codigo SAP</th>
                                    <th scope="col">Asignado a</th>
                                    <th scope="col">Kit</th>
                                    <th scope="col">Proyecto</th>
                                    <th scope="col">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade" id="disponible" role="tabpanel" aria-labelledby="profile-tab">
                    <div class="col-md-12 table-responsive">
                        <table class="table align-items-center table-flush table-striped" id="TablaKit2" name="TablaDisponibles2" width="100%">
                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col">Codigo SAP</th>
                                    <th scope="col">Kit</th>
                                    <th scope="col">Proyecto</th>
                                    <th scope="col">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade" id="dtodo" role="tabpanel" aria-labelledby="profile-tab">
                    <div class="col-md-12 table-responsive">
                        <table class="table align-items-center table-flush table-striped" id="TablaKit3" name="todo" width="100%">
                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col">Codigo SAP</th>
                                    <th scope="col">Kit</th>
                                    <th scope="col">precio</th>
                                    <th scope="col">Proyecto</th>
                                    <th scope="col">estado</th>
                                    <th scope="col">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade" id="asignarKitCompleto" role="tabpanel" aria-labelledby="profile-tab">
                    <div class="col-md-12 table-responsive">
                        <!-- Floating Labels Form -->
                        <form id="modalLineas" name="modalEquiposAsignacion" class="row g-3 p-4">
                            <div class="col-md-5">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="usuario3" name="usuario3" aria-label="State">
                                        <option selected value="-1">Selecciona un usuario</option>
                                    </select>
                                    <label for="floatingSelect">Usuario a asignar</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="cempleado1" name="cempleado1" placeholder="City" disabled>
                                    <label for="cempleado">Codigo de Empleado</label>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="kitID2" name="kitID2" aria-label="State">
                                        <option selected value="-1">Selecciona un Elemento</option>
                                    </select>
                                    <label for="floatingSelect">Kit a asignar</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="proyectoID2" name="proyectoID2" placeholder="City" disabled>
                                    <label for="proyectoID">Pertenece al proyecto</label>
                                </div>
                            </div>
                            <div class="col-md-3 py-2">
                                <button type="button" class="btn btn-success">
                                    <div class="col-md text-end" style="font-size: 13px">
                                        <i class="bi bi-alt"></i>
                                    </div>
                                </button>
                            </div>

                            <style>
                                .col-codigo-sap {
                                    width: 10%;
                                }

                                .col-kit {
                                    width: 40%;
                                }

                                .col-precio {
                                    width: 10%;
                                }

                                .col-proyecto {
                                    width: 10%;
                                }

                                .col-estado {
                                    width: 20%;
                                }
                            </style>

                            <table class="table table-bordered" id="TablaKit3" name="todo" width="100%">
                                <thead class="thead-dark">
                                    <tr>
                                        <th scope="col" class="col-codigo-sap">Codigo SAP</th>
                                        <th scope="col" class="col-kit">Kit</th>
                                        <th scope="col" class="col-precio">Precio</th>
                                        <th scope="col" class="col-proyecto">Proyecto</th>
                                        <th scope="col" class="col-estado">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                            <hr>
                            <div class="col-md-5">
                                <div class="form-floating">
                                    <input type="date" class="form-control" id="fechaAsignacionKit2" name="fechaAsignacionKit2" placeholder="City">
                                    <label for="floatingCity">Fecha de asignación</label>
                                </div>
                            </div>


                            <div class="form-floating col-md-9 ">
                                <label for="exampleFormControlTextarea1" class="form-label"></label>
                                <textarea class="form-control" id="observaciones" placeholder="City" style="height: 100px"></textarea>
                                <label for="floatingCity">Observaciones</label>
                            </div>

                        </form><!-- End floating Labels Form -->
                    </div>
                </div>
            </div><!-- End Default Tabs -->
        </div>
    </div>
</div>
</div>

<!-- Vertically centered Modal -->
<div class="modal fade" id="asignarEquipoModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Asignar Kit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="card">
                <div class="card-body">
                    <!-- Floating Labels Form -->
                    <form id="modalLineas" name="modalEquiposAsignacion" class="row g-3 p-4">
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="cempleado" name="cempleado" placeholder="City" disabled>
                                <label for="cempleado">Codigo de Empleado</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <select class="form-select" id="usuario2" name="usuario2" aria-label="State">
                                    <option selected value="-1">Selecciona un usuario</option>
                                </select>
                                <label for="floatingSelect">Usuario a asignar</label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-floating mb-3">
                                <select class="form-select" id="kitID" name="kitID" aria-label="State">
                                    <option selected value="-1">Selecciona un Elemento</option>
                                </select>
                                <label for="floatingSelect">Kit a asignar</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="proyectoID" name="proyectoID" placeholder="City" disabled>
                                <label for="proyectoID">Pertenece al proyecto</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="date" class="form-control" id="fechaAsignacionKit" name="fechaAsignacionKit" placeholder="City">
                                <label for="floatingCity">Fecha de asignación</label>
                            </div>
                        </div>


                        <div class="form-floating">
                            <label for="exampleFormControlTextarea1" class="form-label"></label>
                            <textarea class="form-control" id="observaciones" placeholder="City"></textarea>
                            <label for="floatingCity">Observaciones</label>
                        </div>

                    </form><!-- End floating Labels Form -->
                </div>
            </div>
            <div class="modal-footer">
                <a type="submit" class="btn btn-primary" id="btnAsgKit"><i class="bi bi-cloud-check-fill"></i> Guardar</a>
                <a type="reset" class="btn btn-secondary" id="btnLimpiarModalKit"><i class="bi bi-cloud-fog2"></i> Limpiar</a>
            </div>
        </div>
    </div>
</div><!-- End Extra Large Modal-->

<div class="modal fade" id="nuevoEquipo" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Crear registro de nuevo kit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="card">
                <div class="card-body">
                    <!-- Floating Labels Form -->
                    <form id="modalLineas" name="modalEquiposAsignacion" class="row g-3 p-4">
                        <div class="form-floating">
                            <label for="exampleFormControlTextarea1" class="form-label"></label>
                            <textarea class="form-control" id="descripcion" placeholder="City"></textarea>
                            <label for="floatingCity">Descripcion general</label>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="number" class="form-control" id="precio" name="precio" placeholder="City">
                                <label for="floatingCity">Precio:</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating floating-select2 mb-3">
                                <select class="form-select Select2Modal1 " id="proyecto" name="proyecto" php-email-form aria-label="State">
                                    <option selected>Selecciona un Proyecto</option>
                                </select>
                                <label for="floatingSelect">Proyecto</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="sap" name="sap" placeholder="City">
                                <label for="floatingCity">Codigo SAP:</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="date" class="form-control" id="fecha" name="fecha" placeholder="City">
                                <label for="floatingCity">Fecha de compra:</label>
                            </div>
                        </div>
                    </form><!-- End floating Labels Form -->
                </div>
            </div>
            <div class="modal-footer">
                <a type="submit" class="btn btn-primary" id="btnKit"><i class="bi bi-cloud-check-fill"></i> Guardar</a>
                <a type="reset" class="btn btn-secondary" id="btnLimpiarModalKit"><i class="bi bi-cloud-fog2"></i> Limpiar</a>
            </div>
        </div>
    </div>
</div><!-- End Extra Large Modal-->