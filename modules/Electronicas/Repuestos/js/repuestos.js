let tablaRepuestos = null;

$(document).ready(function () {
  init();

  //////////////////////////////////////////////////////////
  // 🔥 DEBUG GLOBAL AJAX
  //////////////////////////////////////////////////////////
  $(document).ajaxError(function (event, xhr) {
    console.error("❌ AJAX ERROR:");
    console.log(xhr.responseText);
  });
});

//////////////////////////////////////////////////////////
// 🚀 INIT
//////////////////////////////////////////////////////////

function init() {
  listarRepuestos();
  listarProveedores();
  initSelect2();
  cargarTipos();
  cargarMarcas();

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
    abrirModal();
  });

  $("#id_marca").on("change", function () {
    let id_marca = $(this).val();
    cargarModelos(id_marca);
  });

  $("#btnGuardarRepuesto").click(guardarRepuesto);
  $("#btnEditarRepuesto").click(editarRepuesto);

  $("#modalRepuesto").on("hidden.bs.modal", limpiarModalRepuesto);

  // Cambiar modo dinámico
  $("#maneja_serie").on("change", function () {
    let tipo = $(this).val();

    if (tipo == "1") {
      console.log("🔵 Modo serie");

      $("#bloqueStock").hide();
      $("#bloqueSerie").fadeIn();
    } else {
      console.log("🟢 Modo stock");

      $("#bloqueSerie").hide();
      $("#bloqueStock").fadeIn();
    }
  });

  // Aplicar estado al abrir
  $("#modalRepuesto").on("shown.bs.modal", function () {
    $("#maneja_serie").trigger("change");
  });
}

//////////////////////////////////////////////////////////
// 📊 TABLA PRINCIPAL
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
        console.log("📦 RESPONSE:", json);

        if (!json.ok) {
          console.error("❌ BACKEND ERROR:", json.mensaje);
          return [];
        }

        return json.data;
      },
      error: function (xhr) {
        console.error(" DataTable ERROR:");
        console.log(xhr.responseText);
      },
    },

    columns: [
      { data: "nombre" },
      { data: "numero_parte" },
      { data: "proveedor" },

      {
        data: "stock",
        render: (d) => `<span class="badge bg-dark">${d}</span>`,
      },

      {
        data: "costo_promedio",
        render: (d) => parseFloat(d || 0).toFixed(2),
      },

      {
        data: null,
        render: (data, type, row) => {
          let botones = `
      <button class="btn btn-warning btn-sm"
          onclick='cargarEditarRepuesto(${JSON.stringify(row)})'
          title="Editar">
          <i class="bi bi-pencil"></i>
      </button>
    `;

          if (row.maneja_serie == 1) {
            botones += `
        <button class="btn btn-secondary btn-sm" onclick="verDetalle(${row.id_repuesto})" title="Ver detalle por serie">
          <i class="bi bi-box"></i>
        </button>

        <button class="btn btn-success btn-sm" onclick="abrirEntrada(${row.id_repuesto}, 1)" title="Registrar entrada por serie">
          <i class="bi bi-plus"></i>
        </button>

        <button class="btn btn-primary btn-sm" onclick="abrirSalida(${row.id_repuesto}, 1)" title="Registrar salida por serie">
          <i class="bi bi-arrow-up-circle"></i>
        </button>
      `;
          } else {
            botones += `
        <button class="btn btn-info btn-sm" onclick="verKardex(${row.id_repuesto})" title="Ver kardex">
          <i class="bi bi-list"></i>
        </button>

        <button class="btn btn-success btn-sm" onclick="abrirEntrada(${row.id_repuesto}, 0)" title="Registrar entrada por cantidad">
          <i class="bi bi-plus"></i>
        </button>

        <button class="btn btn-primary btn-sm" onclick="abrirSalida(${row.id_repuesto}, 0)" title="Registrar salida por cantidad">
          <i class="bi bi-arrow-up-circle"></i>
        </button>
      `;
          }

          botones += `
      <button class="btn btn-danger btn-sm" onclick="eliminarRepuesto(${row.id_repuesto})" title="Desechar repuesto">
        <i class="bi bi-trash"></i>
      </button>
    `;

          return botones;
        },
      },
    ],
  });
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
      console.log("📦 PROVEEDORES:", resp);

      if (!resp.ok) {
        console.error("❌ ERROR BACK:", resp.mensaje);
        return;
      }

      let html = `<option value="-1">Selecciona</option>`;

      resp.data.forEach((p) => {
        html += `<option value="${p.id_proveedor}">${p.nombre}</option>`;
      });

      $("#id_proveedor").html(html);
    },

    error: function (xhr) {
      console.error("❌ ERROR PROVEEDORES:");
      console.log(xhr.responseText);
    },
  });
}
//////////////////////////////////////////////////////////
// CATALOGO PRINCIPAL REPUESTOS CREAR, EDITAR, ELIMINAR
//////////////////////////////////////////////////////////
function obtenerDatosFormulario() {
  return {
    nombre: $("#nombre").val().trim(),
    numero_parte: $("#numero_parte").val().trim(),
    id_proveedor: $("#id_proveedor").val(),
    costo: parseFloat($("#costo").val()) || 0,
    comentarios: $("#comentarios").val().trim(),
    id_tipo: $("#id_tipo").val(),
    id_marca: $("#id_marca").val(),
    id_modelo: $("#id_modelo").val(),
    maneja_serie: $("#maneja_serie").val(),
  };
}

