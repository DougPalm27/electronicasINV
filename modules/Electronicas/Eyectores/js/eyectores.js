//////////////////////////////////////////////////////////
// 🔩 EYECTORES — barra de componentes de la máquina
//////////////////////////////////////////////////////////

let eyModal = null;
let eyMaquinaActual = null;
let eyConfig = null;
let eyEstados = [];
let eyDatos = []; // eyectores actuales en memoria
let eyModoSeleccion = false;
let eySeleccion = new Set(); // id_eyector seleccionados

function eyPuedeEditar() {
  return ["Administrador", "Técnico"].includes(window.USUARIO_ROL);
}

function abrirEyectores(id_maquina) {
  eyMaquinaActual = id_maquina;

  $("#eyMaquinaNombre").text("");
  $("#eyStats").addClass("d-none");
  $("#btnInicializarBarra, #btnHistorialEyectores").addClass("d-none");
  $("#eyContenido").html(
    '<div class="text-center py-5"><span class="spinner-border text-primary"></span></div>',
  );

  // Reiniciar modo selección
  eyModoSeleccion = false;
  eySeleccion.clear();
  $("#btnAplicarSeleccion, #btnLimpiarSeleccion, #btnModoSeleccion").addClass("d-none");
  eyResetBotonModo();

  if (!eyModal) {
    eyModal = new bootstrap.Modal(document.getElementById("modalEyectores"));
  }
  eyModal.show();

  cargarEyectores();
}

function cargarEyectores() {
  $.ajax({
    url: "./modules/Electronicas/Eyectores/Controllers/listarEyectores.php",
    type: "POST",
    dataType: "json",
    data: { id_maquina: eyMaquinaActual },
    success: function (resp) {
      if (!resp.ok) {
        $("#eyContenido").html(
          `<p class="text-center text-danger py-4"><i class="bi bi-exclamation-circle me-1"></i>${resp.mensaje || "Error"}</p>`,
        );
        return;
      }

      $("#eyMaquinaNombre").text(resp.maquina || "");

      if (!resp.configurado) {
        $("#eyContenido").html(
          '<p class="text-center text-muted py-5"><i class="bi bi-info-circle me-1"></i>Este modelo no tiene configuración de eyectores.</p>',
        );
        return;
      }

      eyConfig = resp.config;
      eyEstados = resp.estados || [];

      if (!resp.inicializado) {
        $("#eyContenido").html(
          '<p class="text-center text-muted py-5"><i class="bi bi-info-circle me-1"></i>La barra de eyectores aún no ha sido inicializada.</p>',
        );
        if (eyPuedeEditar()) $("#btnInicializarBarra").removeClass("d-none");
        return;
      }

      renderBarra(resp.eyectores || []);
    },
    error: function () {
      $("#eyContenido").html(
        '<p class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle me-1"></i>Error al cargar los eyectores.</p>',
      );
    },
  });
}

function eyClase(id_estado) {
  const e = eyEstados.find((x) => x.id_estado == id_estado);
  return e ? e.clase_css : "bg-secondary";
}

function eyLadoLabel(lado) {
  return lado === "I" ? "Izq" : "Der";
}

//////////////////////////////////////////////////////////
// Tooltip propio: muestra el número (y estado) al pasar el mouse
//////////////////////////////////////////////////////////

let eyTooltip = null;

function eyGetTooltip() {
  if (!eyTooltip) {
    eyTooltip = document.createElement("div");
    eyTooltip.className = "ey-tooltip";
    document.body.appendChild(eyTooltip);
  }
  return eyTooltip;
}

$(document).on("mouseenter", "#eyContenido .ey-dot", function () {
  const num = $(this).data("num");
  const lado = $(this).data("lado");
  const ey = eyDatos.find((e) => e.id_eyector == $(this).data("id"));

  const tip = eyGetTooltip();
  tip.textContent = `${eyLadoLabel(lado)} #${num}${ey ? " — " + ey.estado : ""}`;

  const r = this.getBoundingClientRect();
  tip.style.left = r.left + r.width / 2 + "px";
  tip.style.top = r.top - 6 + "px";
  tip.classList.add("show");
});

