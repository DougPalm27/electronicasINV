let tablaMaquinas = null;

$(document).ready(function () {
  init();
});

function init() {
  listarMaquinas();
  listarMarcas();
  listarEstados();

  $("#btnNuevaMaquina").click(limpiarModalMaquina);
  $("#btnGuardarMaquina").click(guardarMaquina);
  $("#btnEditarMaquina").click(editarMaquina);

  $("#id_marca").change(function () {
    let id_marca = $(this).val();
    listarModelos(id_marca);
  });
}

//////////////////////////////////////////////////////////
// 📊 TABLA
//////////////////////////////////////////////////////////

function listarMaquinas() {
  if ($.fn.DataTable.isDataTable("#tablaMaquinas")) {
    $("#tablaMaquinas").DataTable().destroy();
  }

  tablaMaquinas = $("#tablaMaquinas").DataTable({
    ajax: {
      url: "./modules/Electronicas/Maquinas/Controllers/listarMaquinas.php",
      type: "POST",
      dataType: "json",
      dataSrc: function (json) {
        if (!Array.isArray(json)) {
          console.error("Respuesta inválida:", json);
          return [];
        }

        actualizarCards(json);
        return json;
      },
      error: manejarErrorAjax,
    },
    columns: [
      { data: "nombre", defaultContent: "" },
      { data: "marca", defaultContent: "" },
      { data: "modelo", defaultContent: "" },
      { data: "serie", defaultContent: "" },
      { data: "anio", defaultContent: "-" },
      { data: "ubicacion", defaultContent: "-" },
      {
        data: "costo",
        defaultContent: "0",
        render: function (data) {
          return parseFloat(data || 0).toFixed(2);
        },
      },
      {
        data: "estado",
        render: function (data) {
          let clase = "bg-secondary";
          if (data === "Activo") clase = "bg-success";
          if (data === "Mantenimiento") clase = "bg-warning text-dark";
          if (data === "Inactivo") clase = "bg-danger";
          return `<span class="badge ${clase}">${data}</span>`;
        },
      },
      {
        data: null,
        render: function (data, type, row) {
          return `
        <button class="btn btn-sm btn-warning" onclick="cargarEditar(${row.id_maquina})">
          <i class="bi bi-pencil-square"></i>
        </button>
        <button class="btn btn-sm btn-danger" onclick="cambiarEstado(${row.id_maquina}, 3)">
          <i class="bi bi-slash-circle"></i>
        </button>
        <button class="btn btn-sm btn-info" onclick="verRepuestos(${row.id_maquina})">
        <i class="bi bi-tools"></i>
      </button>
      `;
        },
      },
    ],
    language: {
      url: "./modules/Electronicas/Maquinas/js/es-ES.json",
    },
  });
}

//////////////////////////////////////////////////////////
// 📦 SELECTS
//////////////////////////////////////////////////////////

function listarMarcas() {
  $.ajax({
    url: "./modules/Electronicas/Maquinas/Controllers/listarMarcas.php",
    type: "POST",
    dataType: "json",
    success: function (data) {
      let html = `<option value="-1">Seleccione una marca</option>`;

      data.forEach((e) => {
        html += `<option value="${e.id_marca}">${e.nombre}</option>`;
      });

      $("#id_marca").html(html);
    },
    error: manejarErrorAjax,
  });
}

function listarModelos(id_marca, seleccionado = null) {
  if (id_marca == "-1" || id_marca == "") {
    $("#id_modelo").html(`<option value="-1">Seleccione un modelo</option>`);
    return;
  }

  $.ajax({
    url: "./modules/Electronicas/Maquinas/Controllers/listarModelos.php",
    type: "POST",
    dataType: "json",
    data: { id_marca },
    success: function (data) {
      let html = `<option value="-1">Seleccione un modelo</option>`;

      data.forEach((e) => {
        let selected = seleccionado == e.id_modelo ? "selected" : "";
        html += `<option value="${e.id_modelo}" ${selected}>${e.nombre}</option>`;
      });

      $("#id_modelo").html(html);
    },
    error: manejarErrorAjax,
  });
}

function listarEstados() {
  $.ajax({
    url: "./modules/Electronicas/Maquinas/Controllers/listarEstadosMaquina.php",
    type: "POST",
    dataType: "json",
    success: function (data) {
      let html = `<option value="-1">Seleccione un estado</option>`;

      data.forEach((e) => {
        html += `<option value="${e.id_estado}">${e.nombre}</option>`;
      });

      $("#id_estado").html(html);
    },
    error: manejarErrorAjax,
  });
}

//////////////////////////////////////////////////////////
// 💾 GUARDAR
//////////////////////////////////////////////////////////

function guardarMaquina() {
  let datos = obtenerDatosFormulario();

  if (!validarFormulario(datos)) return;

  $.ajax({
    url: "./modules/Electronicas/Maquinas/Controllers/guardarMaquina.php",
    type: "POST",
    dataType: "json",
    data: { losDatos: datos },
    success: function (resp) {
      manejarRespuesta(resp, "Máquina registrada correctamente");
    },
    error: manejarErrorAjax,
  });
}

//////////////////////////////////////////////////////////
// ✏️ EDITAR
//////////////////////////////////////////////////////////

