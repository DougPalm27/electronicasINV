const CTRL = "./modules/Electronicas/Mantenimientos/Controllers/mantenimientosController.php";

let tablaMantenimientos     = null;
let _tareas                 = [];   // tareas del formulario actual
let _instaladosEnMaquina    = [];   // piezas instaladas en la máquina seleccionada
let _solicitudesDisponibles = [];   // solicitudes aprobadas sin usar de la máquina seleccionada
let _repuestosSolicitud     = [];   // repuestos de la solicitud elegida
let _seriesSeleccionadas    = {};   // {id_repuesto: [id_detalle_repuesto, ...]}
let _filasRetiros           = [];   // [{id_maquina_repuesto, tipo_retiro, observaciones}]
let _tiposMap               = {};   // {id_tipo: descripcion} para mostrar al seleccionar

$(document).ready(function () {
    listarMantenimientos();
    cargarSelects();

    // ── Inicializar tooltips al abrir el modal ───────────────
    document.getElementById('modalMantenimiento')
        .addEventListener('shown.bs.modal', function () {
            document.querySelectorAll('#modalMantenimiento .info-tip').forEach(el => {
                if (!bootstrap.Tooltip.getInstance(el)) {
                    new bootstrap.Tooltip(el, { trigger: 'hover focus' });
                }
            });
        });

    // ── Mostrar descripción al cambiar tipo ──────────────────
    $("#id_tipo").on("change", function () {
        const desc = _tiposMap[$(this).val()];
        if (desc) {
            $("#desc_tipo_texto").text(desc);
            $("#desc_tipo").show();
        } else {
            $("#desc_tipo").hide();
        }
    });

    // ── Nuevo mantenimiento ──────────────────────────────────
    $("#btnNuevoMantenimiento").on("click", function () {
        limpiarModal();
        abrirModal("#modalMantenimiento");
    });

    // ── Máquina cambia → cargar instalados y solicitudes ─────
    $("#id_maquina").on("change", function () {
        const id = $(this).val();
        _filasRetiros = [];
        renderRetiros();
        limpiarSolicitud();
        if (!id || id === "-1") {
            _instaladosEnMaquina = [];
            $("#btnAgregarRetiro").prop("disabled", true);
            $("#emptyRetiros").text("Selecciona una máquina para habilitar esta sección.");
            $("#sel_solicitud").prop("disabled", true)
                .html('<option value="">— Selecciona una máquina primero —</option>');
            return;
        }
        cargarInstalados(id);
        cargarSolicitudes(id);
    });

    // ── Agregar tarea del catálogo ──────────────────────────────
    $("#btnAgregarTareaCatalogo").on("click", agregarTareaCatalogo);

    // ── Agregar tarea personalizada ──────────────────────────────
    $("#btnAgregarTareaPersonalizada").on("click", agregarTareaPersonalizada);

    // ── Enter en input personalizado ─────────────────────────────
    $("#inp_tarea_personalizada").on("keypress", function (e) {
        if (e.which === 13) { // Enter
            agregarTareaPersonalizada();
            return false;
        }
    });

    // ── Quitar tarea (delegado) ──────────────────────────────
    $("#listaTareas").on("click", ".btn-quitar-tarea", function () {
        const idx = parseInt($(this).data("idx"));
        _tareas.splice(idx, 1);
        renderTareas();
    });

    // ── Solicitud seleccionada → mostrar repuestos y pre-llenar ──
    $("#sel_solicitud").on("change", function () {
        const id = $(this).val();
        _repuestosSolicitud  = [];
        _seriesSeleccionadas = {};
        if (!id) { $("#repuestosSolicitud").empty(); return; }
        cargarRepuestosSolicitud(id);

        // Pre-llenar tipo, técnico y notas desde la solicitud
        // (valores iniciales, el usuario puede cambiarlos)
        const sol = _solicitudesDisponibles.find(s => s.id_solicitud_maquina == id);
        if (!sol) return;
        if ($(`#id_tipo option[value="${sol.id_tipo}"]`).length) {
            $("#id_tipo").val(sol.id_tipo).change();
        }
        if (!$("#id_tecnico").prop("disabled") && sol.id_tecnico
            && $(`#id_tecnico option[value="${sol.id_tecnico}"]`).length) {
            $("#id_tecnico").val(sol.id_tecnico);
        }
        if (!$("#descripcion").val().trim()) {
            const desc = (sol.descripcion || "")
                + (sol.descripcion_maquina ? "\n" + sol.descripcion_maquina : "");
            $("#descripcion").val(desc.trim());
        }
    });

    // ── Selección de series de la solicitud (delegado) ───────
    $("#repuestosSolicitud").on("change", ".chk-serie", function () {
        const idRep = parseInt($(this).data("rep"));
        const val   = parseInt($(this).val());
        if (!_seriesSeleccionadas[idRep]) _seriesSeleccionadas[idRep] = [];
        const arr = _seriesSeleccionadas[idRep];
        if ($(this).is(":checked")) { if (!arr.includes(val)) arr.push(val); }
        else { _seriesSeleccionadas[idRep] = arr.filter(v => v !== val); }
        actualizarContadorSeries(idRep);
    });

    // ── Agregar retiro ───────────────────────────────────────
    $("#btnAgregarRetiro").on("click", function () {
        _filasRetiros.push({ id_maquina_repuesto: "", tipo_retiro: "devolucion", observaciones: "" });
        renderRetiros();
    });

    // ── Eventos delegados — retiros ──────────────────────────
    $("#listaRetiros").on("change", ".sel-retiro", function () {
        const idx = parseInt($(this).closest(".ret-row").data("idx"));
        _filasRetiros[idx].id_maquina_repuesto = $(this).val();
    });

    $("#listaRetiros").on("change", ".sel-tipo-retiro", function () {
        const idx = parseInt($(this).closest(".ret-row").data("idx"));
        _filasRetiros[idx].tipo_retiro = $(this).val();
    });

    $("#listaRetiros").on("input", ".inp-obs", function () {
        const idx = parseInt($(this).closest(".ret-row").data("idx"));
        _filasRetiros[idx].observaciones = $(this).val();
    });

    $("#listaRetiros").on("click", ".btn-quitar-ret", function () {
        const idx = parseInt($(this).closest(".ret-row").data("idx"));
        _filasRetiros.splice(idx, 1);
        renderRetiros();
    });

    // ── Guardar ──────────────────────────────────────────────
    $("#btnGuardarMantenimiento").on("click", guardar);
});

