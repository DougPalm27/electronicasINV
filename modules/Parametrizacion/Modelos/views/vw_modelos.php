<div class="row mb-3">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">Modelos</h5>
                    <small class="text-muted">Catálogo de modelos por marca</small>
                </div>
                <button class="btn btn-primary" id="btnNuevoModelo">
                    <i class="bi bi-plus-circle"></i> Nuevo Modelo
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <table class="table table-hover w-100" id="tablaModelos">
            <thead>
                <tr>
                    <th style="width:70px">Imagen</th>
                    <th>Nombre</th>
                    <th>Marca</th>
                    <th>Tipo</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- MODAL MODELO -->
<div class="modal fade" id="modalModelo">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="tituloModalModelo">Nuevo Modelo</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form id="formModelo" novalidate>
                    <input type="hidden" id="id_modelo">

                    <div class="mb-3">
                        <label for="nombre_modelo" class="form-label">
                            Nombre <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="nombre_modelo" class="form-control"
                               placeholder="Ej: Galaxy A54" maxlength="100" autocomplete="off">
                    </div>

                    <div class="mb-3">
                        <label for="id_marca_modelo" class="form-label">
                            Marca <span class="text-danger">*</span>
                        </label>
                        <select id="id_marca_modelo" class="form-select">
                            <option value="">-- Seleccione --</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="id_tipo_modelo" class="form-label">Tipo</label>
                        <select id="id_tipo_modelo" class="form-select">
                            <option value="">-- Seleccione --</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="imagen_modelo" class="form-label">Imagen (opcional)</label>
                        <input type="file" id="imagen_modelo" class="form-control"
                               accept="image/jpeg, image/png, image/webp">
                        <div class="form-text">JPG, PNG o WebP, máx. 2 MB. Se muestra en la pantalla de Máquinas.</div>
                    </div>

                    <div class="mb-2 d-none" id="previewImagenWrap">
                        <div class="d-flex align-items-start gap-2">
                            <img id="previewImagen" src="" alt="Imagen del modelo"
                                 style="max-height:110px;max-width:100%;border-radius:8px;border:1px solid #e4e7ec">
                            <button type="button" class="btn btn-sm btn-outline-danger" id="btnQuitarImagen">
                                <i class="bi bi-trash me-1"></i>Quitar
                            </button>
                        </div>
                    </div>

                </form>
            </div>

            <div class="modal-footer">
                <button class="btn btn-primary" id="btnGuardarModelo">
                    <i class="bi bi-save"></i> Guardar
                </button>
                <button class="btn btn-success d-none" id="btnActualizarModelo">
                    <i class="bi bi-pencil-square"></i> Actualizar
                </button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            </div>

        </div>
    </div>
</div>
