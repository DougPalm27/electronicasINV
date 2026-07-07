let tablaModelos = null;
let quitarImagenFlag = false;

$(document).ready(function () {
    init();
});

function init() {
    cargarMarcas();
    cargarTipos();
    listarModelos();

    $("#btnNuevoModelo").click(function () {
        limpiarModal();
        $("#tituloModalModelo").text("Nuevo Modelo");
        $("#btnGuardarModelo").removeClass("d-none");
        $("#btnActualizarModelo").addClass("d-none");
        abrirModal();
    });

    $("#btnGuardarModelo").click(guardarModelo);
    $("#btnActualizarModelo").click(actualizarModelo);
    $("#modalModelo").on("hidden.bs.modal", limpiarModal);

    // Vista previa al elegir archivo
    $("#imagen_modelo").on("change", function () {
        const file = this.files[0];
        if (!file) return;
        quitarImagenFlag = false;
        $("#previewImagen").attr("src", URL.createObjectURL(file));
        $("#previewImagenWrap").removeClass("d-none");
    });

    // Quitar imagen (marca para borrar al actualizar)
    $("#btnQuitarImagen").click(function () {
        quitarImagenFlag = true;
        $("#imagen_modelo").val("");
        $("#previewImagen").attr("src", "");
        $("#previewImagenWrap").addClass("d-none");
    });
}

//////////////////////////////////////////////////////////
// TABLA
//////////////////////////////////////////////////////////

function listarModelos() {
    if ($.fn.DataTable.isDataTable("#tablaModelos")) {
        $("#tablaModelos").DataTable().destroy();
    }

    tablaModelos = $("#tablaModelos").DataTable({
        ajax: {
            url: "./modules/Parametrizacion/Modelos/controllers/modelosController.php",
            type: "POST",
            data: { accion: "listar" },
            dataType: "json",
            dataSrc: function (json) {
                if (!json.ok) {
                    Swal.fire("Error", json.mensaje || "No se pudo cargar la tabla", "error");
                    return [];
                }
                return json.data;
            },
        },
        columns: [
            {
                data: "imagen",
                orderable: false,
                render: (d) => d
                    ? `<img src="./${d}" alt="" style="width:46px;height:34px;object-fit:cover;border-radius:6px;border:1px solid #e4e7ec">`
                    : `<span class="text-muted"><i class="bi bi-image" style="font-size:1.1rem"></i></span>`,
            },
            { data: "nombre" },
            { data: "marca" },
            {
                data: "tipo_modelo",
                render: (d) => d
                    ? `<span class="badge bg-info text-dark">${d}</span>`
                    : `<span class="text-muted">—</span>`,
            },
            {
                data: null,
                orderable: false,
                className: "text-center",
                render: (data, type, row) => `
                    <div class="dropdown">
                        <button class="btn btn-sm btn-primary dropdown-toggle py-1"
                                type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li>
                                <button class="dropdown-item" type="button"
                                        onclick='editarModelo(${JSON.stringify(row)})'>
                                    <i class="bi bi-pencil me-2 text-warning"></i>Editar
                                </button>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <button class="dropdown-item text-danger" type="button"
                                        onclick="eliminarModelo(${row.id_modelo}, '${row.nombre.replace(/'/g, "\\'")}')">
                                    <i class="bi bi-trash me-2"></i>Eliminar
                                </button>
                            </li>
                        </ul>
                    </div>`,
            },
        ],
        language: { url: "./modules/Electronicas/Repuestos/js/es-ES.json" },
        order: [[2, "asc"], [1, "asc"]],
    });
}

//////////////////////////////////////////////////////////
// CATÁLOGOS
//////////////////////////////////////////////////////////

function cargarMarcas() {
    $.post(
        "./modules/Parametrizacion/Modelos/controllers/modelosController.php",
        { accion: "listarMarcas" },
        function (resp) {
            if (!resp.ok) return;
            let html = `<option value="">-- Seleccione --</option>`;
            resp.data.forEach((m) => {
                html += `<option value="${m.id_marca}">${m.nombre}</option>`;
            });
            $("#id_marca_modelo").html(html);
        },
        "json"
    );
}

function cargarTipos() {
    $.post(
        "./modules/Parametrizacion/Modelos/controllers/modelosController.php",
        { accion: "listarTipos" },
        function (resp) {
            if (!resp.ok) return;
            let html = `<option value="">-- Seleccione --</option>`;
            resp.data.forEach((t) => {
                html += `<option value="${t.id_tipo_modelo}">${t.nombre}</option>`;
            });
            $("#id_tipo_modelo").html(html);
        },
        "json"
    );
}

