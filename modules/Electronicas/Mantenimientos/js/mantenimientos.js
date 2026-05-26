const CTRL = "./modules/Electronicas/Mantenimientos/Controllers/mantenimientosController.php";

let tablaMantenimientos     = null;
let _tareas                 = [];   // tareas del formulario actual
let _repuestosDisponibles   = [];   // catálogo cargado una vez
let _instaladosEnMaquina    = [];   // piezas instaladas en la máquina seleccionada
let _filasRepuestos         = [];   // [{id_repuesto, maneja_serie, cantidad, costo, series:[]}]
let _filasRetiros           = [];   // [{id_maquina_repuesto, tipo_retiro, observaciones}]
let _tiposMap               = {};   // {id_tipo: descripcion} para mostrar al seleccionar

$(document).ready(function () {
    listarMantenimientos();
    cargarSelects();
    cargarRepuestosDisponibles();

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

    // ── Máquina cambia → cargar instalados para retiros ─────
    $("#id_maquina").on("change", function () {
        const id = $(this).val();
        _filasRetiros = [];
        renderRetiros();
        if (!id || id === "-1") {
            _instaladosEnMaquina = [];
            $("#btnAgregarRetiro").prop("disabled", true);
            $("#emptyRetiros").text("Selecciona una máquina para habilitar esta sección.");
            return;
        }
        cargarInstalados(id);
    });

    // ── Agregar tarea con botón ──────────────────────────────
    $("#btnAgregarTarea").on("click", agregarTarea);

    // ── Agregar tarea con Enter ──────────────────────────────
    $("#inp_nueva_tarea").on("keydown", function (e) {
        if (e.key === "Enter") { e.preventDefault(); agregarTarea(); }
    });

    // ── Quitar tarea (delegado) ──────────────────────────────
    $("#listaTareas").on("click", ".btn-quitar-tarea", function () {
        const idx = parseInt($(this).data("idx"));
        _tareas.splice(idx, 1);
        renderTareas();
    });

    // ── Agregar repuesto ─────────────────────────────────────
    $("#btnAgregarRepuesto").on("click", function () {
        _filasRepuestos.push({ id_repuesto: "", maneja_serie: 0, cantidad: 1, costo: 0, series: [] });
        renderRepuestos();
    });

    // ── Eventos delegados — repuestos ────────────────────────
    $("#listaRepuestos").on("change", ".sel-repuesto", function () {
        const idx = parseInt($(this).closest(".rep-row").data("idx"));
        const opt = $(this).find("option:selected");
        const rep = _repuestosDisponibles.find(r => r.id_repuesto == $(this).val());
        if (!rep) return;
        _filasRepuestos[idx] = {
            id_repuesto:  rep.id_repuesto,
            maneja_serie: parseInt(rep.maneja_serie),
            cantidad:     1,
            costo:        parseFloat(rep.costo || 0),
            series:       []
        };
        renderRepuestos();
        // Si maneja serie, cargar series disponibles
        if (parseInt(rep.maneja_serie) === 1) cargarSeries(idx, rep.id_repuesto);
    });

    $("#listaRepuestos").on("input", ".inp-cantidad", function () {
        const idx = parseInt($(this).closest(".rep-row").data("idx"));
        _filasRepuestos[idx].cantidad = parseInt($(this).val()) || 1;
    });

    $("#listaRepuestos").on("input", ".inp-costo", function () {
        const idx = parseInt($(this).closest(".rep-row").data("idx"));
        _filasRepuestos[idx].costo = parseFloat($(this).val()) || 0;
    });

    $("#listaRepuestos").on("change", ".chk-serie", function () {
        const idx   = parseInt($(this).closest(".rep-row").data("idx"));
        const val   = parseInt($(this).val());
        const arr   = _filasRepuestos[idx].series;
        if ($(this).is(":checked")) { if (!arr.includes(val)) arr.push(val); }
        else { _filasRepuestos[idx].series = arr.filter(v => v !== val); }
    });

    $("#listaRepuestos").on("click", ".btn-quitar-rep", function () {
        const idx = parseInt($(this).closest(".rep-row").data("idx"));
        _filasRepuestos.splice(idx, 1);
        renderRepuestos();
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
                    const btnAnular = (row.anulado != 1 && window.USUARIO_ROL === 'Administrador')
                        ? `<button class="btn btn-sm btn-outline-danger btn-anular ms-1"
                                   data-id="${row.id_mantenimiento}"
                                   title="Anular mantenimiento">
                               <i class="bi bi-slash-circle"></i>
                           </button>`
                        : '';
                    return `
                        <button class="btn btn-sm btn-info btn-ver-detalle me-1"
                                data-id="${row.id_mantenimiento}"
                                data-maquina="${escHtml(row.maquina)}"
                                data-fecha="${escHtml(row.fecha_mantenimiento)}"
                                data-anulado="${row.anulado}"
                                data-motivo="${escHtml(row.motivo_anulacion || '')}"
                                title="Ver detalle">
                            <i class="bi bi-eye"></i>
                        </button>
                        <a class="btn btn-sm btn-outline-secondary"
                           href="./modules/Electronicas/Mantenimientos/reporte.php?id=${row.id_mantenimiento}"
                           target="_blank" title="Imprimir / PDF">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </a>
                        ${btnAnular}`;
                }
            }
        ],
        createdRow: function (row, data) {
            if (data.anulado == 1) $(row).addClass('table-secondary').css('opacity', '0.6');
        },
        language: { url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" }
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

function agregarTarea() {
    const texto = $("#inp_nueva_tarea").val().trim();
    if (!texto) {
        $("#inp_nueva_tarea").addClass("is-invalid").focus();
        setTimeout(() => $("#inp_nueva_tarea").removeClass("is-invalid"), 1500);
        return;
    }
    _tareas.push(texto);
    $("#inp_nueva_tarea").val("").focus();
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
        html += `
        <li class="list-group-item">
            <span class="tarea-numero">${i + 1}.</span>
            <span class="tarea-texto">${escHtml(t)}</span>
            <button type="button" class="btn btn-sm btn-outline-danger btn-quitar-tarea" data-idx="${i}">
                <i class="bi bi-x"></i>
            </button>
        </li>`;
    });
    $("#listaTareas").html(html);
}

