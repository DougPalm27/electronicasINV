<?php $esAdmin = ($_SESSION['nombre_rol'] ?? '') === 'Administrador'; ?>

<!-- ══ Encabezado ═══════════════════════════════════════════ -->
<div class="row mb-3">
  <div class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
          <h5 class="mb-0"><i class="bi bi-calendar-week me-2 text-primary"></i>Horarios de Monitoreo</h5>
          <small class="text-muted">Gestión de turnos del área de monitoreo</small>
        </div>
        <?php if ($esAdmin): ?>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary btn-sm" id="btnVerHistorial" title="Historial">
            <i class="bi bi-clock-history me-1"></i>Historial
          </button>
          <button class="btn btn-primary" id="btnNuevoMes">
            <i class="bi bi-calendar-plus me-1"></i>Proponer mes
          </button>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ══ Contenido con pestañas ════════════════════════════════ -->
<div class="card shadow-sm border-0">
  <div class="card-body px-2 px-md-3">

    <!-- Navegación de pestañas -->
    <ul class="nav nav-tabs mb-3" id="tabsHorario" role="tablist">
      <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-horario" type="button">
          <i class="bi bi-grid-3x3 me-1"></i>Horario
        </button>
      </li>
      <?php if ($esAdmin): ?>
      <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-excepciones" type="button" id="tabBtnExcepciones">
          <i class="bi bi-exclamation-triangle me-1"></i>Excepciones
          <span class="badge bg-danger ms-1 d-none" id="badgeExcepciones">0</span>
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-config" type="button">
          <i class="bi bi-gear me-1"></i>Configuración
        </button>
      </li>
      <?php endif; ?>
    </ul>

    <!-- ═══ Pestaña: Horario ════════════════════════════════ -->
    <div class="tab-content">
      <div class="tab-pane fade show active" id="tab-horario">

        <!-- Navegación de mes -->
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div class="d-flex align-items-center gap-2">
            <button class="btn btn-outline-secondary btn-sm" id="btnMesPrev">
              <i class="bi bi-chevron-left"></i>
            </button>
            <h6 class="mb-0 fw-bold" id="cal-mes-label">Cargando...</h6>
            <button class="btn btn-outline-secondary btn-sm" id="btnMesNext">
              <i class="bi bi-chevron-right"></i>
            </button>
          </div>
          <div class="d-flex align-items-center gap-2">
            <!-- Toggle de vista -->
            <div class="btn-group btn-group-sm" role="group">
              <button class="btn btn-primary active" id="btnVistaTabla" data-vista="tabla" title="Vista por técnico">
                <i class="bi bi-table"></i>
              </button>
              <button class="btn btn-outline-primary" id="btnVistaSemanal" data-vista="semanal" title="Vista semanal">
                <i class="bi bi-calendar-week"></i>
              </button>
            </div>
            <div id="cal-estado-badge"></div>
            <?php if ($esAdmin): ?>
            <button class="btn btn-outline-danger btn-sm" id="btnNuevaIncapacidad" title="Registrar incapacidad">
              <i class="bi bi-bandaid me-1"></i>Incapacidad
            </button>
            <?php endif; ?>
          </div>
        </div>

        <!-- Leyenda de turnos -->
        <div class="d-flex flex-wrap gap-2 mb-3" id="cal-leyenda">
          <span class="badge bg-primary">8am - 5pm</span>
          <span class="badge bg-success">5am - 2pm</span>
          <span class="badge bg-warning text-dark">2pm - 10pm</span>
          <span class="badge bg-dark">10pm - 5am</span>
          <span class="badge bg-secondary">Descanso</span>
          <span class="badge bg-danger">Incapacitado</span>
          <span class="badge" style="background:#dc354522;color:#dc3545;border:1px solid #dc3545">
            <i class="bi bi-star-fill me-1" style="color:#ffc107"></i>Feriado
          </span>
        </div>

        <!-- Spinner -->
        <div id="cal-loading" class="text-center py-5">
          <span class="spinner-border text-primary"></span>
        </div>

        <!-- Sin horario -->
        <div id="cal-empty" class="text-center py-5 d-none">
          <i class="bi bi-calendar-x text-muted" style="font-size:3rem;opacity:.3"></i>
          <p class="mt-2 text-muted mb-0">No hay horario registrado para <strong id="cal-empty-mes"></strong>.</p>
          <?php if ($esAdmin): ?>
          <p class="text-muted small">Usa <strong>Proponer mes</strong> para crear uno.</p>
          <?php endif; ?>
        </div>

        <!-- Alertas de cobertura -->
        <div id="cal-alertas" class="mb-2"></div>

        <!-- Tabla del calendario -->
        <div id="cal-content" class="d-none">
          <div class="table-responsive-xxl">
            <table class="table table-hover w-100 table-calendar" id="tablaCalendario">
              <thead></thead>
              <tbody></tbody>
            </table>
          </div>
        </div>

        <!-- Vista semanal del calendario -->
        <div id="cal-semanal" class="d-none"></div>

      </div><!-- /tab-horario -->

      <?php if ($esAdmin): ?>
      <!-- ═══ Pestaña: Excepciones ════════════════════════════ -->
      <div class="tab-pane fade" id="tab-excepciones">

        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="mb-0 text-muted" id="exc-mes-titulo">Excepciones del mes activo</h6>
          <button class="btn btn-sm btn-outline-primary" id="btnAgregarExcepcion">
            <i class="bi bi-plus-lg me-1"></i>Registrar excepción manual
          </button>
        </div>

        <div id="exc-loading" class="text-center py-4 d-none">
          <span class="spinner-border spinner-border-sm text-primary"></span>
        </div>

        <div id="exc-empty" class="text-center py-4 text-muted d-none">
          <i class="bi bi-check-circle text-success" style="font-size:2rem"></i>
          <p class="mt-2 mb-0">Sin excepciones pendientes este mes.</p>
        </div>

        <div id="exc-lista"></div>

      </div><!-- /tab-excepciones -->

      <!-- ═══ Pestaña: Configuración ═══════════════════════════ -->
      <div class="tab-pane fade" id="tab-config">

        <!-- Técnicos de monitoreo -->
        <div class="mb-4">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0"><i class="bi bi-people me-1 text-primary"></i>Técnicos de Monitoreo</h6>
            <button class="btn btn-sm btn-primary" id="btnAgregarTecnico">
              <i class="bi bi-person-plus me-1"></i>Agregar
            </button>
          </div>
          <div id="cfg-tecnicos-loading" class="text-center py-3">
            <span class="spinner-border spinner-border-sm"></span>
          </div>
          <table class="table table-hover w-100 d-none" id="tablaTecnicos">
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Usuario</th>
                <th>Desde</th>
                <th class="text-center">Estado</th>
                <th class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>

        <hr>

        <!-- Feriados nacionales -->
        <div>
          <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <h6 class="mb-0"><i class="bi bi-calendar-event me-1 text-primary"></i>Feriados Nacionales</h6>
            <div class="d-flex gap-2 align-items-center">
              <select class="form-select form-select-sm" id="selAnioFeriados" style="width:auto"></select>
              <button class="btn btn-sm btn-primary" id="btnAgregarFeriado">
                <i class="bi bi-plus-lg me-1"></i>Agregar
              </button>
            </div>
          </div>
          <div id="cfg-feriados-loading" class="text-center py-3">
            <span class="spinner-border spinner-border-sm"></span>
          </div>
          <table class="table table-hover w-100 d-none" id="tablaFeriados">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Nombre</th>
                <th>Tipo</th>
                <th class="text-center">Activo</th>
                <th class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>

      </div><!-- /tab-config -->
      <?php endif; ?>

    </div><!-- /tab-content -->
  </div>
