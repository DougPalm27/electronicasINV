$(document).ready(function () {
  $(".Select2Modal1").select2({
    width: "100%",
    dropdownParent: $("#nuevoEquipo"),
  });
  listarKit();
  listarUsuarios();
  listarProyectos();
  listarAsignacionesKit();
  listarDisponiblesKit();
  listarTodoKit();

  // -------------------------- TABLA ASIGNACIONES--------------------------
  $("#TablaKit1").on("click", "button", function () {
    // Obtener el id que trae el botón
    let id = $(this).attr("data-id");
    idRegistro = id;
    console.log(idRegistro);
    // Obtener la acción a realizar
    let accion = $(this).attr("name");

    // Dependiendo del botón al que se le hace click
    // se realizará una acción transmitida por el atributo name
    if (accion == "registro-eliminar") {
      // Llamamos a la alerta para eliminar
      eliminarAsignacionKit(id);
    } else if (accion == "registro-imprimir") {
      const url = `./modules/Equipo/reports/custodios.php?id=${id}`;
      window.open(url);
    }
  }); // -------------------------- TABLA --------------------------


  $("#btnKit").on("click", function () {
    let losDatos = {
      descripcion: $("#descripcion").val(),
      precio: $("#precio").val(),
      proyecto: $("#proyecto").val(),
      fecha: $("#fecha").val(),
      sap: $("#sap").val(),
    };
    console.log(losDatos);
    if (losDatos.descripcion.trim() === "" || losDatos.descripcion.length < 3) {
      swal.fire({
        icon: "error",
        title: "Error en la descripción",
        text: "La descripción no debe estar vacía y debe tener al menos 3 caracteres.",
      });
      return false; // Detener el envío del formulario
    }

    // Validación del precio
    else if (
      parseFloat(losDatos.precio) < 0
    ) {
      swal.fire({
        icon: "error",
        title: "Error en el precio",
        text: "El precio debe ser un número positivo.",
      });
      return false; // Detener el envío del formulario
    }

    // Validación del proyectoID
    else if (
      !/^\d+$/.test(losDatos.proyecto) ||
      parseInt(losDatos.proyecto) <= 0
    ) {
      swal.fire({
        icon: "error",
        title: "Error en el ID del proyecto",
        text: "El ID del proyecto debe ser un número mayor que 0.",
      });
      return false; // Detener el envío del formulario
    }
    if (losDatos.fecha.trim() === "") {
      swal.fire({
        icon: "error",
        title: "Error en la fecha",
        text: "La fecha no debe estar vacía.",
      });
      return false; // Detener el envío del formulario
    }

    // Validación del campo SAP
    if (!/^[a-zA-Z0-9]+$/.test(losDatos.sap)) {
      swal.fire({
        icon: "error",
        title: "Error en el campo SAP",
        text: "El campo SAP solo debe contener letras y números.",
      });
      return false; // Detener el envío del formulario
    }
    guardarKit(losDatos);
  });

  $("#btnAsgKit").on("click", function () {
    let losDatos = {
      usuario: $("#usuario2").val(),
      kit: $("#kitID").val(),
      fecha: $("#fechaAsignacionKit").val(),
      observaciones: $("#observaciones").val(),
    };
    console.log(losDatos);
    // Validación de usuario y kit
    if (losDatos.usuario <= 0) {
      swal.fire("Ups", "Debes seleccionar un usuario", "warning");
      return;
    }

    if (losDatos.kit <= 0) {
      swal.fire("Ups", "Debes seleccionar un equipo", "warning");
      return;
    }

    // Validación de fecha
    if (!isValidDate(losDatos.fecha)) {
      swal.fire("Ups", "La fecha proporcionada no es válida", "warning");
      return;
    }

    // Confirmación de observaciones vacías
    if (losDatos.observaciones.trim() === "") {
      swal
        .fire({
          title: "¿Deseas guardar la asignación sin observaciones?",
          text: "El campo de observaciones está vacío.",
          icon: "warning",
          showCancelButton: true,
          confirmButtonText: "Sí, guardar",
          cancelButtonText: "No, cancelar",
        })
        .then((result) => {
          if (result.isConfirmed) {

            asignacionKit(losDatos);
          }
        });
    } else {
      console.log(losDatos);
      asignacionKit(losDatos);
    }
  });


  $("#usuario2").on("change", function () {
    const valor = $("#usuario2").val();
    $("#usuario2").val(valor);

    let cod =$("#usuario2 option:selected").attr("data-codigo");
    $("#cempleado").val(cod);
  });
  $("#kitID").on("change", function () {
    const valor = $("#kitID").val();
    let np = $("#kitID option:selected").attr("data-nombre");
    $("#proyectoID").val(np);
  });

  //Modal de registro de kitr
  $("#proyecto").on("change", function () {
    const valor = $("#proyecto").val();
    $("#proyecto").val(valor);
    console.log(valor);
  });
});

