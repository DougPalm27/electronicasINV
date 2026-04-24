let tablaRepuestos = null;

$(document).ready(function () {
  init();
});

//////////////////////////////////////////////////////////
// INIT
//////////////////////////////////////////////////////////

function init() {
  listarRepuestos();
  listarProveedores();
  initSelect2();
  cargarTipos();
  cargarMarcas();
  cargarDivisas();

  $("#modalSalida").on("shown.bs.modal", function () {
    $("#series_salida").select2({
      theme: "bootstrap-5",
      width: "100%",
      dropdownParent: $("#modalSalida"),
      placeholder: "Selecciona series",
    });
  });

  $("#btnNuevoRepuesto").click(() => {
    limpiarModalRepuesto();
    $("#btnGuardarRepuesto").show();
    $("#btnEditarRepuesto").addClass("d-none");
    abrirModal("#modalRepuesto");
  });

  $("#id_marca").on("change", function () {
    cargarModelos($(this).val());
  });

  $("#btnGuardarRepuesto").click(guardarRepuesto);
  $("#btnEditarRepuesto").click(editarRepuesto);

  $("#modalRepuesto").on("hidden.bs.modal", limpiarModalRepuesto);

  $("#maneja_serie").on("change", function () {
    if ($(this).val() == "1") {
      $("#bloqueStock").hide();
      $("#bloqueSerie").fadeIn();
    } else {
      $("#bloqueSerie").hide();
      $("#bloqueStock").fadeIn();
    }
  });

  $("#modalRepuesto").on("shown.bs.modal", function () {
    $("#maneja_serie").trigger("change");
  });

  // Actualizar símbolo al cambiar divisa
  $("#id_divisa").on("change", function () {
    const simbolo = $(this).find("option:selected").data("simbolo") || "L.";
    $("#simbolo_divisa").text(simbolo);
  });
}

//////////////////////////////////////////////////////////
// TABLA PRINCIPAL
//////////////////////////////////////////////////////////

function listarRepuestos() {
  if ($.fn.DataTable.isDataTable("#tablaRepuestos")) {
    $("#tablaRepuestos").DataTable().destroy();
  }

  tablaRepuestos = $("#tablaRepuestos").DataTable({
    ajax: {
      url: "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
      type: "POST",
      data: { accion: "listar" },
      dataType: "json",
      dataSrc: function (json) {
        if (!json.ok) return [];
        return json.data;
      },
      error: function () {
        Swal.fire("Error", "No se pudo cargar la tabla de repuestos", "error");
      },
    },

    columns: [
      { data: "nombre" },
      { data: "numero_parte" },
      { data: "proveedor" },
      {
        data: "stock",
        render: function (d, type, row) {
          const bajo = row.stock_minimo && d <= row.stock_minimo;
          return `<span class="badge ${bajo ? "bg-danger" : "bg-dark"}">${d}</span>`;
        },
      },
      {
        data: "costo_promedio",
        render: (d, t, row) => `${row.divisa_simbolo ?? window.DIVISA?.simbolo ?? 'L.'} ${parseFloat(d || 0).toFixed(2)}`,
      },
      {
        data: null,
        render: (data, type, row) => {
          let botones = `
            <button class="btn btn-warning btn-sm"
                onclick='cargarEditarRepuesto(${JSON.stringify(row)})'
                title="Editar">
                <i class="bi bi-pencil"></i>
            </button>`;

          if (row.maneja_serie == 1) {
            botones += `
              <button class="btn btn-secondary btn-sm" onclick="verDetalle(${row.id_repuesto})" title="Ver detalle por serie">
                <i class="bi bi-box"></i>
              </button>
              <button class="btn btn-success btn-sm" onclick="abrirEntrada(${row.id_repuesto}, 1)" title="Registrar entrada por serie">
                <i class="bi bi-plus-circle"></i>
              </button>
              <button class="btn btn-primary btn-sm" onclick="abrirSalida(${row.id_repuesto}, 1)" title="Registrar salida por serie">
                <i class="bi bi-arrow-up-circle"></i>
              </button>`;
          } else {
            botones += `
              <button class="btn btn-info btn-sm" onclick="verKardex(${row.id_repuesto})" title="Ver kardex">
                <i class="bi bi-list-ul"></i>
              </button>
              <button class="btn btn-success btn-sm" onclick="abrirEntrada(${row.id_repuesto}, 0)" title="Registrar entrada por cantidad">
                <i class="bi bi-plus-circle"></i>
              </button>
              <button class="btn btn-primary btn-sm" onclick="abrirSalida(${row.id_repuesto}, 0)" title="Registrar salida por cantidad">
                <i class="bi bi-arrow-up-circle"></i>
              </button>`;
          }

          botones += `
            <button class="btn btn-danger btn-sm" onclick="eliminarRepuesto(${row.id_repuesto})" title="Desechar repuesto">
              <i class="bi bi-trash"></i>
            </button>`;

          return botones;
        },
      },
    ],
  });
}