</div>


<!-- ══ MODAL: Proponer / Editar Horario ══════════════════════ -->
<div class="modal fade" id="modalGestion" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
    <div class="modal-content">

      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>
          Propuesta de horario — <span id="gestion-mes-titulo"></span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div id="gestion-loading" class="text-center py-4">
          <span class="spinner-border text-primary"></span>
          <p class="mt-2 text-muted">Calculando rotación...</p>
        </div>

        <div id="gestion-content" class="d-none">
          <p class="text-muted small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Revisa la rotación propuesta. Puedes cambiar el turno de cualquier técnico antes de guardar.
            Los turnos se rotan en orden: 8am-5pm → 5am-2pm → 2pm-10pm → 10pm-5am → 8am-5pm.
          </p>

          <table class="table table-hover w-100">
            <thead>
              <tr>
                <th>Técnico</th>
                <th>Turno actual</th>
                <th></th>
                <th>Turno propuesto</th>
                <th class="text-center">Meses en turno</th>
              </tr>
            </thead>
            <tbody id="tbodyGestion"></tbody>
          </table>

          <div class="mb-3 mt-2">
            <label class="form-label fw-semibold small">Notas del horario (opcional)</label>
            <textarea class="form-control form-control-sm" id="gestion-notas" rows="2" maxlength="500"
                      placeholder="Observaciones del mes..."></textarea>
          </div>
        </div>
      </div>

      <div class="modal-footer flex-wrap gap-2">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-outline-primary d-none" id="btnGuardarBorrador">
          <i class="bi bi-floppy me-1"></i>Guardar borrador
        </button>
        <button class="btn btn-success d-none" id="btnActivarHorario">
          <i class="bi bi-check-circle me-1"></i>Guardar y activar
        </button>
      </div>

    </div>
  </div>
