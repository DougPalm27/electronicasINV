//////////////////////////////////////////////////////////
// 🧩 CATÁLOGO DE COMPONENTES POR MODELO
//////////////////////////////////////////////////////////

const CC_CTRL = "./modules/Electronicas/Componentes/controllers/componentesController.php";

let ccModelos = [];        // dataset de cards
let ccFicha = null;        // { modelo, componentes } del modelo abierto
let ccCatalogo = [];       // catálogo maestro (selects)
let ccRepuestos = [];      // repuestos (select de vínculo)
let ccModalItem = null;
let ccEditandoPines = false;
let ccPosicionesOriginales = {};

function ccEsAdmin() {
  return window.USUARIO_ROL === "Administrador";
}

function ccEscape(texto) {
  return $("<span>").text(texto ?? "").html();
}

$(document).ready(function () {
  ccListarModelos();

  $("#ccBuscarModelo").on("input", ccFiltrarCards);
  $("#ccGridModelos").on("click", ".cc-card-modelo", function () {
    ccAbrirFicha($(this).data("modelo"));
  });
  $("#ccBtnVolver").click(ccMostrarCards);

  // CRUD de items (delegado)
  $("#ccBtnAgregar").click(() => ccAbrirModalItem(null));
  $("#ccTablaComponentes")
    .on("click", ".btn-editar-item", function () {
      ccAbrirModalItem($(this).data("id"));
    })
    .on("click", ".btn-eliminar-item", function () {
      ccEliminarItem($(this).data("id"));
    });
  $("#ccBtnGuardarItem").click(ccGuardarItem);
  $("#ccBtnNuevoComponente").click(ccNuevoComponenteCatalogo);

  // Sincronía pin ↔ fila
  $("#ccTablaComponentes").on("mouseenter", "tbody tr[data-ref]", function () {
    ccActivar($(this).data("ref"), true);
  }).on("mouseleave", "tbody tr[data-ref]", function () {
    ccActivar($(this).data("ref"), false);
  });
  $("#ccPines").on("mouseenter", ".cc-pin", function () {
    if (!ccEditandoPines) ccActivar($(this).data("ref"), true);
  }).on("mouseleave", ".cc-pin", function () {
    if (!ccEditandoPines) ccActivar($(this).data("ref"), false);
  });

  // Radiografía: imagen y pines
  $("#ccBtnSubirEsquema").click(() => $("#ccInputEsquema").trigger("click"));
  $("#ccInputEsquema").on("change", ccSubirEsquema);
  $("#ccBtnEditarPines").click(ccEntrarEdicionPines);
  $("#ccBtnCancelarPines").click(ccCancelarEdicionPines);
  $("#ccBtnGuardarPines").click(ccGuardarPosiciones);
});

//////////////////////////////////////////////////////////
// 🃏 CARDS POR MODELO
//////////////////////////////////////////////////////////

function ccListarModelos() {
  $.post(CC_CTRL, { accion: "listarModelos" }, null, "json")
    .done(function (resp) {
      if (!resp.ok) return console.error(resp.mensaje);
      ccModelos = resp.data || [];
      ccRenderCards();
    })
    .fail(ccErrorAjax);
}

function ccRenderCards() {
  if (ccModelos.length === 0) {
    $("#ccGridModelos").html(`
      <div class="col-12 text-center py-5 text-muted">
        <i class="bi bi-diagram-3" style="font-size:2.5rem"></i>
        <p class="mt-2 mb-0">No hay modelos registrados todavía.</p>
      </div>`);
    return;
  }

  let html = "";
  ccModelos.forEach((m) => {
    const texto = `${m.marca} ${m.modelo} ${m.tipo_modelo || ""}`.toLowerCase();
    const imagen = m.imagen
      ? `<img src="./${ccEscape(m.imagen)}" alt="${ccEscape(m.modelo)}" loading="lazy">`
      : `<i class="bi bi-cpu cc-placeholder"></i>`;
    const comps = Number(m.componentes);

    html += `
      <div class="col-12 col-sm-6 col-lg-4 col-xxl-3 cc-col-card" data-texto="${ccEscape(texto)}">
        <div class="card shadow-sm border-0 cc-card-modelo h-100" data-modelo="${m.id_modelo}">
          <div class="cc-img">${imagen}</div>
          <div class="card-body pb-3">
            <small class="text-muted">${ccEscape(m.marca)}${m.tipo_modelo ? " · " + ccEscape(m.tipo_modelo) : ""}</small>
            <h6 class="mt-1 mb-2">${ccEscape(m.modelo)}</h6>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-1">
              <span class="small fw-semibold">${m.maquinas} máquina${m.maquinas != 1 ? "s" : ""}</span>
              <span class="badge ${comps > 0 ? "bg-success" : "bg-light text-muted border"}">
                ${comps > 0 ? `${comps} componente${comps != 1 ? "s" : ""}` : "Sin catálogo"}
              </span>
            </div>
          </div>
        </div>
      </div>`;
  });

  $("#ccGridModelos").html(html);
  ccFiltrarCards();
}