//////////////////////////////////////////////////////////
// DIVISAS
//////////////////////////////////////////////////////////

function cargarDivisas() {
  $.post(
    "./modules/Parametrizacion/Divisas/controllers/divisasController.php",
    { accion: "listar" },
    function (resp) {
      if (!resp.ok) return;

      const activas = resp.data.filter(d => d.activo == 1);
      let html = '<option value="">— Selecciona —</option>';
      activas.forEach(d => {
        html += `<option value="${d.id_divisa}" data-simbolo="${d.simbolo}">
                   ${d.simbolo} ${d.codigo}
                 </option>`;
      });

      $("#id_divisa").html(html);

      // Seleccionar la predeterminada por defecto
      const pred = activas.find(d => d.predeterminada == 1);
      if (pred) {
        $("#id_divisa").val(pred.id_divisa).trigger("change");
      }
    },
    "json"
  );
}

//////////////////////////////////////////////////////////
// PROVEEDORES
//////////////////////////////////////////////////////////

function listarProveedores() {
  $.ajax({
    url: "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
    type: "POST",
    dataType: "json",
    data: { accion: "proveedores" },
    success: function (resp) {
      if (!resp.ok) return;

      let html = `<option value="-1">-- Selecciona --</option>`;
      resp.data.forEach((p) => {
        html += `<option value="${p.id_proveedor}">${p.nombre}</option>`;
      });

      $("#id_proveedor").html(html);
    },
  });
}

//////////////////////////////////////////////////////////
// FORMULARIO — DATOS Y VALIDACIÓN
//////////////////////////////////////////////////////////

function obtenerDatosFormulario() {
  return {
    nombre:       $("#nombre").val().trim(),
    numero_parte: $("#numero_parte").val().trim(),
    id_proveedor: $("#id_proveedor").val(),
    costo:        parseFloat($("#costo").val()) || 0,
    stock_minimo: parseInt($("#stock_minimo").val()) || 0,
    comentarios:  $("#comentarios").val().trim(),
    id_tipo:      $("#id_tipo").val(),
    id_marca:     $("#id_marca").val(),
    id_modelo:    $("#id_modelo").val(),
    maneja_serie: $("#maneja_serie").val(),
    id_divisa:    $("#id_divisa").val(),
  };
}

function validarFormularioRepuesto(d) {
  limpiarErrores("#formRepuesto");
  let valido = true;

  if (!d.nombre) {
    marcarInvalido("#nombre", "El nombre es requerido");
    valido = false;
  }

  if (!d.id_proveedor || d.id_proveedor == "-1") {
    marcarInvalidoSelect2("#id_proveedor", "Debes seleccionar un proveedor");
    valido = false;
  }

  if (d.maneja_serie == "0" && d.costo < 0) {
    marcarInvalido("#costo", "El costo no puede ser negativo");
    valido = false;
  }

  if (d.maneja_serie == "0" && d.stock_minimo < 0) {
    marcarInvalido("#stock_minimo", "El stock mínimo no puede ser negativo");
    valido = false;
  }

  return valido;
}

