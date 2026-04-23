let tablaMantenimientos = null;
let detalleRepuestos    = [];
let repuestosDisponibles = [];

const CTRL = "./modules/Electronicas/Mantenimientos/Controllers/mantenimientosController.php";

$(document).ready(function () {
  init();

  $(document).ajaxError(function (event, xhr) {
    console.error("❌ AJAX ERROR:", xhr.responseText);
  });
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

  // ── Agregar repuesto ─────────────────────────────────────
  $("#btnAgregarRepuesto").click(function (e) {
    e.preventDefault();

    const id = $("#select_repuesto").val();
    if (id == "-1" || !id) {
      Swal.fire("Ups", "Selecciona un repuesto", "warning");
      return;
    }

    const repuesto = repuestosDisponibles.find((r) => r.id_repuesto == id);
    if (!repuesto) {
      Swal.fire("Ups", "Repuesto inválido", "warning");
      return;
    }

    const yaExiste = detalleRepuestos.some((r) => r.id_repuesto == id);
    if (yaExiste) {
      Swal.fire("Ups", "Ese repuesto ya fue agregado", "warning");
      return;
    }

    const cantidad = parseInt($("#cantidad_repuesto").val()) || 1;

    // Validar stock antes de continuar
    validarStockYAgregar(repuesto, cantidad);
  });

  // ── Cambio de repuesto en select ─────────────────────────
  $("#select_repuesto").change(function () {
    const id      = $(this).val();
    const repuesto = repuestosDisponibles.find((r) => r.id_repuesto == id);

    if (repuesto) {
      $("#costo_unitario").val(repuesto.costo || 0);
      if (parseInt(repuesto.maneja_serie) === 1) {
        $("#bloqueCantidadRepuesto").hide();
      } else {
        $("#bloqueCantidadRepuesto").show();
      }
    } else {
      $("#costo_unitario").val("");
      $("#bloqueCantidadRepuesto").show();
    }
  });
}

//////////////////////////////////////////////////////////
// TABLA PRINCIPAL
//////////////////////////////////////////////////////////

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
        if (!json.ok) {
          console.error("❌ ERROR:", json.mensaje);
          return [];
        }
        return json.data;
      },
      error: manejarError,
    },
    columns: [
      { data: "maquina",               title: "Máquina" },
      { data: "tipo",                  title: "Tipo" },
      { data: "tecnico",               title: "Técnico" },
      { data: "fecha_mantenimiento",   title: "Fecha" },
      { data: "proximo_mantenimiento", title: "Próximo" },
      { data: "descripcion",           title: "Descripción" },
      {
        data:       null,
        title:      "Detalle",
        orderable:  false,
        searchable: false,
        render: function (data, type, row) {
          return `
            <button class="btn btn-sm btn-outline-info"
                    onclick="verDetalle(${row.id_mantenimiento}, '${escHtml(row.maquina)}', '${escHtml(row.fecha_mantenimiento)}')">
              <i class="bi bi-tools"></i> Ver
            </button>`;
        }
      }
    ],
    language: {
      url: "./modules/Electronicas/Mantenimientos/js/es-ES.json",
    },
  });
}

//////////////////////////////////////////////////////////
// DETALLE DE UN MANTENIMIENTO
//////////////////////////////////////////////////////////

function verDetalle(id_mantenimiento, maquina, fecha) {
  $("#modalDetalleLabel").text(`Repuestos — ${maquina} (${fecha})`);
  $("#tablaDetalleModal tbody").html(`
    <tr><td colspan="5" class="text-center text-muted">
      <div class="spinner-border spinner-border-sm me-2"></div>Cargando...
    </td></tr>`);

  new bootstrap.Modal("#modalDetalle").show();

  $.ajax({
    url:      CTRL,
    type:     "POST",
    dataType: "json",
    data:     { accion: "detalle", id_mantenimiento },
    success: function (resp) {
      if (!resp.ok) {
        $("#tablaDetalleModal tbody").html(
          `<tr><td colspan="5" class="text-danger text-center">${resp.mensaje}</td></tr>`
        );
        return;
      }

      if (!resp.data || resp.data.length === 0) {
        $("#tablaDetalleModal tbody").html(
          `<tr><td colspan="5" class="text-center text-muted">Sin repuestos registrados</td></tr>`
        );
        return;
      }

      let totalCosto = 0;
      let html = "";

      resp.data.forEach((r) => {
        const subtotal = parseFloat(r.subtotal || 0);
        totalCosto += subtotal;

        const serieTag = r.serie
          ? `<span class="badge bg-secondary">${escHtml(r.serie)}</span>`
          : `<span class="text-muted">—</span>`;

        html += `
          <tr>
            <td>${escHtml(r.repuesto)}</td>
            <td class="text-center">${r.cantidad}</td>
            <td class="text-end">Q ${parseFloat(r.costo_unitario).toFixed(2)}</td>
            <td class="text-end">Q ${subtotal.toFixed(2)}</td>
            <td class="text-center">${serieTag}</td>
          </tr>`;
      });

      html += `
        <tr class="table-light fw-bold">
          <td colspan="3" class="text-end">Total</td>
          <td class="text-end">Q ${totalCosto.toFixed(2)}</td>
          <td></td>
        </tr>`;

      $("#tablaDetalleModal tbody").html(html);
    },
    error: manejarError,
  });
}