$(document).on("mouseleave", "#eyContenido .ey-dot", function () {
  if (eyTooltip) eyTooltip.classList.remove("show");
});

// Ocultar el tooltip al cerrar el modal (evita que quede huérfano)
$(document).on("hide.bs.modal", "#modalEyectores", function () {
  if (eyTooltip) eyTooltip.classList.remove("show");
});

function renderBarra(eyectores) {
  eyDatos = eyectores;

  const ladoHtml = (lado, titulo) => {
    const lista = eyectores.filter((e) => e.lado === lado);
    const filas = {};
    lista.forEach((e) => {
      (filas[e.fila] = filas[e.fila] || []).push(e);
    });

    let rows = "";
    Object.keys(filas)
      .sort((a, b) => a - b)
      .forEach((f) => {
        const dots = filas[f]
          .sort((a, b) => a.posicion - b.posicion)
          .map(
            (e) =>
              `<span class="ey-dot ${eyClase(e.id_estado)}" data-id="${e.id_eyector}" data-num="${e.numero}" data-lado="${e.lado}" aria-label="${eyLadoLabel(e.lado)} #${e.numero} — ${e.estado}"></span>`,
          )
          .join("");
        rows += `<div class="ey-row"><span class="ey-fila">Fila ${f}</span><div class="ey-dots">${dots}</div></div>`;
      });

    return `<div class="ey-side"><div class="ey-side-title">${titulo} — ${lista.length} eyectores</div>${rows}</div>`;
  };

  $("#eyContenido").html(
    `<div class="ey-bar">${ladoHtml("I", "Lado Izquierdo")}${ladoHtml("D", "Lado Derecho")}</div>`,
  );

  actualizarEyStats();
  $("#eyStats").removeClass("d-none");
  $("#btnHistorialEyectores").removeClass("d-none");

  if (eyPuedeEditar()) $("#btnModoSeleccion").removeClass("d-none");
}

function actualizarEyStats() {
  const c = { OK: 0, Falla: 0, Advertencia: 0, Apagado: 0 };
  eyDatos.forEach((e) => {
    if (c[e.estado] !== undefined) c[e.estado]++;
  });
  $("#eyOk").text(c.OK);
  $("#eyFalla").text(c.Falla);
  $("#eyAdv").text(c.Advertencia);
  $("#eyOff").text(c.Apagado);
}

//////////////////////////////////////////////////////////
// Click en un eyector → ver / editar
//////////////////////////////////////////////////////////

$(document).on("click", "#eyContenido .ey-dot", function () {
  const id = $(this).data("id");
  const num = $(this).data("num");
  const lado = $(this).data("lado");
  const etiqueta = `${eyLadoLabel(lado)} #${num}`;
  const ey = eyDatos.find((e) => e.id_eyector == id);
  if (!ey) return;

  // En modo selección, el click/arrastre lo maneja el "pintado" (mousedown)
  if (eyModoSeleccion) return;

  if (!eyPuedeEditar()) {
    Swal.fire({
      title: etiqueta,
      icon: "info",
      html:
        `<p class="mb-1"><strong>Estado:</strong> ${ey.estado}</p>` +
        (ey.observacion ? `<p class="text-muted small mb-0">${ey.observacion}</p>` : ""),
    });
    return;
  }

  const opciones = eyEstados
    .map(
      (e) =>
        `<option value="${e.id_estado}" ${e.id_estado == ey.id_estado ? "selected" : ""}>${e.nombre}</option>`,
    )
    .join("");

  Swal.fire({
    title: etiqueta,
    html: `
      <select id="swEstado" class="form-select mb-2">${opciones}</select>
      <textarea id="swObs" class="form-control" rows="2" placeholder="Observación (opcional)">${ey.observacion || ""}</textarea>`,
    showCancelButton: true,
    confirmButtonText: "Guardar",
    cancelButtonText: "Cancelar",
    focusConfirm: false,
    preConfirm: () => ({
      id_estado: document.getElementById("swEstado").value,
      observacion: document.getElementById("swObs").value.trim(),
    }),
  }).then((r) => {
    if (!r.isConfirmed) return;
    guardarEyector(id, r.value.id_estado, r.value.observacion);
  });
});

