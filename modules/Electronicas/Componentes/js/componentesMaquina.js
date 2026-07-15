//////////////////////////////////////////////////////////
// 🧩 COMPONENTES DE UNA MÁQUINA — estados sobre la radiografía
//    (se usa dentro de la vista de Máquinas, patrón eyectores)
//////////////////////////////////////////////////////////

const CM_CTRL = "./modules/Electronicas/Componentes/controllers/componentesController.php";

let cmModal = null;
let cmHistModal = null;
let cmMaquinaActual = null;
let cmDatos = null;          // { maquina, estados, componentes }
let cmVolverABarra = false;  // reabrir este modal al cerrar la barra

function cmPuedeEditar() {
  return ["Administrador", "Técnico"].includes(window.USUARIO_ROL);
}

function cmEscape(texto) {
  return $("<span>").text(texto ?? "").html();
}

function abrirComponentesMaquina(id_maquina) {
  cmMaquinaActual = id_maquina;

  $("#cmMaquinaNombre").text("");
  $("#btnVerBarraEyectores, #btnHistorialComponentes").addClass("d-none");
  $("#cmContenido").html(
    '<div class="text-center py-5"><span class="spinner-border text-primary"></span></div>',
  );

  if (!cmModal) {
    // focus:false — sin esto el modal recaptura el foco y no se puede
    // escribir en los campos de los diálogos SweetAlert (observaciones)
    cmModal = new bootstrap.Modal(document.getElementById("modalComponentesMaquina"), {
      focus: false,
    });
  }
  cmModal.show();
  cmCargar();
}

function cmCargar() {
  $.post(CM_CTRL, { accion: "estadoMaquina", id_maquina: cmMaquinaActual }, null, "json")
    .done(function (resp) {
      if (!resp.ok) {
        $("#cmContenido").html(
          `<p class="text-center text-danger py-4"><i class="bi bi-exclamation-circle me-1"></i>${cmEscape(resp.mensaje || "Error")}</p>`,
        );
        return;
      }
      cmDatos = resp.data;
      $("#cmMaquinaNombre").text(cmDatos.maquina.maquina || "");
      $("#btnVerBarraEyectores").toggleClass("d-none", !(Number(cmDatos.maquina.tiene_eyectores) > 0));
      $("#btnHistorialComponentes").removeClass("d-none");
      cmRender();
    })
    .fail(function () {
      $("#cmContenido").html(
        '<p class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle me-1"></i>Error al cargar los componentes.</p>',
      );
    });
}

function cmRender() {
  const comps = cmDatos.componentes || [];

  if (comps.length === 0) {
    $("#cmContenido").html(
      '<p class="text-center text-muted py-5"><i class="bi bi-info-circle me-1"></i>El modelo de esta máquina no tiene componentes en el catálogo.</p>',
    );
    return;
  }

  // Numeración: mismo orden que la ficha del modelo (por id del catálogo)
  let lienzo = "";
  if (cmDatos.maquina.imagen_esquema) {
    let pines = "";
    comps.forEach((c, i) => {
      if (c.pos_x === null || c.pos_y === null) return;
      pines += `<button class="cm-pin ${c.clase_css}" type="button"
                        data-id="${c.id_maquina_componente}"
                        style="left:${Number(c.pos_x)}%;top:${Number(c.pos_y)}%"
                        title="${cmEscape(c.componente)} — ${cmEscape(c.estado)}">${i + 1}</button>`;
    });
    lienzo = `
      <div class="cm-lienzo">
        <img src="./${cmEscape(cmDatos.maquina.imagen_esquema)}" alt="Radiografía del modelo">
        ${pines}
      </div>`;
  }

  let lista = "";
  comps.forEach((c, i) => {
    lista += `
      <div class="cm-item d-flex align-items-center gap-2 border-bottom py-2 px-1"
           data-id="${c.id_maquina_componente}">
        <span class="cm-num">${i + 1}</span>
        <div class="flex-grow-1">
          <span class="fw-semibold">${cmEscape(c.componente)}</span>
          <span class="text-muted small">× ${c.cantidad}</span>
          ${c.especificacion ? `<span class="cm-espec d-block">${cmEscape(c.especificacion)}</span>` : ""}
          ${c.observacion ? `<span class="cm-obs d-block"><i class="bi bi-chat-left-text me-1"></i>${cmEscape(c.observacion)}</span>` : ""}
        </div>
        <span class="badge ${c.clase_css}${c.clase_css === "bg-warning" ? " text-dark" : ""}">${cmEscape(c.estado)}</span>
      </div>`;
  });

  $("#cmContenido").html(lienzo + `<div>${lista}</div>`);
}