//////////////////////////////////////////////////////////
// SELECTS
//////////////////////////////////////////////////////////

function cargarSelects() {
  cargarSelect("maquinas", "#id_maquina", "id_maquina");
  cargarSelect("tipos",    "#id_tipo",    "id_tipo");
  cargarSelect("tecnicos", "#id_tecnico", "id_tecnico");
}

function cargarSelect(accion, selector, valueKey) {
  $.ajax({
    url:      CTRL,
    type:     "POST",
    dataType: "json",
    data:     { accion },
    success: function (resp) {
      if (!resp.ok) { console.error(resp.mensaje); return; }
      let html = `<option value="-1">Seleccione</option>`;
      resp.data.forEach((e) => {
        html += `<option value="${e[valueKey]}">${e.nombre}</option>`;
      });
      $(selector).html(html);
    },
    error: manejarError,
  });
}

//////////////////////////////////////////////////////////
// REPUESTOS
//////////////////////////////////////////////////////////

function listarRepuestosSelect() {
  $.ajax({
    url:      CTRL,
    type:     "POST",
    dataType: "json",
    data:     { accion: "repuestosDisponibles" },
    success: function (resp) {
      if (!resp.ok) { console.error(resp.mensaje); return; }

      repuestosDisponibles = Array.isArray(resp.data) ? resp.data : [];

      let html = `<option value="-1">Seleccione repuesto</option>`;
      repuestosDisponibles.forEach((r) => {
        const stock = parseInt(r.stock);
        const etiqueta = parseInt(r.maneja_serie) === 1
          ? ` [series: ${stock}]`
          : ` [stock: ${stock}]`;
        const disabled = stock <= 0 ? " disabled" : "";

        html += `<option value="${r.id_repuesto}"${disabled}>${r.nombre}${etiqueta}</option>`;
      });

      $("#select_repuesto").html(html);
    },
    error: manejarError,
  });
}

//////////////////////////////////////////////////////////
// VALIDAR STOCK Y AGREGAR REPUESTO
//////////////////////////////////////////////////////////

function validarStockYAgregar(repuesto, cantidad) {
  $.ajax({
    url:      CTRL,
    type:     "POST",
    dataType: "json",
    data: {
      accion:       "validarStock",
      id_repuesto:  repuesto.id_repuesto,
      maneja_serie: repuesto.maneja_serie,
      cantidad:     parseInt(repuesto.maneja_serie) === 1 ? 1 : cantidad,
    },
    success: function (resp) {
      if (!resp.ok) {
        Swal.fire("Sin stock", resp.mensaje, "warning");
        return;
      }

      // Stock OK → construir item
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
        if (item.cantidad <= 0) {
          Swal.fire("Ups", "Cantidad inválida", "warning");
          return;
        }
        if (item.cantidad > resp.data.stock) {
          Swal.fire("Sin stock", `Cantidad solicitada (${item.cantidad}) supera el stock disponible (${resp.data.stock})`, "warning");
          return;
        }
        detalleRepuestos.push(item);
        removerRepuestoDeSelect(repuesto.id_repuesto);
        resetSelectorRepuesto();
        renderDetalle();
      }
    },
    error: manejarError,
  });
}