function guardarRepuesto() {
  let d = obtenerDatosFormulario();

  if (!d.nombre) {
    Swal.fire("Ups", "Nombre requerido", "warning");
    return;
  }
  console.log("📦 DATOS:", d);
  $.ajax({
    url: "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
    type: "POST",
    dataType: "json",
    data: {
      accion: "guardar",
      ...d,
    },

    success: function (resp) {
      console.log("📦 GUARDAR:", resp);

      if (!resp.ok) {
        Swal.fire("Error", resp.mensaje, "error");
        return;
      }

      Swal.fire("OK", "Repuesto creado", "success");

      cerrarModal();
      listarRepuestos();
    },
  });
}
function editarRepuesto() {
  let d = obtenerDatosFormulario();
  d.id_repuesto = $("#id_repuesto").val();

  $.ajax({
    url: "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
    type: "POST",
    dataType: "json",
    data: {
      accion: "editar",
      ...d,
    },

    success: function (resp) {
      if (!resp.ok) {
        Swal.fire("Error", resp.mensaje, "error");
        return;
      }

      Swal.fire("OK", "Actualizado", "success");

      cerrarModal();
      listarRepuestos();
    },
  });
}

function eliminarRepuesto(id) {
  Swal.fire({
    title: "¿Eliminar?",
    text: "No podrás revertir esto",
    icon: "warning",
    showCancelButton: true,
  }).then((result) => {
    if (!result.isConfirmed) return;

    $.post(
      "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
      {
        accion: "eliminar",
        id_repuesto: id,
      },
      function (resp) {
        if (!resp.ok) {
          Swal.fire("Error", resp.mensaje, "error");
          return;
        }

        Swal.fire("OK", "Eliminado", "success");
        listarRepuestos();
      },
      "json",
    );
  });
}

function cargarEditarRepuesto(row) {
  console.log("📥 EDIT ROW:", row);

  $("#id_repuesto").val(row.id_repuesto);
  $("#nombre").val(row.nombre);
  $("#numero_parte").val(row.numero_parte);
  $("#id_proveedor").val(row.id_proveedor).trigger("change");
  $("#costo").val(row.costo_promedio);
  $("#comentarios").val(row.comentarios);

  $("#id_tipo").val(row.id_tipo).trigger("change");
  $("#id_marca").val(row.id_marca).trigger("change");

  setTimeout(() => {
    $("#id_modelo").val(row.id_modelo).trigger("change");
  }, 300);

  $("#maneja_serie").val(row.maneja_serie).trigger("change");

  $("#btnGuardarRepuesto").hide();
  $("#btnEditarRepuesto").removeClass("d-none");

  abrirModal();
}

//////////////////////////////////////////////////////////
// ENTRADA
//////////////////////////////////////////////////////////