// ── Sincronía pin ↔ item ────────────────────────────────
$(document).on("mouseenter", "#cmContenido .cm-pin, #cmContenido .cm-item", function () {
  const id = $(this).data("id");
  $(`#cmContenido [data-id="${id}"]`).addClass("activo");
}).on("mouseleave", "#cmContenido .cm-pin, #cmContenido .cm-item", function () {
  const id = $(this).data("id");
  $(`#cmContenido [data-id="${id}"]`).removeClass("activo");
});

// ── Clic en pin o item → ver / cambiar estado ───────────
$(document).on("click", "#cmContenido .cm-pin, #cmContenido .cm-item", function () {
  const id = $(this).data("id");
  const c = (cmDatos.componentes || []).find((x) => x.id_maquina_componente == id);
  if (!c) return;

  if (!cmPuedeEditar()) {
    Swal.fire({
      title: cmEscape(c.componente),
      icon: "info",
      html:
        `<p class="mb-1"><strong>Estado:</strong> ${cmEscape(c.estado)}</p>` +
        (c.observacion ? `<p class="text-muted small mb-0">${cmEscape(c.observacion)}</p>` : ""),
    });
    return;
  }

  const opciones = (cmDatos.estados || [])
    .map((e) => `<option value="${e.id_estado}" ${e.id_estado == c.id_estado ? "selected" : ""}>${cmEscape(e.nombre)}</option>`)
    .join("");

  Swal.fire({
    title: cmEscape(c.componente),
    html: `
      <select id="swCmEstado" class="form-select mb-2">${opciones}</select>
      <textarea id="swCmObs" class="form-control" rows="2" placeholder="Observación (opcional)">${cmEscape(c.observacion || "")}</textarea>`,
    showCancelButton: true,
    confirmButtonText: "Guardar",
    cancelButtonText: "Cancelar",
    focusConfirm: false,
    preConfirm: () => ({
      id_estado: document.getElementById("swCmEstado").value,
      observacion: document.getElementById("swCmObs").value.trim(),
    }),
  }).then((r) => {
    if (!r.isConfirmed) return;
    $.post(CM_CTRL, {
      accion: "actualizarEstado",
      id_maquina_componente: id,
      id_estado: r.value.id_estado,
      observacion: r.value.observacion,
    }, null, "json")
      .done(function (resp) {
        if (!resp.ok) return Swal.fire("Error", resp.mensaje, "error");
        const est = (cmDatos.estados || []).find((e) => e.id_estado == r.value.id_estado);
        if (est) {
          c.id_estado = est.id_estado;
          c.estado = est.nombre;
          c.clase_css = est.clase_css;
          c.observacion = r.value.observacion;
        }
        cmRender();
      })
      .fail(function () {
        Swal.fire("Error", "Error en el servidor", "error");
      });
  });
});

// ── Ver barra de eyectores (visor detallado existente) ──
$(document).on("click", "#btnVerBarraEyectores", function () {
  if (typeof abrirEyectores !== "function") return;
  cmVolverABarra = true;
  cmModal.hide();
  abrirEyectores(cmMaquinaActual);
});

