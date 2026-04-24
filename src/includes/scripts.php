<!-- Vendor JS Files -->
<script src="./assets/js/jquery.js"></script>
<script src="./assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="./assets/vendor/apexcharts/apexcharts.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.28/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- DataTables -->
<script src="./assets/vendor/datatables.net/js/jquery.dataTables.min.js"></script>
<script src="./assets/vendor/datatables.net-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="./assets/vendor/datatables.net-buttons/js/dataTables.buttons.min.js"></script>
<script src="./assets/vendor/datatables.net-buttons-bs4/js/buttons.bootstrap4.min.js"></script>
<script src="./assets/vendor/datatables.net-select/js/dataTables.select.min.js"></script>


<script>
  // Interceptor global: si cualquier respuesta AJAX indica sesión expirada, redirige al login
  $(document).ajaxComplete(function (event, xhr) {
    try {
      var resp = JSON.parse(xhr.responseText);
      if (resp && resp.session === false) {
        Swal.fire({
          icon: 'warning',
          title: 'Sesión expirada',
          text: 'Tu sesión ha terminado. Serás redirigido al login.',
          timer: 2500,
          showConfirmButton: false,
          allowOutsideClick: false
        }).then(function () {
          window.location.href = '/Electronicas/index.php';
        });
      }
    } catch (e) {}
  });

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

<?php
// ── Divisa predeterminada (global para toda la app) ────────
$simbolo_divisa = 'L.';
$codigo_divisa  = 'HNL';
try {
    require_once './config/Connection.php';
    $__conn = (new Connection())->dbConnect();
    $__stmt = $__conn->query(
        "SELECT simbolo, codigo FROM electronicas.Divisas WHERE predeterminada = 1 AND activo = 1"
    );
    $__div = $__stmt->fetch(PDO::FETCH_ASSOC);
    if ($__div) {
        $simbolo_divisa = $__div['simbolo'];
        $codigo_divisa  = $__div['codigo'];
    }
} catch (Exception $e) { /* usa defaults */ }
?>
<script>
    window.DIVISA = {
        simbolo: '<?= htmlspecialchars($simbolo_divisa, ENT_QUOTES) ?>',
        codigo:  '<?= htmlspecialchars($codigo_divisa,  ENT_QUOTES) ?>'
    };
</script>

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
  if ($_GET['module'] == 'marcas') {
    echo '<script src="./modules/Parametrizacion/Marcas/js/marcas.js"></script>';
  }
  if ($_GET['module'] == 'modelos') {
    echo '<script src="./modules/Parametrizacion/Modelos/js/modelos.js"></script>';
  }
  if ($_GET['module'] == 'proveedores') {
    echo '<script src="./modules/Parametrizacion/Proveedores/js/proveedores.js"></script>';
  }
  if ($_GET['module'] == 'tiposRepuestos') {
    echo '<script src="./modules/Parametrizacion/TiposRepuestos/js/tiposRepuestos.js"></script>';
  }
  if ($_GET['module'] == 'usuarios') {
    echo '<script src="./modules/Usuarios/js/usuarios.js"></script>';
  }
  if ($_GET['module'] == 'divisas') {
    echo '<script src="./modules/Parametrizacion/Divisas/js/divisas.js"></script>';
  }

} else {
  //echo '<script src="/modules/home/"></script>';
}
?>