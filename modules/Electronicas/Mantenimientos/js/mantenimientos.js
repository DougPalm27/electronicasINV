let tablaMantenimientos = null;
let detalleRepuestos    = [];   // repuestos a instalar
let retirosDetalle      = [];   // piezas a retirar
let repuestosDisponibles = [];
let instaladosActuales   = [];  // piezas actualmente en la máquina seleccionada

const CTRL = "./modules/Electronicas/Mantenimientos/Controllers/mantenimientosController.php";

$(document).ready(function () {
    init();
});

function init() {
    listarMantenimientos();
    cargarSelects();
    listarRepuestosSelect();

    // ── Nuevo mantenimiento ──────────────────────────────────
    $("#btnNuevoMantenimiento").click(() => {
        limpiarModal();
        abrirModal();
    });

    // ── Guardar ──────────────────────────────────────────────
    $("#btnGuardarMantenimiento").click(guardar);

    // ── Agregar repuesto a instalar ──────────────────────────
    $("#btnAgregarRepuesto").click(function (e) {
        e.preventDefault();
        const id = $("#select_repuesto").val();
        if (id == "-1" || !id) { Swal.fire("Ups", "Selecciona un repuesto", "warning"); return; }

        const repuesto = repuestosDisponibles.find(r => r.id_repuesto == id);
        if (!repuesto) { Swal.fire("Ups", "Repuesto inválido", "warning"); return; }
        if (detalleRepuestos.some(r => r.id_repuesto == id)) {
            Swal.fire("Ups", "Ese repuesto ya fue agregado", "warning"); return;
        }

        const cantidad = parseInt($("#cantidad_repuesto").val()) || 1;
        validarStockYAgregar(repuesto, cantidad);
    });

    // ── Al cambiar máquina → cargar instalados ───────────────
    $("#id_maquina").change(function () {
        const id = $(this).val();
        if (id && id != "-1") {
            $("#bloqueRetiros").show();
            cargarInstalados(id);
        } else {
            $("#bloqueRetiros").hide();
            instaladosActuales = [];
            renderInstalados();
        }
    });

    // ── Cambio de repuesto en select ─────────────────────────
    $("#select_repuesto").change(function () {
        const repuesto = repuestosDisponibles.find(r => r.id_repuesto == $(this).val());
        if (repuesto) {
            $("#costo_unitario").val(repuesto.costo || 0);
            parseInt(repuesto.maneja_serie) === 1
                ? $("#bloqueCantidadRepuesto").hide()
                : $("#bloqueCantidadRepuesto").show();
        } else {
            $("#costo_unitario").val("");
            $("#bloqueCantidadRepuesto").show();
        }
    });

    // ── Retirar pieza (delegado) ─────────────────────────────
    $(document).on("click", ".btn-retirar-pieza", function () {
        const idx = parseInt($(this).data("idx"));
        const p   = instaladosActuales[idx];
        if (!p) return;

        // Verificar si ya está en la lista de retiros
        if (retirosDetalle.some(r => r.id_maquina_repuesto == p.id_maquina_repuesto)) {
            Swal.fire("Aviso", "Esa pieza ya está en la lista de retiros", "info");
            return;
        }

        const etiqueta = p.serie
            ? `<strong>${escHtml(p.repuesto)}</strong> — serie: <code>${escHtml(p.serie)}</code>`
            : `<strong>${escHtml(p.repuesto)}</strong> (cant: ${p.cantidad})`;

        Swal.fire({
            title:  "Retirar pieza",
            html: `
                <p class="mb-3">${etiqueta}</p>
                <div class="mb-3 text-start">
                    <label class="form-label fw-semibold">Tipo de retiro <span class="text-danger">*</span></label>
                    <select id="swal_tipo_retiro" class="form-select">
                        <option value="devolucion">Devolución a stock (pieza reutilizable)</option>
                        <option value="baja">Dar de baja (pieza descartada)</option>
                    </select>
                </div>
                <div class="text-start">
                    <label class="form-label fw-semibold">Observaciones</label>
                    <textarea id="swal_obs" class="form-control" rows="2"
                              placeholder="Ej: pieza desgastada, cambio preventivo..."></textarea>
                </div>`,
            width:             550,
            showCancelButton:  true,
            confirmButtonText: "Confirmar retiro",
            cancelButtonText:  "Cancelar",
            confirmButtonColor:"#dc3545",
            preConfirm: () => ({
                tipo_retiro:  $("#swal_tipo_retiro").val(),
                observaciones: $("#swal_obs").val().trim()
            })
        }).then(result => {
            if (!result.isConfirmed) return;
            retirosDetalle.push({
                id_maquina_repuesto: p.id_maquina_repuesto,
                id_repuesto:         p.id_repuesto,
                id_detalle_repuesto: p.id_detalle_repuesto,
                maneja_serie:        p.maneja_serie,
                cantidad:            p.cantidad,
                tipo_retiro:         result.value.tipo_retiro,
                observaciones:       result.value.observaciones,
                _nombre:             p.repuesto,
                _serie:              p.serie
            });
            renderRetiros();
        });
    });

    // ── Eliminar retiro de la lista ──────────────────────────
    $(document).on("click", ".btn-quitar-retiro", function () {
        const idx = parseInt($(this).data("idx"));
        retirosDetalle.splice(idx, 1);
        renderRetiros();
    });
}