function guardarEyector(id_eyector, id_estado, observacion) {
  $.ajax({
    url: "./modules/Electronicas/Eyectores/Controllers/actualizarEyector.php",
    type: "POST",
    dataType: "json",
    data: { id_eyector, id_estado, observacion },
    success: function (resp) {
      if (!resp.ok) {
        Swal.fire("Error", resp.mensaje || "No se pudo actualizar", "error");
        return;
      }

      const ey = eyDatos.find((e) => e.id_eyector == id_eyector);
      const est = eyEstados.find((e) => e.id_estado == id_estado);
      if (ey && est) {
        ey.id_estado = est.id_estado;
        ey.estado = est.nombre;
        ey.observacion = observacion;

        const dot = $(`#eyContenido .ey-dot[data-id="${id_eyector}"]`);
        dot.attr("class", `ey-dot ${est.clase_css}`);
        dot.attr("aria-label", `${eyLadoLabel(ey.lado)} #${ey.numero} — ${est.nombre}`);
      }
      actualizarEyStats();
    },
    error: function () {
      Swal.fire("Error", "Error en el servidor", "error");
    },
  });
}

//////////////////////////////////////////////////////////
// Inicializar barra
//////////////////////////////////////////////////////////

$(document).on("click", "#btnInicializarBarra", function () {
  Swal.fire({
    title: "¿Inicializar barra?",
    text: "Se generarán todos los eyectores en estado OK.",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Inicializar",
    cancelButtonText: "Cancelar",
  }).then((r) => {
    if (!r.isConfirmed) return;

    $.ajax({
      url: "./modules/Electronicas/Eyectores/Controllers/inicializarBarra.php",
      type: "POST",
      dataType: "json",
      data: { id_maquina: eyMaquinaActual },
      success: function (resp) {
        if (!resp.ok) {
          Swal.fire("Error", resp.mensaje || "Error", "error");
          return;
        }
        $("#btnInicializarBarra").addClass("d-none");
        cargarEyectores();
      },
      error: function () {
        Swal.fire("Error", "Error en el servidor", "error");
      },
    });
  });
});

//////////////////////////////////////////////////////////
// Selección múltiple
//////////////////////////////////////////////////////////

function eyRefrescarContador() {
  $("#eySelCount").text(eySeleccion.size);
  $("#btnAplicarSeleccion").prop("disabled", eySeleccion.size === 0);
}

// ── Arrastrar para seleccionar (pintar) ──────────────────
let eyPintando = false;
let eyPintarModo = "add"; // "add" | "remove"

function eyAplicarPaint(dot, id) {
  if (eyPintarModo === "add") {
    if (!eySeleccion.has(id)) {
      eySeleccion.add(id);
      $(dot).addClass("ey-selected");
      eyRefrescarContador();
    }
  } else {
    if (eySeleccion.has(id)) {
      eySeleccion.delete(id);
      $(dot).removeClass("ey-selected");
      eyRefrescarContador();
    }
  }
}

$(document).on("mousedown", "#eyContenido .ey-dot", function (e) {
  if (!eyModoSeleccion) return;
  e.preventDefault(); // evita seleccionar texto al arrastrar
  const id = $(this).data("id");
  eyPintando = true;
  // Si el primero ya estaba seleccionado, el arrastre deselecciona
  eyPintarModo = eySeleccion.has(id) ? "remove" : "add";
  eyAplicarPaint(this, id);
});

