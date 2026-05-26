<div class="row mb-3">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="mb-0">
                    <i class="bi bi-journal-medical me-2 text-primary"></i>
                    Manual Satake Evolution RGB
                    <small class="text-muted fw-normal fs-6 ms-2">HonduCafé</small>
                </h5>
                <small class="text-muted">Catálogo de tareas de mantenimiento, alertas y normas de seguridad</small>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
<div class="card-body">

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3" id="satakeTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-tareas-btn"
                    data-bs-toggle="tab" data-bs-target="#tab-tareas"
                    type="button" role="tab">
                <i class="bi bi-list-check me-1"></i>Catálogo de tareas
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-alertas-btn"
                    data-bs-toggle="tab" data-bs-target="#tab-alertas"
                    type="button" role="tab">
                <i class="bi bi-exclamation-triangle me-1"></i>Alertas y señales
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-productos-btn"
                    data-bs-toggle="tab" data-bs-target="#tab-productos"
                    type="button" role="tab">
                <i class="bi bi-droplet me-1"></i>Productos de limpieza
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-seguridad-btn"
                    data-bs-toggle="tab" data-bs-target="#tab-seguridad"
                    type="button" role="tab">
                <i class="bi bi-shield-check me-1"></i>Seguridad
            </button>
        </li>
    </ul>

    <div class="tab-content" id="satakeTabsContent">

        <!-- ══════════════════════════════════════════
             TAB 1 — CATÁLOGO DE TAREAS
        ══════════════════════════════════════════ -->
        <div class="tab-pane fade show active" id="tab-tareas" role="tabpanel">

            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <!-- Filtros de frecuencia — se generan desde JS con datos de BD -->
                <div class="d-flex flex-wrap gap-2" id="filtrosFrecuencia">
                    <button class="btn btn-sm btn-outline-secondary filtro-frec active"
                            data-id-frecuencia="">Todas</button>
                </div>
                <?php if (($_SESSION['nombre_rol'] ?? '') === 'Administrador'): ?>
                <button class="btn btn-sm btn-outline-primary" id="btnNuevaTarea">
                    <i class="bi bi-plus-circle me-1"></i> Nueva tarea
                </button>
                <?php endif; ?>
            </div>

            <table id="tblTareas" class="table table-hover w-100">
                <thead>
                    <tr>
                        <th>Tarea</th>
                        <th style="width:110px" class="text-center">Frecuencia</th>
                        <th style="width:105px" class="text-center col-hide-sm">Categoría</th>
                        <th style="width:80px"  class="text-center col-hide-sm">Técnico</th>
                        <th style="width:75px"  class="text-center col-hide-sm">Estado</th>
                        <th style="width:85px"  class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <!-- ══════════════════════════════════════════
             TAB 2 — ALERTAS Y SEÑALES
        ══════════════════════════════════════════ -->
        <div class="tab-pane fade" id="tab-alertas" role="tabpanel">

            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <p class="text-muted small mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Señales de problema detectadas durante la operación del clasificador.
                </p>
                <?php if (($_SESSION['nombre_rol'] ?? '') === 'Administrador'): ?>
                <button class="btn btn-sm btn-outline-warning" id="btnNuevaAlerta">
                    <i class="bi bi-plus-circle me-1"></i> Nueva alerta
                </button>
                <?php endif; ?>
            </div>

            <div id="acordeonAlertas">
                <div class="text-center py-4">
                    <span class="spinner-border spinner-border-sm me-2"></span> Cargando...
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════
             TAB 3 — PRODUCTOS DE LIMPIEZA (ESTÁTICO)
        ══════════════════════════════════════════ -->
        <div class="tab-pane fade" id="tab-productos" role="tabpanel">
            <div class="alert alert-info py-2 small mb-3">
                <i class="bi bi-info-circle me-1"></i>
                Productos recomendados según el manual oficial Satake USA Inc. © 2012.
                Usar <strong>exclusivamente</strong> los aprobados para cada superficie.
            </div>
            <div class="row g-3">

                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-primary text-white py-2">
                            <i class="bi bi-eye me-1"></i> Vidrio óptico
                            <small class="opacity-75">(ventanas del visor, lentes de cámara)</small>
                        </div>
                        <div class="card-body small">
                            <ul class="mb-2 ps-3">
                                <li>Paños de microfibra — Edmund <strong>NT54-718</strong></li>
                                <li>Toallas especiales de vidrio óptico — Edmund <strong>NT53-984</strong></li>
                                <li>Toallitas prehumedecidas — Edmund <strong>NT53-985</strong></li>
                                <li>Limpiador de vidrios especializado — Edmund <strong>NT54-828</strong></li>
                                <li>Limpiador industrial — Kimball Medio Oeste <strong>80-749</strong></li>
                                <li>Mezcla 50/50 de alcohol + agua destilada</li>
                                <li>Detergente lavavajillas suave + agua</li>
                            </ul>
                            <div class="alert alert-danger py-1 px-2 mb-0 small">
                                <i class="bi bi-x-circle me-1"></i>
                                <strong>PROHIBIDO:</strong> Windex, 409, productos con amoníaco,
                                toallas de papel, manos o dedos, paños sucios.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-info text-white py-2">
                            <i class="bi bi-circle-half me-1"></i> Lentes acrílicas
                            <small class="opacity-75">(Fresnel y LED)</small>
                        </div>
                        <div class="card-body small">
                            <ul class="mb-2 ps-3">
                                <li>Paños de microfibra — Edmund <strong>NT54-718</strong></li>
                                <li>Toallas especiales de vidrio óptico — Edmund <strong>NT53-984</strong></li>
                                <li>Toallitas prehumedecidas — Edmund <strong>NT53-985</strong></li>
                                <li>Novus #1 Plastic Clean &amp; Shine — <em>www.novuspolish.com</em></li>
                                <li>Brillianize limpiador de plástico — <em>www.brillianize.com</em></li>
                                <li>Detergente lavavajillas suave + agua</li>
                            </ul>
                            <div class="alert alert-danger py-1 px-2 mb-0 small">
                                <i class="bi bi-x-circle me-1"></i>
                                <strong>PROHIBIDO:</strong> Paño seco o toalla de papel, alcohol,
                                Windex o productos con amoníaco.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-secondary text-white py-2">
                            <i class="bi bi-wind me-1"></i> Eyectores (interior)
                        </div>
                        <div class="card-body small">
                            <ul class="mb-0 ps-3">
                                <li>Alcohol desnaturalizado</li>
                                <li>Limpiador de contactos eléctricos</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-success text-white py-2">
                            <i class="bi bi-circle me-1"></i> Juntas tóricas
                        </div>
                        <div class="card-body small">
                            <ul class="mb-0 ps-3">
                                <li>Grasa <strong>Dow Corning 44</strong> o equivalente</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-warning text-dark py-2">
                            <i class="bi bi-display me-1"></i> Pantalla táctil
                        </div>
                        <div class="card-body small">
                            <ul class="mb-2 ps-3">
                                <li>Paño limpio no abrasivo con agua jabonosa suave</li>
                                <li>Espray a base de alcohol</li>
                            </ul>
                            <div class="alert alert-warning py-1 px-2 mb-0 small">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Limpiar <strong>solo con la máquina apagada</strong>.
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ══════════════════════════════════════════
             TAB 4 — SEGURIDAD (ESTÁTICO)
        ══════════════════════════════════════════ -->
        <div class="tab-pane fade" id="tab-seguridad" role="tabpanel">
            <div class="alert alert-danger py-2 small mb-3">
                <i class="bi bi-shield-exclamation me-1"></i>
                Normas de seguridad — Satake USA Inc. © 2012. Cumplimiento <strong>obligatorio</strong>.
            </div>
            <div class="list-group list-group-flush">
                <?php
                $normas = [
                    ['danger', 'El mantenimiento del clasificador solo debe ser realizado por <strong>personal calificado</strong>. Los procedimientos técnicos (eyectores, filtros, tarjetas) son exclusivos para personal de servicio.'],
                    ['danger', '<strong>Apagar siempre la máquina</strong> antes de retirar o instalar tarjetas de circuito impreso.'],
                    ['danger', '<strong>Desconectar el voltaje de entrada</strong> antes de trabajar en la unidad de aire acondicionado.'],
                    ['danger', '<strong>Cerrar siempre el suministro de aire y esperar a que se descargue la presión</strong> antes de retirar eyectores.'],
                    ['warning', 'No limpiar la <strong>pantalla táctil</strong> mientras la máquina está en operación.'],
                    ['warning', '<strong>No usar aire comprimido</strong> para limpiar el interior del visor. El polvo puede entrar a la zona óptica y es difícil de eliminar.'],
                    ['warning', 'Tener cuidado al operar los calentadores de canaletas. <strong>Las canaletas pueden calentarse.</strong>'],
                    ['warning', 'Al apretar los eyectores de 5 mm, hacerlo <strong>solo a mano</strong>. No usar herramientas de apriete.'],
                    ['primary', 'Al menos <strong>una vez al año</strong>, un técnico oficial de Satake debe visitar las instalaciones para revisión y calibración profunda.'],
                ];
                foreach ($normas as $i => [$color, $texto]):
                    $textColor = $color === 'warning' ? 'text-dark' : '';
                ?>
                <div class="list-group-item border rounded mb-2 py-3">
                    <div class="d-flex gap-3 align-items-start">
                        <span class="badge bg-<?= $color ?> <?= $textColor ?> rounded-circle fs-6 px-2 py-1 flex-shrink-0">
                            <?= $i + 1 ?>
                        </span>
                        <p class="mb-0 small"><?= $texto ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div><!-- /tab-content -->