function seleccionarSeriesParaRepuesto(item) {
  $.ajax({
    url:      CTRL,
    type:     "POST",
    dataType: "json",
    data:     { accion: "seriesDisponibles", id_repuesto: item.id_repuesto },
    success: function (resp) {
      if (!resp.ok) { Swal.fire("Error", resp.mensaje, "error"); return; }

      if (!resp.data || resp.data.length === 0) {
        Swal.fire("Ups", "No hay series disponibles para este repuesto", "warning");
        return;
      }

      const html = `
        <div class="text-start">
          ${resp.data.map(s => `
            <div class="form-check">
              <input class="form-check-input chk-serie" type="checkbox"
                     value="${s.id_detalle_repuesto}" id="serie_${s.id_detalle_repuesto}">
              <label class="form-check-label" for="serie_${s.id_detalle_repuesto}">
                ${escHtml(s.serie)}
              </label>
            </div>
          `).join("")}
        </div>`;

      Swal.fire({
        title:             "Selecciona series",
        html:              html,
        width:             600,
        showCancelButton:  true,
        confirmButtonText: "Agregar",
        preConfirm: () => {
          const seleccionadas = [];
          $(".chk-serie:checked").each(function () {
            seleccionadas.push($(this).val());
          });
          if (seleccionadas.length === 0) {
            Swal.showValidationMessage("Debes seleccionar al menos una serie");
            return false;
          }
          return seleccionadas;
        }
      }).then((result) => {
        if (!result.isConfirmed) return;
        item.series   = result.value;
        item.cantidad = result.value.length;
        detalleRepuestos.push(item);
        removerRepuestoDeSelect(item.id_repuesto);
        resetSelectorRepuesto();
        renderDetalle();
      });
    },
    error: manejarError,
  });
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

//////////////////////////////////////////////////////////
// GUARDAR
//////////////////////////////////////////////////////////

function guardar() {
  const d = {
    id_maquina:             $("#id_maquina").val(),
    id_tipo:                $("#id_tipo").val(),
    id_tecnico:             $("#id_tecnico").val(),
    fecha_mantenimiento:    $("#fecha_mantenimiento").val(),
    proximo_mantenimiento:  $("#proximo_mantenimiento").val(),
    descripcion:            $("#descripcion").val(),
    repuestos:              detalleRepuestos,
  };

  if (d.id_maquina == "-1" || !d.id_maquina) {
    Swal.fire("Ups", "Selecciona una máquina", "warning"); return;
  }
  if (d.id_tipo == "-1" || !d.id_tipo) {
    Swal.fire("Ups", "Selecciona un tipo de mantenimiento", "warning"); return;
  }
  if (!d.fecha_mantenimiento) {
    Swal.fire("Ups", "Selecciona una fecha", "warning"); return;
  }

  $.ajax({
    url:      CTRL,
    type:     "POST",
    dataType: "json",
    data:     { accion: "guardar", losDatos: JSON.stringify(d) },
    success:  manejarRespuesta,
    error:    manejarError,
  });
}

//////////////////////////////////////////////////////////
// UTILIDADES
//////////////////////////////////////////////////////////

function manejarRespuesta(resp) {
  if (!resp.ok) {
    Swal.fire("Error", resp.mensaje || "No se pudo guardar", "error");
    return;
  }
  Swal.fire("Excelente", "Guardado correctamente", "success").then(() => {
    cerrarModal();
    limpiarModal();
    listarMantenimientos();
    listarRepuestosSelect();
  });
}

function manejarError(e) {
  console.error(e.responseText);
  Swal.fire("Error", "Error en servidor", "error");
}

function abrirModal() {
  new bootstrap.Modal("#modalMantenimiento").show();
}

function cerrarModal() {
  const modal = bootstrap.Modal.getInstance(document.getElementById("modalMantenimiento"));
  if (modal) modal.hide();
  $(".modal-backdrop").remove();
  $("body").removeClass("modal-open");
}

function limpiarModal() {
  $("#formMantenimiento")[0].reset();
  detalleRepuestos = [];
  $("#cantidad_repuesto").val("1");
  renderDetalle();
}

function renderDetalle() {
  let html = "";
  detalleRepuestos.forEach((r, i) => {
    const tipoUso = parseInt(r.maneja_serie) === 1
      ? `Series: ${r.cantidad}`
      : `Cantidad: ${r.cantidad}`;

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

/** Escapa HTML para evitar XSS en renders manuales */
function escHtml(str) {
  if (!str) return "";
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}