function ccFiltrarCards() {
  const q = ($("#ccBuscarModelo").val() || "").trim().toLowerCase();
  $("#ccGridModelos .cc-col-card").each(function () {
    $(this).toggle(String($(this).data("texto")).includes(q));
  });
}

function ccMostrarCards() {
  ccFicha = null;
  $("#ccSeccionFicha").addClass("d-none");
  $("#ccSeccionCards").removeClass("d-none");
  ccListarModelos(); // refresca conteos de las cards
}

//////////////////////////////////////////////////////////
// 📄 FICHA DEL MODELO
//////////////////////////////////////////////////////////

function ccAbrirFicha(id_modelo) {
  $.post(CC_CTRL, { accion: "ficha", id_modelo }, null, "json")
    .done(function (resp) {
      if (!resp.ok) return Swal.fire("Error", resp.mensaje, "error");
      ccFicha = resp.data;
      ccSalirEdicionPines();
      ccRenderFicha();
      $("#ccSeccionCards").addClass("d-none");
      $("#ccSeccionFicha").removeClass("d-none");
    })
    .fail(ccErrorAjax);
}

function ccRecargarFicha() {
  if (ccFicha) ccAbrirFicha(ccFicha.modelo.id_modelo);
}

/* Orden de filas: cada padre seguido de sus hijos.
   Numeración: solo los de primer nivel (coincide con los pines). */
function ccOrdenarComponentes() {
  const items = ccFicha.componentes || [];
  const padres = items.filter((c) => !c.id_padre);
  const hijosDe = (id) => items.filter((c) => c.id_padre == id);

  const filas = [];
  padres.forEach((p, i) => {
    filas.push({ ...p, ref: i + 1, hija: false });
    hijosDe(p.id_modelo_componente).forEach((h) => filas.push({ ...h, ref: i + 1, hija: true }));
  });
  // hijos huérfanos (padre eliminado/reasignado): mostrarlos al final
  items
    .filter((c) => c.id_padre && !padres.some((p) => p.id_modelo_componente == c.id_padre))
    .forEach((h) => filas.push({ ...h, ref: null, hija: false }));
  return filas;
}

function ccChipStock(c) {
  if (!c.id_repuesto) {
    return '<span class="text-muted small">Sin vincular</span>';
  }
  const stock = Number(c.stock ?? 0);
  const minimo = Number(c.stock_minimo ?? 0);
  let clase = "cc-chip-ok", texto = `Stock ${stock}`;
  if (stock <= 0) { clase = "cc-chip-cero"; texto = "Sin stock"; }
  else if (minimo > 0 && stock < minimo) { clase = "cc-chip-bajo"; texto = `Stock ${stock} · bajo mín.`; }
  return `
    <span class="cc-rep-nombre">${ccEscape(c.repuesto)}</span>
    <span class="cc-chip ${clase}">${texto}</span>`;
}

