let tablaMaquinas = null;
let estadosMaquina = [];
let modelosConEyectores = new Set();
let maquinasData = [];      // dataset completo (se agrupa en cards y alimenta la tabla)
let modeloActual;           // undefined = vista cards · null = todas · id = modelo filtrado

$(document).ready(function () {
  init();
});

function init() {
  initTabla();

  // Carga los modelos que tienen barra de eyectores antes de dibujar la tabla,
  // para que el dropdown muestre "Componentes" solo donde aplica.
  cargarModelosConEyectores(listarMaquinas);
  listarMarcas();
  listarEstados();

  $("#btnNuevaMaquina").click(limpiarModalMaquina);
  $("#btnGuardarMaquina").click(guardarMaquina);
  $("#btnEditarMaquina").click(editarMaquina);

  // Navegación cards ↔ detalle
  $("#btnVolverCards").click(mostrarCards);
  $("#btnVerTablaCompleta").click(() => mostrarDetalle(null));
  $("#buscarModelo").on("input", filtrarCards);
  $("#gridModelos").on("click", ".modelo-card", function () {
    mostrarDetalle($(this).data("modelo"));
  });

  $("#id_marca").change(function () {
    let id_marca = $(this).val();
    listarModelos(id_marca);
  });

  // Handlers delegados de la tabla (convención del proyecto)
  $("#tablaMaquinas")
    .on("click", ".btn-editar", function () {
      cargarEditar($(this).data("id"));
    })
    .on("click", ".btn-historial", function () {
      verHistorial($(this).data("id"));
    })
    .on("click", ".btn-repuestos", function () {
      verRepuestos($(this).data("id"));
    })
    .on("click", ".btn-componentes", function () {
      abrirEyectores($(this).data("id"));
    })
    .on("click", ".btn-baja", function () {
      darDeBaja($(this).data("id"));
    });
}

//////////////////////////////////////////////////////////
// 📊 TABLA
//////////////////////////////////////////////////////////

function listarMaquinas() {
  $.ajax({
    url: "./modules/Electronicas/Maquinas/Controllers/listarMaquinas.php",
    type: "POST",
    dataType: "json",
    success: function (json) {
      if (!Array.isArray(json)) {
        console.error("Respuesta inválida:", json);
        return;
      }
      maquinasData = json;
      renderCards();
      refrescarTabla();
    },
    error: manejarErrorAjax,
  });
}