$(document).on("mouseenter", "#eyContenido .ey-dot", function () {
  if (!eyModoSeleccion || !eyPintando) return;
  eyAplicarPaint(this, $(this).data("id"));
});

$(document).on("mouseup", function () {
  eyPintando = false;
});

function eyLimpiarSeleccion() {
  eySeleccion.clear();
  $("#eyContenido .ey-dot.ey-selected").removeClass("ey-selected");
  eyRefrescarContador();
}

function eyResetBotonModo() {
  $("#btnModoSeleccion")
    .removeClass("btn-primary")
    .addClass("btn-outline-primary")
    .html('<i class="bi bi-check2-square me-1"></i>Seleccionar varios');
}

$(document).on("click", "#btnModoSeleccion", function () {
  eyModoSeleccion = !eyModoSeleccion;
  eyLimpiarSeleccion();

  if (eyModoSeleccion) {
    $(this)
      .removeClass("btn-outline-primary")
      .addClass("btn-primary")
      .html('<i class="bi bi-x-lg me-1"></i>Salir de selección');
    $("#btnAplicarSeleccion, #btnLimpiarSeleccion").removeClass("d-none");
  } else {
    eyResetBotonModo();
    $("#btnAplicarSeleccion, #btnLimpiarSeleccion").addClass("d-none");
  }
});

$(document).on("click", "#btnLimpiarSeleccion", eyLimpiarSeleccion);

$(document).on("click", "#btnAplicarSeleccion", function () {
  if (eySeleccion.size === 0) return;

  const opciones = eyEstados
    .map((e) => `<option value="${e.id_estado}">${e.nombre}</option>`)
    .join("");

  Swal.fire({
    title: `Cambiar estado de ${eySeleccion.size} eyector(es)`,
    html: `
      <select id="swEstado" class="form-select mb-2">${opciones}</select>
      <textarea id="swObs" class="form-control" rows="2" placeholder="Observación (opcional)"></textarea>`,
    showCancelButton: true,
    confirmButtonText: "Aplicar",
    cancelButtonText: "Cancelar",
    focusConfirm: false,
    preConfirm: () => ({
      id_estado: document.getElementById("swEstado").value,
      observacion: document.getElementById("swObs").value.trim(),
    }),
  }).then((r) => {
    if (!r.isConfirmed) return;
    aplicarLote(r.value.id_estado, r.value.observacion);
  });
});

function aplicarLote(id_estado, observacion) {
  const ids = Array.from(eySeleccion);

  $.ajax({
    url: "./modules/Electronicas/Eyectores/Controllers/actualizarEyectoresLote.php",
    type: "POST",
    dataType: "json",
    data: { ids, id_estado, observacion },
    success: function (resp) {
      if (!resp.ok) {
        Swal.fire("Error", resp.mensaje || "No se pudo actualizar", "error");
        return;
      }

      const est = eyEstados.find((e) => e.id_estado == id_estado);
      ids.forEach((id) => {
        const ey = eyDatos.find((e) => e.id_eyector == id);
        if (ey && est) {
          ey.id_estado = est.id_estado;
          ey.estado = est.nombre;
          ey.observacion = observacion;

          const dot = $(`#eyContenido .ey-dot[data-id="${id}"]`);
          dot.attr("class", `ey-dot ${est.clase_css}`);
          dot.attr("aria-label", `${eyLadoLabel(ey.lado)} #${ey.numero} — ${est.nombre}`);
        }
      });

      eyLimpiarSeleccion();
      actualizarEyStats();

      Swal.fire({
        icon: "success",
        title: "Listo",
        text: `${resp.count} eyector(es) actualizados`,
        timer: 1500,
        showConfirmButton: false,
      });
    },
    error: function () {
      Swal.fire("Error", "Error en el servidor", "error");
    },
  });
}

