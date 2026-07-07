let tablaRepuestos = null;
const ES_ADMIN = window.USUARIO_ROL === "Administrador";

$(document).ready(function () {
  init();
});

//////////////////////////////////////////////////////////
// INIT
//////////////////////////////////////////////////////////

function init() {
  listarRepuestos();
  listarProveedores();
  cargarUbicaciones();
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

  // Ajuste negativo de inventario (merma, daño, conteo físico) — solo admin
  $(document).on("click", ".btn-ajuste-inv", function () {
    const id     = $(this).data("id");
    const stock  = parseInt($(this).data("stock")) || 0;
    const nombre = $(this).data("nombre");

    Swal.fire({
      title: "Ajuste negativo de inventario",
      html: `
        <p class="mb-2 text-start small">${nombre} — stock actual: <b>${stock}</b></p>
        <input type="number" id="swal-aj-cant" class="form-control mb-2"
               min="1" max="${stock}" placeholder="Cantidad a descontar">
        <textarea id="swal-aj-motivo" class="form-control" rows="2"
                  placeholder="Motivo (merma, daño, conteo físico...)"></textarea>`,
      showCancelButton: true,
      confirmButtonText: "Registrar ajuste",
      cancelButtonText: "Cancelar",
      confirmButtonColor: "#d33",
      preConfirm: () => {
        const cantidad = parseInt($("#swal-aj-cant").val());
        const motivo   = $("#swal-aj-motivo").val().trim();
        if (!cantidad || cantidad <= 0) {
          Swal.showValidationMessage("Ingresa una cantidad válida");
          return false;
        }
        if (cantidad > stock) {
          Swal.showValidationMessage(`La cantidad no puede exceder el stock (${stock})`);
          return false;
        }
        if (!motivo) {
          Swal.showValidationMessage("El motivo es obligatorio");
          return false;
        }
        return { cantidad, motivo };
      },
    }).then((res) => {
      if (!res.isConfirmed) return;
      $.post(
        "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
        { accion: "ajusteNegativo", id_repuesto: id, cantidad: res.value.cantidad, motivo: res.value.motivo },
        function (resp) {
          if (!resp.ok) {
            Swal.fire("Error", resp.mensaje || "Error al registrar el ajuste", "error");
            return;
          }
          listarRepuestos();
          Swal.fire({ icon: "success", title: "Listo", text: "Ajuste registrado", timer: 1800, showConfirmButton: false });
        },
        "json"
      );
    });
  });

  // Solicitar compra desde stock bajo → abre el módulo de compras precargado
  $(document).on("click", ".btn-solicitar-compra", function () {
    const stock = parseInt($(this).data("stock")) || 0;
    const min   = parseInt($(this).data("min")) || 0;
    sessionStorage.setItem("compraPrecargada", JSON.stringify({
      id_repuesto: $(this).data("id"),
      cantidad: Math.max(1, min - stock),
    }));
    window.location.href = "?module=compras";
  });

  // Kardex — delegación para capturar botones generados por DataTable
  $(document).on("click", ".btn-ver-kardex", function () {
    const btn = $(this);
    verKardex(
      btn.data("id"),
      btn.data("nombre"),
      btn.data("marca"),
      btn.data("modelo"),
      parseInt(btn.data("stock")) || 0,
      parseFloat(btn.data("costo")) || 0
    );
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
      { data: "marca",  defaultContent: "—" },
      { data: "modelo", defaultContent: "—" },
      {
        data: "ubicacion",
        defaultContent: "—",
        render: v => v
          ? `<span class="badge bg-secondary">${v}</span>`
          : '<span class="text-muted small">—</span>',
      },
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
          const id = row.id_repuesto;

          const itemsEspecificos = row.maneja_serie == 1
            ? `<li>
                   <button class="dropdown-item" type="button" onclick="verDetalle(${id})">
                       <i class="bi bi-box me-2 text-secondary"></i>Ver series
                   </button>
               </li>
               <li>
                   <button class="dropdown-item btn-ver-kardex" type="button"
                           data-id="${id}"
                           data-nombre="${(row.nombre  || '').replace(/"/g, '&quot;')}"
                           data-marca="${(row.marca    || '—').replace(/"/g, '&quot;')}"
                           data-modelo="${(row.modelo  || '—').replace(/"/g, '&quot;')}"
                           data-stock="${row.stock}" data-costo="${row.costo_promedio || 0}">
                       <i class="bi bi-journal-text me-2 text-info"></i>Ver kardex
                   </button>
               </li>
               <li>
                   <button class="dropdown-item" type="button" onclick="abrirEntrada(${id}, 1)">
                       <i class="bi bi-plus-circle me-2 text-success"></i>Registrar entrada
                   </button>
               </li>
               `
            : `<li>
                   <button class="dropdown-item btn-ver-kardex" type="button"
                           data-id="${id}"
                           data-nombre="${(row.nombre  || '').replace(/"/g, '&quot;')}"
                           data-marca="${(row.marca    || '—').replace(/"/g, '&quot;')}"
                           data-modelo="${(row.modelo  || '—').replace(/"/g, '&quot;')}"
                           data-stock="${row.stock}" data-costo="${row.costo_promedio || 0}">
                       <i class="bi bi-journal-text me-2 text-info"></i>Ver kardex
                   </button>
               </li>
               <li>
                   <button class="dropdown-item" type="button" onclick="abrirEntrada(${id}, 0)">
                       <i class="bi bi-plus-circle me-2 text-success"></i>Registrar entrada
                   </button>
               </li>
               ${ES_ADMIN ? `
               <li>
                   <button class="dropdown-item btn-ajuste-inv" type="button"
                           data-id="${id}" data-stock="${row.stock}"
                           data-nombre="${(row.nombre || '').replace(/"/g, '&quot;')}">
                       <i class="bi bi-clipboard-minus me-2 text-danger"></i>Ajuste de inventario
                   </button>
               </li>` : ''}
               `;

          const stockBajo = row.stock_minimo && parseInt(row.stock) <= parseInt(row.stock_minimo);
          const itemSolicitarCompra = stockBajo
            ? `<li>
                   <button class="dropdown-item btn-solicitar-compra" type="button"
                           data-id="${id}" data-stock="${row.stock}" data-min="${row.stock_minimo}">
                       <i class="bi bi-cart-plus me-2 text-primary"></i>Solicitar compra
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
                        <button class="dropdown-item" type="button"
                                onclick='cargarEditarRepuesto(${JSON.stringify(row)})'>
                            <i class="bi bi-pencil me-2 text-warning"></i>Editar
                        </button>
                    </li>
                    ${itemsEspecificos}
                    ${itemSolicitarCompra}
                    ${ES_ADMIN ? `
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <button class="dropdown-item text-danger" type="button"
                                onclick="eliminarRepuesto(${id})">
                            <i class="bi bi-trash me-2"></i>Desechar
                        </button>
                    </li>` : ''}
                </ul>
            </div>`;
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

      $("#id_proveedor").html(html).trigger("change");
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
    id_ubicacion: $("#id_ubicacion").val() || null,
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

  // Ubicación del repuesto
  $("#id_ubicacion").val(row.id_ubicacion || null).trigger("change");

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
  $("#tipo_entrada_mov").val("Compra");

  if (manejaSerie == 1) {
    $("#entradaStock").hide();
    $("#entradaSerie").show();
  } else {
    $("#entradaSerie").hide();
    $("#entradaStock").show();
  }

  // Cargar proveedores en el select de la entrada
  $.post(
    "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
    { accion: "proveedores" },
    function (resp) {
      let html = '<option value="">— Sin especificar —</option>';
      if (resp.ok && resp.data) {
        resp.data.forEach(p => {
          html += `<option value="${p.id_proveedor}">${p.nombre}</option>`;
        });
      }
      $("#id_proveedor_mov").html(html);
    },
    "json"
  );

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
      data: {
        accion:       "entradaSerie",
        id_repuesto:  id,
        series:       series,
        id_proveedor: $("#id_proveedor_mov").val(),
        tipo_entrada: $("#tipo_entrada_mov").val(),
      },
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
      data: {
        accion:        "entrada",
        id_repuesto:   id,
        cantidad:      cantidad,
        costo:         costo,
        id_proveedor:  $("#id_proveedor_mov").val(),
        tipo_entrada:  $("#tipo_entrada_mov").val(),
      },
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
  $("#referencia_salida").val("SALIDA MANUAL");
  $("#cantidad_salida").val("");
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
  const referencia  = $("#referencia_salida").val().trim() || "SALIDA MANUAL";
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

    if (!cantidad || cantidad <= 0) {
      marcarInvalido("#cantidad_salida", "Ingresa una cantidad válida");
      valido = false;
    }

    if (!valido) return;

    $.ajax({
      url: "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
      type: "POST",
      dataType: "json",
      data: { accion: "salida", id_repuesto, id_maquina, cantidad, referencia },
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

// Datos del repuesto activo en el kardex
let _kardexIdRepuesto = null;
let _kardexInfo       = { nombre: '', marca: '', modelo: '' };

function verKardex(id, nombre, marca, modelo, stock, costoPromedio) {
  _kardexIdRepuesto = id;
  _kardexInfo       = { nombre, marca, modelo, stock: stock || 0, costo: costoPromedio || 0 };

  // Título del modal
  $('#kardexTituloNombre').text(nombre);
  $('#kardexTituloMeta').text([marca, modelo].filter(v => v && v !== '—').join(' · '));

  // Valuación actual del inventario de este repuesto
  const valorActual = (_kardexInfo.stock * _kardexInfo.costo);
  $('#kardexValorActual').text(
    `L ${valorActual.toLocaleString('es-HN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
  );

  // Reiniciar filtro de período
  $('#kardex_desde').val('');
  $('#kardex_hasta').val('');

  cargarKardex(id);
  abrirModal("#modalKardex");
}

function aplicarFiltroKardex() {
  if (_kardexIdRepuesto) cargarKardex(_kardexIdRepuesto);
}

function limpiarFiltroKardex() {
  $('#kardex_desde').val('');
  $('#kardex_hasta').val('');
  if (_kardexIdRepuesto) cargarKardex(_kardexIdRepuesto);
}

function fmtLps(n) {
  return parseFloat(n || 0).toLocaleString('es-HN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function cargarKardex(id) {
  $.post(
    "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
    {
      accion: "kardex",
      id_repuesto: id,
      desde: $('#kardex_desde').val(),
      hasta: $('#kardex_hasta').val(),
    },
    function (resp) {
      if (!resp.ok) {
        Swal.fire("Error", resp.mensaje, "error");
        return;
      }

      const movimientos  = resp.data.movimientos || [];
      const saldoInicial = parseInt(resp.data.saldo_inicial) || 0;
      const hayFiltro    = $('#kardex_desde').val() || $('#kardex_hasta').val();

      // ── Fila de saldo inicial del período ──────────────────
      let html = `
        <tr class="table-primary">
          <td class="text-nowrap small">—</td>
          <td><span class="badge bg-primary me-1">
                <i class="bi bi-flag me-1"></i>Saldo inicial${hayFiltro ? ' del período' : ''}
              </span></td>
          <td class="text-center"><span class="text-muted">—</span></td>
          <td class="text-center"><span class="text-muted">—</span></td>
          <td class="text-end small">—</td>
          <td class="text-end small">—</td>
          <td class="text-end"><strong>${saldoInicial}</strong></td>
          <td></td>
        </tr>`;

      if (movimientos.length === 0) {
        html += `<tr><td colspan="8" class="text-center text-muted py-3">Sin movimientos en el período</td></tr>`;
        $("#tablaKardex tbody").html(html);
        $("#kardexResumen").html('');
        return;
      }

      // Totales del período (incluye anulados y sus contra-movimientos: se netean)
      let totEntU = 0, totSalU = 0, totEntL = 0, totSalL = 0;

      movimientos.forEach((m) => {
        const anulado     = parseInt(m.anulado) === 1;
        const esAnulacion = m.referencia && m.referencia.startsWith('ANULACION');
        const esEntrada   = parseInt(m.id_tipo_movimiento) === 1;
        const cantidad    = parseInt(m.cantidad) || 0;
        const costo       = parseFloat(m.costo_unitario) || 0;
        const importe     = cantidad * costo;

        if (esEntrada) { totEntU += cantidad; totEntL += importe; }
        else           { totSalU += cantidad; totSalL += importe; }

        // ── Clase de fila ──────────────────────────────────────
        const trClass     = anulado ? 'table-secondary' : '';
        const strikeStyle = anulado ? 'style="text-decoration:line-through;opacity:.6"' : '';

        // ── Columna Descripción ────────────────────────────────
        let descripcion = '';
        if (esAnulacion) {
          const detalle = m.observaciones || m.referencia;
          descripcion = `<span class="badge bg-warning text-dark me-1">
                           <i class="bi bi-arrow-counterclockwise me-1"></i>Ajuste por anulación
                         </span>
                         <small class="text-muted">${detalle}</small>`;
        } else if (anulado) {
          descripcion = `<span class="badge bg-secondary me-1">
                           <i class="bi bi-slash-circle me-1"></i>Anulado
                         </span>`;
        } else {
          descripcion = `<span class="badge ${esEntrada ? 'bg-success' : 'bg-danger'} me-1">
                           <i class="bi ${esEntrada ? 'bi-box-arrow-in-down' : 'bi-box-arrow-up'} me-1"></i>
                           ${m.tipo}
                         </span>`;
        }
        if (!esAnulacion && !anulado) {
          if (m.tipo_entrada && m.tipo_entrada !== 'Compra') {
            descripcion += ` <span class="badge bg-warning text-dark">${m.tipo_entrada}</span>`;
          }
          if (m.proveedor) {
            descripcion += ` <small class="text-muted"><i class="bi bi-truck me-1"></i>${m.proveedor}</small>`;
          } else if (m.referencia && m.referencia !== 'COMPRA' && m.referencia !== 'MANTENIMIENTO') {
            descripcion += `<small class="text-muted ms-1">${m.referencia}</small>`;
          }
          if (m.observaciones && m.referencia && m.referencia.startsWith('AJUSTE')) {
            descripcion += ` <small class="text-muted">— ${m.observaciones}</small>`;
          }
        }
        if (m.usuario) {
          descripcion += ` <small class="text-muted">· ${m.usuario}</small>`;
        }

        // ── Entradas / Salidas (columnas separadas) ────────────
        const cantEntrada = (!anulado && esEntrada)
          ? `<span class="fw-semibold">${cantidad}</span>`
          : `<span class="text-muted">—</span>`;
        const cantSalida  = (!anulado && !esEntrada)
          ? `<span class="fw-semibold">${cantidad}</span>`
          : `<span class="text-muted">—</span>`;

        const cantEntradaFinal = anulado
          ? `<span class="text-muted" ${strikeStyle}>${esEntrada ? cantidad : '—'}</span>`
          : cantEntrada;
        const cantSalidaFinal  = anulado
          ? `<span class="text-muted" ${strikeStyle}>${!esEntrada ? cantidad : '—'}</span>`
          : cantSalida;

        // ── Saldo (stock_nuevo) ────────────────────────────────
        const saldo = anulado
          ? `<span class="text-muted" ${strikeStyle}>${parseInt(m.stock_nuevo)}</span>`
          : `<strong>${parseInt(m.stock_nuevo)}</strong>`;

        // ── Botón anular (solo Administrador) ──────────────────
        let btnAnular = '';
        if (ES_ADMIN && !anulado && !esAnulacion) {
          btnAnular = `<button class="btn btn-sm btn-danger"
                         onclick="confirmarAnulacion(${m.id_movimiento})"
                         title="Anular este movimiento">
                         <i class="bi bi-slash-circle"></i>
                       </button>`;
        }

        html += `
          <tr class="${trClass}">
            <td class="text-nowrap small" ${strikeStyle}>${m.fecha_movimiento}</td>
            <td>${descripcion}</td>
            <td class="text-center">${cantEntradaFinal}</td>
            <td class="text-center">${cantSalidaFinal}</td>
            <td class="text-end small" ${strikeStyle}>${costo.toFixed(2)}</td>
            <td class="text-end small" ${strikeStyle}>${importe > 0 ? fmtLps(importe) : '—'}</td>
            <td class="text-end">${saldo}</td>
            <td class="text-center">${btnAnular}</td>
          </tr>`;
      });

      $("#tablaKardex tbody").html(html);

      // ── Resumen del período ──────────────────────────────────
      const saldoFinal = parseInt(movimientos[movimientos.length - 1].stock_nuevo);
      $("#kardexResumen").html(`
        <span><i class="bi bi-box-arrow-in-down text-success me-1"></i>
          Entradas: <strong>${totEntU}</strong> uds · L ${fmtLps(totEntL)}</span>
        <span><i class="bi bi-box-arrow-up text-danger me-1"></i>
          Salidas: <strong>${totSalU}</strong> uds · L ${fmtLps(totSalL)}</span>
        <span><i class="bi bi-flag me-1"></i>
          Saldo inicial: <strong>${saldoInicial}</strong></span>
        <span><i class="bi bi-flag-fill me-1"></i>
          Saldo final: <strong>${saldoFinal}</strong></span>
      `);
    },
    "json"
  );
}

function confirmarAnulacion(id_movimiento) {
  Swal.fire({
    title: '¿Anular este movimiento?',
    html: 'Se generará un movimiento inverso y el stock quedará corregido.<br><br>' +
          '<strong>Esta acción queda registrada en el kardex.</strong>',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    confirmButtonText: 'Sí, anular',
    cancelButtonText: 'Cancelar',
  }).then((result) => {
    if (!result.isConfirmed) return;

    $.post(
      "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
      { accion: "anularMovimiento", id_movimiento },
      function (resp) {
        if (!resp.ok) {
          Swal.fire("Error", resp.mensaje, "error");
          return;
        }
        Swal.fire({
          icon: "success",
          title: "Movimiento anulado",
          text: `Stock actualizado a ${resp.data.stock_nuevo}.`,
          timer: 2000,
          showConfirmButton: false,
        });
        // Recargar kardex y tabla principal
        cargarKardex(_kardexIdRepuesto);
        listarRepuestos();
      },
      "json"
    );
  });
}

function imprimirKardex() {
  const { nombre, marca, modelo } = _kardexInfo;
  const meta    = [marca, modelo].filter(v => v && v !== '—').join(' · ');
  const fecha   = new Date().toLocaleDateString('es-HN', { day: '2-digit', month: '2-digit', year: 'numeric' });
  const hora    = new Date().toLocaleTimeString('es-HN', { hour: '2-digit', minute: '2-digit' });
  const usuario = window.USUARIO_NOMBRE || 'Usuario';

  // Clonar la tabla del kardex sin los botones de anular
  const tablaOrig = document.getElementById('tablaKardex');
  const tablaClone = tablaOrig.cloneNode(true);

  // Eliminar la última columna (Anular) del encabezado y filas clonadas
  tablaClone.querySelectorAll('tr').forEach(tr => {
    const ultima = tr.lastElementChild;
    if (ultima) ultima.remove();
  });

  const tablaHtml = tablaClone.outerHTML;

  // Período filtrado y resumen de totales
  const desde   = $('#kardex_desde').val();
  const hasta   = $('#kardex_hasta').val();
  const periodo = (desde || hasta)
    ? `Período: ${desde || 'inicio'} → ${hasta || 'hoy'}`
    : 'Historial completo';
  const resumenHtml = document.getElementById('kardexResumen')
    ? document.getElementById('kardexResumen').innerHTML
    : '';

  const ventana = window.open('', '_blank', 'width=950,height=750');
  ventana.document.write(`
    <!DOCTYPE html>
    <html lang="es">
    <head>
      <meta charset="UTF-8">
      <title>Kardex — ${nombre}</title>
      <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #212529; padding: 28px 36px; }

        .header { border-bottom: 2px solid #0d6efd; padding-bottom: 10px; margin-bottom: 16px;
                  display: flex; justify-content: space-between; align-items: flex-end; }
        .header .left h1 { font-size: 16px; color: #0d6efd; margin-bottom: 2px; }
        .header .left p  { font-size: 12px; color: #555; margin: 0; }
        .header .right   { text-align: right; font-size: 11px; color: #555; line-height: 1.6; }
        .header .right strong { color: #212529; }

        table { width: 100%; border-collapse: collapse; font-size: 11px; margin-top: 4px; }
        thead th { background: #f1f3f5; border: 1px solid #dee2e6; padding: 5px 7px;
                   font-weight: 600; color: #495057; }
        tbody td { border: 1px solid #dee2e6; padding: 4px 7px; }
        tfoot td { border: 1px solid #dee2e6; padding: 4px 7px; }

        /* colores de fila Bootstrap clonados → reinterpretados */
        .table-primary td { background: #cfe2ff !important; }
        .table-secondary td { background: #e2e3e5 !important; color: #6c757d; }

        .badge { display: inline-block; padding: 1px 6px; border-radius: 3px;
                 font-size: 10px; font-weight: 600; }
        .bg-success   { background:#198754; color:#fff; }
        .bg-danger    { background:#dc3545; color:#fff; }
        .bg-primary   { background:#0d6efd; color:#fff; }
        .bg-secondary { background:#6c757d; color:#fff; }
        .bg-warning   { background:#ffc107; color:#212529; }

        .text-end    { text-align: right; }
        .text-center { text-align: center; }
        .text-muted  { color: #6c757d; }
        .fw-semibold { font-weight: 600; }
        .fw-bold     { font-weight: 700; }
        .small       { font-size: 10px; }
        .text-nowrap { white-space: nowrap; }
        .me-1        { margin-right: 3px; }
        .bi::before  { display: none; }

        .footer-print { margin-top: 14px; font-size: 11px; color: #555;
                        border-top: 1px solid #dee2e6; padding-top: 8px;
                        display: flex; justify-content: space-between; }

        @media print { body { padding: 15mm 18mm; } @page { margin: 0; } }
      </style>
    </head>
    <body>
      <div class="header">
        <div class="left">
          <h1><i class="bi bi-journal-text"></i> Kardex de Repuesto</h1>
          <p><strong>${nombre}</strong>${meta ? ' &nbsp;·&nbsp; ' + meta : ''}</p>
        </div>
        <div class="right">
          <div>Fecha de impresión: <strong>${fecha} ${hora}</strong></div>
          <div>Impreso por: <strong>${usuario}</strong></div>
          <div>${periodo} · Valuación en Lempiras (L)</div>
        </div>
      </div>

      ${tablaHtml}

      <div class="footer-print" style="justify-content:flex-start;gap:24px;">
        ${resumenHtml}
      </div>

      <div class="footer-print">
        <span>Honducafe — Sistema de Control de Inventario</span>
        <span>Generado el ${fecha} a las ${hora} por ${usuario}</span>
      </div>

      <script>window.onload = function(){ window.print(); };<\/script>
    </body>
    </html>
  `);
  ventana.document.close();
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
              ${ES_ADMIN ? `<button class="btn btn-warning btn-sm"
                onclick="abrirEditarDetalle(${d.id_detalle_repuesto}, '${d.serie}', ${d.id_estado_repuesto}, ${d.id_maquina_actual || 0})">
                <i class="bi bi-pencil"></i>
              </button>` : '<span class="text-muted">—</span>'}
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

function cargarUbicaciones() {
  $.post(
    "./modules/Electronicas/Repuestos/Controllers/repuestosController.php",
    { accion: "ubicaciones" },
    function (resp) {
      if (!resp.ok) return;

      let html = `<option value="">-- Sin ubicación --</option>`;
      resp.data.forEach((u) => {
        html += `<option value="${u.id_ubicacion}">${u.nombre}</option>`;
      });

      $("#id_ubicacion").html(html).trigger("change");
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
  // Resetear todos los Select2 del modal explícitamente
  $("#id_proveedor, #id_tipo, #id_marca, #id_modelo, #id_ubicacion")
    .val(null).trigger("change");
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