function guardarRepuesto() {
  let d = obtenerDatosFormulario();
  if (!validarFormularioRepuesto(d)) return;

  $.ajax({
    url: "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
    type: "POST",
    dataType: "json",
    data: { accion: "guardar", ...d },
    success: function (resp) {
      if (!resp.ok) {
        Swal.fire("Error", resp.mensaje, "error");
        return;
      }
      cerrarModal("#modalRepuesto");
      listarRepuestos();
      Swal.fire({ icon: "success", title: "Listo", text: "Repuesto creado correctamente", timer: 1800, showConfirmButton: false });
    },
  });
}

function editarRepuesto() {
  let d = obtenerDatosFormulario();
  if (!validarFormularioRepuesto(d)) return;

  d.id_repuesto = $("#id_repuesto").val();

  $.ajax({
    url: "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
    type: "POST",
    dataType: "json",
    data: { accion: "editar", ...d },
    success: function (resp) {
      if (!resp.ok) {
        Swal.fire("Error", resp.mensaje, "error");
        return;
      }
      cerrarModal("#modalRepuesto");
      listarRepuestos();
      Swal.fire({ icon: "success", title: "Listo", text: "Repuesto actualizado", timer: 1800, showConfirmButton: false });
    },
  });
}

function eliminarRepuesto(id) {
  Swal.fire({
    title: "¿Desechar repuesto?",
    text: "El repuesto quedará inactivo",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, desechar",
    cancelButtonText: "Cancelar",
  }).then((result) => {
    if (!result.isConfirmed) return;

    $.post(
      "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
      { accion: "eliminar", id_repuesto: id },
      function (resp) {
        if (!resp.ok) {
          Swal.fire("Error", resp.mensaje, "error");
          return;
        }
        Swal.fire("Listo", "Repuesto desechado", "success");
        listarRepuestos();
      },
      "json"
    );
  });
}

function cargarEditarRepuesto(row) {
  limpiarModalRepuesto();

  $("#id_repuesto").val(row.id_repuesto);
  $("#nombre").val(row.nombre);
  $("#numero_parte").val(row.numero_parte);
  $("#id_proveedor").val(row.id_proveedor).trigger("change");
  $("#costo").val(row.costo_promedio);
  $("#stock_minimo").val(row.stock_minimo);
  $("#comentarios").val(row.comentarios);
  $("#id_tipo").val(row.id_tipo).trigger("change");
  $("#id_marca").val(row.id_marca).trigger("change");

  // Divisa del repuesto
  if (row.id_divisa) {
    $("#id_divisa").val(row.id_divisa).trigger("change");
  }

  setTimeout(() => {
    $("#id_modelo").val(row.id_modelo).trigger("change");
  }, 300);

  $("#maneja_serie").val(row.maneja_serie).trigger("change");

  $("#btnGuardarRepuesto").hide();
  $("#btnEditarRepuesto").removeClass("d-none");

  abrirModal("#modalRepuesto");
}

//////////////////////////////////////////////////////////
// ENTRADA
//////////////////////////////////////////////////////////

function abrirEntrada(id, manejaSerie) {
  limpiarEntrada();
  $("#id_repuesto_mov").val(id);
  $("#maneja_serie_mov").val(manejaSerie);

  if (manejaSerie == 1) {
    $("#entradaStock").hide();
    $("#entradaSerie").show();
  } else {
    $("#entradaSerie").hide();
    $("#entradaStock").show();
  }

  abrirModal("#modalEntrada");
}