function initTabla() {
  tablaMaquinas = $("#tablaMaquinas").DataTable({
    data: [],
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
        <div class="dropdown">
            <button class="btn btn-sm btn-primary dropdown-toggle py-1"
                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-three-dots-vertical"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li>
                    <button class="dropdown-item btn-editar" type="button" data-id="${row.id_maquina}">
                        <i class="bi bi-pencil me-2 text-warning"></i>Editar
                    </button>
                </li>
                <li>
                    <button class="dropdown-item btn-historial" type="button" data-id="${row.id_maquina}">
                        <i class="bi bi-clock-history me-2 text-secondary"></i>Historial
                    </button>
                </li>
                <li>
                    <button class="dropdown-item btn-repuestos" type="button" data-id="${row.id_maquina}">
                        <i class="bi bi-box-seam me-2 text-primary"></i>Ver repuestos
                    </button>
                </li>
                ${modelosConEyectores.has(String(row.id_modelo))
                  ? `<li>
                    <button class="dropdown-item btn-componentes" type="button" data-id="${row.id_maquina}">
                        <i class="bi bi-grid-3x3-gap me-2 text-success"></i>Componentes
                    </button>
                </li>`
                  : ""}
                <li><hr class="dropdown-divider"></li>
                <li>
                    <button class="dropdown-item text-danger btn-baja" type="button" data-id="${row.id_maquina}">
                        <i class="bi bi-slash-circle me-2"></i>Dar de baja
                    </button>
                </li>
            </ul>
        </div>`;
        },
      },
    ],
    language: {
      url: "./modules/Electronicas/Maquinas/js/es-ES.json",
    },
  });
}

//////////////////////////////////////////////////////////
// 🃏 CARDS POR MODELO
//////////////////////////////////////////////////////////

function renderCards() {
  const grupos = {};

  maquinasData.forEach((m) => {
    const k = String(m.id_modelo);
    if (!grupos[k]) {
      grupos[k] = {
        id_modelo: m.id_modelo,
        modelo: m.modelo,
        marca: m.marca,
        tipo: m.tipo_modelo,
        imagen: m.modelo_imagen,
        total: 0,
        activas: 0,
        mantenimiento: 0,
        inactivas: 0,
      };
    }
    const g = grupos[k];
    g.total++;
    if (m.estado === "Activo") g.activas++;
    else if (m.estado === "Mantenimiento") g.mantenimiento++;
    else g.inactivas++;
  });

  const lista = Object.values(grupos).sort((a, b) =>
    (a.marca + " " + a.modelo).localeCompare(b.marca + " " + b.modelo, "es"),
  );

  if (lista.length === 0) {
    $("#gridModelos").html(`
      <div class="col-12 text-center py-5 text-muted">
        <i class="bi bi-cpu" style="font-size:2.5rem"></i>
        <p class="mt-2 mb-0">No hay máquinas registradas todavía.</p>
      </div>`);
    return;
  }

  let html = "";
  lista.forEach((g) => {
    const texto = `${g.marca} ${g.modelo} ${g.tipo || ""}`.toLowerCase();
    const imagen = g.imagen
      ? `<img src="./${g.imagen}" alt="${g.modelo}" loading="lazy">`
      : `<i class="bi bi-cpu mc-placeholder"></i>`;

    const badges =
      (g.activas       ? `<span class="badge bg-success">${g.activas} activa${g.activas !== 1 ? "s" : ""}</span>` : "") +
      (g.mantenimiento ? `<span class="badge bg-warning">${g.mantenimiento} en mant.</span>` : "") +
      (g.inactivas     ? `<span class="badge bg-secondary">${g.inactivas} inactiva${g.inactivas !== 1 ? "s" : ""}</span>` : "");

    html += `
      <div class="col-12 col-sm-6 col-lg-4 col-xxl-3 col-card-modelo" data-texto="${texto}">
        <div class="card shadow-sm border-0 modelo-card h-100" data-modelo="${g.id_modelo}">
          <div class="mc-img">${imagen}</div>
          <div class="card-body pb-3">
            <small class="text-muted">${g.marca}${g.tipo ? " · " + g.tipo : ""}</small>
            <h6 class="mt-1 mb-2">${g.modelo}</h6>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-1">
              <span class="small fw-semibold">${g.total} máquina${g.total !== 1 ? "s" : ""}</span>
              <span class="mc-badges">${badges}</span>
            </div>
          </div>
        </div>
      </div>`;
  });

  $("#gridModelos").html(html);
  filtrarCards();
}

function filtrarCards() {
  const q = ($("#buscarModelo").val() || "").trim().toLowerCase();
  $("#gridModelos .col-card-modelo").each(function () {
    $(this).toggle(String($(this).data("texto")).includes(q));
  });
}

function mostrarDetalle(idModelo) {
  modeloActual = idModelo;
  refrescarTabla();

  $("#seccionCards").addClass("d-none");
  $("#seccionDetalle").removeClass("d-none");
}

function mostrarCards() {
  modeloActual = undefined;
  $("#seccionDetalle").addClass("d-none");
  $("#seccionCards").removeClass("d-none");
}

function refrescarTabla() {
  if (!tablaMaquinas) return;

  const filtrado = modeloActual === null || modeloActual === undefined
    ? maquinasData
    : maquinasData.filter((m) => String(m.id_modelo) === String(modeloActual));

  tablaMaquinas.clear().rows.add(filtrado).draw();

  // Encabezado del detalle
  if (modeloActual === null) {
    $("#detalleTitulo").text("Todas las máquinas");
    $("#detalleSubtitulo").text(`${maquinasData.length} máquina${maquinasData.length !== 1 ? "s" : ""} registrada${maquinasData.length !== 1 ? "s" : ""}`);
  } else if (modeloActual !== undefined) {
    const ref = filtrado[0];
    if (ref) {
      $("#detalleTitulo").text(`${ref.marca} ${ref.modelo}`);
      $("#detalleSubtitulo").text(
        `${filtrado.length} máquina${filtrado.length !== 1 ? "s" : ""}${ref.tipo_modelo ? " · " + ref.tipo_modelo : ""}`,
      );
    } else {
      // El modelo quedó sin máquinas (p. ej. tras editar): volver a las cards
      mostrarCards();
    }
  }
}

//////////////////////////////////////////////////////////
// 🔩 EYECTORES — modelos habilitados
//////////////////////////////////////////////////////////

function cargarModelosConEyectores(callback) {
  $.ajax({
    url: "./modules/Electronicas/Eyectores/Controllers/modelosConEyectores.php",
    type: "POST",
    dataType: "json",
    success: function (data) {
      modelosConEyectores = new Set((data || []).map(String));
    },
    error: function () {
      modelosConEyectores = new Set();
    },
    complete: function () {
      if (callback) callback();
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
      estadosMaquina = Array.isArray(data) ? data : [];

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

      $("#id_marca").val(d.id_marca);
      listarModelos(d.id_marca, d.id_modelo);

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

function darDeBaja(id) {
  const estado = estadosMaquina.find((e) =>
    ["inactivo", "baja", "dado de baja"].includes((e.nombre || "").trim().toLowerCase()),
  );

  if (!estado) {
    Swal.fire(
      "Error",
      "No se encontró un estado de baja en el catálogo de EstadoMaquina",
      "error",
    );
    return;
  }

  cambiarEstado(id, estado.id_estado);
}

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
    const msg = (resp && resp[0] && resp[0].mensaje) || "Operación fallida";
    Swal.fire("Error", msg, "error");
    console.error(resp);
  }
}

function manejarErrorAjax(error) {
  console.error("Error AJAX:", error.responseText);
  Swal.fire("Error", "Error en el servidor", "error");
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
        const anulado = Number(m.anulado) === 1;
        const badge  = tipoBadge[m.tipo]  || 'bg-secondary';
        const borde  = anulado ? 'border-danger' : (tipoBorde[m.tipo] || 'border-secondary');

        // Tabla de repuestos si tiene
        let repHtml = '';
        if (m.repuestos.length > 0) {
          const totalGeneral = m.repuestos.reduce((s, r) =>
            s + parseFloat(r.total || 0) * parseFloat(r.tipo_cambio || 1), 0);

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
                    <th class="text-end" style="width:130px">Costo unit.</th>
                    <th class="text-end" style="width:130px">Total</th>
                  </tr>
                </thead>
                <tbody>
                  ${m.repuestos.map(r => {
                    const tc      = parseFloat(r.tipo_cambio || 1);
                    const costoLps = parseFloat(r.costo_unitario || 0) * tc;
                    const totalLps = parseFloat(r.total || 0) * tc;
                    const esLps    = r.divisa_simbolo === 'L.' || tc === 1;
                    const nota     = !esLps
                      ? `<br><small class="text-muted">${r.divisa_simbolo} ${parseFloat(r.costo_unitario).toFixed(2)}</small>`
                      : '';
                    return `
                    <tr>
                      <td>${r.repuesto}</td>
                      <td class="text-center">${r.cantidad}</td>
                      <td class="text-end">L. ${costoLps.toFixed(2)}${nota}</td>
                      <td class="text-end fw-semibold">L. ${totalLps.toFixed(2)}</td>
                    </tr>`;
                  }).join('')}
                </tbody>
                <tfoot class="table-light">
                  <tr>
                    <td colspan="3" class="text-end fw-bold">Total mantenimiento:</td>
                    <td class="text-end fw-bold">L. ${totalGeneral.toFixed(2)}</td>
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
          <div class="card mb-3 border-start border-4 ${borde} shadow-sm ${anulado ? 'opacity-75' : ''}">
            <div class="card-body py-2 px-3">
              <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                  <span class="badge ${badge}">${m.tipo}</span>
                  ${anulado ? '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Anulado</span>' : ''}
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
              ${anulado && m.motivo_anulacion ? `<p class="text-danger small mb-1 mt-1 border-top pt-1"><i class="bi bi-x-circle me-1"></i><strong>Motivo de anulación:</strong> ${m.motivo_anulacion}</p>` : ''}
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
        const simbolo = r.divisa_simbolo || window.DIVISA?.simbolo || "L.";
        html += `
          <tr>
            <td>${r.nombre}</td>
            <td>${r.estado}</td>
            <td>${simbolo} ${parseFloat(r.costo || 0).toFixed(2)}</td>
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