function abrirEntrada(id) {
  $("#id_repuesto_mov").val(id);
  $("#cantidad_mov").val(1);
  $("#costo_mov").val(0);

  new bootstrap.Modal("#modalEntrada").show();
}

function guardarEntrada() {
  $.post(
    "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
    {
      accion: "entrada",
      id_repuesto: $("#id_repuesto_mov").val(),
      cantidad: $("#cantidad_mov").val(),
      costo: $("#costo_mov").val(),
      referencia: "COMPRA",
    },
    function (resp) {
      console.log("📥 ENTRADA:", resp);

      if (!resp.ok) {
        Swal.fire("Error", resp.mensaje, "error");
        return;
      }

      Swal.fire("OK", "Entrada registrada", "success");
      cerrarModal("#modalEntrada");
      listarRepuestos();
    },
    "json",
  );
}

//////////////////////////////////////////////////////////
// 📊 KARDEX
//////////////////////////////////////////////////////////

function verKardex(id) {
  $.post(
    "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
    { accion: "kardex", id_repuesto: id },
    function (resp) {
      console.log("📊 KARDEX:", resp);

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
            <td>${m.costo_unitario}</td>
            <td>${m.referencia}</td>
          </tr>
        `;
      });

      $("#tablaKardex tbody").html(html);
      new bootstrap.Modal("#modalKardex").show();
    },
    "json",
  );
}

//////////////////////////////////////////////////////////
// 📦 DETALLE
//////////////////////////////////////////////////////////

function verDetalle(id) {
  $.post(
    "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
    { accion: "listarDetalle", id_repuesto: id },
    function (resp) {
      console.log("📦 DETALLE:", resp);

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
          </tr>
        `;
      });

      $("#tablaDetalle tbody").html(html);
      new bootstrap.Modal("#modalDetalle").show();
    },
    "json",
  );
}

//////////////////////////////////////////////////////////
// 🧼 UTILIDADES
//////////////////////////////////////////////////////////

function validarFormulario(d) {
  if (!d.nombre) {
    Swal.fire("Ups", "Nombre requerido", "warning");
    return false;
  }
  if (d.id_proveedor == "-1") {
    Swal.fire("Ups", "Selecciona proveedor", "warning");
    return false;
  }
  return true;
}

function abrirModal() {
  new bootstrap.Modal("#modalRepuesto").show();
}

function cerrarModal(id = "#modalRepuesto") {
  const modal = bootstrap.Modal.getInstance(document.querySelector(id));
  if (modal) modal.hide();
}

function limpiarModalRepuesto() {
  $("#formRepuesto")[0].reset();
  $("#id_repuesto").val("");
}
function cargarTipos() {
  $.post(
    "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
    { accion: "tipos" },
    function (resp) {
      console.log("📦 TIPOS:", resp);

      if (!resp.ok) return;

      let html = `<option value="">Seleccione</option>`;

      resp.data.forEach((t) => {
        html += `<option value="${t.id_tipo}">${t.nombre}</option>`;
      });

      $("#id_tipo").html(html).trigger("change");
    },
    "json",
  );
}
function cargarMarcas() {
  $.post(
    "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
    { accion: "marcas" },
    function (resp) {
      console.log("📦 MARCAS:", resp);

      if (!resp.ok) return;

      let html = `<option value="">Seleccione</option>`;

      resp.data.forEach((m) => {
        html += `<option value="${m.id_marca}">${m.nombre}</option>`;
      });

      $("#id_marca").html(html).trigger("change");
    },
    "json",
  );
}
function cargarModelos(id_marca) {
  if (!id_marca) {
    $("#id_modelo").html(`<option value="">Seleccione marca primero</option>`);
    return;
  }

  $.post(
    "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
    {
      accion: "modelos",
      id_marca: id_marca,
      id_tipo_modelo: 2, // 1=MAQUINA, 2=REPUESTO,
    },
    function (resp) {
      console.log("📦 MODELOS:", resp);

      if (!resp.ok) return;

      let html = `<option value="">Seleccione</option>`;

      resp.data.forEach((m) => {
        html += `<option value="${m.id_modelo}">${m.nombre}</option>`;
      });

      $("#id_modelo").html(html).trigger("change");
    },
    "json",
  );
}