// ════════════════════════════════════════════════════════════
// REPUESTOS
// ════════════════════════════════════════════════════════════

function cargarRepuestosDisponibles() {
    $.post(CTRL, { accion: "repuestos" }, function (resp) {
        if (!resp.ok) return;
        _repuestosDisponibles = resp.data;
    }, "json");
}

function cargarSeries(idx, id_repuesto) {
    $.post(CTRL, { accion: "series", id_repuesto }, function (resp) {
        if (!resp.ok) return;
        const $row = $(`.rep-row[data-idx="${idx}"]`);
        const $cont = $row.find(".series-cont");
        if (!resp.data.length) {
            $cont.html('<span class="text-danger small">Sin series disponibles en stock.</span>');
            return;
        }
        let html = '<div class="d-flex flex-wrap gap-2 mt-1">';
        resp.data.forEach(s => {
            html += `<div class="form-check form-check-inline mb-0">
                <input class="form-check-input chk-serie" type="checkbox"
                       value="${s.id_detalle_repuesto}" id="serie_${idx}_${s.id_detalle_repuesto}">
                <label class="form-check-label small" for="serie_${idx}_${s.id_detalle_repuesto}">
                    <span class="badge bg-secondary">${escHtml(s.serie)}</span>
                </label>
            </div>`;
        });
        html += '</div>';
        $cont.html(html);
    }, "json");
}

