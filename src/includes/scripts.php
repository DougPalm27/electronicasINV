<!-- Vendor JS Files -->
<script src="./assets/vendor/apexcharts/apexcharts.min.js"></script>
<script src="./assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="./assets/vendor/chart.js/chart.umd.js"></script>
<script src="./assets/vendor/echarts/echarts.min.js"></script>
<script src="./assets/vendor/quill/quill.min.js"></script>
<script src="./assets/vendor/simple-datatables/simple-datatables.js"></script>
<script src="./assets/vendor/tinymce/tinymce.min.js"></script>
<script src="./assets/vendor/php-email-form/validate.js"></script>
<script src="./assets/js/jquery.js"></script>
<!-- 
mar -->
<script src="./assets/vendor/apexcharts/apexcharts.min.js"></script>
<script src="./assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="./assets/vendor/chart.js/chart.umd.js"></script>
<script src="./assets/vendor/echarts/echarts.min.js"></script>
<script src="./assets/vendor/quill/quill.min.js"></script>
<script src="./assets/vendor/simple-datatables/simple-datatables.js"></script>
<script src="./assets/vendor/tinymce/tinymce.min.js"></script>
<script src="./assets/vendor/php-email-form/validate.js"></script>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- DataTables -->
<script src="./assets/vendor/datatables.net/js/jquery.dataTables.min.js"></script>
<script src="./assets/vendor/datatables.net-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="./assets/vendor/datatables.net-buttons/js/dataTables.buttons.min.js"></script>
<script src="./assets/vendor/datatables.net-buttons-bs4/js/buttons.bootstrap4.min.js"></script>
<script src="./assets/vendor/datatables.net-buttons/js/buttons.html5.min.js"></script>
<script src="./assets/vendor/datatables.net-buttons/js/buttons.flash.min.js"></script>
<script src="./assets/vendor/datatables.net-buttons/js/buttons.print.min.js"></script>
<script src="./assets/vendor/datatables.net-select/js/dataTables.select.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.28/dist/sweetalert2.all.min.js"></script>


<script>
  function capitalizeText(text) {
    return text.charAt(0).toUpperCase() + text.slice(1).toLowerCase();

  }

  function eliminarEspacios(cadena) {
    return cadena ? cadena.replace(/\s/g, '') : "";
  }

  function contieneCaracteresEspeciales(cadena) {
    // Expresión regular para buscar caracteres que no sean letras, números o guiones bajos (_)
    var expresionRegular = /[^a-zA-Z0-9_]/;
    return expresionRegular.test(cadena);
  }

  function limpiarTodo() {
    // Limpiar campos de texto y contraseñas
    $("input[type='text']").val("");
    $("input[type='password']").val("");
    $("textarea").val("");

    // Limpiar campos de selección
    $("select").val("-1");

    // Limpiar campos de casillas de verificación
    $("input[type='checkbox']").prop("checked", false);

    // Limpiar campos de radio
    $("input[type='radio']").prop("checked", false);
  }
</script>
<!-- Template Main JS File -->
<script src="./assets/js/main.js"></script>




<!-- Carga de  js según el módulo-->
<?php
echo '<script src="./modules/dasboard/js/dash.js"></script>';
if (!empty($_GET['module'])) {


   if ($_GET['module'] == 'repuestos') {
    echo '<script src="./modules/Electronicas/Repuestos/js/repuestos.js"></script>';
  }
     if ($_GET['module'] == 'maquinas') {
    echo '<script src="./modules/Electronicas/Maquinas/js/maquinas.js"></script>';
  }
  if ($_GET['module'] == 'mantenimientos') {
    echo '<script src="./modules/Electronicas/Mantenimientos/js/mantenimientos.js"></script>';
  }

} else {
  //echo '<script src="/modules/home/"></script>';
}
?>