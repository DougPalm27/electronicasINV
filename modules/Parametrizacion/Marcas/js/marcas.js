let tablaMarcas = null;

$(document).ready(function () {
    init();
});

function init() {
    listarMarcas();

    $("#btnNuevaMarca").click(function () {
        limpiarModal();
        $("#tituloModalMarca").text("Nueva Marca");
        $("#btnGuardarMarca").removeClass("d-none");
        $("#btnActualizarMarca").addClass("d-none");
        abrirModal();
    });

    $("#btnGuardarMarca").click(guardarMarca);
    $("#btnActualizarMarca").click(actualizarMarca);

    $("#modalMarca").on("hidden.bs.modal", limpiarModal);

    // Guardar con Enter
    $("#nombre_marca").on("keydown", function (e) {
        if (e.key !== "Enter") return;
        e.preventDefault();
        const guardando = !$("#btnGuardarMarca").hasClass("d-none");
        guardando ? guardarMarca() : actualizarMarca();
    });
}

//////////////////////////////////////////////////////////
// TABLA
//////////////////////////////////////////////////////////

function listarMarcas() {
    if ($.fn.DataTable.isDataTable("#tablaMarcas")) {
        $("#tablaMarcas").DataTable().destroy();
    }

    tablaMarcas = $("#tablaMarcas").DataTable({
        ajax: {
            url: "./modules/Parametrizacion/Marcas/controllers/marcasController.php",
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
            { data: "id_marca" },
            { data: "nombre" },
            {
                data: "total_modelos",
                className: "text-center",
                render: (d) => `<span class="badge bg-secondary">${d}</span>`,
            },
            {
                data: null,
                orderable: false,
                className: "text-center",
                render: (data, type, row) => `
                    <button class="btn btn-warning btn-sm me-1"
                        onclick='editarMarca(${JSON.stringify(row)})'
                        title="Editar">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-danger btn-sm"
                        onclick="eliminarMarca(${row.id_marca}, '${row.nombre.replace(/'/g, "\\'")}')"
                        title="Eliminar"
                        ${row.total_modelos > 0 ? 'disabled' : ''}>
                        <i class="bi bi-trash"></i>
                    </button>`,
            },
        ],
        language: { url: "./modules/Electronicas/Repuestos/js/es-ES.json" },
        order: [[1, "asc"]],
    });
}

//////////////////////////////////////////////////////////
// CRUD
//////////////////////////////////////////////////////////

function guardarMarca() {
    limpiarErrores();
    const nombre = $("#nombre_marca").val().trim();

    if (!nombre) {
        marcarInvalido("#nombre_marca", "El nombre es requerido");
        return;
    }

    $.post(
        "./modules/Parametrizacion/Marcas/controllers/marcasController.php",
        { accion: "guardar", nombre },
        function (resp) {
            if (!resp.ok) {
                Swal.fire("Error", resp.mensaje, "error");
                return;
            }
            cerrarModal();
            listarMarcas();
            Swal.fire({ icon: "success", title: "Listo", text: "Marca creada", timer: 1500, showConfirmButton: false });
        },
        "json"
    );
}

function editarMarca(row) {
    limpiarModal();
    $("#id_marca").val(row.id_marca);
    $("#nombre_marca").val(row.nombre);
    $("#tituloModalMarca").text("Editar Marca");
    $("#btnGuardarMarca").addClass("d-none");
    $("#btnActualizarMarca").removeClass("d-none");
    abrirModal();
}

function actualizarMarca() {
    limpiarErrores();
    const id     = $("#id_marca").val();
    const nombre = $("#nombre_marca").val().trim();

    if (!nombre) {
        marcarInvalido("#nombre_marca", "El nombre es requerido");
        return;
    }

    $.post(
        "./modules/Parametrizacion/Marcas/controllers/marcasController.php",
        { accion: "editar", id_marca: id, nombre },
        function (resp) {
            if (!resp.ok) {
                Swal.fire("Error", resp.mensaje, "error");
                return;
            }
            cerrarModal();
            listarMarcas();
            Swal.fire({ icon: "success", title: "Listo", text: "Marca actualizada", timer: 1500, showConfirmButton: false });
        },
        "json"
    );
}

function eliminarMarca(id, nombre) {
    Swal.fire({
        title: `¿Eliminar "${nombre}"?`,
        text: "Esta acción no se puede revertir",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#dc3545",
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
    }).then((result) => {
        if (!result.isConfirmed) return;

        $.post(
            "./modules/Parametrizacion/Marcas/controllers/marcasController.php",
            { accion: "eliminar", id_marca: id },
            function (resp) {
                if (!resp.ok) {
                    Swal.fire("No se puede eliminar", resp.mensaje, "warning");
                    return;
                }
                listarMarcas();
                Swal.fire({ icon: "success", title: "Listo", text: "Marca eliminada", timer: 1500, showConfirmButton: false });
            },
            "json"
        );
    });
}

//////////////////////////////////////////////////////////
// UTILIDADES
//////////////////////////////////////////////////////////

function abrirModal() {
    new bootstrap.Modal(document.querySelector("#modalMarca")).show();
    setTimeout(() => $("#nombre_marca").focus(), 300);
}

function cerrarModal() {
    const el    = document.querySelector("#modalMarca");
    const modal = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
    modal.hide();
}

function limpiarModal() {
    $("#formMarca")[0].reset();
    $("#id_marca").val("");
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
    $("#formMarca .is-invalid").removeClass("is-invalid");
    $("#formMarca .invalid-feedback").remove();
}