// ════════════════════════════════════════════════════════════
// TABLA PRINCIPAL
// ════════════════════════════════════════════════════════════

function listarMantenimientos() {
    if ($.fn.DataTable.isDataTable("#tablaMantenimientos")) {
        $("#tablaMantenimientos").DataTable().destroy();
    }

    tablaMantenimientos = $("#tablaMantenimientos").DataTable({
        ajax: {
            url: CTRL, type: "POST", dataType: "json",
            data: { accion: "listar" },
            dataSrc: function (json) {
                if (!json.ok) { console.error("ERROR:", json.mensaje); return []; }
                return json.data;
            },
            error: manejarError
        },
        columns: [
            { data: "codigo", title: "Código",
              render: v => `<span class="badge bg-light text-dark border font-monospace fw-semibold">${v}</span>` },
            { data: "maquina", title: "Máquina",
              render: (v, t, row) => {
                  const n = escHtml(v);
                  return row.anulado == 1
                      ? `<span class="text-decoration-line-through text-muted">${n}</span>
                         <span class="badge bg-danger ms-1 fw-normal" style="font-size:.7rem">Anulado</span>`
                      : n;
              }
            },
            { data: "tipo",                  title: "Tipo" },
            { data: "tecnico",               title: "Técnico" },
            { data: "fecha_mantenimiento",   title: "Fecha" },
            { data: "proximo_mantenimiento", title: "Próximo", defaultContent: "—" },
            { data: "descripcion",           title: "Notas",  defaultContent: "—",
              render: v => v ? `<span title="${escHtml(v)}">${escHtml(v.length > 40 ? v.slice(0,40)+'…' : v)}</span>` : '—' },
            {
                data: null, title: "Acciones", orderable: false, searchable: false,
                render: function (data, type, row) {
                    const motivo    = escHtml(row.motivo_anulacion || '');
                    const btnAnular = (row.anulado != 1 && window.USUARIO_ROL === 'Administrador')
                        ? `<li><hr class="dropdown-divider"></li>
                           <li>
                               <button class="dropdown-item text-danger btn-anular" type="button"
                                       data-id="${row.id_mantenimiento}">
                                   <i class="bi bi-slash-circle me-2"></i>Anular
                               </button>
                           </li>`
                        : '';
                    return `
                        <div class="dropdown">
                            <button class="btn btn-sm btn-primary dropdown-toggle py-1"
                                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li>
                                    <button class="dropdown-item btn-ver-detalle" type="button"
                                            data-id="${row.id_mantenimiento}"
                                            data-maquina="${escHtml(row.maquina)}"
                                            data-fecha="${escHtml(row.fecha_mantenimiento)}"
                                            data-anulado="${row.anulado}"
                                            data-motivo="${motivo}">
                                        <i class="bi bi-eye me-2 text-info"></i>Ver detalle
                                    </button>
                                </li>
                                ${row.anulado != 1 ? `<li>
                                    <button class="dropdown-item btn-editar" type="button"
                                            data-id="${row.id_mantenimiento}">
                                        <i class="bi bi-pencil me-2 text-warning"></i>Editar
                                    </button>
                                </li>` : ''}
                                <li>
                                    <a class="dropdown-item"
                                       href="./modules/Electronicas/Mantenimientos/reporte.php?id=${row.id_mantenimiento}"
                                       target="_blank">
                                        <i class="bi bi-file-earmark-pdf me-2 text-secondary"></i>Imprimir / PDF
                                    </a>
                                </li>
                                ${btnAnular}
                            </ul>
                        </div>`;
                }
            }
        ],
        createdRow: function (row, data) {
            if (data.anulado == 1) $(row).addClass('table-secondary').css('opacity', '0.6');
        },
        language: { url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
        order: [[0, 'desc']]
    });

    // ── Abrir detalle desde la tabla ─────────────────────────
    $("#tablaMantenimientos").on("click", ".btn-ver-detalle", function () {
        const id      = $(this).data("id");
        const maquina = $(this).data("maquina");
        const fecha   = $(this).data("fecha");
        const anulado = $(this).data("anulado");
        const motivo  = $(this).data("motivo");
        verDetalle(id, maquina, fecha, anulado, motivo);
    });

    // ── Editar mantenimiento ─────────────────────────────────
    $("#tablaMantenimientos").on("click", ".btn-editar", function () {
        const id = $(this).data("id");
        cargarEdicion(id);
    });

    // ── Anular mantenimiento (solo admin) ────────────────────
    $("#tablaMantenimientos").on("click", ".btn-anular", function () {
        const id = $(this).data("id");
        confirmarAnulacion(id);
    });
}

// ════════════════════════════════════════════════════════════
// SELECTS
// ════════════════════════════════════════════════════════════

function cargarSelects() {
    cargarSelect("maquinas", "#id_maquina", "id_maquina");
    cargarSelectTipos();
    cargarSelectTecnicos();
    cargarTareasActivas();
}

function cargarTareasActivas() {
    $.post(CTRL, { accion: 'tareasActivas' }, function (resp) {
        if (!resp.ok) return;
        // Agrupar por frecuencia para usar <optgroup>
        const grupos = {};
        resp.data.forEach(t => {
            if (!grupos[t.frecuencia]) grupos[t.frecuencia] = [];
            grupos[t.frecuencia].push(t);
        });
        let html = '<option value="">— Selecciona una tarea —</option>';
        Object.keys(grupos).forEach(frec => {
            html += `<optgroup label="${escHtml(frec)}">`;
            grupos[frec].forEach(t => {
                html += `<option value="${t.id_tarea}" data-nombre="${escHtml(t.nombre)}">${escHtml(t.nombre)}</option>`;
            });
            html += '</optgroup>';
        });
        $('#sel_nueva_tarea').html(html);
    }, 'json');
}

function cargarSelectTipos() {
    $.post(CTRL, { accion: "tipos" }, function (resp) {
        if (!resp.ok) { console.error(resp.mensaje); return; }
        let html = '<option value="-1">Seleccione</option>';
        _tiposMap = {};
        resp.data.forEach(t => {
            html += `<option value="${t.id_tipo}">${escHtml(t.nombre)}</option>`;
            if (t.descripcion) _tiposMap[t.id_tipo] = t.descripcion;
        });
        $("#id_tipo").html(html);
    }, "json");
}

function cargarSelect(accion, selector, valueKey) {
    $.post(CTRL, { accion }, function (resp) {
        if (!resp.ok) { console.error(resp.mensaje); return; }
        let html = '<option value="-1">Seleccione</option>';
        resp.data.forEach(e => {
            html += `<option value="${e[valueKey]}">${escHtml(e.nombre)}</option>`;
        });
        $(selector).html(html);
    }, "json");
}

function cargarSelectTecnicos() {
    $.post(CTRL, { accion: "tecnicos" }, function (resp) {
        if (!resp.ok) { console.error(resp.mensaje); return; }

        let html = '<option value="-1">Seleccione</option>';
        resp.data.forEach(e => {
            html += `<option value="${e.id_usuario}">${escHtml(e.nombre)}</option>`;
        });
        $("#id_tecnico").html(html);

        // Si el usuario logueado es Técnico: pre-seleccionar y bloquear el select
        if (window.USUARIO_ROL === "Técnico") {
            $("#id_tecnico")
                .val(window.USUARIO_ID)
                .prop("disabled", true);
        }
    }, "json");
}

// ════════════════════════════════════════════════════════════
// TAREAS
// ════════════════════════════════════════════════════════════

// ── Agregar tarea desde catálogo ────────────────────────
function agregarTareaCatalogo() {
    const $sel   = $("#sel_nueva_tarea");
    const id     = parseInt($sel.val());
    const nombre = $sel.find("option:selected").data("nombre") || "";

    if (!id || !nombre) {
        $sel.addClass("is-invalid");
        setTimeout(() => $sel.removeClass("is-invalid"), 1500);
        return;
    }
    if (_tareas.some(t => t.id_tarea === id)) {
        Swal.fire({ icon: "info", title: "Ya agregada", text: `"${nombre}" ya está en la lista.`, timer: 1800, showConfirmButton: false });
        return;
    }
    _tareas.push({ id_tarea: id, nombre });
    $sel.val("").focus();
    renderTareas();
}

// ── Agregar tarea personalizada ──────────────────────────
function agregarTareaPersonalizada() {
    const $inp = $("#inp_tarea_personalizada");
    const desc = $inp.val().trim();

    if (!desc) {
        $inp.addClass("is-invalid");
        setTimeout(() => $inp.removeClass("is-invalid"), 1500);
        return;
    }

    // Evitar duplicados exactos
    if (_tareas.some(t => t.nombre.toLowerCase() === desc.toLowerCase())) {
        Swal.fire({ icon: "info", title: "Duplicada", text: `Ya existe una tarea similar en la lista.`, timer: 1800, showConfirmButton: false });
        return;
    }

    _tareas.push({ id_tarea: null, nombre: desc });
    $inp.val("").focus();
    renderTareas();
}

function renderTareas() {
    if (!_tareas.length) {
        $("#listaTareas").empty();
        $("#emptyTareas").show();
        return;
    }
    $("#emptyTareas").hide();
    let html = "";
    _tareas.forEach((t, i) => {
        const badge = t.id_tarea
            ? '<span class="badge bg-info text-dark" style="font-size:.65rem">Catálogo</span>'
            : '<span class="badge bg-warning text-dark" style="font-size:.65rem">Personalizada</span>';
        html += `
        <li class="list-group-item">
            <span class="tarea-numero">${i + 1}.</span>
            <span class="tarea-texto">${escHtml(t.nombre)}</span>
            ${badge}
            <button type="button" class="btn btn-sm btn-outline-danger btn-quitar-tarea" data-idx="${i}">
                <i class="bi bi-x"></i>
            </button>
        </li>`;
    });
    $("#listaTareas").html(html);
}

// ════════════════════════════════════════════════════════════
// SOLICITUDES DE REPUESTOS (aprobadas, sin usar)
// ════════════════════════════════════════════════════════════

function limpiarSolicitud() {
    _solicitudesDisponibles = [];
    _repuestosSolicitud     = [];
    _seriesSeleccionadas    = {};
    $("#sel_solicitud").val("");
    $("#repuestosSolicitud").empty();
}

function cargarSolicitudes(id_maquina) {
    $.post(CTRL, { accion: "solicitudesDisponibles", id_maquina }, function (resp) {
        _solicitudesDisponibles = resp.ok ? resp.data : [];
        if (!_solicitudesDisponibles.length) {
            $("#sel_solicitud").prop("disabled", true)
                .html('<option value="">— Sin solicitudes aprobadas para esta máquina —</option>');
            return;
        }
        let html = '<option value="">— No usar solicitud —</option>';
        _solicitudesDisponibles.forEach(s => {
            const desc = s.descripcion.length > 60 ? s.descripcion.slice(0, 60) + '…' : s.descripcion;
            html += `<option value="${s.id_solicitud_maquina}">
                        ${escHtml(s.codigo)} — ${escHtml(desc)} (${escHtml(s.tipo)}, prog. ${escHtml(s.fecha_programada)})
                     </option>`;
        });
        $("#sel_solicitud").prop("disabled", false).html(html);
    }, "json");
}

function cargarRepuestosSolicitud(id_solicitud_maquina) {
    $("#repuestosSolicitud").html(
        '<p class="text-muted small mb-0"><span class="spinner-border spinner-border-sm me-1"></span> Cargando repuestos...</p>'
    );
    $.post(CTRL, { accion: "repuestosSolicitud", id_solicitud_maquina }, function (resp) {
        if (!resp.ok) {
            $("#repuestosSolicitud").html(`<p class="text-danger small mb-0">${escHtml(resp.mensaje)}</p>`);
            return;
        }
        _repuestosSolicitud = resp.data;
        renderRepuestosSolicitud();
    }, "json");
}

function renderRepuestosSolicitud() {
    if (!_repuestosSolicitud.length) {
        $("#repuestosSolicitud").html('<p class="text-muted small fst-italic mb-0">La solicitud no tiene repuestos.</p>');
        return;
    }

    let html = '';
    _repuestosSolicitud.forEach(rep => {
        const esSerie  = parseInt(rep.maneja_serie) === 1;
        const stock    = parseInt(rep.stock_actual);
        const cantidad = parseInt(rep.cantidad);
        const stockBadge = stock >= cantidad
            ? `<span class="badge bg-success">${stock} disp.</span>`
            : `<span class="badge bg-danger">⚠ ${stock} disp.</span>`;

        html += `
        <div class="card mb-2 rep-sol-row" data-rep="${rep.id_repuesto}">
            <div class="card-body p-2">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-1">
                    <span>
                        <strong>${escHtml(rep.repuesto)}</strong>
                        <span class="badge ${esSerie ? 'bg-info text-dark' : 'bg-secondary'} ms-1" style="font-size:.7rem">
                            ${esSerie ? 'Serie' : 'Cantidad'}
                        </span>
                    </span>
                    <span>
                        <span class="me-2 small text-muted">Cant: <strong>${cantidad}</strong></span>
                        ${stockBadge}
                    </span>
                </div>
                ${esSerie ? `
                <div class="series-cont mt-2">
                    <small class="text-muted">
                        Selecciona <strong>${cantidad}</strong> serie(s):
                        <span class="contador-series" data-rep="${rep.id_repuesto}">0/${cantidad}</span>
                    </small>
                    <div class="series-lista"><span class="spinner-border spinner-border-sm"></span></div>
                </div>` : ''}
            </div>
        </div>`;
    });
    $("#repuestosSolicitud").html(html);

    // Cargar series disponibles para los repuestos que las manejan
    _repuestosSolicitud
        .filter(r => parseInt(r.maneja_serie) === 1)
        .forEach(r => cargarSeriesSolicitud(r.id_repuesto));
}

function cargarSeriesSolicitud(id_repuesto) {
    $.post(CTRL, { accion: "series", id_repuesto }, function (resp) {
        const $cont = $(`.rep-sol-row[data-rep="${id_repuesto}"] .series-lista`);
        if (!resp.ok || !resp.data.length) {
            $cont.html('<span class="text-danger small">Sin series disponibles en stock.</span>');
            return;
        }
        let html = '<div class="d-flex flex-wrap gap-2 mt-1">';
        resp.data.forEach(s => {
            html += `<div class="form-check form-check-inline mb-0">
                <input class="form-check-input chk-serie" type="checkbox"
                       data-rep="${id_repuesto}" value="${s.id_detalle_repuesto}"
                       id="serie_${id_repuesto}_${s.id_detalle_repuesto}">
                <label class="form-check-label small" for="serie_${id_repuesto}_${s.id_detalle_repuesto}">
                    <span class="badge bg-secondary">${escHtml(s.serie)}</span>
                </label>
            </div>`;
        });
        html += '</div>';
        $cont.html(html);
    }, "json");
}

function actualizarContadorSeries(id_repuesto) {
    const rep = _repuestosSolicitud.find(r => r.id_repuesto == id_repuesto);
    if (!rep) return;
    const sel   = (_seriesSeleccionadas[id_repuesto] || []).length;
    const $cont = $(`.contador-series[data-rep="${id_repuesto}"]`);
    $cont.text(`${sel}/${rep.cantidad}`)
         .toggleClass("text-success fw-bold", sel == rep.cantidad)
         .toggleClass("text-danger", sel != rep.cantidad);
}

// ════════════════════════════════════════════════════════════
// RETIROS
// ════════════════════════════════════════════════════════════

function cargarInstalados(id_maquina) {
    $("#emptyRetiros").text("Cargando piezas instaladas...");
    $.post(CTRL, { accion: "instalados", id_maquina }, function (resp) {
        _instaladosEnMaquina = resp.ok ? resp.data : [];
        $("#btnAgregarRetiro").prop("disabled", !_instaladosEnMaquina.length);
        $("#emptyRetiros").text(
            _instaladosEnMaquina.length
                ? "Usa el botón 'Agregar' para registrar piezas retiradas."
                : "Esta máquina no tiene piezas instaladas."
        );
    }, "json");
}

function renderRetiros() {
    const $lista = $("#listaRetiros");
    if (!_filasRetiros.length) {
        const msg = _instaladosEnMaquina.length
            ? "Usa el botón 'Agregar' para registrar piezas retiradas."
            : "Selecciona una máquina para habilitar esta sección.";
        $lista.html(`<p class="text-muted small fst-italic mb-0" id="emptyRetiros">${msg}</p>`);
        return;
    }

    let html = '';
    _filasRetiros.forEach((ret, idx) => {
        const optsInstalados = _instaladosEnMaquina.map(p => {
            const label = p.serie
                ? `${escHtml(p.repuesto)} — Serie: ${escHtml(p.serie)}`
                : `${escHtml(p.repuesto)} (cant: ${p.cantidad})`;
            const sel = p.id_maquina_repuesto == ret.id_maquina_repuesto ? "selected" : "";
            return `<option value="${p.id_maquina_repuesto}" ${sel}>${label}</option>`;
        }).join('');

        html += `
        <div class="card mb-2 ret-row" data-idx="${idx}">
            <div class="card-body p-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Pieza instalada</label>
                        <select class="form-select form-select-sm sel-retiro">
                            <option value="">— Selecciona —</option>
                            ${optsInstalados}
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Tipo de retiro</label>
                        <select class="form-select form-select-sm sel-tipo-retiro">
                            <option value="devolucion" ${ret.tipo_retiro === 'devolucion' ? 'selected' : ''}>Devolución al stock</option>
                            <option value="baja"       ${ret.tipo_retiro === 'baja'       ? 'selected' : ''}>Baja (descarte)</option>
                        </select>
                    </div>
                    <div class="col">
                        <label class="form-label small mb-1">Observaciones</label>
                        <input type="text" class="form-control form-control-sm inp-obs"
                               placeholder="Motivo, estado de la pieza..." value="${escHtml(ret.observaciones)}">
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-quitar-ret">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>`;
    });
    $lista.html(html);
}

// ════════════════════════════════════════════════════════════
// GUARDAR
// ════════════════════════════════════════════════════════════

function guardar() {
    const id_maquina = $("#id_maquina").val();
    const id_tipo    = $("#id_tipo").val();
    const fecha      = $("#fecha_mantenimiento").val();

    if (!id_maquina || id_maquina === "-1") {
        Swal.fire("Atención", "Selecciona una máquina", "warning"); return;
    }
    if (!id_tipo || id_tipo === "-1") {
        Swal.fire("Atención", "Selecciona el tipo de mantenimiento", "warning"); return;
    }
    if (!fecha) {
        Swal.fire("Atención", "Indica la fecha del mantenimiento", "warning"); return;
    }
    if (!_tareas.length) {
        Swal.fire("Atención", "Agrega al menos una tarea realizada", "warning");
        $("#inp_nueva_tarea").focus();
        return;
    }

    // .val() devuelve null en selects disabled; usamos el atributo directo
    const _tecRaw    = $("#id_tecnico").prop("disabled")
        ? $("#id_tecnico option:selected").val()
        : $("#id_tecnico").val();
    const id_tecnico = (_tecRaw && _tecRaw !== "-1") ? _tecRaw : null;

    // Validar solicitud: si hay repuestos por serie, deben tener
    // exactamente la cantidad de series seleccionadas
    const id_solicitud_maquina = $("#sel_solicitud").val() || null;
    if (id_solicitud_maquina) {
        for (const rep of _repuestosSolicitud) {
            if (parseInt(rep.maneja_serie) !== 1) continue;
            const sel = (_seriesSeleccionadas[rep.id_repuesto] || []).length;
            if (sel !== parseInt(rep.cantidad)) {
                Swal.fire("Atención",
                    `Selecciona exactamente ${rep.cantidad} serie(s) para "${rep.repuesto}" (tienes ${sel}).`,
                    "warning");
                return;
            }
        }
    }

    // Validar retiros: todos deben tener pieza seleccionada
    for (let i = 0; i < _filasRetiros.length; i++) {
        if (!_filasRetiros[i].id_maquina_repuesto) {
            Swal.fire("Atención", `La fila ${i + 1} de retiros no tiene una pieza seleccionada.`, "warning");
            return;
        }
    }

    const payload = {
        id_maquina,
        id_tipo,
        id_tecnico,
        fecha_mantenimiento:    fecha,
        proximo_mantenimiento:  $("#proximo_mantenimiento").val() || null,
        descripcion:            $("#descripcion").val().trim(),
        tareas:                 _tareas,
        id_solicitud_maquina,
        series_solicitud:       _seriesSeleccionadas,
        retiros:                _filasRetiros
    };

    const $btn = $("#btnGuardarMantenimiento")
        .prop("disabled", true)
        .html('<span class="spinner-border spinner-border-sm me-1"></span> Guardando...');

    // Detectar si es edición o nuevo
    const id_edit = $("#id_mantenimiento_edit").val();
    const accion = id_edit ? "actualizar" : "guardar";
    const datos = id_edit ? { accion, id_mantenimiento: id_edit, losDatos: JSON.stringify(payload) }
                          : { accion, losDatos: JSON.stringify(payload) };

    $.post(CTRL, datos, function (resp) {
        $btn.prop("disabled", false).html('<i class="bi bi-save me-1"></i> Guardar');
        if (!resp.ok) { Swal.fire("Error", resp.mensaje || "No se pudo guardar", "error"); return; }
        cerrarModal("#modalMantenimiento");
        limpiarModal();
        tablaMantenimientos.ajax.reload();
        const msg = id_edit ? "Mantenimiento actualizado correctamente" : "Mantenimiento guardado correctamente";
        Swal.fire({ icon: "success", title: "Éxito", text: msg, timer: 1800, showConfirmButton: false });
    }, "json").fail(manejarError);
}

// ════════════════════════════════════════════════════════════
// DETALLE
// ════════════════════════════════════════════════════════════

function verDetalle(id_mantenimiento, maquina, fecha, anulado, motivo) {
    $("#modalDetalleLabel").html(
        `<i class="bi bi-tools me-2"></i>${escHtml(maquina)} — ${escHtml(fecha)}`
    );
    $("#detalleContenido").html(
        '<div class="text-center py-4"><span class="spinner-border spinner-border-sm me-2"></span> Cargando...</div>'
    );
    abrirModal("#modalDetalle");

    $.post(CTRL, { accion: "detalle", id_mantenimiento }, function (resp) {
        if (!resp.ok) {
            $("#detalleContenido").html(`<div class="alert alert-danger m-3">${escHtml(resp.mensaje)}</div>`);
            return;
        }
        renderDetalle(resp.data, anulado, motivo);
    }, "json").fail(manejarError);
}

function confirmarAnulacion(id) {
    // Paso 1 — análisis previo
    $.post(CTRL, { accion: 'verificarAnulacion', id_mantenimiento: id }, function (resp) {
        if (!resp.ok) { Swal.fire('Error', resp.mensaje, 'error'); return; }

        const d = resp.data;
        let html = '<div style="text-align:left">';

        // Solicitud vinculada
        if (d.solicitud) {
            html += `<div class="alert alert-warning py-2 mb-3 small">
                <i class="bi bi-receipt me-1"></i>
                Este mantenimiento usó la solicitud de repuestos
                <strong>#${d.solicitud.id_solicitud}</strong>
                &ldquo;${escHtml(d.solicitud.descripcion)}&rdquo;<br>
                Al anular, la solicitud volverá a quedar <strong>disponible</strong> para otro mantenimiento.
            </div>`;
        }

        // Qué SÍ se revertirá
        if (d.revertibles.length) {
            html += `<p class="fw-semibold small mb-1">
                <i class="bi bi-check-circle text-success me-1"></i>Se revertirá automáticamente:
            </p><ul class="small mb-3 ps-3">`;
            d.revertibles.forEach(r => {
                html += `<li><strong>${escHtml(r.repuesto)}</strong> — ${escHtml(r.info)}</li>`;
            });
            html += '</ul>';
        }

        // Qué NO se puede revertir
        if (d.conflictos.length) {
            html += `<p class="fw-semibold small mb-1 text-danger">
                <i class="bi bi-exclamation-triangle me-1"></i>No se puede revertir automáticamente:
            </p><ul class="small mb-3 ps-3 text-danger">`;
            d.conflictos.forEach(c => {
                html += `<li><strong>${escHtml(c.repuesto)}</strong> — ${escHtml(c.razon)}</li>`;
            });
            html += '</ul>';
        }

        if (!d.revertibles.length && !d.conflictos.length) {
            html += '<p class="small text-muted mb-3">Este mantenimiento no tiene movimientos de inventario.</p>';
        }

        // Campo motivo
        html += `<label class="form-label small fw-semibold">
                     Motivo de anulación <span class="text-danger">*</span>
                 </label>
                 <textarea id="swal-motivo" class="form-control form-control-sm"
                           rows="2" maxlength="500"
                           placeholder="Describe el motivo..."></textarea>`;
        html += '</div>';

        // Paso 2 — confirmación y ejecución
        Swal.fire({
            title: 'Anular mantenimiento',
            html,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: '<i class="bi bi-slash-circle me-1"></i> Confirmar anulación',
            cancelButtonText: 'Cancelar',
            didOpen: () => {
                // Forzar scroll al inicio del contenido
                Swal.getHtmlContainer().scrollTop = 0;
            },
            preConfirm: () => {
                const motivo = document.getElementById('swal-motivo').value.trim();
                if (!motivo) {
                    Swal.showValidationMessage('El motivo es obligatorio.');
                    return false;
                }
                return motivo;
            }
        }).then(result => {
            if (!result.isConfirmed) return;
            $.post(CTRL, {
                accion: 'anular',
                id_mantenimiento: id,
                motivo: result.value
            }, function (resp2) {
                if (!resp2.ok) { Swal.fire('Error', resp2.mensaje, 'error'); return; }
                tablaMantenimientos.ajax.reload(null, false);
                Swal.fire({ icon: 'success', title: 'Anulado', timer: 1800, showConfirmButton: false });
            }, 'json').fail(manejarError);
        });
    }, 'json').fail(manejarError);
}

function renderDetalle(data, anulado, motivo) {
    const tareas     = data.tareas     || [];
    const instalados = data.instalados || [];
    const retiros    = data.retiros    || [];
    let html = "";

    // ── Banner de anulado ─────────────────────────────────────
    if (anulado == 1) {
        html += `<div class="alert alert-danger d-flex align-items-start gap-2 mb-0 rounded-0 border-0">
            <i class="bi bi-slash-circle-fill fs-5 flex-shrink-0 mt-1"></i>
            <div>
                <strong>Mantenimiento anulado</strong>
                ${motivo ? `<br><small class="text-danger-emphasis">${escHtml(motivo)}</small>` : ''}
            </div>
        </div>`;
    }

    // ── Tareas ────────────────────────────────────────────────
    html += `<div class="px-3 pt-3 pb-2">
        <h6 class="text-muted small text-uppercase fw-bold mb-2">
            <i class="bi bi-list-check me-1"></i>Tareas realizadas
        </h6>`;

    if (tareas.length) {
        html += '<ol class="mb-0 ps-3">';
        tareas.forEach(t => { html += `<li class="mb-1">${escHtml(t)}</li>`; });
        html += '</ol>';
    } else {
        html += '<p class="text-muted small fst-italic mb-0">Sin tareas registradas.</p>';
    }
    html += '</div>';

    // ── Repuestos instalados (desde solicitud) ─────────────────
    if (instalados.length) {
        html += `<hr class="my-0">
        <div class="px-3 pt-3 pb-1">
            <h6 class="text-muted small text-uppercase fw-bold mb-1">
                <i class="bi bi-box-seam me-1"></i>Repuestos instalados
                <span class="badge bg-info text-dark ms-1" style="font-size:.7rem">vía Solicitud</span>
            </h6>
        </div>
        <table class="table table-hover table-bordered mb-0 table-sm">
            <thead class="table-light">
                <tr>
                    <th>Repuesto</th>
                    <th class="text-center" style="width:70px">Cant.</th>
                    <th class="text-end"    style="width:110px">Costo (L.)</th>
                    <th class="text-end"    style="width:110px">Subtotal (L.)</th>
                    <th class="text-center" style="width:120px">N° Serie</th>
                </tr>
            </thead><tbody>`;

        let totalLps = 0;
        instalados.forEach(r => {
            const tc      = parseFloat(r.tipo_cambio || 1);
            const cLps    = parseFloat(r.costo_unitario || 0) * tc;
            const subLps  = parseFloat(r.subtotal || 0) * tc;
            totalLps += subLps;

            const sim   = r.divisa_simbolo || 'L.';
            const nota  = (tc !== 1 && sim !== 'L.')
                ? `<br><small class="text-muted">${sim} ${parseFloat(r.costo_unitario).toFixed(2)} × ${tc}</small>`
                : '';

            html += `<tr>
                <td>${escHtml(r.repuesto)}</td>
                <td class="text-center">${r.cantidad}</td>
                <td class="text-end">L. ${cLps.toFixed(2)}${nota}</td>
                <td class="text-end">L. ${subLps.toFixed(2)}</td>
                <td class="text-center">
                    ${r.serie ? `<span class="badge bg-secondary">${escHtml(r.serie)}</span>` : '<span class="text-muted">—</span>'}
                </td>
            </tr>`;
        });
        html += `<tr class="table-light fw-bold">
            <td colspan="3" class="text-end">Total</td>
            <td class="text-end">L. ${totalLps.toFixed(2)}</td>
            <td></td>
        </tr></tbody></table>`;
    }

    // ── Retiros históricos (solo visualización) ─────────────────
    if (retiros.length) {
        html += `<hr class="my-0">
        <div class="px-3 pt-3 pb-1">
            <h6 class="text-muted small text-uppercase fw-bold mb-1">
                <i class="bi bi-arrow-return-left me-1 text-warning"></i>Piezas retiradas
            </h6>
        </div>
        <table class="table table-hover table-bordered mb-0 table-sm">
            <thead class="table-warning">
                <tr>
                    <th>Repuesto</th>
                    <th class="text-center" style="width:70px">Cant.</th>
                    <th class="text-center" style="width:120px">N° Serie</th>
                    <th class="text-center" style="width:100px">Tipo</th>
                    <th>Observaciones</th>
                </tr>
            </thead><tbody>`;
        retiros.forEach(r => {
            const tipoBadge = r.tipo_retiro === "devolucion"
                ? '<span class="badge bg-success">Devolución</span>'
                : '<span class="badge bg-danger">Baja</span>';
            html += `<tr>
                <td>${escHtml(r.repuesto)}</td>
                <td class="text-center">${r.cantidad}</td>
                <td class="text-center">
                    ${r.serie ? `<span class="badge bg-secondary">${escHtml(r.serie)}</span>` : '<span class="text-muted">—</span>'}
                </td>
                <td class="text-center">${tipoBadge}</td>
                <td>${escHtml(r.observaciones_retiro || "—")}</td>
            </tr>`;
        });
        html += '</tbody></table>';
    }

    $("#detalleContenido").html(html);
}

// ════════════════════════════════════════════════════════════
// UTILIDADES
// ════════════════════════════════════════════════════════════

function limpiarModal() {
    $("#formMantenimiento")[0].reset();
    $("#desc_tipo").hide();

    // Tareas
    _tareas = [];
    renderTareas();
    $("#emptyTareas").show();
    $("#sel_nueva_tarea").val("").removeClass("is-invalid");
    $("#inp_tarea_personalizada").val("").removeClass("is-invalid");

    // Solicitud de repuestos
    limpiarSolicitud();
    $("#sel_solicitud").prop("disabled", true)
        .html('<option value="">— Selecciona una máquina primero —</option>');
    $("#seccionSolicitud").show();
    $("#repuestosEdicion").hide().empty();

    // Retiros
    _filasRetiros        = [];
    _instaladosEnMaquina = [];
    renderRetiros();
    $("#btnAgregarRetiro").prop("disabled", true);

    // Restaurar estado del select de técnico según el rol de la sesión
    if (window.USUARIO_ROL === "Técnico") {
        $("#id_tecnico").val(window.USUARIO_ID).prop("disabled", true);
    } else {
        $("#id_tecnico").prop("disabled", false).val("-1");
    }

    // Limpiar modo edición
    $("#id_mantenimiento_edit").val("");
    $("#modalTitulo").text("Registrar Mantenimiento");
    $("#id_maquina").prop("disabled", false);
}

// ════════════════════════════════════════════════════════════
// EDICIÓN
// ════════════════════════════════════════════════════════════

function cargarEdicion(id_mantenimiento) {
    $.post(CTRL, { accion: "obtenerEdicion", id_mantenimiento }, function (resp) {
        if (!resp.ok) {
            Swal.fire("Error", resp.mensaje || "No se pudo cargar", "error");
            return;
        }

        const data = resp.data;
        if (!data.mantenimiento) {
            Swal.fire("Error", "Mantenimiento no encontrado o ya está anulado", "error");
            return;
        }

        limpiarModal();
        cargarSelects();

        const m = data.mantenimiento;

        // Marcar como edición
        $("#id_mantenimiento_edit").val(id_mantenimiento);
        $("#modalTitulo").text("Editar Mantenimiento");

        // Llenar campos principales
        setTimeout(() => {
            $("#id_maquina").val(m.id_maquina).prop("disabled", true).change();
            $("#id_tipo").val(m.id_tipo).change();
            $("#id_tecnico").val(m.id_tecnico || "-1");
            $("#fecha_mantenimiento").val(m.fecha_mantenimiento);
            $("#proximo_mantenimiento").val(m.proximo_mantenimiento || "");
            $("#descripcion").val(m.descripcion || "");

            // Cargar tareas (como descripciones simples)
            _tareas = data.tareas.map(t => {
                if (typeof t === 'string') {
                    return { id_tarea: null, nombre: t };
                }
                return { id_tarea: t.id_tarea || null, nombre: t.descripcion || t.nombre || '' };
            });
            renderTareas();

            // Repuestos: provienen de la solicitud usada; en edición
            // solo se muestran como referencia (no son modificables)
            $("#seccionSolicitud").hide();
            let repHtml;
            if (data.repuestos.length) {
                repHtml = '<ul class="list-group list-group-flush small">';
                data.repuestos.forEach(r => {
                    repHtml += `<li class="list-group-item px-0 py-1 d-flex justify-content-between">
                        <span>${escHtml(r.nombre)}</span>
                        <span class="text-muted">Cant: ${r.cantidad}</span>
                    </li>`;
                });
                repHtml += '</ul><p class="text-muted small fst-italic mb-0">Los repuestos instalados no se pueden modificar al editar.</p>';
            } else {
                repHtml = '<p class="text-muted small fst-italic mb-0">Este mantenimiento no tiene repuestos instalados.</p>';
            }
            $("#repuestosEdicion").html(repHtml).show();

            // Cargar retiros
            _filasRetiros = data.retiros.map(ret => ({
                id_maquina_repuesto: ret.id_maquina_repuesto,
                tipo_retiro:         ret.tipo_retiro,
                observaciones:       ret.observaciones_retiro || ""
            }));
            renderRetiros();

            abrirModal("#modalMantenimiento");
        }, 100);

    }, "json").fail(manejarError);
}

function limpiarModalEdicion() {
    $("#id_mantenimiento_edit").val("");
    $("#modalTitulo").text("Registrar Mantenimiento");
}

function abrirModal(sel) {
    const el = document.querySelector(sel);
    (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show();
}

function cerrarModal(sel) {
    const el = document.querySelector(sel);
    const m  = bootstrap.Modal.getInstance(el);
    if (m) m.hide();
}

function manejarError(e) {
    console.error(e.responseText);
    Swal.fire("Error", "Error en servidor", "error");
}

function escHtml(str) {
    if (!str) return "";
    return String(str)
        .replace(/&/g, "&amp;").replace(/</g, "&lt;")
        .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}