function guardarEntrada() {
  let id          = $("#id_repuesto_mov").val();
  let manejaSerie = $("#maneja_serie_mov").val();

  if (!id) {
    Swal.fire("Error", "Repuesto inválido", "error");
    return;
  }

  limpiarErrores("#modalEntrada");

  if (manejaSerie == "1") {
    let raw = $("#series_input").val();

    if (!raw.trim()) {
      marcarInvalido("#series_input", "Ingresa al menos una serie");
      return;
    }

    let series = raw.split("\n").map((s) => s.trim()).filter((s) => s !== "");

    if (series.length === 0) {
      marcarInvalido("#series_input", "No hay series válidas");
      return;
    }

    $.ajax({
      url: "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
      type: "POST",
      dataType: "json",
      data: { accion: "entradaSerie", id_repuesto: id, series: series },
      success: function (resp) {
        if (!resp.ok) {
          Swal.fire("Error", resp.mensaje || "Error al guardar", "error");
          return;
        }

        cerrarModal("#modalEntrada");
        listarRepuestos();

        if (resp.duplicadas && resp.duplicadas.length > 0) {
          Swal.fire({
            icon: "warning",
            title: "Registro parcial",
            html: `Se registraron <b>${resp.insertadas}</b> series.<br><b>Duplicadas:</b><br>${resp.duplicadas.join("<br>")}`,
          });
        } else {
          Swal.fire({ icon: "success", title: "Listo", text: `${resp.insertadas} serie(s) registrada(s)`, timer: 1800, showConfirmButton: false });
        }
      },
      error: function () {
        Swal.fire("Error", "Error de servidor", "error");
      },
    });

  } else {
    let cantidad = parseInt($("#cantidad_mov").val());
    let costo    = parseFloat($("#costo_mov").val());
    let valido   = true;

    if (!cantidad || cantidad <= 0) {
      marcarInvalido("#cantidad_mov", "Ingresa una cantidad válida");
      valido = false;
    }

    if (isNaN(costo) || costo < 0) {
      marcarInvalido("#costo_mov", "Ingresa un costo válido");
      valido = false;
    }

    if (!valido) return;

    $.ajax({
      url: "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
      type: "POST",
      dataType: "json",
      data: { accion: "entrada", id_repuesto: id, cantidad: cantidad, costo: costo, referencia: "COMPRA" },
      success: function (resp) {
        if (!resp.ok) {
          Swal.fire("Error", resp.mensaje || "Error al guardar", "error");
          return;
        }
        cerrarModal("#modalEntrada");
        listarRepuestos();
        Swal.fire({ icon: "success", title: "Listo", text: "Entrada registrada correctamente", timer: 1800, showConfirmButton: false });
      },
      error: function () {
        Swal.fire("Error", "Error de servidor", "error");
      },
    });
  }
}

//////////////////////////////////////////////////////////
// SALIDA
//////////////////////////////////////////////////////////

function abrirSalida(id_repuesto, manejaSerie) {
  limpiarErrores("#modalSalida");
  $("#id_repuesto_salida").val(id_repuesto);
  $("#maneja_serie_salida").val(manejaSerie);
  $("#id_maquina_salida").val("").removeClass("is-invalid");
  $("#referencia_salida").val("MANTENIMIENTO");
  $("#cantidad_salida").val("");
  $("#costo_salida").val("");
  $("#series_salida").html("").trigger("change");

  if (parseInt(manejaSerie) === 1) {
    $("#bloqueSalidaCantidad").hide();
    $("#bloqueSalidaSerie").show();
    cargarSeriesDisponibles(id_repuesto);
  } else {
    $("#bloqueSalidaSerie").hide();
    $("#bloqueSalidaCantidad").show();
  }

  abrirModal("#modalSalida");
}

function cargarSeriesDisponibles(id_repuesto) {
  $.ajax({
    url: "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
    type: "POST",
    dataType: "json",
    data: { accion: "seriesDisponibles", id_repuesto: id_repuesto },
    success: function (resp) {
      if (!resp.ok) {
        Swal.fire("Error", resp.mensaje || "No se pudieron cargar las series", "error");
        return;
      }

      let html = "";
      resp.data.forEach((item) => {
        html += `<option value="${item.id_detalle_repuesto}">${item.serie}</option>`;
      });

      $("#series_salida").html(html).trigger("change");
    },
    error: function () {
      Swal.fire("Error", "Error cargando series disponibles", "error");
    },
  });
}