function cargarEditar(id) {
  $.ajax({
    url: "./modules/Electronicas/Maquinas/Controllers/obtenerMaquina.php",
    type: "POST",
    dataType: "json",
    data: { id_maquina: id },
    success: function (data) {
      if (!data || data.length === 0) return;

      let d = data[0];

      $("#id_maquina").val(d.id_maquina);
      $("#nombre").val(d.nombre);
      $("#serie").val(d.serie);
      $("#comentarios").val(d.comentarios);
      $("#id_estado").val(d.id_estado);
      $("#costo").val(d.costo);
      $("#anio").val(d.anio);
      $("#ubicacion").val(d.ubicacion);

      obtenerMarcaDesdeModelo(d.id_modelo, function (marca) {
        $("#id_marca").val(marca);
        listarModelos(marca, d.id_modelo);
      });

      $("#btnGuardarMaquina").hide();
      $("#btnEditarMaquina").show();

      abrirModal();
    },
    error: manejarErrorAjax,
  });
}
function editarMaquina() {
  let datos = obtenerDatosFormulario();

  if ($("#id_maquina").val() === "") {
    Swal.fire("Error", "No se encontró la máquina", "error");
    return;
  }

  datos.id_maquina = $("#id_maquina").val();

  $.ajax({
    url: "./modules/Electronicas/Maquinas/Controllers/editarMaquina.php",
    type: "POST",
    dataType: "json",
    data: { losDatos: datos },
    success: function (resp) {
      manejarRespuesta(resp, "Máquina actualizada correctamente");
    },
    error: manejarErrorAjax,
  });
}

//////////////////////////////////////////////////////////
// 🔄 ESTADO
//////////////////////////////////////////////////////////

function cambiarEstado(id, estado) {
  Swal.fire({
    title: "¿Cambiar estado?",
    icon: "warning",
    showCancelButton: true,
  }).then((r) => {
    if (!r.isConfirmed) return;

    $.ajax({
      url: "./modules/Electronicas/Maquinas/Controllers/cambiarEstadoMaquina.php",
      type: "POST",
      dataType: "json",
      data: { id_maquina: id, id_estado: estado },
      success: function () {
        listarMaquinas();
      },
      error: manejarErrorAjax,
    });
  });
}

//////////////////////////////////////////////////////////
// 🧼 UTILIDADES
//////////////////////////////////////////////////////////

function obtenerDatosFormulario() {
  return {
    nombre: $("#nombre").val().trim(),
    id_modelo: $("#id_modelo").val(),
    serie: $("#serie").val().trim(),
    comentarios: $("#comentarios").val().trim(),
    id_estado: $("#id_estado").val(),
    costo: $("#costo").val() === "" ? 0 : $("#costo").val(),
    anio: $("#anio").val().trim(),
    ubicacion: $("#ubicacion").val(),
  };
}

function validarFormulario(d) {
  if (d.nombre === "") {
    Swal.fire("Ups", "Nombre requerido", "warning");
    return false;
  }
  if (d.id_modelo == "-1") {
    Swal.fire("Ups", "Seleccione modelo", "warning");
    return false;
  }
  if (d.id_estado == "-1") {
    Swal.fire("Ups", "Seleccione estado", "warning");
    return false;
  }
  return true;
}

function limpiarModalMaquina() {
  $("#id_maquina").val("");
  $("#formMaquina")[0].reset();
  $("#id_modelo").html(`<option value="-1">Seleccione un modelo</option>`);
  $("#costo").val("");
  $("#anio").val("");
  $("#ubicacion").val("-1");

  $("#btnGuardarMaquina").show();
  $("#btnEditarMaquina").hide();
}

function abrirModal() {
  const modal = new bootstrap.Modal(document.getElementById("modalMaquina"));
  modal.show();
}

function cerrarModal() {
  const modal = bootstrap.Modal.getInstance(
    document.getElementById("modalMaquina"),
  );
  if (modal) modal.hide();

  $(".modal-backdrop").remove();
  $("body").removeClass("modal-open");
}

function manejarRespuesta(resp, mensaje) {
  if (resp[0] && resp[0].status == "200") {
    Swal.fire("Excelente", mensaje, "success").then(() => {
      cerrarModal();
      limpiarModalMaquina();
      listarMaquinas();
    });
  } else {
    Swal.fire("Error", "Operación fallida", "error");
    console.error(resp);
  }
}

function manejarErrorAjax(error) {
  console.error("Error AJAX:", error.responseText);
  Swal.fire("Error", "Error en el servidor", "error");
}

function actualizarCards(data) {
  $("#cardActivas").text(data.filter((x) => x.estado === "Activa").length);
  $("#cardMantenimiento").text(
    data.filter((x) => x.estado === "Mantenimiento").length,
  );
  $("#cardBaja").text(data.filter((x) => x.estado === "Baja").length);
}

function obtenerMarcaDesdeModelo(id_modelo, callback) {
  $.ajax({
    url: "./modules/Electronicas/Maquinas/Controllers/listarMarcasConModelos.php",
    type: "POST",
    dataType: "json",
    success: function (data) {
      let r = data.find((x) => x.id_modelo == id_modelo);
      if (r) callback(r.id_marca);
    },
  });
}


function verRepuestos(id_maquina) {
  $.ajax({
    url: "./modules/Electronicas/Maquinas/Controllers/obtenerRepuestosMaquina.php",
    type: "POST",
    dataType: "json",
    data: { id_maquina },
    success: function (data) {
      if (!Array.isArray(data)) {
        console.error("Respuesta inválida", data);
        return;
      }

      let html = "";

      data.forEach((r) => {
        html += `
          <tr>
            <td>${r.nombre}</td>
            <td>${r.estado}</td>
            <td>${parseFloat(r.costo || 0).toFixed(2)}</td>
            <td>${r.ultima_fecha || "-"}</td>
          </tr>
        `;
      });

      $("#tablaRepuestosMaquina tbody").html(html);

      const modal = new bootstrap.Modal(document.getElementById("modalRepuestosMaquina"));
      modal.show();
    },
  });
}