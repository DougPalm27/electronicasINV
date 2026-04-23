<div class="col-lg-12">
    <div class="card">
        <div class="card-body">

            <h5 class="card-title">Inventario de Repuestos</h5>

            <div class="d-flex justify-content-between mb-3">
                <button id="btnNuevoRepuesto" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuevo Repuesto
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-striped" id="tablaRepuestos">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>N° Parte</th>
                            <th>Proveedor</th>
                            <th>Stock</th>
                            <th>Costo</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<!-- ============================= -->
<!-- MODAL REPUESTO -->
<!-- ============================= -->

<div class="modal fade" id="modalRepuesto">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header">
                <h5 class="modal-title">Gestión de Repuesto</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body">
                <form id="formRepuesto">

                    <input type="hidden" id="id_repuesto">

                    <!-- 🔹 INFORMACIÓN GENERAL -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre del repuesto</label>
                            <input type="text" class="form-control" id="nombre" placeholder="Ej: Fuente de poder">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Número de parte</label>
                            <input type="text" class="form-control" id="numero_parte" placeholder="Ej: PSU-450W">
                        </div>
                    </div>

                    <!-- 🔹 CLASIFICACIÓN -->
                    <div class="border rounded p-3 mb-3">
                        <h6 class="text-muted mb-3">Clasificación</h6>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Tipo</label>
                                <select class="form-select select2" id="id_tipo"></select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Marca</label>
                                <select class="form-select select2" id="id_marca"></select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Modelo</label>
                                <select class="form-select select2" id="id_modelo"></select>
                            </div>
                        </div>
                    </div>

                    <!-- 🔹 CONFIGURACIÓN -->
                    <div class="border rounded p-3 mb-3">
                        <h6 class="text-muted mb-3">Configuración</h6>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Proveedor</label>
                                <select class="form-select select2" id="id_proveedor"></select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tipo de control</label>
                                <select class="form-select" id="maneja_serie">
                                    <option value="0">Por cantidad</option>
                                    <option value="1">Por serie</option>
                                </select>
                            </div>
                        </div>

                        <!-- 🔹 BLOQUE STOCK -->
                        <div class="row" id="bloqueStock">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stock mínimo</label>
                                <input type="number" class="form-control" id="stock_minimo" placeholder="Ej: 5">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Costo promedio</label>
                                <input type="number" step="0.01" class="form-control" id="costo" placeholder="0.00">
                            </div>
                        </div>

                        <!-- 🔹 BLOQUE SERIE -->
                        <div id="bloqueSerie" style="display:none;">
                            <div class="alert alert-info mb-0">
                                <i class="bi bi-info-circle"></i>
                                Este repuesto se manejará por <strong>número de serie individual</strong>.
                                El control de inventario será por unidades únicas.
                            </div>
                        </div>

                    </div>

                    <!-- 🔹 COMENTARIOS -->
                    <div class="mb-3">
                        <label class="form-label">Comentarios</label>
                        <textarea class="form-control" id="comentarios" rows="2"></textarea>
                    </div>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer">

                <button class="btn btn-primary" id="btnGuardarRepuesto">
                    <i class="bi bi-save"></i> Guardar
                </button>

                <button class="btn btn-success d-none" id="btnEditarRepuesto">
                    <i class="bi bi-pencil"></i> Actualizar
                </button>

                <button class="btn btn-secondary" data-bs-dismiss="modal">
                    Cancelar
                </button>

            </div>

        </div>
    </div>
</div>
<!-- ============================= -->
<!-- MODAL ENTRADA -->
<!-- ============================= -->