function guardarKit(losDatos) {
  console.log(losDatos);
  $.ajax({
    type: "POST",
    url: "./modules/Kit/controllers/ctr_kit.php",
    data: {
      losDatos: losDatos,
    },
    error: function (error) {
      console.log(error);
    },
    success: function (respuesta) {
      console.log(respuesta);
      const resp = JSON.parse(respuesta);
      console.log(resp);

      if (resp[0].status == "200") {
        swal
          .fire("Enhorabuena", "Kit registrado Correctamente", "success")
          .then(() => {
            window.location.reload();
          });
      } else if (resp[0] == "23000") {
        swal.fire(
          "Ups",
          "Parece que estas duplicando el Codigo SAP",
          "warning"
        );
      }
    },
  });
}
function listarKit() {
  // POST  // GET   POST -Envia Recibe   | GET RECEPCIÓ
  $.ajax({
    type: "GET",
    url: "./modules/kit/controllers/listarKit.php",
    data: {},
    // Error en la petición
    error: function (error) {
      console.log(error);
    },
    // Petición exitosa
    success: function (respuesta) {
      let datos = JSON.parse(respuesta);
      datos.forEach((e) => {
        $("#kitID").append(
          `<option value="${e.kitID}" data-nombre="${e.nombreProyecto}" data-codigo="${e.proyectoID}">${e.kit}</option>`
        );
      });
      console.log(datos);
    },
  });
}

function cargarTabla(tableID, data, columns) {
  $(tableID).dataTable().fnClearTable();
  $(tableID).dataTable().fnDestroy();
  var params = {
    aaData: data,
    aoColumns: columns,
    bSortable: false,
    ordering: true,
    language: {
      sProcessing: "Procesando...",
      sLengthMenu: "Mostrar _MENU_ registros",
      sZeroRecords: "No se encontraron resultados",
      sEmptyTable: "Ningún dato disponible en esta tabla",
      sInfo:
        "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
      sInfoEmpty: "Mostrando registros del 0 al 0 de un total de 0 registros",
      sInfoFiltered: "(filtrado de un total de _MAX_ registros)",
      sInfoPostFix: "",
      sSearch: "Buscar:",
      sUrl: "",
      sInfoThousands: ",",
      sLoadingRecords: "Cargando...",
      oPaginate: {
        sFirst: "Primero",
        sLast: "Último",
        sNext: "Siguiente",
        sPrevious: "Anterior",
      },
      oAria: {
        sSortAscending:
          ": Activar para ordenar la columna de manera ascendente",
        sSortDescending:
          ": Activar para ordenar la columna de manera descendente",
      },
      buttons: {
        copy: "Copiar",
        colvis: "Visibilidad",
      },
    },
  };

  $(tableID).DataTable(params);
}

function capitalizeText(text) {
  return text.charAt(0).toUpperCase() + text.slice(1).toLowerCase();
}

function asignacionKit(losDatos) {
  $.ajax({
    type: "POST",
    url: "./modules/Kit/controllers/ctr_kitAsignacion.php",
    data: {
      losDatos: losDatos,
    },
    error: function (error) {
      console.log(error);
    },
    success: function (respuesta) {
      console.log(respuesta);
      const resp = JSON.parse(respuesta);
      console.log(resp);

      if (resp[0].status == "200") {
        swal
          .fire("Enhorabuena", "Kit registrado Correctamente", "success")
          .then(() => {
            window.location.reload();
          });
      }
    },
  });
}

// Función para validar la fecha
function isValidDate(dateString) {
  let regEx = /^\d{4}-\d{2}-\d{2}$/;
  if (!dateString.match(regEx)) return false; // Formato inválido
  let d = new Date(dateString);
  let dNum = d.getTime();
  if (!dNum && dNum !== 0) return false; // Fecha inválida
  return d.toISOString().slice(0, 10) === dateString;
}