</div>


<!-- ══ MODAL: Resolver Excepción ════════════════════════════ -->
<div class="modal fade" id="modalExcepcion" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
    <div class="modal-content">

      <div class="modal-header" style="background:#fff3cd">
        <h5 class="modal-title text-dark">
          <i class="bi bi-exclamation-triangle me-2 text-warning"></i>
          Resolver excepción — <span id="exc-fecha-titulo"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="exc-id-excepcion">
        <input type="hidden" id="exc-tipo-hidden" value="feriado">

        <p class="mb-1"><strong id="exc-descripcion-modal"></strong></p>
        <p class="text-muted small mb-3" id="exc-tecnicos-afectados"></p>

        <p class="fw-semibold mb-2">¿Qué hacemos con este día?</p>

        <!-- Opción 1: Doble pago -->
        <div class="opcion-resolucion border rounded p-3 mb-2 cursor-pointer" data-valor="doble_pago">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="resolucion" value="doble_pago" id="opt_doble_pago">
            <label class="form-check-label fw-semibold" for="opt_doble_pago">
              <i class="bi bi-cash-coin me-1 text-success"></i>Pago doble
            </label>
          </div>
          <small class="text-muted d-block ms-4">
            Los técnicos que trabajen ese día reciben el doble de su pago diario. Quedan trabajando en sus turnos normales.
          </small>
        </div>

        <!-- Opción 2: Compensación -->
        <div class="opcion-resolucion border rounded p-3 mb-2 cursor-pointer" data-valor="compensacion">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="resolucion" value="compensacion" id="opt_compensacion">
            <label class="form-check-label fw-semibold" for="opt_compensacion">
              <i class="bi bi-calendar-check me-1 text-info"></i>Compensación de día
            </label>
          </div>
          <small class="text-muted d-block ms-4">
            Los técnicos trabajan normalmente pero se les da un día libre compensatorio. Usar las notas para indicar cuándo.
          </small>
        </div>

        <!-- Opción 3: Sin acción -->
        <div class="opcion-resolucion border rounded p-3 mb-3 cursor-pointer" data-valor="sin_accion">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="resolucion" value="sin_accion" id="opt_sin">
            <label class="form-check-label fw-semibold" for="opt_sin">
              <i class="bi bi-dash-circle me-1 text-secondary"></i>Sin acción necesaria
            </label>
          </div>
          <small class="text-muted d-block ms-4">
            El feriado cae en el día de descanso natural de todos los técnicos, o no aplica para el área.
          </small>
        </div>

        <div>
          <label class="form-label fw-semibold small">Notas adicionales</label>
          <textarea class="form-control form-control-sm" id="exc-notas" rows="2" maxlength="500"
                    placeholder="Observaciones, día compensatorio, etc."></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" id="btnConfirmarExcepcion">
          <i class="bi bi-check-lg me-1"></i>Confirmar resolución
        </button>
      </div>

    </div>
  </div>