//////////////////////////////////////////////////////////
// CRUD
//////////////////////////////////////////////////////////

function guardarModelo() {
    if (!validar()) return;

    enviarModelo("guardar", "Modelo creado");
}

/** Envía el formulario (con imagen opcional) vía FormData. */
function enviarModelo(accion, mensajeExito) {
    const fd = new FormData();
    fd.append("accion",         accion);
    fd.append("nombre",         $("#nombre_modelo").val().trim());
    fd.append("id_marca",       $("#id_marca_modelo").val());
    fd.append("id_tipo_modelo", $("#id_tipo_modelo").val());

    if (accion === "editar") {
        fd.append("id_modelo",     $("#id_modelo").val());
        fd.append("quitar_imagen", quitarImagenFlag ? "1" : "0");
    }

    const archivo = $("#imagen_modelo")[0].files[0];
    if (archivo) fd.append("imagen", archivo);

    $.ajax({
        url: "./modules/Parametrizacion/Modelos/controllers/modelosController.php",
        type: "POST",
        data: fd,
        processData: false,
        contentType: false,
        dataType: "json",
        success: function (resp) {
            if (!resp.ok) {
                Swal.fire("Error", resp.mensaje, "error");
                return;
            }
            cerrarModal();
            listarModelos();
            Swal.fire({ icon: "success", title: "Listo", text: mensajeExito, timer: 1500, showConfirmButton: false });
        },
        error: function () {
            Swal.fire("Error", "Error en el servidor", "error");
        },
    });
}

function editarModelo(row) {
    limpiarModal();
    $("#id_modelo").val(row.id_modelo);
    $("#nombre_modelo").val(row.nombre);
    $("#id_marca_modelo").val(row.id_marca);
    $("#id_tipo_modelo").val(row.id_tipo_modelo || "");

    if (row.imagen) {
        $("#previewImagen").attr("src", "./" + row.imagen);
        $("#previewImagenWrap").removeClass("d-none");
    }

    $("#tituloModalModelo").text("Editar Modelo");
    $("#btnGuardarModelo").addClass("d-none");
    $("#btnActualizarModelo").removeClass("d-none");
    abrirModal();
}

function actualizarModelo() {
    if (!validar()) return;

    enviarModelo("editar", "Modelo actualizado");
}

function eliminarModelo(id, nombre) {
    Swal.fire({
        title: `¿Eliminar "${nombre}"?`,
        text: "El modelo quedará inactivo",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#dc3545",
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
    }).then((result) => {
        if (!result.isConfirmed) return;

        $.post(
            "./modules/Parametrizacion/Modelos/controllers/modelosController.php",
            { accion: "eliminar", id_modelo: id },
            function (resp) {
                if (!resp.ok) {
                    Swal.fire("No se puede eliminar", resp.mensaje, "warning");
                    return;
                }
                listarModelos();
                Swal.fire({ icon: "success", title: "Listo", text: "Modelo eliminado", timer: 1500, showConfirmButton: false });
            },
            "json"
        );
    });
}

//////////////////////////////////////////////////////////
// VALIDACIÓN
//////////////////////////////////////////////////////////

function validar() {
    limpiarErrores();
    let valido = true;

    if (!$("#nombre_modelo").val().trim()) {
        marcarInvalido("#nombre_modelo", "El nombre es requerido");
        valido = false;
    }

    if (!$("#id_marca_modelo").val()) {
        marcarInvalido("#id_marca_modelo", "Debes seleccionar una marca");
        valido = false;
    }

    return valido;
}

//////////////////////////////////////////////////////////
// UTILIDADES
//////////////////////////////////////////////////////////

function abrirModal() {
    new bootstrap.Modal(document.querySelector("#modalModelo")).show();
    setTimeout(() => $("#nombre_modelo").focus(), 300);
}

function cerrarModal() {
    const el    = document.querySelector("#modalModelo");
    const modal = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
    modal.hide();
}

function limpiarModal() {
    $("#formModelo")[0].reset();
    $("#id_modelo").val("");
    quitarImagenFlag = false;
    $("#previewImagen").attr("src", "");
    $("#previewImagenWrap").addClass("d-none");
    limpiarErrores();
}

function marcarInvalido(selector, mensaje) {
    const $el = $(selector);
    $el.addClass("is-invalid");
    if ($el.next(".invalid-feedback").length === 0) {
        $el.after(`<div class="invalid-feedback">${mensaje}</div>`);
    } else {
        $el.next(".invalid-feedback").text(mensaje);
    }
}

function limpiarErrores() {
    $("#formModelo .is-invalid").removeClass("is-invalid");
    $("#formModelo .invalid-feedback").remove();
}