function listarUsuarios() {
  // POST  // GET   POST -Envia Recibe   | GET RECEPCIÓ
  $.ajax({
    type: "GET",
    url: "./modules/Asignaciones/controllers/listarUsuariosM.php",
    data: {},
    // Error en la petición
    error: function (error) {
      console.log(error);
    },
    // Petición exitosa
    success: function (respuesta) {
      let datos = JSON.parse(respuesta);
      datos.forEach((e) => {
        $("#usuario2").append(
          `<option value="${e.UsuarioID}" data-codigo="${e.codigoUsuario}">${e.nombre}</option>`
        );
      });
    },
  });
}

function listarProyectos() {
  // POST  // GET   POST -Envia Recibe   | GET RECEPCIÓ
  $.ajax({
    type: "GET",
    url: "./modules//Generales/controllersGenerales/listarProyectos.php",
    data: {},
    // Error en la petición
    error: function (error) {
      console.log(error);
    },
    // Petición exitosa
    success: function (respuesta) {
      let datos = JSON.parse(respuesta);
      datos.forEach((e) => {
        $("#proyecto").append(
          `<option value="${e.proyectoID}">${e.nombreProyecto}</option>`
        );
      });
    },
  });
}

function listarAsignacionesKit() {
  // Petición
  $.ajax({
    type: "GET",
    url: "modules/Kit/controllers/ctr_listarKitAsignacion.php",
    dataType: "json",
    error: function (error) {
      console.log(error);
      Swal.fire({
        title: "Reporte",
        icon: "warning",
        text: `${error}`,
        confirmButtonColor: "#3085d6",
      });
    },
    success: function (respuesta) {
      // Creamos las columnas de nuestra tabla
      console.log(respuesta);
      var columns = [
        {
          mDataProp: "codigoSAP",
          width: 5,
        },
        {
          mDataProp: "nombreCompleto",
          width: 30,
        },
        {
          mDataProp: "kitDes",
          width: 5,
        },
        {
          mDataProp: "nombreProyecto",
          width: 5,
        },
        {
          className: "text-left",
          width: 5,
          render: function (data, types, full, meta) {
            /* let btnImprimir = `<button data-id = ${full.asignacionID} name="registro-imprimir" class="btn btn-outline-primary" type="button" data-toggle="tooltip" data-placement="top" title="Editar productor">
                                      <i class="bx bxs-printer"></i>
                                    </button>`; */
            let btnEliminar = `<button data-id = ${full.asignacionID} name="registro-eliminar" class="btn btn-outline-danger" type="button" data-toggle="tooltip" data-placement="top" title="Eliminar productor">
                                    <i class="fas fa-trash"></i>
                                  </button>`;
            return ` ${btnEliminar}`;
          },
        },
      ];
      // Llamado a la función para crear la tabla con los datos
      cargarTabla("#TablaKit1", respuesta, columns);
    },
  });
}
function listarDisponiblesKit() {
  // Petición
  $.ajax({
    type: "GET",
    url: "modules/Kit/controllers/ctr_listarKitDisponible.php",
    dataType: "json",
    error: function (error) {
      console.log(error);
      Swal.fire({
        title: "Reporte",
        icon: "warning",
        text: `${error}`,
        confirmButtonColor: "#3085d6",
      });
    },
    success: function (respuesta) {
      // Creamos las columnas de nuestra tabla
      console.log(respuesta);
      var columns = [
        {
          mDataProp: "codigoSAP",
          width: 5,
        },
        {
          mDataProp: "descripcion",
          width: 30,
        },
        {
          mDataProp: "nombreProyecto",
          width: 5,
        },
        {
          className: "text-left",
          width: 5,
          render: function (data, types, full, meta) {
            /* let btnImprimir = `<button data-id = ${full.asignacionID} name="registro-imprimir" class="btn btn-outline-primary" type="button" data-toggle="tooltip" data-placement="top" title="Editar productor">
                                      <i class="bx bxs-printer"></i>
                                    </button>`; */
            let btnEliminar = `<button data-id = ${full.asignacionID} name="registro-eliminar" class="btn btn-outline-danger" type="button" data-toggle="tooltip" data-placement="top" title="Eliminar productor">
                                    <i class="fas fa-trash"></i>
                                  </button>`;
            return ` ${btnEliminar}`;
          },
        },
      ];
      // Llamado a la función para crear la tabla con los datos
      cargarTabla("#TablaKit2", respuesta, columns);
    },
  });
}

