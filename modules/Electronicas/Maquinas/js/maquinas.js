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
        render: (data) => `${window.DIVISA?.simbolo ?? 'L.'} ${parseFloat(data || 0).toFixed(2)}`,
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
        <button class="btn btn-sm btn-warning me-1" onclick="cargarEditar(${row.id_maquina})" title="Editar">
          <i class="bi bi-pencil"></i>
        </button>
        <button class="btn btn-sm btn-danger me-1" onclick="cambiarEstado(${row.id_maquina}, 3)" title="Dar de baja">
          <i class="bi bi-slash-circle"></i>
        </button>
        <button class="btn btn-sm btn-secondary" onclick="verHistorial(${row.id_maquina})" title="Historial de mantenimientos">
          <i class="bi bi-clock-history"></i>
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



//////////////////////////////////////////////////////////
// 📋 HISTORIAL DE MANTENIMIENTOS
//////////////////////////////////////////////////////////

function verHistorial(id_maquina) {
  // Mostrar modal con spinner mientras carga
  $('#contenidoHistorial').html(
    '<div class="text-center py-5"><span class="spinner-border text-primary"></span></div>'
  );
  const modal = new bootstrap.Modal(document.getElementById('modalHistorialMaquina'));
  modal.show();

  $.ajax({
    url: './modules/Electronicas/Maquinas/Controllers/historialMantenimientosMaquina.php',
    type: 'POST',
    dataType: 'json',
    data: { id_maquina },
    success: function (resp) {
      if (!resp.ok) {
        $('#contenidoHistorial').html(
          `<p class="text-center text-danger py-4"><i class="bi bi-exclamation-circle me-1"></i>${resp.mensaje}</p>`
        );
        return;
      }

      $('#histMaquinaNombre').text(resp.maquina);

      if (!resp.data || resp.data.length === 0) {
        $('#contenidoHistorial').html(
          '<p class="text-center text-muted py-5"><i class="bi bi-info-circle me-1"></i>Esta máquina no tiene mantenimientos registrados.</p>'
        );
        return;
      }

      const tipoBadge = {
        'Preventivo': 'bg-success',
        'Correctivo': 'bg-danger',
        'Predictivo': 'bg-info'
      };
      const tipoBorde = {
        'Preventivo': 'border-success',
        'Correctivo': 'border-danger',
        'Predictivo': 'border-info'
      };

      let html = `<p class="text-muted small mb-3">
                    <i class="bi bi-list-check me-1"></i>
                    ${resp.data.length} mantenimiento(s) registrado(s)
                  </p>`;

      resp.data.forEach(function (m) {
        const badge  = tipoBadge[m.tipo]  || 'bg-secondary';
        const borde  = tipoBorde[m.tipo]  || 'border-secondary';

        // Tabla de repuestos si tiene
        let repHtml = '';
        if (m.repuestos.length > 0) {
          const totalGeneral = m.repuestos.reduce((s, r) => s + parseFloat(r.total || 0), 0);
          const simbolo      = m.repuestos[0].divisa_simbolo || 'L.';

          repHtml = `
            <div class="mt-2">
              <p class="text-muted small mb-1 fw-semibold">
                <i class="bi bi-box-seam me-1"></i>Repuestos utilizados (${m.repuestos.length})
              </p>
              <table class="table table-sm table-bordered mb-1">
                <thead class="table-light">
                  <tr>
                    <th>Repuesto</th>
                    <th class="text-center" style="width:80px">Cant.</th>
                    <th class="text-end"  style="width:120px">Costo unit.</th>
                    <th class="text-end"  style="width:120px">Total</th>
                  </tr>
                </thead>
                <tbody>
                  ${m.repuestos.map(r => `
                    <tr>
                      <td>${r.repuesto}</td>
                      <td class="text-center">${r.cantidad}</td>
                      <td class="text-end">${r.divisa_simbolo} ${parseFloat(r.costo_unitario).toFixed(2)}</td>
                      <td class="text-end fw-semibold">${r.divisa_simbolo} ${parseFloat(r.total).toFixed(2)}</td>
                    </tr>`).join('')}
                </tbody>
                <tfoot class="table-light">
                  <tr>
                    <td colspan="3" class="text-end fw-bold">Total mantenimiento:</td>
                    <td class="text-end fw-bold">${simbolo} ${totalGeneral.toFixed(2)}</td>
                  </tr>
                </tfoot>
              </table>
            </div>`;
        } else {
          repHtml = `<p class="text-muted small mb-0 mt-1">
                       <i class="bi bi-dash-circle me-1"></i>Sin repuestos utilizados en este mantenimiento
                     </p>`;
        }

        html += `
          <div class="card mb-3 border-start border-4 ${borde} shadow-sm">
            <div class="card-body py-2 px-3">
              <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                  <span class="badge ${badge}">${m.tipo}</span>
                  <span class="fw-semibold small">
                    <i class="bi bi-calendar3 me-1"></i>${m.fecha}
                  </span>
                  <span class="text-muted small">
                    <i class="bi bi-person me-1"></i>${m.tecnico}
                  </span>
                  ${m.proximo ? `<span class="text-muted small"><i class="bi bi-calendar-check me-1"></i>Próximo: ${m.proximo}</span>` : ''}
                </div>
                <span class="badge ${m.repuestos.length > 0 ? 'bg-primary' : 'bg-light text-muted border'}">
                  <i class="bi bi-box-seam me-1"></i>
                  ${m.repuestos.length > 0 ? m.repuestos.length + ' repuesto(s)' : 'Sin repuestos'}
                </span>
              </div>
              ${m.descripcion ? `<p class="text-muted small mb-1 mt-1 border-top pt-1">${m.descripcion}</p>` : ''}
              ${repHtml}
            </div>
          </div>`;
      });

      $('#contenidoHistorial').html(html);
    },
    error: function () {
      $('#contenidoHistorial').html(
        '<p class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle me-1"></i>Error al cargar el historial.</p>'
      );
    }
  });
}