</div>


<!-- ══ MODAL: Excepción Manual ══════════════════════════════ -->
<div class="modal fade" id="modalNuevaExcepcion" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Registrar excepción manual</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="nueva-exc-id-horario">
        <div class="mb-3">
          <label class="form-label fw-semibold">Fecha <span class="text-danger">*</span></label>
          <input type="date" class="form-control" id="nueva-exc-fecha">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Tipo</label>
          <select class="form-select" id="nueva-exc-tipo">
            <option value="urgencia_sabado">Urgencia sábado (comodín)</option>
            <option value="cambio_turno">Cambio de turno puntual</option>
            <option value="otro">Otro</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Descripción</label>
          <textarea class="form-control" id="nueva-exc-desc" rows="3" maxlength="200"
                    placeholder="¿Qué ocurrió o qué se necesita?"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" id="btnGuardarNuevaExcepcion">
          <i class="bi bi-save me-1"></i>Registrar
        </button>
      </div>
    </div>
  </div>
</div>


<!-- ══ MODAL: Agregar Feriado ════════════════════════════════ -->
<div class="modal fade" id="modalFeriado" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="feriado-modal-titulo">
          <i class="bi bi-calendar-event me-2"></i>Agregar feriado
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="feriado-id">
        <div class="mb-3">
          <label class="form-label fw-semibold">Fecha <span class="text-danger">*</span></label>
          <input type="date" class="form-control" id="feriado-fecha">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="feriado-nombre" maxlength="100"
                 placeholder="ej. Navidad">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Tipo</label>
          <select class="form-select" id="feriado-tipo">
            <option value="nacional">Nacional</option>
            <option value="especial">Especial / Empresa</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" id="btnGuardarFeriado">
          <i class="bi bi-save me-1"></i>Guardar
        </button>
      </div>
    </div>
  </div>
</div>


<!-- ══ MODAL: Agregar Técnico ════════════════════════════════ -->
<div class="modal fade" id="modalAgregarTecnico" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Agregar técnico de monitoreo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small">Selecciona un usuario del sistema para designarlo como técnico de monitoreo.</p>
        <div class="mb-3">
          <label class="form-label fw-semibold">Usuario</label>
          <select class="form-select" id="sel-nuevo-tecnico">
            <option value="">— Cargando... —</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" id="btnConfirmarAgregarTecnico">
          <i class="bi bi-check-lg me-1"></i>Agregar
        </button>
      </div>
    </div>
  </div>
</div>


<!-- ══ MODAL: Historial ═════════════════════════════════════ -->
<div class="modal fade" id="modalHistorial" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>Historial de horarios</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="hist-loading" class="text-center py-4">
          <span class="spinner-border spinner-border-sm"></span>
        </div>
        <table class="table table-hover w-100 d-none" id="tablaHistorial">
          <thead>
            <tr>
              <th>Período</th>
              <th class="text-center">Técnicos</th>
              <th class="text-center">Estado</th>
              <th>Activado</th>
              <th class="text-center">Ver</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- ══ MODAL: Cobertura de incapacidad ═════════════════════ -->