function guardarSalida() {
  limpiarErrores("#modalSalida");

  const id_repuesto = $("#id_repuesto_salida").val();
  const manejaSerie = $("#maneja_serie_salida").val();
  const id_maquina  = $("#id_maquina_salida").val();
  const referencia  = $("#referencia_salida").val().trim() || "MANTENIMIENTO";
  let valido        = true;

  if (!id_repuesto) {
    Swal.fire("Error", "Repuesto inválido", "error");
    return;
  }

  if (!id_maquina) {
    marcarInvalido("#id_maquina_salida", "Debes indicar la máquina destino");
    valido = false;
  }

  if (parseInt(manejaSerie) === 1) {
    const rawSeries = $("#series_salida").val();
    const series    = Array.isArray(rawSeries) ? rawSeries : (rawSeries ? rawSeries.split(",") : []);

    if (series.length === 0) {
      marcarInvalidoSelect2("#series_salida", "Selecciona al menos una serie");
      valido = false;
    }

    if (!valido) return;

    $.ajax({
      url: "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
      type: "POST",
      dataType: "json",
      traditional: true,
      data: { accion: "salidaSerie", id_repuesto, id_maquina, referencia, series },
      success: function (resp) {
        if (!resp.ok) {
          Swal.fire("Error", resp.mensaje || "Error al registrar la salida", "error");
          return;
        }
        cerrarModal("#modalSalida");
        listarRepuestos();
        Swal.fire({ icon: "success", title: "Listo", text: resp.mensaje || "Salida por serie registrada", timer: 1800, showConfirmButton: false });
      },
      error: function () {
        Swal.fire("Error", "Error de servidor", "error");
      },
    });

  } else {
    const cantidad = parseInt($("#cantidad_salida").val());
    const costo    = parseFloat($("#costo_salida").val()) || 0;

    if (!cantidad || cantidad <= 0) {
      marcarInvalido("#cantidad_salida", "Ingresa una cantidad válida");
      valido = false;
    }

    if (!valido) return;

    $.ajax({
      url: "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
      type: "POST",
      dataType: "json",
      data: { accion: "salida", id_repuesto, id_maquina, cantidad, costo, referencia },
      success: function (resp) {
        if (!resp.ok) {
          Swal.fire("Error", resp.mensaje || "Error al registrar la salida", "error");
          return;
        }
        cerrarModal("#modalSalida");
        listarRepuestos();
        Swal.fire({ icon: "success", title: "Listo", text: resp.mensaje || "Salida registrada", timer: 1800, showConfirmButton: false });
      },
      error: function () {
        Swal.fire("Error", "Error de servidor", "error");
      },
    });
  }
}

//////////////////////////////////////////////////////////
// KARDEX
//////////////////////////////////////////////////////////

function verKardex(id) {
  $.post(
    "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
    { accion: "kardex", id_repuesto: id },
    function (resp) {
      if (!resp.ok) {
        Swal.fire("Error", resp.mensaje, "error");
        return;
      }

      let html = "";
      resp.data.forEach((m) => {
        html += `
          <tr>
            <td>${m.fecha_movimiento}</td>
            <td>${m.tipo}</td>
            <td>${m.cantidad}</td>
            <td>${m.stock_anterior}</td>
            <td>${m.stock_nuevo}</td>
            <td>${parseFloat(m.costo_unitario || 0).toFixed(2)}</td>
            <td>${m.referencia}</td>
          </tr>`;
      });

      $("#tablaKardex tbody").html(html || `<tr><td colspan="7" class="text-center text-muted">Sin movimientos</td></tr>`);
      abrirModal("#modalKardex");
    },
    "json"
  );
}

//////////////////////////////////////////////////////////
// DETALLE (SERIE)
//////////////////////////////////////////////////////////

function verDetalle(id) {
  $.post(
    "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
    { accion: "listarDetalle", id_repuesto: id },
    function (resp) {
      if (!resp.ok) {
        Swal.fire("Error", resp.mensaje, "error");
        return;
      }

      let html = "";
      resp.data.forEach((d) => {
        html += `
          <tr>
            <td>${d.serie}</td>
            <td>${d.estado}</td>
            <td>${d.id_maquina_actual || "-"}</td>
            <td>
              <button class="btn btn-warning btn-sm"
                onclick="abrirEditarDetalle(${d.id_detalle_repuesto}, '${d.serie}', ${d.id_estado_repuesto}, ${d.id_maquina_actual || 0})">
                <i class="bi bi-pencil"></i>
              </button>
            </td>
          </tr>`;
      });

      $("#tablaDetalle tbody").html(html || `<tr><td colspan="4" class="text-center text-muted">Sin series</td></tr>`);
      abrirModal("#modalDetalle");
    },
    "json"
  );
}