function imprimirHistorial() {
  const maquina   = $('#histMaquinaNombre').text();
  const contenido = $('#contenidoHistorial').html();
  const fecha     = new Date().toLocaleDateString('es-HN', { day: '2-digit', month: '2-digit', year: 'numeric' });

  const ventana = window.open('', '_blank', 'width=900,height=700');
  ventana.document.write(`
    <!DOCTYPE html>
    <html lang="es">
    <head>
      <meta charset="UTF-8">
      <title>Historial de mantenimientos — ${maquina}</title>
      <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 13px; color: #212529; padding: 30px 40px; }

        /* Encabezado */
        .report-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0d6efd; padding-bottom: 12px; margin-bottom: 20px; }
        .report-header .title h1 { font-size: 18px; color: #0d6efd; margin-bottom: 2px; }
        .report-header .title p  { font-size: 12px; color: #6c757d; }
        .report-header .meta     { text-align: right; font-size: 12px; color: #6c757d; }

        /* Contador */
        .text-muted.small { color: #6c757d; font-size: 12px; margin-bottom: 14px; }

        /* Tarjetas */
        .card { border: 1px solid #dee2e6; border-radius: 6px; margin-bottom: 16px; page-break-inside: avoid; }
        .card.border-start.border-4.border-success { border-left: 4px solid #198754 !important; }
        .card.border-start.border-4.border-danger  { border-left: 4px solid #dc3545 !important; }
        .card.border-start.border-4.border-info    { border-left: 4px solid #0dcaf0 !important; }
        .card.border-start.border-4.border-secondary { border-left: 4px solid #6c757d !important; }
        .card-body { padding: 10px 14px; }

        /* Badges */
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; color: #fff; }
        .bg-success   { background: #198754; }
        .bg-danger    { background: #dc3545; }
        .bg-info      { background: #0dcaf0; color: #212529; }
        .bg-secondary { background: #6c757d; }
        .bg-primary   { background: #0d6efd; }
        .bg-light     { background: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6; }
        .text-muted   { color: #6c757d; }
        .border       { border: 1px solid #dee2e6; }

        /* Cabecera de la card */
        .d-flex { display: flex; }
        .flex-wrap { flex-wrap: wrap; }
        .align-items-center { align-items: center; }
        .justify-content-between { justify-content: space-between; }
        .gap-2 { gap: 8px; }
        .fw-semibold { font-weight: 600; }
        .small { font-size: 12px; }

        /* Descripción */
        .border-top { border-top: 1px solid #dee2e6; }
        .pt-1 { padding-top: 4px; }
        .mt-1 { margin-top: 4px; }
        .mb-1 { margin-bottom: 4px; }

        /* Tablas */
        table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 12px; }
        th, td { padding: 5px 8px; border: 1px solid #dee2e6; }
        thead th { background: #f8f9fa; font-weight: 600; }
        tfoot td { background: #f8f9fa; }
        .text-center { text-align: center; }
        .text-end    { text-align: right; }
        .fw-bold     { font-weight: 700; }
        .table-light { background: #f8f9fa; }

        /* Íconos Bootstrap — se ocultan en impresión (usamos texto alternativo) */
        .bi::before { display: none; }

        @media print {
          body { padding: 20px; }
          @page { margin: 15mm 20mm; }
        }
      </style>
    </head>
    <body>
      <div class="report-header">
        <div class="title">
          <h1>Historial de mantenimientos</h1>
          <p>Máquina: <strong>${maquina}</strong></p>
        </div>
        <div class="meta">
          <div>Fecha de emisión: <strong>${fecha}</strong></div>
          <div>Sistema Electronicas — Honducafe</div>
        </div>
      </div>

      ${contenido}

      <script>
        window.onload = function () { window.print(); };
      <\/script>
    </body>
    </html>
  `);
  ventana.document.close();
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