function initSelect2() {
  $(".select2").select2({
    width: "100%",
    dropdownParent: $("#modalRepuesto"), //  ESTO ES CLAVE
    theme: "bootstrap-5",
    placeholder: "Seleccione",
    allowClear: true,
  });
}

function abrirEntrada(id, manejaSerie) {
  $("#id_repuesto_mov").val(id);
  $("#maneja_serie_mov").val(manejaSerie);

  if (manejaSerie == 1) {
    $("#entradaStock").hide();
    $("#entradaSerie").show();
  } else {
    $("#entradaSerie").hide();
    $("#entradaStock").show();
  }

  new bootstrap.Modal("#modalEntrada").show();
}

function guardarEntrada() {
  let id = $("#id_repuesto_mov").val();
  let manejaSerie = $("#maneja_serie_mov").val();

  if (!id) {
    Swal.fire("Error", "Repuesto inválido", "error");
    return;
  }

  //////////////////////////////////////////////////////////
  // 🔵 MODO SERIE
  //////////////////////////////////////////////////////////
  if (manejaSerie == "1") {
    let raw = $("#series_input").val();

    if (!raw.trim()) {
      Swal.fire("Ups", "Ingresa al menos una serie", "warning");
      return;
    }

    let series = raw
      .split("\n")
      .map((s) => s.trim())
      .filter((s) => s !== "");

    if (series.length === 0) {
      Swal.fire("Ups", "No hay series válidas", "warning");
      return;
    }

    console.log("📦 SERIES A ENVIAR:", series);

    $.ajax({
      url: "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
      type: "POST",
      dataType: "json",
      data: {
        accion: "entradaSerie",
        id_repuesto: id,
        series: series,
      },

      success: function (resp) {
        console.log("📦 RESPUESTA:", resp);

        if (!resp.ok) {
          Swal.fire("Error", resp.mensaje || "Error al guardar", "error");
          return;
        }

        // 🔥 CASO PRO: duplicadas
        if (resp.duplicadas && resp.duplicadas.length > 0) {
          Swal.fire({
            icon: "warning",
            title: "Parcial",
            html: `
              Se registraron ${resp.insertadas} series.<br>
              <b>Duplicadas:</b><br>
              ${resp.duplicadas.join("<br>")}
            `,
          });
        } else {
          Swal.fire("OK", "Series registradas correctamente", "success");
        }

        cerrarModal("#modalEntrada");
        listarRepuestos();
      },

      error: function (xhr) {
        console.error("❌ ERROR:", xhr.responseText);
        Swal.fire("Error", "Error de servidor", "error");
      },
    });
  }

  //////////////////////////////////////////////////////////
  //  MODO STOCK
  //////////////////////////////////////////////////////////
  else {
    let cantidad = parseInt($("#cantidad_mov").val());
    let costo = parseFloat($("#costo_mov").val());

    if (!cantidad || cantidad <= 0) {
      Swal.fire("Ups", "Cantidad inválida", "warning");
      return;
    }

    if (!costo || costo < 0) {
      Swal.fire("Ups", "Costo inválido", "warning");
      return;
    }

    console.log("📦 ENTRADA STOCK:", { cantidad, costo });

    $.ajax({
      url: "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
      type: "POST",
      dataType: "json",
      data: {
        accion: "entrada",
        id_repuesto: id,
        cantidad: cantidad,
        costo: costo,
        referencia: "COMPRA",
      },

      success: function (resp) {
        console.log("📦 RESPUESTA:", resp);

        if (!resp.ok) {
          Swal.fire("Error", resp.mensaje || "Error al guardar", "error");
          return;
        }

        Swal.fire("OK", "Entrada registrada correctamente", "success");

        cerrarModal("#modalEntrada");
        listarRepuestos();
      },

      error: function (xhr) {
        console.error("❌ ERROR:", xhr.responseText);
        Swal.fire("Error", "Error de servidor", "error");
      },
    });
  }
}

function limpiarEntrada() {
  $("#cantidad_mov").val("");
  $("#costo_mov").val("");
  $("#series_input").val("");
}
function cerrarModal(id) {
  const modal = bootstrap.Modal.getInstance(document.querySelector(id));
  if (modal) modal.hide();
  limpiarEntrada();
}