function ccRenderFicha() {
  const m = ccFicha.modelo;
  const filas = ccOrdenarComponentes();
  const esAdmin = ccEsAdmin();

  $("#ccFichaTitulo").text(`${m.marca} ${m.modelo}`);
  $("#ccFichaSubtitulo").text(m.tipo_modelo || "");
  const unidades = (ccFicha.componentes || []).reduce((s, c) => s + Number(c.cantidad), 0);
  $("#ccFichaMeta").html(
    `${m.maquinas} máquina${m.maquinas != 1 ? "s" : ""}<br>` +
    `${filas.filter((f) => !f.hija && f.ref).length} componentes · ${unidades} unidades`
  );
  $("#ccBtnAgregar").toggleClass("d-none", !esAdmin);
  $("#ccBtnSubirEsquema").toggleClass("d-none", !esAdmin);

  // ── Radiografía ──
  if (m.imagen_esquema) {
    $("#ccEsquemaImg").attr("src", "./" + m.imagen_esquema).removeClass("d-none");
    $("#ccSinEsquema").addClass("d-none");
  } else {
    $("#ccEsquemaImg").addClass("d-none").attr("src", "");
    $("#ccSinEsquema").removeClass("d-none");
  }
  ccRenderPines();
  $("#ccBtnEditarPines").toggleClass(
    "d-none",
    !esAdmin || !m.imagen_esquema || filas.filter((f) => !f.hija && f.ref).length === 0
  );

  // ── Tabla ──
  let html = "";
  if (filas.length === 0) {
    html = `<tr><td colspan="5" class="text-center text-muted py-4">
              <i class="bi bi-info-circle me-1"></i>Este modelo aún no tiene componentes registrados.
            </td></tr>`;
  }
  filas.forEach((f) => {
    const acciones = esAdmin
      ? `<div class="dropdown">
            <button class="btn btn-sm btn-primary dropdown-toggle py-1"
                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-three-dots-vertical"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li>
                    <button class="dropdown-item btn-editar-item" type="button"
                            data-id="${f.id_modelo_componente}">
                        <i class="bi bi-pencil me-2 text-warning"></i>Editar
                    </button>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <button class="dropdown-item text-danger btn-eliminar-item" type="button"
                            data-id="${f.id_modelo_componente}">
                        <i class="bi bi-trash me-2"></i>Eliminar
                    </button>
                </li>
            </ul>
        </div>`
      : "";

    html += `
      <tr class="${f.hija ? "cc-hija" : ""}" data-ref="${f.hija || !f.ref ? "" : f.ref}">
        <td>${!f.hija && f.ref ? `<span class="cc-ref">${f.ref}</span>` : ""}</td>
        <td class="cc-nombre">
          <span class="fw-semibold">${ccEscape(f.componente)}</span>
          ${f.categoria && !f.hija ? `<span class="cc-categoria">${ccEscape(f.categoria)}</span>` : ""}
          ${f.especificacion ? `<span class="cc-espec">${ccEscape(f.especificacion)}</span>` : ""}
        </td>
        <td><span class="ft-num">${f.cantidad}</span></td>
        <td>${ccChipStock(f)}</td>
        <td class="text-end">${acciones}</td>
      </tr>`;
  });
  $("#ccTablaComponentes tbody").html(html);
}

function ccActivar(ref, on) {
  if (!ref) return;
  $(`#ccTablaComponentes tbody tr[data-ref="${ref}"]`).toggleClass("activo", on);
  $(`#ccPines .cc-pin[data-ref="${ref}"]`).toggleClass("activo", on);
}

//////////////////////////////////////////////////////////
// 📍 PINES SOBRE LA RADIOGRAFÍA
//////////////////////////////////////////////////////////

function ccPadresConRef() {
  return ccOrdenarComponentes().filter((f) => !f.hija && f.ref);
}

function ccRenderPines() {
  const conEsquema = !!ccFicha.modelo.imagen_esquema;
  let html = "";

  if (conEsquema) {
    ccPadresConRef().forEach((p, i) => {
      let x = p.pos_x !== null ? Number(p.pos_x) : null;
      let y = p.pos_y !== null ? Number(p.pos_y) : null;

      // Sin posición guardada: visible solo en modo edición, en fila de espera
      if (x === null || y === null) {
        if (!ccEditandoPines) return;
        x = 10 + (i % 5) * 12;
        y = 6;
      }
      html += `<button class="cc-pin" type="button"
                       data-id="${p.id_modelo_componente}" data-ref="${p.ref}"
                       style="left:${x}%;top:${y}%"
                       title="${ccEscape(p.componente)}">${p.ref}</button>`;
    });
  }
  $("#ccPines").html(html);
  if (ccEditandoPines) ccActivarArrastre();
}

function ccEntrarEdicionPines() {
  ccEditandoPines = true;
  ccPosicionesOriginales = {};
  ccPadresConRef().forEach((p) => {
    ccPosicionesOriginales[p.id_modelo_componente] = { x: p.pos_x, y: p.pos_y };
  });
  $("#ccRadioCard").addClass("editando");
  $("#ccHintEdicion, #ccAccionesEdicion").removeClass("d-none");
  $("#ccBtnEditarPines, #ccBtnSubirEsquema").addClass("d-none");
  ccRenderPines();
}

function ccSalirEdicionPines() {
  ccEditandoPines = false;
  $("#ccRadioCard").removeClass("editando");
  $("#ccHintEdicion, #ccAccionesEdicion").addClass("d-none");
}