</div><!-- /card-body -->
</div><!-- /card -->


<!-- ══════════════════════════════════════════════════════════
     MODAL — VER / EDITAR TAREA
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalTarea" tabindex="-1"
     aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTareaTitulo">
                    <i class="bi bi-list-check me-2"></i>Tarea de Mantenimiento
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body px-4">
                <input type="hidden" id="tarea_id">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            Nombre <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="tarea_nombre" maxlength="300">
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label fw-semibold">
                            Frecuencia <span class="text-danger">*</span>
                        </label>
                        <!-- opciones cargadas desde JS -->
                        <select class="form-select" id="tarea_id_frecuencia">
                            <option value="">— Selecciona —</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label fw-semibold">
                            Categoría <span class="text-danger">*</span>
                        </label>
                        <!-- opciones cargadas desde JS -->
                        <select class="form-select" id="tarea_id_categoria">
                            <option value="">— Selecciona —</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4 d-flex align-items-end pb-1">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="tarea_solo_tecnico">
                            <label class="form-check-label fw-semibold" for="tarea_solo_tecnico">
                                Solo personal de servicio
                            </label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            ¿Por qué se hace? <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="tarea_por_que" rows="3" maxlength="2000"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            ¿Qué pasa si no se hace? <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="tarea_si_no_se_hace" rows="2" maxlength="2000"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            Método / Instrucciones
                            <small class="text-muted fw-normal">(opcional)</small>
                        </label>
                        <textarea class="form-control" id="tarea_metodo" rows="3" maxlength="3000"
                                  placeholder="Pasos, materiales, advertencias..."></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <?php if (($_SESSION['nombre_rol'] ?? '') === 'Administrador'): ?>
                <button class="btn btn-primary  d-none" id="btnGuardarTarea">
                    <i class="bi bi-floppy me-1"></i> Guardar
                </button>
                <button class="btn btn-success  d-none" id="btnActualizarTarea">
                    <i class="bi bi-check-circle me-1"></i> Actualizar
                </button>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     MODAL — VER / EDITAR ALERTA
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalAlerta" tabindex="-1"
     aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">

            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="modalAlertaTitulo">
                    <i class="bi bi-exclamation-triangle me-2"></i>Alerta / Señal de Problema
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body px-4">
                <input type="hidden" id="alerta_id">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            Señal observada <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="alerta_senal"
                               maxlength="300" placeholder="Ej: Eyector sopla continuamente">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            Causa <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="alerta_causa" rows="2" maxlength="2000"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            Acción a tomar <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="alerta_accion" rows="2" maxlength="2000"></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <?php if (($_SESSION['nombre_rol'] ?? '') === 'Administrador'): ?>
                <button class="btn btn-warning text-dark d-none" id="btnGuardarAlerta">
                    <i class="bi bi-floppy me-1"></i> Guardar
                </button>
                <button class="btn btn-success d-none" id="btnActualizarAlerta">
                    <i class="bi bi-check-circle me-1"></i> Actualizar
                </button>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>


<style>
/* ── Filtros ───────────────────────────────────────────── */
.filtro-frec.active      { opacity: 1; font-weight: 600; }
.filtro-frec:not(.active){ opacity: .65; }

/* ── Cards de alerta ───────────────────────────────────── */
.alerta-card {
    border-left: 4px solid #ffc107;
    border-radius: 6px;
    background: #fffdf0;
    margin-bottom: .75rem;
    padding: .75rem 1rem;
}
.alerta-card.inactiva { opacity: .4; }
.alerta-senal { font-weight: 600; font-size: .95rem; color: #856404; }

/* ── Responsive tabla ──────────────────────────────────── */
@media (max-width: 767px) {
    #tblTareas th.col-hide-sm,
    #tblTareas td:nth-child(3),
    #tblTareas td:nth-child(4),
    #tblTareas td:nth-child(5) { display: none; }
}
</style>