function renderRepuestos() {
    const $lista = $("#listaRepuestos");
    if (!_filasRepuestos.length) {
        $lista.html('<p class="text-muted small fst-italic mb-0" id="emptyRepuestos">Sin repuestos agregados.</p>');
        return;
    }

    let html = '';
    _filasRepuestos.forEach((rep, idx) => {
        const optsRep = _repuestosDisponibles.map(r => {
            const sel = r.id_repuesto == rep.id_repuesto ? "selected" : "";
            const stock = parseInt(r.maneja_serie) === 1
                ? `${r.stock} series`
                : `stock: ${r.stock}`;
            return `<option value="${r.id_repuesto}" data-serie="${r.maneja_serie}" data-costo="${r.costo}" ${sel}>
                        ${escHtml(r.nombre)} (${stock})
                    </option>`;
        }).join('');

        const esSerie = rep.maneja_serie === 1;
        const cantOSerie = esSerie
            ? `<div class="col-12 series-cont mt-1">
                   <small class="text-muted">Selecciona las series a instalar:</small>
               </div>`
            : `<div class="col-auto">
                   <label class="form-label small mb-1">Cantidad</label>
                   <input type="number" class="form-control form-control-sm inp-cantidad"
                          style="width:80px" min="1" value="${rep.cantidad}">
               </div>`;

        html += `
        <div class="card mb-2 rep-row" data-idx="${idx}">
            <div class="card-body p-2">
                <div class="row g-2 align-items-start">
                    <div class="col">
                        <label class="form-label small mb-1">Repuesto</label>
                        <select class="form-select form-select-sm sel-repuesto">
                            <option value="">— Selecciona —</option>
                            ${optsRep}
                        </select>
                    </div>
                    ${!esSerie ? `<div class="col-auto">
                        <label class="form-label small mb-1">Cantidad</label>
                        <input type="number" class="form-control form-control-sm inp-cantidad"
                               style="width:80px" min="1" value="${rep.cantidad}">
                    </div>` : ''}
                    <div class="col-auto">
                        <label class="form-label small mb-1">Costo unit.</label>
                        <input type="number" class="form-control form-control-sm inp-costo"
                               style="width:100px" min="0" step="0.01" value="${rep.costo}">
                    </div>
                    <div class="col-auto d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-quitar-rep mb-0">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                    ${esSerie ? `<div class="col-12 series-cont"><small class="text-muted fst-italic">Selecciona el repuesto para ver series.</small></div>` : ''}
                </div>
            </div>
        </div>`;
    });
    $lista.html(html);
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

    // Validar repuestos: todos deben tener repuesto seleccionado
    for (let i = 0; i < _filasRepuestos.length; i++) {
        const r = _filasRepuestos[i];
        if (!r.id_repuesto) {
            Swal.fire("Atención", `La fila ${i + 1} de repuestos no tiene un repuesto seleccionado.`, "warning");
            return;
        }
        if (r.maneja_serie === 1 && !r.series.length) {
            Swal.fire("Atención", `Selecciona al menos una serie para el repuesto de la fila ${i + 1}.`, "warning");
            return;
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
        fecha_mantenimiento:   fecha,
        proximo_mantenimiento: $("#proximo_mantenimiento").val() || null,
        descripcion:           $("#descripcion").val().trim(),
        tareas:                _tareas,
        repuestos:             _filasRepuestos,
        retiros:               _filasRetiros
    };

    const $btn = $("#btnGuardarMantenimiento")
        .prop("disabled", true)
        .html('<span class="spinner-border spinner-border-sm me-1"></span> Guardando...');

    $.post(CTRL, { accion: "guardar", losDatos: JSON.stringify(payload) }, function (resp) {
        $btn.prop("disabled", false).html('<i class="bi bi-save me-1"></i> Guardar');
        if (!resp.ok) { Swal.fire("Error", resp.mensaje || "No se pudo guardar", "error"); return; }
        cerrarModal("#modalMantenimiento");
        limpiarModal();
        tablaMantenimientos.ajax.reload();
        Swal.fire({ icon: "success", title: "Guardado", timer: 1800, showConfirmButton: false });
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
    Swal.fire({
        title: 'Anular mantenimiento',
        html: `<p class="text-muted small mb-2">
                   Esta acción marcará el registro como anulado.<br>
                   <strong>Los movimientos de inventario no se revierten automáticamente.</strong>
               </p>`,
        icon: 'warning',
        input: 'textarea',
        inputLabel: 'Motivo de anulación',
        inputPlaceholder: 'Describe el motivo...',
        inputAttributes: { maxlength: 500, rows: 3 },
        inputValidator: v => !v.trim() ? 'El motivo es obligatorio.' : null,
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: '<i class="bi bi-slash-circle me-1"></i> Sí, anular',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (!result.isConfirmed) return;
        $.post(CTRL, {
            accion: 'anular',
            id_mantenimiento: id,
            motivo: result.value.trim()
        }, function (resp) {
            if (!resp.ok) { Swal.fire('Error', resp.mensaje, 'error'); return; }
            tablaMantenimientos.ajax.reload(null, false);
            Swal.fire({ icon: 'success', title: 'Anulado', timer: 1800, showConfirmButton: false });
        }, 'json').fail(manejarError);
    });
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

    // Repuestos
    _filasRepuestos = [];
    renderRepuestos();

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