function ccCancelarEdicionPines() {
  // Restaurar posiciones originales en memoria
  (ccFicha.componentes || []).forEach((c) => {
    const orig = ccPosicionesOriginales[c.id_modelo_componente];
    if (orig) { c.pos_x = orig.x; c.pos_y = orig.y; }
  });
  ccSalirEdicionPines();
  ccRenderFicha();
}

function ccGuardarPosiciones() {
  const posiciones = [];
  $("#ccPines .cc-pin").each(function () {
    posiciones.push({
      id: $(this).data("id"),
      x: parseFloat(this.style.left),
      y: parseFloat(this.style.top),
    });
  });

  $.post(CC_CTRL, { accion: "guardarPosiciones", posiciones: JSON.stringify(posiciones) }, null, "json")
    .done(function (resp) {
      if (!resp.ok) return Swal.fire("Error", resp.mensaje, "error");
      // Actualizar en memoria
      posiciones.forEach((p) => {
        const c = (ccFicha.componentes || []).find((x) => x.id_modelo_componente == p.id);
        if (c) { c.pos_x = p.x; c.pos_y = p.y; }
      });
      ccSalirEdicionPines();
      ccRenderFicha();
    })
    .fail(ccErrorAjax);
}

function ccActivarArrastre() {
  const lienzo = document.getElementById("ccLienzo");

  document.querySelectorAll("#ccPines .cc-pin").forEach((pin) => {
    pin.onpointerdown = function (e) {
      if (!ccEditandoPines) return;
      e.preventDefault();
      pin.classList.add("arrastrando");
      pin.setPointerCapture(e.pointerId);

      pin.onpointermove = function (ev) {
        const r = lienzo.getBoundingClientRect();
        const x = Math.max(0, Math.min(100, ((ev.clientX - r.left) / r.width) * 100));
        const y = Math.max(0, Math.min(100, ((ev.clientY - r.top) / r.height) * 100));
        pin.style.left = x.toFixed(2) + "%";
        pin.style.top = y.toFixed(2) + "%";
      };
      pin.onpointerup = function () {
        pin.classList.remove("arrastrando");
        pin.onpointermove = null;
        pin.onpointerup = null;
      };
    };
  });
}

//////////////////////////////////////////////////////////
// 🖼️ SUBIR ESQUEMA
//////////////////////////////////////////////////////////

function ccSubirEsquema() {
  const archivo = this.files && this.files[0];
  $(this).val("");
  if (!archivo || !ccFicha) return;

  const fd = new FormData();
  fd.append("accion", "subirEsquema");
  fd.append("id_modelo", ccFicha.modelo.id_modelo);
  fd.append("esquema", archivo);

  $.ajax({
    url: CC_CTRL,
    type: "POST",
    data: fd,
    processData: false,
    contentType: false,
    dataType: "json",
  })
    .done(function (resp) {
      if (!resp.ok) return Swal.fire("Error", resp.mensaje, "error");
      ccFicha.modelo.imagen_esquema = resp.data.imagen_esquema;
      ccRenderFicha();
    })
    .fail(ccErrorAjax);
}

//////////////////////////////////////////////////////////
// ✏️ CRUD DE ITEMS
//////////////////////////////////////////////////////////

function ccCargarSelects(callback) {
  const pedidos = [];
  if (ccCatalogo.length === 0) {
    pedidos.push($.post(CC_CTRL, { accion: "catalogo" }, null, "json")
      .done((r) => { if (r.ok) ccCatalogo = r.data; }));
  }
  if (ccRepuestos.length === 0) {
    pedidos.push($.post(CC_CTRL, { accion: "repuestos" }, null, "json")
      .done((r) => { if (r.ok) ccRepuestos = r.data; }));
  }
  $.when.apply($, pedidos).always(callback);
}

function ccPoblarSelects(item) {
  let optComp = `<option value="">Seleccione un componente</option>`;
  ccCatalogo.forEach((c) => {
    optComp += `<option value="${c.id_componente}">${ccEscape(c.nombre)}</option>`;
  });
  $("#ccItemComponente").html(optComp).val(item ? String(item.id_componente) : "");

  let optPadre = `<option value="">— Ninguno —</option>`;
  ccPadresConRef().forEach((p) => {
    if (item && p.id_modelo_componente == item.id_modelo_componente) return;
    optPadre += `<option value="${p.id_modelo_componente}">${ccEscape(p.componente)}</option>`;
  });
  $("#ccItemPadre").html(optPadre).val(item && item.id_padre ? String(item.id_padre) : "");

  let optRep = `<option value="">— Sin vincular —</option>`;
  ccRepuestos.forEach((r) => {
    optRep += `<option value="${r.id_repuesto}">${ccEscape(r.nombre)}</option>`;
  });
  $("#ccItemRepuesto").html(optRep).val(item && item.id_repuesto ? String(item.id_repuesto) : "");
}

