let tablaModelos = null;

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
            { data: "id_modelo" },
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
                    <button class="btn btn-warning btn-sm me-1"
                        onclick='editarModelo(${JSON.stringify(row)})'
                        title="Editar">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-danger btn-sm"
                        onclick="eliminarModelo(${row.id_modelo}, '${row.nombre.replace(/'/g, "\\'")}')"
                        title="Eliminar">
                        <i class="bi bi-trash"></i>
                    </button>`,
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

    $.post(
        "./modules/Parametrizacion/Modelos/controllers/modelosController.php",
        {
            accion:          "guardar",
            nombre:          $("#nombre_modelo").val().trim(),
            id_marca:        $("#id_marca_modelo").val(),
            id_tipo_modelo:  $("#id_tipo_modelo").val(),
        },
        function (resp) {
            if (!resp.ok) {
                Swal.fire("Error", resp.mensaje, "error");
                return;
            }
            cerrarModal();
            listarModelos();
            Swal.fire({ icon: "success", title: "Listo", text: "Modelo creado", timer: 1500, showConfirmButton: false });
        },
        "json"
    );
}

function editarModelo(row) {
    limpiarModal();
    $("#id_modelo").val(row.id_modelo);
    $("#nombre_modelo").val(row.nombre);
    $("#id_marca_modelo").val(row.id_marca);
    $("#id_tipo_modelo").val(row.id_tipo_modelo || "");
    $("#tituloModalModelo").text("Editar Modelo");
    $("#btnGuardarModelo").addClass("d-none");
    $("#btnActualizarModelo").removeClass("d-none");
    abrirModal();
}

function actualizarModelo() {
    if (!validar()) return;

    $.post(
        "./modules/Parametrizacion/Modelos/controllers/modelosController.php",
        {
            accion:          "editar",
            id_modelo:       $("#id_modelo").val(),
            nombre:          $("#nombre_modelo").val().trim(),
            id_marca:        $("#id_marca_modelo").val(),
            id_tipo_modelo:  $("#id_tipo_modelo").val(),
        },
        function (resp) {
            if (!resp.ok) {
                Swal.fire("Error", resp.mensaje, "error");
                return;
            }
            cerrarModal();
            listarModelos();
            Swal.fire({ icon: "success", title: "Listo", text: "Modelo actualizado", timer: 1500, showConfirmButton: false });
        },
        "json"
    );
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