//////////////////////////////////////////////////////////
// 📋 Historial de cambios de la barra
//////////////////////////////////////////////////////////

let eyHistModal = null;

function eyEscape(texto) {
  return $("<span>").text(texto ?? "").html();
}

$(document).on("click", "#btnHistorialEyectores", function () {
  if (!eyHistModal) {
    eyHistModal = new bootstrap.Modal(document.getElementById("modalHistorialEyectores"));
    // Al cerrar el historial se vuelve al modal de la barra
    document
      .getElementById("modalHistorialEyectores")
      .addEventListener("hidden.bs.modal", () => {
        if (eyModal) eyModal.show();
      });
  }

  $("#eyHistMaquinaNombre").text($("#eyMaquinaNombre").text());
  $("#eyHistFiltro").val("");
  $("#eyHistContenido").html(
    '<div class="text-center py-5"><span class="spinner-border text-primary"></span></div>',
  );

  eyModal.hide();
  eyHistModal.show();

  $.ajax({
    url: "./modules/Electronicas/Eyectores/Controllers/historialEyectores.php",
    type: "POST",
    dataType: "json",
    data: { id_maquina: eyMaquinaActual },
    success: function (resp) {
      if (!resp.ok) {
        $("#eyHistContenido").html(
          `<p class="text-center text-danger py-4"><i class="bi bi-exclamation-circle me-1"></i>${eyEscape(resp.mensaje || "Error")}</p>`,
        );
        return;
      }
      renderHistorialEyectores(resp.data || []);
    },
    error: function () {
      $("#eyHistContenido").html(
        '<p class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle me-1"></i>Error al cargar el historial.</p>',
      );
    },
  });
});

function renderHistorialEyectores(data) {
  if (data.length === 0) {
    $("#eyHistContenido").html(
      '<p class="text-center text-muted py-5"><i class="bi bi-info-circle me-1"></i>Esta barra no tiene cambios de estado registrados.</p>',
    );
    return;
  }

  const filas = data
    .map((h) => {
      const etiqueta = `${eyLadoLabel(h.lado)} #${h.numero}`;
      const ant = h.estado_anterior
        ? `<span class="badge ${h.clase_anterior}">${eyEscape(h.estado_anterior)}</span>`
        : '<span class="text-muted small">—</span>';
      const texto = [etiqueta, h.estado_anterior, h.estado_nuevo, h.usuario, h.observacion]
        .filter(Boolean)
        .join(" ")
        .toLowerCase();

      return `
      <tr data-texto="${eyEscape(texto)}">
        <td class="text-nowrap small">${eyEscape(h.fecha)}</td>
        <td class="text-nowrap fw-semibold">${etiqueta}</td>
        <td class="text-nowrap">${ant} <i class="bi bi-arrow-right small text-muted"></i> <span class="badge ${h.clase_nuevo}">${eyEscape(h.estado_nuevo)}</span></td>
        <td class="small">${h.observacion ? eyEscape(h.observacion) : '<span class="text-muted">—</span>'}</td>
        <td class="small">${h.usuario ? eyEscape(h.usuario) : '<span class="text-muted">—</span>'}</td>
      </tr>`;
    })
    .join("");

  $("#eyHistContenido").html(`
    <p class="text-muted small mb-2">
      <i class="bi bi-list-check me-1"></i>
      ${data.length} cambio(s) registrado(s)${data.length >= 500 ? " — mostrando los 500 más recientes" : ""}
    </p>
    <table class="table table-hover w-100 mb-0">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Eyector</th>
          <th>Cambio</th>
          <th>Observación</th>
          <th>Usuario</th>
        </tr>
      </thead>
      <tbody id="eyHistBody">${filas}</tbody>
    </table>`);
}

$(document).on("input", "#eyHistFiltro", function () {
  const q = $(this).val().trim().toLowerCase();
  $("#eyHistBody tr").each(function () {
    $(this).toggle(String($(this).data("texto")).includes(q));
  });
});