function ccAbrirModalItem(id_modelo_componente) {
  const item = id_modelo_componente
    ? (ccFicha.componentes || []).find((c) => c.id_modelo_componente == id_modelo_componente)
    : null;

  ccCargarSelects(function () {
    $("#ccModalTitulo").text(item ? "Editar componente" : "Agregar componente");
    $("#ccItemId").val(item ? item.id_modelo_componente : "");
    $("#ccItemCantidad").val(item ? item.cantidad : 1);
    $("#ccItemEspec").val(item ? item.especificacion || "" : "");
    ccPoblarSelects(item);

    if (!ccModalItem) {
      // focus:false — sin esto el modal recaptura el foco y no se puede
      // escribir en los diálogos SweetAlert (componente nuevo)
      ccModalItem = new bootstrap.Modal(document.getElementById("ccModalItem"), { focus: false });
    }
    ccModalItem.show();
  });
}

function ccGuardarItem() {
  const id = $("#ccItemId").val();
  const datos = {
    accion: id ? "editarItem" : "guardarItem",
    id_modelo_componente: id,
    id_modelo: ccFicha.modelo.id_modelo,
    id_componente: $("#ccItemComponente").val(),
    id_padre: $("#ccItemPadre").val(),
    cantidad: $("#ccItemCantidad").val(),
    especificacion: $("#ccItemEspec").val().trim(),
    id_repuesto: $("#ccItemRepuesto").val(),
  };

  if (!datos.id_componente) {
    Swal.fire("Ups", "Selecciona un componente", "warning");
    return;
  }

  $.post(CC_CTRL, datos, null, "json")
    .done(function (resp) {
      if (!resp.ok) return Swal.fire("Error", resp.mensaje, "error");
      ccModalItem.hide();
      ccRecargarFicha();
    })
    .fail(ccErrorAjax);
}

function ccEliminarItem(id_modelo_componente) {
  Swal.fire({
    title: "¿Eliminar componente?",
    text: "Se quita del catálogo de este modelo (no afecta el inventario).",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Eliminar",
    cancelButtonText: "Cancelar",
  }).then((r) => {
    if (!r.isConfirmed) return;
    $.post(CC_CTRL, { accion: "eliminarItem", id_modelo_componente }, null, "json")
      .done(function (resp) {
        if (!resp.ok) return Swal.fire("Error", resp.mensaje, "error");
        ccRecargarFicha();
      })
      .fail(ccErrorAjax);
  });
}

function ccNuevoComponenteCatalogo() {
  Swal.fire({
    title: "Nuevo componente",
    html: `
      <input id="swCompNombre" class="form-control mb-2" placeholder="Nombre (ej. Cámara CCD)" maxlength="100">
      <input id="swCompCategoria" class="form-control" placeholder="Categoría (opcional, ej. Óptica)" maxlength="60">`,
    showCancelButton: true,
    confirmButtonText: "Crear",
    cancelButtonText: "Cancelar",
    focusConfirm: false,
    preConfirm: () => ({
      nombre: document.getElementById("swCompNombre").value.trim(),
      categoria: document.getElementById("swCompCategoria").value.trim(),
    }),
  }).then((r) => {
    if (!r.isConfirmed) return;
    if (!r.value.nombre) return Swal.fire("Ups", "El nombre es requerido", "warning");

    $.post(CC_CTRL, { accion: "guardarComponenteCatalogo", ...r.value }, null, "json")
      .done(function (resp) {
        if (!resp.ok) return Swal.fire("Error", resp.mensaje, "error");
        ccCatalogo = []; // forzar recarga
        ccCargarSelects(function () {
          const seleccionado = { id_componente: resp.data.id_componente };
          ccPoblarSelects({
            ...seleccionado,
            id_modelo_componente: $("#ccItemId").val(),
            id_padre: $("#ccItemPadre").val(),
            id_repuesto: $("#ccItemRepuesto").val(),
          });
        });
      })
      .fail(ccErrorAjax);
  });
}

//////////////////////////////////////////////////////////
// 🧼 UTILIDADES
//////////////////////////////////////////////////////////

function ccErrorAjax(xhr) {
  console.error("Error AJAX:", xhr.responseText);
  Swal.fire("Error", "Error en el servidor", "error");
}