function abrirEditarDetalle(id, serie, estado, maquina) {
  limpiarErrores("#modalEditarDetalle");
  $("#id_detalle_repuesto").val(id);
  $("#serie_detalle").val(serie);
  $("#maquina_detalle").val(maquina || "");

  cargarEstados(estado);
  abrirModal("#modalEditarDetalle");
}

function cargarEstados(estadoActual) {
  $.post(
    "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
    { accion: "estados" },
    function (resp) {
      if (!resp.ok) return;

      let html = "";
      resp.data.forEach((e) => {
        const sel = e.id_estado == estadoActual ? "selected" : "";
        html += `<option value="${e.id_estado}" ${sel}>${e.nombre}</option>`;
      });

      $("#estado_detalle").html(html);
    },
    "json"
  );
}

function guardarDetalle() {
  limpiarErrores("#modalEditarDetalle");
  let serie = $("#serie_detalle").val().trim();

  if (!serie) {
    marcarInvalido("#serie_detalle", "La serie es requerida");
    return;
  }

  $.post(
    "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
    {
      accion: "editarDetalle",
      id_detalle_repuesto: $("#id_detalle_repuesto").val(),
      serie: serie,
      id_estado_repuesto: $("#estado_detalle").val(),
      id_maquina_actual: $("#maquina_detalle").val(),
    },
    function (resp) {
      if (!resp.ok) {
        Swal.fire("Error", resp.mensaje, "error");
        return;
      }
      Swal.fire("Listo", "Detalle actualizado", "success");
      cerrarModal("#modalEditarDetalle");
    },
    "json"
  );
}

//////////////////////////////////////////////////////////
// CATÁLOGOS
//////////////////////////////////////////////////////////

function cargarTipos() {
  $.post(
    "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
    { accion: "tipos" },
    function (resp) {
      if (!resp.ok) return;

      let html = `<option value="">-- Seleccione --</option>`;
      resp.data.forEach((t) => {
        html += `<option value="${t.id_tipo}">${t.nombre}</option>`;
      });

      $("#id_tipo").html(html).trigger("change");
    },
    "json"
  );
}

function cargarMarcas() {
  $.post(
    "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
    { accion: "marcas" },
    function (resp) {
      if (!resp.ok) return;

      let html = `<option value="">-- Seleccione --</option>`;
      resp.data.forEach((m) => {
        html += `<option value="${m.id_marca}">${m.nombre}</option>`;
      });

      $("#id_marca").html(html).trigger("change");
    },
    "json"
  );
}

function cargarModelos(id_marca) {
  if (!id_marca) {
    $("#id_modelo").html(`<option value="">-- Seleccione marca primero --</option>`);
    return;
  }

  $.post(
    "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
    { accion: "modelos", id_marca: id_marca, id_tipo_modelo: 2 },
    function (resp) {
      if (!resp.ok) return;

      let html = `<option value="">-- Seleccione --</option>`;
      resp.data.forEach((m) => {
        html += `<option value="${m.id_modelo}">${m.nombre}</option>`;
      });

      $("#id_modelo").html(html).trigger("change");
    },
    "json"
  );
}

//////////////////////////////////////////////////////////
// UTILIDADES — MODALES
//////////////////////////////////////////////////////////

function abrirModal(id) {
  new bootstrap.Modal(document.querySelector(id)).show();
}

function cerrarModal(id) {
  const el = document.querySelector(id);
  if (!el) return;
  const modal = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
  modal.hide();
}

function initSelect2() {
  $(".select2").select2({
    width: "100%",
    dropdownParent: $("#modalRepuesto"),
    theme: "bootstrap-5",
    placeholder: "Seleccione",
    allowClear: true,
  });
}