// ════════════════════════════════════════════════════════════
// TABLA PRINCIPAL
// ════════════════════════════════════════════════════════════

function listarMantenimientos() {
    if ($.fn.DataTable.isDataTable("#tablaMantenimientos")) {
        $("#tablaMantenimientos").DataTable().destroy();
    }

    tablaMantenimientos = $("#tablaMantenimientos").DataTable({
        ajax: {
            url:      CTRL,
            type:     "POST",
            dataType: "json",
            data:     { accion: "listar" },
            dataSrc: function (json) {
                if (!json.ok) { console.error("ERROR:", json.mensaje); return []; }
                return json.data;
            },
            error: manejarError
        },
        columns: [
            { data: "maquina",               title: "Máquina" },
            { data: "tipo",                  title: "Tipo" },
            { data: "tecnico",               title: "Técnico" },
            { data: "fecha_mantenimiento",   title: "Fecha" },
            { data: "proximo_mantenimiento", title: "Próximo", defaultContent: "—" },
            { data: "descripcion",           title: "Descripción", defaultContent: "—" },
            {
                data: null, title: "Acciones", orderable: false, searchable: false,
                render: function (data, type, row) {
                    return `
                        <button class="btn btn-sm btn-info me-1"
                                onclick="verDetalle(${row.id_mantenimiento}, '${escHtml(row.maquina)}', '${escHtml(row.fecha_mantenimiento)}')"
                                title="Ver detalle">
                            <i class="bi bi-tools"></i>
                        </button>
                        <a class="btn btn-sm btn-danger"
                           href="./modules/Electronicas/Mantenimientos/reporte.php?id=${row.id_mantenimiento}"
                           target="_blank" title="Imprimir / PDF">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </a>`;
                }
            }
        ],
        language: { url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" }
    });
}

// ════════════════════════════════════════════════════════════
// PIEZAS INSTALADAS EN LA MÁQUINA
// ════════════════════════════════════════════════════════════

function cargarInstalados(id_maquina) {
    instaladosActuales = [];
    $("#instaladosContainer").html(
        '<p class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Cargando...</p>'
    );

    $.post(CTRL, { accion: "instalados", id_maquina }, function (resp) {
        if (!resp.ok) {
            $("#instaladosContainer").html('<p class="text-danger small">' + escHtml(resp.mensaje) + '</p>');
            return;
        }
        instaladosActuales = Array.isArray(resp.data) ? resp.data : [];
        renderInstalados();
    }, "json").fail(() => {
        $("#instaladosContainer").html('<p class="text-danger small">Error al cargar piezas instaladas.</p>');
    });
}

function renderInstalados() {
    if (!instaladosActuales.length) {
        $("#instaladosContainer").html(
            '<p class="text-muted small fst-italic">Esta máquina no tiene piezas instaladas registradas.</p>'
        );
        return;
    }

    let html = `
        <table class="table table-sm table-bordered mb-1">
            <thead class="table-light">
                <tr>
                    <th>Repuesto</th>
                    <th class="text-center">Serie / Cant.</th>
                    <th>Instalado</th>
                    <th style="width:90px"></th>
                </tr>
            </thead>
            <tbody>`;

    instaladosActuales.forEach((p, i) => {
        const yaEnLista = retirosDetalle.some(r => r.id_maquina_repuesto == p.id_maquina_repuesto);
        const badge = p.serie
            ? `<span class="badge bg-secondary">${escHtml(p.serie)}</span>`
            : `<span class="text-muted">cant: ${p.cantidad}</span>`;

        html += `
            <tr class="${yaEnLista ? 'table-warning' : ''}">
                <td>${escHtml(p.repuesto)}</td>
                <td class="text-center">${badge}</td>
                <td class="text-muted" style="font-size:.8rem">${p.fecha_instalacion}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-danger btn-retirar-pieza"
                            data-idx="${i}" ${yaEnLista ? 'disabled' : ''} title="Retirar pieza">
                        <i class="bi bi-arrow-return-left"></i>
                    </button>
                </td>
            </tr>`;
    });

    html += "</tbody></table>";
    $("#instaladosContainer").html(html);
}

function renderRetiros() {
    if (!retirosDetalle.length) {
        $("#bloqueTablaRetiros").hide();
        renderInstalados(); // actualiza botones
        return;
    }

    let html = "";
    retirosDetalle.forEach((r, i) => {
        const serie = r._serie
            ? `<span class="badge bg-secondary">${escHtml(r._serie)}</span>`
            : `<span class="text-muted">cant: ${r.cantidad}</span>`;

        const tipoBadge = r.tipo_retiro === "devolucion"
            ? '<span class="badge bg-success">Devolución</span>'
            : '<span class="badge bg-danger">Baja</span>';

        html += `
            <tr>
                <td>${escHtml(r._nombre)}</td>
                <td class="text-center">${serie}</td>
                <td class="text-center">${tipoBadge}</td>
                <td>${escHtml(r.observaciones || "—")}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-secondary btn-quitar-retiro"
                            data-idx="${i}" title="Quitar">
                        <i class="bi bi-x"></i>
                    </button>
                </td>
            </tr>`;
    });

    $("#tablaRetiros tbody").html(html);
    $("#bloqueTablaRetiros").show();
    renderInstalados(); // actualiza estado de botones
}

// ════════════════════════════════════════════════════════════
// DETALLE DE UN MANTENIMIENTO
// ════════════════════════════════════════════════════════════

function verDetalle(id_mantenimiento, maquina, fecha) {
    $("#modalDetalleLabel").text(`${maquina} — ${fecha}`);
    $("#tablaDetalleModal tbody").html(
        '<tr><td colspan="5" class="text-center"><div class="spinner-border spinner-border-sm me-2"></div>Cargando...</td></tr>'
    );
    $("#bloqueDetalleRetiros").hide();

    new bootstrap.Modal("#modalDetalle").show();

    $.post(CTRL, { accion: "detalle", id_mantenimiento }, function (resp) {
        if (!resp.ok) {
            $("#tablaDetalleModal tbody").html(
                `<tr><td colspan="5" class="text-danger text-center">${resp.mensaje}</td></tr>`
            );
            return;
        }

        // ── Instalados ─────────────────────────────────────────
        const instalados = resp.data.instalados || [];
        if (!instalados.length) {
            $("#tablaDetalleModal tbody").html(
                '<tr><td colspan="5" class="text-center text-muted">Sin repuestos instalados</td></tr>'
            );
        } else {
            let totalCosto = 0;
            let html = "";
            instalados.forEach(r => {
                const subtotal = parseFloat(r.subtotal || 0);
                totalCosto += subtotal;
                html += `
                    <tr>
                        <td>${escHtml(r.repuesto)}</td>
                        <td class="text-center">${r.cantidad}</td>
                        <td class="text-end">Q ${parseFloat(r.costo_unitario).toFixed(2)}</td>
                        <td class="text-end">Q ${subtotal.toFixed(2)}</td>
                        <td class="text-center">
                            ${r.serie ? `<span class="badge bg-secondary">${escHtml(r.serie)}</span>` : '<span class="text-muted">—</span>'}
                        </td>
                    </tr>`;
            });
            html += `
                <tr class="table-light fw-bold">
                    <td colspan="3" class="text-end">Total</td>
                    <td class="text-end">Q ${totalCosto.toFixed(2)}</td>
                    <td></td>
                </tr>`;
            $("#tablaDetalleModal tbody").html(html);
        }

        // ── Retiros ────────────────────────────────────────────
        const retiros = resp.data.retiros || [];
        if (retiros.length) {
            let htmlR = "";
            retiros.forEach(r => {
                const tipoBadge = r.tipo_retiro === "devolucion"
                    ? '<span class="badge bg-success">Devolución</span>'
                    : '<span class="badge bg-danger">Baja</span>';
                htmlR += `
                    <tr>
                        <td>${escHtml(r.repuesto)}</td>
                        <td class="text-center">${r.cantidad}</td>
                        <td class="text-center">
                            ${r.serie ? `<span class="badge bg-secondary">${escHtml(r.serie)}</span>` : '<span class="text-muted">—</span>'}
                        </td>
                        <td class="text-center">${tipoBadge}</td>
                        <td>${escHtml(r.observaciones_retiro || "—")}</td>
                    </tr>`;
            });
            $("#tablaDetalleRetiros tbody").html(htmlR);
            $("#bloqueDetalleRetiros").show();
        }
    }, "json").fail(manejarError);
}

// ════════════════════════════════════════════════════════════
// SELECTS
// ════════════════════════════════════════════════════════════

function cargarSelects() {
    cargarSelect("maquinas", "#id_maquina",  "id_maquina");
    cargarSelect("tipos",    "#id_tipo",     "id_tipo");
    cargarSelect("tecnicos", "#id_tecnico",  "id_tecnico");
}

function cargarSelect(accion, selector, valueKey) {
    $.post(CTRL, { accion }, function (resp) {
        if (!resp.ok) { console.error(resp.mensaje); return; }
        let html = '<option value="-1">Seleccione</option>';
        resp.data.forEach(e => {
            html += `<option value="${e[valueKey]}">${e.nombre}</option>`;
        });
        $(selector).html(html);
    }, "json");
}

function listarRepuestosSelect() {
    $.post(CTRL, { accion: "repuestosDisponibles" }, function (resp) {
        if (!resp.ok) { console.error(resp.mensaje); return; }
        repuestosDisponibles = Array.isArray(resp.data) ? resp.data : [];

        let html = '<option value="-1">Seleccione repuesto</option>';
        repuestosDisponibles.forEach(r => {
            const stock    = parseInt(r.stock);
            const etiqueta = parseInt(r.maneja_serie) === 1
                ? ` [series: ${stock}]` : ` [stock: ${stock}]`;
            const disabled = stock <= 0 ? " disabled" : "";
            html += `<option value="${r.id_repuesto}"${disabled}>${r.nombre}${etiqueta}</option>`;
        });
        $("#select_repuesto").html(html);
    }, "json");
}

// ════════════════════════════════════════════════════════════
// STOCK Y AGREGAR REPUESTO A INSTALAR
// ════════════════════════════════════════════════════════════

function validarStockYAgregar(repuesto, cantidad) {
    $.post(CTRL, {
        accion:       "validarStock",
        id_repuesto:  repuesto.id_repuesto,
        maneja_serie: repuesto.maneja_serie,
        cantidad:     parseInt(repuesto.maneja_serie) === 1 ? 1 : cantidad
    }, function (resp) {
        if (!resp.ok) { Swal.fire("Sin stock", resp.mensaje, "warning"); return; }

        const item = {
            id_repuesto:    repuesto.id_repuesto,
            nombre:         repuesto.nombre,
            costo_unitario: parseFloat($("#costo_unitario").val()) || 0,
            maneja_serie:   parseInt(repuesto.maneja_serie),
            cantidad:       1,
            series:         []
        };

        if (item.maneja_serie === 1) {
            seleccionarSeriesParaRepuesto(item);
        } else {
            item.cantidad = cantidad;
            if (item.cantidad <= 0) { Swal.fire("Ups", "Cantidad inválida", "warning"); return; }
            if (item.cantidad > resp.data.stock) {
                Swal.fire("Sin stock", `Cantidad (${item.cantidad}) supera el disponible (${resp.data.stock})`, "warning");
                return;
            }
            detalleRepuestos.push(item);
            removerRepuestoDeSelect(repuesto.id_repuesto);
            resetSelectorRepuesto();
            renderDetalle();
        }
    }, "json").fail(manejarError);
}

function seleccionarSeriesParaRepuesto(item) {
    $.post(CTRL, { accion: "seriesDisponibles", id_repuesto: item.id_repuesto }, function (resp) {
        if (!resp.ok) { Swal.fire("Error", resp.mensaje, "error"); return; }
        if (!resp.data || !resp.data.length) {
            Swal.fire("Ups", "No hay series disponibles", "warning"); return;
        }

        const html = `<div class="text-start">${resp.data.map(s => `
            <div class="form-check">
                <input class="form-check-input chk-serie" type="checkbox"
                       value="${s.id_detalle_repuesto}" id="serie_${s.id_detalle_repuesto}">
                <label class="form-check-label" for="serie_${s.id_detalle_repuesto}">
                    ${escHtml(s.serie)}
                </label>
            </div>`).join("")}
        </div>`;

        Swal.fire({
            title: "Selecciona series", html, width: 600,
            showCancelButton: true, confirmButtonText: "Agregar",
            preConfirm: () => {
                const sel = [];
                $(".chk-serie:checked").each(function () { sel.push($(this).val()); });
                if (!sel.length) { Swal.showValidationMessage("Selecciona al menos una serie"); return false; }
                return sel;
            }
        }).then(result => {
            if (!result.isConfirmed) return;
            item.series   = result.value;
            item.cantidad = result.value.length;
            detalleRepuestos.push(item);
            removerRepuestoDeSelect(item.id_repuesto);
            resetSelectorRepuesto();
            renderDetalle();
        });
    }, "json").fail(manejarError);
}

function removerRepuestoDeSelect(id) {
    $(`#select_repuesto option[value="${id}"]`).remove();
}

function resetSelectorRepuesto() {
    $("#select_repuesto").val("-1");
    $("#costo_unitario").val("");
    $("#cantidad_repuesto").val("1");
    $("#bloqueCantidadRepuesto").show();
}

// ════════════════════════════════════════════════════════════
// RENDER DETALLE REPUESTOS A INSTALAR
// ════════════════════════════════════════════════════════════

function renderDetalle() {
    let html = "";
    detalleRepuestos.forEach((r, i) => {
        const tipoUso = parseInt(r.maneja_serie) === 1
            ? `Series: ${r.cantidad}` : `Cantidad: ${r.cantidad}`;

        html += `
            <tr>
                <td>${escHtml(r.nombre)}</td>
                <td>${tipoUso}</td>
                <td>Q ${parseFloat(r.costo_unitario || 0).toFixed(2)}</td>
                <td>
                    <button class="btn btn-sm btn-danger" onclick="eliminarItem(${i})">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>`;
    });
    $("#tablaDetalleRepuestos tbody").html(html);
}

function eliminarItem(index) {
    detalleRepuestos.splice(index, 1);
    listarRepuestosSelect();
    renderDetalle();
}

// ════════════════════════════════════════════════════════════
// GUARDAR
// ════════════════════════════════════════════════════════════

function guardar() {
    const d = {
        id_maquina:            $("#id_maquina").val(),
        id_tipo:               $("#id_tipo").val(),
        id_tecnico:            $("#id_tecnico").val(),
        fecha_mantenimiento:   $("#fecha_mantenimiento").val(),
        proximo_mantenimiento: $("#proximo_mantenimiento").val(),
        descripcion:           $("#descripcion").val(),
        repuestos:             detalleRepuestos,
        retiros:               retirosDetalle
    };

    if (!d.id_maquina || d.id_maquina == "-1") {
        Swal.fire("Ups", "Selecciona una máquina", "warning"); return;
    }
    if (!d.id_tipo || d.id_tipo == "-1") {
        Swal.fire("Ups", "Selecciona un tipo de mantenimiento", "warning"); return;
    }
    if (!d.fecha_mantenimiento) {
        Swal.fire("Ups", "Selecciona una fecha", "warning"); return;
    }

    $.post(CTRL, { accion: "guardar", losDatos: JSON.stringify(d) },
        manejarRespuesta, "json"
    ).fail(manejarError);
}

// ════════════════════════════════════════════════════════════
// UTILIDADES
// ════════════════════════════════════════════════════════════

function manejarRespuesta(resp) {
    if (!resp.ok) { Swal.fire("Error", resp.mensaje || "No se pudo guardar", "error"); return; }
    cerrarModal();
    limpiarModal();
    listarMantenimientos();
    listarRepuestosSelect();
    Swal.fire({ icon: "success", title: "Guardado correctamente", timer: 1800, showConfirmButton: false });
}

function manejarError(e) {
    console.error(e.responseText);
    Swal.fire("Error", "Error en servidor", "error");
}

function abrirModal() {
    new bootstrap.Modal("#modalMantenimiento").show();
}

function cerrarModal() {
    const el    = document.getElementById("modalMantenimiento");
    const modal = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
    modal.hide();
}

function limpiarModal() {
    $("#formMantenimiento")[0].reset();
    detalleRepuestos  = [];
    retirosDetalle    = [];
    instaladosActuales = [];
    $("#cantidad_repuesto").val("1");
    $("#bloqueRetiros").hide();
    $("#bloqueTablaRetiros").hide();
    $("#instaladosContainer").html(
        '<p class="text-muted small">Selecciona una máquina para ver sus piezas instaladas.</p>'
    );
    renderDetalle();
}

function escHtml(str) {
    if (!str) return "";
    return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}