$(document).on("hidden.bs.modal", "#modalEyectores", function () {
  if (cmVolverABarra) {
    cmVolverABarra = false;
    cmModal.show();
    cmCargar(); // refresca por si cambió algo en la barra
  }
});

// ── Historial de componentes de la máquina ──────────────
$(document).on("click", "#btnHistorialComponentes", function () {
  if (!cmHistModal) {
    cmHistModal = new bootstrap.Modal(document.getElementById("modalHistorialComponentes"));
    document
      .getElementById("modalHistorialComponentes")
      .addEventListener("hidden.bs.modal", () => {
        if (cmModal) cmModal.show();
      });
  }

  $("#cmHistMaquinaNombre").text($("#cmMaquinaNombre").text());
  $("#cmHistFiltro").val("");
  $("#cmHistContenido").html(
    '<div class="text-center py-5"><span class="spinner-border text-primary"></span></div>',
  );

  cmModal.hide();
  cmHistModal.show();

  $.post(CM_CTRL, { accion: "historialComponentes", id_maquina: cmMaquinaActual }, null, "json")
    .done(function (resp) {
      if (!resp.ok) {
        $("#cmHistContenido").html(
          `<p class="text-center text-danger py-4"><i class="bi bi-exclamation-circle me-1"></i>${cmEscape(resp.mensaje || "Error")}</p>`,
        );
        return;
      }
      cmRenderHistorial(resp.data || []);
    })
    .fail(function () {
      $("#cmHistContenido").html(
        '<p class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle me-1"></i>Error al cargar el historial.</p>',
      );
    });
});

function cmRenderHistorial(data) {
  if (data.length === 0) {
    $("#cmHistContenido").html(
      '<p class="text-center text-muted py-5"><i class="bi bi-info-circle me-1"></i>Esta máquina no tiene cambios de componentes registrados.</p>',
    );
    return;
  }

  const filas = data
    .map((h) => {
      const ant = h.estado_anterior
        ? `<span class="badge ${h.clase_anterior}${h.clase_anterior === "bg-warning" ? " text-dark" : ""}">${cmEscape(h.estado_anterior)}</span>`
        : '<span class="text-muted small">—</span>';
      const texto = [h.componente, h.estado_anterior, h.estado_nuevo, h.usuario, h.observacion]
        .filter(Boolean).join(" ").toLowerCase();

      return `
      <tr data-texto="${cmEscape(texto)}">
        <td class="text-nowrap small">${cmEscape(h.fecha)}</td>
        <td class="fw-semibold">${cmEscape(h.componente)}</td>
        <td class="text-nowrap">${ant} <i class="bi bi-arrow-right small text-muted"></i>
            <span class="badge ${h.clase_nuevo}${h.clase_nuevo === "bg-warning" ? " text-dark" : ""}">${cmEscape(h.estado_nuevo)}</span></td>
        <td class="small">${h.observacion ? cmEscape(h.observacion) : '<span class="text-muted">—</span>'}</td>
        <td class="small">${h.usuario ? cmEscape(h.usuario) : '<span class="text-muted">—</span>'}</td>
      </tr>`;
    })
    .join("");

  $("#cmHistContenido").html(`
    <p class="text-muted small mb-2">
      <i class="bi bi-list-check me-1"></i>
      ${data.length} cambio(s) registrado(s)${data.length >= 500 ? " — mostrando los 500 más recientes" : ""}
    </p>
    <table class="table table-hover w-100 mb-0">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Componente</th>
          <th>Cambio</th>
          <th>Observación</th>
          <th>Usuario</th>
        </tr>
      </thead>
      <tbody id="cmHistBody">${filas}</tbody>
    </table>`);
}

$(document).on("input", "#cmHistFiltro", function () {
  const q = $(this).val().trim().toLowerCase();
  $("#cmHistBody tr").each(function () {
    $(this).toggle(String($(this).data("texto")).includes(q));
  });
});