function limpiarModalRepuesto() {
  $("#formRepuesto")[0].reset();
  $("#id_repuesto").val("");
  limpiarErrores("#formRepuesto");
}

function limpiarEntrada() {
  $("#cantidad_mov").val("");
  $("#costo_mov").val("");
  $("#series_input").val("");
  limpiarErrores("#modalEntrada");
}

//////////////////////////////////////////////////////////
// UTILIDADES — VALIDACIÓN VISUAL
//////////////////////////////////////////////////////////

function marcarInvalido(selector, mensaje) {
  const $el = $(selector);
  $el.addClass("is-invalid");

  if ($el.next(".invalid-feedback").length === 0) {
    $el.after(`<div class="invalid-feedback">${mensaje}</div>`);
  } else {
    $el.next(".invalid-feedback").text(mensaje);
  }
}

function marcarInvalidoSelect2(selector, mensaje) {
  const $el = $(selector);
  $el.next(".select2-container").find(".select2-selection").addClass("is-invalid border border-danger");

  if ($el.next(".select2-container").next(".invalid-feedback").length === 0) {
    $el.next(".select2-container").after(`<div class="invalid-feedback d-block">${mensaje}</div>`);
  } else {
    $el.next(".select2-container").next(".invalid-feedback").text(mensaje);
  }
}

function limpiarErrores(contenedor) {
  $(contenedor).find(".is-invalid").removeClass("is-invalid");
  $(contenedor).find(".invalid-feedback").remove();
  $(contenedor).find(".select2-selection").removeClass("border border-danger");
}

//////////////////////////////////////////////////////////
// 📥 IMPORTAR DESDE PLANTILLA CSV
//////////////////////////////////////////////////////////

function importarRepuestos() {
  const archivo = document.getElementById('archivoImport').files[0];

  if (!archivo) {
    Swal.fire('Atención', 'Selecciona un archivo CSV antes de importar.', 'warning');
    return;
  }

  const ext = archivo.name.split('.').pop().toLowerCase();
  if (!['csv', 'txt'].includes(ext)) {
    Swal.fire('Formato inválido', 'Solo se aceptan archivos .csv', 'error');
    return;
  }

  const btn = document.getElementById('btnImportar');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando...';

  $('#resultadoImport').hide();

  const formData = new FormData();
  formData.append('archivo', archivo);

  $.ajax({
    url: './modules/Electronicas/Repuestos/Controllers/importarRepuestos.php',
    type: 'POST',
    data: formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function (resp) {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-upload me-1"></i> Importar';

      if (!resp.ok) {
        $('#resultadoImport')
          .html(`<div class="alert alert-danger"><i class="bi bi-x-circle me-1"></i>${resp.mensaje}</div>`)
          .show();
        return;
      }

      // Construir resumen
      let html = '';

      if (resp.insertados > 0) {
        html += `<div class="alert alert-success mb-2">
                   <i class="bi bi-check-circle me-1"></i>
                   <strong>${resp.insertados}</strong> repuesto(s) importado(s) correctamente.
                 </div>`;
      }

      if (resp.errores && resp.errores.length > 0) {
        const items = resp.errores.map(e => `<li>${e}</li>`).join('');
        html += `<div class="alert alert-warning mb-0">
                   <strong><i class="bi bi-exclamation-triangle me-1"></i>${resp.errores.length} advertencia(s):</strong>
                   <ul class="mb-0 mt-1 ps-3" style="font-size:13px">${items}</ul>
                 </div>`;
      }

      if (resp.insertados === 0 && (!resp.errores || resp.errores.length === 0)) {
        html = `<div class="alert alert-info"><i class="bi bi-info-circle me-1"></i>No se encontraron filas para importar.</div>`;
      }

      $('#resultadoImport').html(html).show();

      // Recargar tabla si hubo inserciones
      if (resp.insertados > 0) {
        listarRepuestos();
        document.getElementById('archivoImport').value = '';
      }
    },
    error: function () {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-upload me-1"></i> Importar';
      Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
    }
  });
}