function listarTodoKit() {
  // Petición
  $.ajax({
    type: "GET",
    url: "modules/Kit/controllers/ctr_listarKitCompleto.php",
    dataType: "json",
    error: function (error) {
      console.log(error);
      Swal.fire({
        title: "Reporte",
        icon: "warning",
        text: `${error}`,
        confirmButtonColor: "#3085d6",
      });
    },
    success: function (respuesta) {
      // Creamos las columnas de nuestra tabla
      console.log(respuesta);
      var columns = [
        {
          mDataProp: "codigoSAP",
          width: 5,
        },
        {
          mDataProp: "kit",
          width: 30,
        },
        {
          mDataProp: "precio",
          width: 5,
        },
        {
          mDataProp: "nombreProyecto",
          width: 5,
        },
        {
          mDataProp: "estado",
          width: 5,
        },
        {
          className: "text-left",
          width: 5,
          render: function (data, types, full, meta) {
            /* let btnImprimir = `<button data-id = ${full.asignacionID} name="registro-imprimir" class="btn btn-outline-primary" type="button" data-toggle="tooltip" data-placement="top" title="Editar productor">
                                      <i class="bx bxs-printer"></i>
                                    </button>`; */
            let btnEliminar = `<button data-id = ${full.asignacionID} name="registro-eliminar" class="btn btn-outline-danger" type="button" data-toggle="tooltip" data-placement="top" title="Eliminar productor">
                                    <i class="fas fa-trash"></i>
                                  </button>`;
            return ` ${btnEliminar}`;
          },
        },
      ];
      // Llamado a la función para crear la tabla con los datos
      cargarTabla("#TablaKit3", respuesta, columns);
    },
  });
}

function cargarTabla(tableID, data, columns) {
  $(tableID).dataTable().fnClearTable();
  $(tableID).dataTable().fnDestroy();
  var params = {
    aaData: data,

    aoColumns: columns,
    bSortable: true,
    ordering: true,

    language: {
      sProcessing: "Procesando...",
      sLengthMenu: "Mostrar _MENU_ registros",
      sZeroRecords: "No se encontraron resultados",
      sEmptyTable: "Ningún dato disponible en esta tabla",
      sInfo:
        "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
      sInfoEmpty: "Mostrando registros del 0 al 0 de un total de 0 registros",
      sInfoFiltered: "(filtrado de un total de _MAX_ registros)",
      sInfoPostFix: "",
      sSearch: "Buscar:",
      sUrl: "",
      sInfoThousands: ",",
      sLoadingRecords: "Cargando...",
      oPaginate: {
        sFirst: "Primero",
        sLast: "Último",
        sNext: "Siguiente",
        sPrevious: "Anterior",
      },
      oAria: {
        sSortAscending:
          ": Activar para ordenar la columna de manera ascendente",
        sSortDescending:
          ": Activar para ordenar la columna de manera descendente",
      },
      buttons: {
        copy: "Copiar",
        colvis: "Visibilidad",
      },
    },
  };

  $(tableID).DataTable(params);
  $('a[data-toggle="tab"]').on("shown.bs.tab", function (e) {
    $($.fn.dataTable.tables(true))
      .DataTable()
      .columns.adjust()
      .fixedColumns()
      .relayout();
  });
}
function eliminarAsignacionKit(id) {
  Swal.fire({
    title: "¿Está seguro?",
    text: "La asignacion de este kit se eliminara, pero quedara en el historico",
    icon: "question",
    showCancelButton: true,
    confirmButtonColor: "#3085D6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Eliminar",
    cancelButtonText: "Cancelar",
  }).then((result) => {
    if (result.isConfirmed) {
      $.ajax({
        type: "POST",
        url: "./modules/Kit/controllers/ctr_eliminarKitAsignacion",
        data: {
          recio: id,
        },
        // Error en la petición
        error: function (error) {
          console.log(error);
          Swal.fire({
            title: "Error en la peticion",
            icon: "error",
            text: "",
            confirmButtonColor: "#3085d6",
          });
        },
        // Petición exitosa
        success: function (respuesta) {
          console.log(respuesta);
          const datos = JSON.parse(respuesta);

          // Eliminado correctamente
          if (datos[0].status == 200) {
            Swal.fire({
              title: "Listo calixto",
              icon: "success",
              text: `${datos[0].mensaje}`,
              confirmButtonColor: "#3085d6",
            });

            // Actualizar los datos en tabla
            listarAsignacionesKit();
          }
          // Error
          else {
            Swal.fire({
              title: "Cagada",
              icon: "error",
              text: `Ocurrió un error al intentar eliminar la asignacion.`,
              confirmButtonColor: "#3085d6",
            });
          }
        },
      });
    }
  });
}