<div class="modal fade" id="modalCoberturaInc" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header" style="background:#fff0f0">
        <h5 class="modal-title text-danger">
          <i class="bi bi-person-fill-exclamation me-2"></i>Asignar cobertura — <span id="cob-fecha-titulo"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="cob-id-excepcion">
        <input type="hidden" id="cob-id-tecausente">
        <p class="mb-3">
          <span class="badge bg-danger me-1">Incapacitado</span>
          <strong id="cob-nombre-ausente"></strong>
          — turno normal: <span id="cob-turno-ausente" class="text-muted"></span>
        </p>
        <div class="mb-3">
          <label class="form-label fw-semibold small">¿Quién cubre?</label>
          <select class="form-select form-select-sm" id="cob-tecnico-cubre"></select>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold small">Horario de cobertura</label>
          <input type="text" class="form-control form-control-sm" id="cob-horario"
                 placeholder="ej. 5am - 2pm" maxlength="30">
        </div>
        <div>
          <label class="form-label fw-semibold small">Notas <span class="text-muted fw-normal">(opcional)</span></label>
          <textarea class="form-control form-control-sm" id="cob-notas" rows="2" maxlength="300"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-danger" id="btnConfirmarCobertura">
          <i class="bi bi-check-lg me-1"></i>Confirmar cobertura
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ══ MODAL: Incapacidad ══════════════════════════════════ -->
<div class="modal fade" id="modalIncapacidad" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header" style="background:#fff0f0">
        <h5 class="modal-title text-danger">
          <i class="bi bi-bandaid me-2"></i><span id="inc-modal-titulo">Registrar incapacidad</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="inc-modo" value="crear">
        <input type="hidden" id="inc-referencia-editar" value="">
        <input type="hidden" id="inc-id-excepcion-editar" value="">
        <div class="mb-3">
          <label class="form-label fw-semibold small">Técnico ausente</label>
          <select class="form-select form-select-sm" id="inc-tecnico">
            <option value="">— Selecciona —</option>
          </select>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label fw-semibold small">Desde</label>
            <input type="date" class="form-control form-control-sm" id="inc-fecha-ini">
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold small">Hasta</label>
            <input type="date" class="form-control form-control-sm" id="inc-fecha-fin">
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label fw-semibold small">Notas <span class="text-muted fw-normal">(opcional)</span></label>
          <textarea class="form-control form-control-sm" id="inc-notas" rows="2" maxlength="200"
                    placeholder="Diagnóstico, número de incapacidad, etc."></textarea>
        </div>
        <p class="text-muted small mb-0">
          <i class="bi bi-info-circle me-1"></i>
          Se crea una excepción por cada día del rango. La cobertura se asigna día a día desde el calendario.
        </p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-danger" id="btnConfirmarIncapacidad">
          <i class="bi bi-bandaid me-1"></i><span id="inc-btn-texto">Registrar</span>
        </button>
      </div>
    </div>
  </div>
</div>


<style>
/* ── Calendario ─────────────────────────────────────── */
.table-calendar th,
.table-calendar td { vertical-align: middle; font-size: .85rem; }
.table-calendar th { white-space: nowrap; }

.td-dia { min-width: 90px; font-size: .82rem; }
.td-turno { min-width: 130px; }

.celda-descanso   { background: #f1f3f5; }
.celda-feriado-bg { background: #fff8e1; }

.badge-comodin { font-size:.7rem; vertical-align: middle; }
.badge-meses-alerta { background: #ffc107; color: #000; font-size:.7rem; }

/* ── Opciones de resolución ─────────────────────────── */
.opcion-resolucion {
    cursor: pointer;
    transition: border-color .15s, background .15s;
}
.opcion-resolucion:hover     { border-color: #0d6efd !important; background: #f0f5ff; }
.opcion-resolucion.selected  { border-color: #0d6efd !important; background: #e8f0fe; }

/* ── Tarjetas de excepción ──────────────────────────── */
.exc-card {
    border-left: 4px solid #ffc107;
    border-radius: 8px;
    background: #fffdf0;
}
.exc-card.resuelta { border-left-color: #198754; background: #f0fdf4; }

/* ── Badge turno en cabecera ────────────────────────── */
.th-turno-badge { font-size: .72rem; display: block; margin-top: 2px; }

/* ── Vista semanal ──────────────────────────────────── */
.table-semanal th,
.table-semanal td { vertical-align: middle; font-size: .82rem; }
.table-semanal th { white-space: nowrap; }

.col-turno { min-width: 115px; }

.semana-bloque { border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; }
.semana-titulo  { font-size: .78rem; font-weight: 700; text-transform: uppercase;
                  letter-spacing: .04em; color: #6c757d;
                  padding: 6px 12px; background: #f8f9fa;
                  border-bottom: 1px solid #dee2e6; }

.feriado-header { background: #fff8e1 !important; }
</style>