function abrirSalida(id_repuesto, manejaSerie) {
  $("#id_repuesto_salida").val(id_repuesto);
  $("#maneja_serie_salida").val(manejaSerie);
  $("#id_maquina_salida").val("");
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

  new bootstrap.Modal("#modalSalida").show();
}

function cargarSeriesDisponibles(id_repuesto) {
  $.ajax({
    url: "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
    type: "POST",
    dataType: "json",
    data: {
      accion: "seriesDisponibles",
      id_repuesto: id_repuesto,
    },
    success: function (resp) {
      console.log("📦 SERIES DISPONIBLES:", resp);

      if (!resp.ok) {
        Swal.fire(
          "Error",
          resp.mensaje || "No se pudieron cargar las series",
          "error",
        );
        return;
      }

      let html = "";
      resp.data.forEach((item) => {
        html += `<option value="${item.id_detalle_repuesto}">${item.serie}</option>`;
      });

      $("#series_salida").html(html).trigger("change");
    },
    error: function (xhr) {
      console.error("❌ ERROR SERIES:", xhr.responseText);
      Swal.fire("Error", "Error cargando series disponibles", "error");
    },
  });
}

function guardarSalida() {
  const id_repuesto = $("#id_repuesto_salida").val();
  const manejaSerie = $("#maneja_serie_salida").val();
  const id_maquina = $("#id_maquina_salida").val();
  const referencia = $("#referencia_salida").val().trim() || "MANTENIMIENTO";

  if (!id_repuesto) {
    Swal.fire("Error", "Repuesto inválido", "error");
    return;
  }

  if (!id_maquina) {
    Swal.fire("Ups", "Debes indicar la máquina destino", "warning");
    return;
  }

  if (parseInt(manejaSerie) === 1) {
    const rawSeries = $("#series_salida").val();

    const series = Array.isArray(rawSeries)
      ? rawSeries
      : rawSeries
        ? rawSeries.split(",")
        : [];

    if (series.length === 0) {
      Swal.fire("Ups", "Debes seleccionar al menos una serie", "warning");
      return;
    }

    console.log("SERIES:", series);
console.log("TIPO:", typeof series);
    $.ajax({
      url: "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
      type: "POST",
      dataType: "json",
      traditional: true,
      data: {
        accion: "salidaSerie",
        id_repuesto: id_repuesto,
        id_maquina: id_maquina,
        referencia: referencia,
        series: series,
      },
      success: function (resp) {
        console.log("📤 SALIDA SERIE:", resp);

        if (!resp.ok) {
          Swal.fire(
            "Error",
            resp.mensaje || "Error al registrar la salida",
            "error",
          );
          return;
        }

        Swal.fire(
          "OK",
          resp.mensaje || "Salida por serie registrada",
          "success",
        );
        cerrarModal("#modalSalida");
        listarRepuestos();
      },
      error: function (xhr) {
        console.error("❌ ERROR SALIDA SERIE:", xhr.responseText);
        Swal.fire("Error", "Error de servidor", "error");
      },
    });
  } else {
    const cantidad = parseInt($("#cantidad_salida").val());
    const costo = parseFloat($("#costo_salida").val()) || 0;

    if (!cantidad || cantidad <= 0) {
      Swal.fire("Ups", "Cantidad inválida", "warning");
      return;
    }

    $.ajax({
      url: "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
      type: "POST",
      dataType: "json",
      data: {
        accion: "salida",
        id_repuesto: id_repuesto,
        id_maquina: id_maquina,
        cantidad: cantidad,
        costo: costo,
        referencia: referencia,
      },
      success: function (resp) {
        console.log("📤 SALIDA CANTIDAD:", resp);

        if (!resp.ok) {
          Swal.fire(
            "Error",
            resp.mensaje || "Error al registrar la salida",
            "error",
          );
          return;
        }

        Swal.fire("OK", resp.mensaje || "Salida registrada", "success");
        cerrarModal("#modalSalida");
        listarRepuestos();
      },
      error: function (xhr) {
        console.error("❌ ERROR SALIDA:", xhr.responseText);
        Swal.fire("Error", "Error de servidor", "error");
      },
    });
  }
}