<div class="modal fade" id="modalEntrada">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5>Entrada de Inventario</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <input type="hidden" id="id_repuesto_mov">
                <input type="hidden" id="maneja_serie_mov">

                <!-- 🔹 MODO STOCK -->
                <div id="entradaStock">

                    <div class="mb-3">
                        <label>Cantidad</label>
                        <input type="number" id="cantidad_mov" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label>Costo unitario</label>
                        <input type="number" id="costo_mov" class="form-control">
                    </div>

                </div>

                <!-- 🔹 MODO SERIE -->
                <div id="entradaSerie" style="display:none;">

                    <label>Series (una por línea)</label>
                    <textarea id="series_input" class="form-control" rows="5"
                        placeholder="SERIE001\nSERIE002\nSERIE003"></textarea>

                    <div class="mt-2 text-muted small">
                        Cada línea representa un equipo individual
                    </div>

                </div>

            </div>

            <div class="modal-footer">
                <button class="btn btn-success" onclick="guardarEntrada()">Guardar</button>
            </div>

        </div>
    </div>
</div>

<!-- ============================= -->
<!-- MODAL SALIDA -->
<!-- ============================= -->

<div class="modal fade" id="modalSalida">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Salida de Repuesto</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <input type="hidden" id="id_repuesto_salida">
        <input type="hidden" id="maneja_serie_salida">

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">ID Máquina destino</label>
            <input type="number" class="form-control" id="id_maquina_salida" placeholder="Ej: 3">
          </div>

          <div class="col-md-6">
            <label class="form-label">Referencia</label>
            <input type="text" class="form-control" id="referencia_salida" value="MANTENIMIENTO">
          </div>
        </div>

        <div id="bloqueSalidaCantidad">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Cantidad</label>
              <input type="number" class="form-control" id="cantidad_salida" min="1">
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Costo unitario</label>
              <input type="number" class="form-control" id="costo_salida" step="0.01" min="0">
            </div>
          </div>
        </div>

        <div id="bloqueSalidaSerie" style="display:none;">
          <label class="form-label">Series disponibles</label>
          <select id="series_salida" class="form-select select2" multiple></select>
          <small class="text-muted">Selecciona una o varias series disponibles.</small>
        </div>

      </div>

      <div class="modal-footer">
        <button class="btn btn-primary" onclick="guardarSalida()">Registrar salida</button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      </div>

    </div>
  </div>
</div>


<!-- ============================= -->
<!-- MODAL KARDEX -->
<!-- ============================= -->

<div class="modal fade" id="modalKardex">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5>Kardex del Repuesto</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <table class="table table-sm" id="tablaKardex">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Cantidad</th>
                            <th>Stock Antes</th>
                            <th>Stock Después</th>
                            <th>Costo</th>
                            <th>Referencia</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<!-- ============================= -->
<!-- MODAL DETALLE (SERIE) -->
<!-- ============================= -->

<div class="modal fade" id="modalDetalle">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5>Detalle por Serie</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <table class="table" id="tablaDetalle">
                    <thead>
                        <tr>
                            <th>Serie</th>
                            <th>Estado</th>
                            <th>Máquina</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<!-- ============================= -->
<!-- MODAL EDITAR DETALLE -->
<!-- ============================= -->

<div class="modal fade" id="modalEditarDetalle">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5>Editar Detalle</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <input type="hidden" id="id_detalle_repuesto">

                <div class="mb-3">
                    <label>Serie</label>
                    <input type="text" id="serie_detalle" class="form-control">
                </div>

                <div class="mb-3">
                    <label>Estado</label>
                    <select id="estado_detalle" class="form-control">
                        <option value="1">Disponible</option>
                        <option value="2">Instalado</option>
                        <option value="3">Dañado</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label>ID Máquina</label>
                    <input type="number" id="maquina_detalle" class="form-control">
                </div>

            </div>

            <div class="modal-footer">
                <button class="btn btn-success" onclick="guardarDetalle()">Guardar</button>
            </div>

        </div>
    </div>
</div>

<style>
    /* Select2 alineado con Bootstrap */
    .select2-container--bootstrap-5 .select2-selection {
        height: 38px;
        display: flex;
        align-items: center;
        padding-left: 10px;
    }

    .select2-container {
        width: 100% !important;
    }

    /* Secciones más limpias */
    .border h6 {
        font-weight: 600;
    }
</style>