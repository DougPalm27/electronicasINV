$(document).ready(function() {

    // evento click del botón guardar
    $("#btnGuardar").on("click", function() {
        // Obtener los valores de los campos
        let losDatos = {
            nombre: $("#nombre").val(),
            email: $("#email").val(),
            fechaActivo: $("#fechaActivo").val(),
            ubicacionID: $("#ubicacionID").val()
        };
    
        // Validar los campos uno por uno
        if (losDatos.nombre === "") {
            swal.fire("Usuario", "Por favor, ingrese un nombre.", "warning");
        } else if (!isValidEmail(losDatos.email)) {
            swal.fire("Usuario", "Por favor, ingrese un correo electrónico válido.", "warning");
        } else if (losDatos.fechaActivo === "") {
            swal.fire("Usuario", "Por favor, seleccione una fecha de activación.", "warning");
        } else if (losDatos.ubicacionID === "") {
            swal.fire("Usuario", "Por favor, seleccione una ubicación.", "warning");
        } else {
            // Si todos los campos son válidos, puedes continuar con la lógica de guardar los datos
            console.log(losDatos);
            guardarDatos(losDatos);
        }
    });

   });

   function guardarDatos(losDatos){
       $.ajax({
           type: "POST",
           url: "./modules/usuarios/controllers/agregarUsuario.php",
           data: {
             losDatos:losDatos
           },
           error: function (error) {
               console.log(error);
           },
           success: function (respuesta) {
               console.log(respuesta);
               const resp = JSON.parse(respuesta);
               console.log(resp);
   
               if(resp[0].status =="200"){
                   swal.fire("Usuario","Usuario registrado Correctamente","success");
               }
           },
         });
   }
   function isValidEmail(email) {
    // Expresión regular para validar el formato del correo electrónico
    let emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}