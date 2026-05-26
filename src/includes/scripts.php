<!-- ── Librerías que NO dependen de jQuery ─────────────────── -->
<script src="./assets/vendor/apexcharts/apexcharts.min.js"></script>
<script src="./assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="./assets/vendor/chart.js/chart.umd.js"></script>
<script src="./assets/vendor/echarts/echarts.min.js"></script>
<script src="./assets/vendor/quill/quill.min.js"></script>
<script src="./assets/vendor/simple-datatables/simple-datatables.js"></script>
<script src="./assets/vendor/tinymce/tinymce.min.js"></script>
<script src="./assets/vendor/php-email-form/validate.js"></script>

<!-- ── jQuery primero (requerido por DataTables, Select2, etc.) ── -->
<script src="./assets/js/jquery.js"></script>

<!-- ── Librerías que SÍ dependen de jQuery ─────────────────── -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.28/dist/sweetalert2.all.min.js"></script>

<!-- DataTables -->
<script src="./assets/vendor/datatables.net/js/jquery.dataTables.min.js"></script>
<script src="./assets/vendor/datatables.net-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="./assets/vendor/datatables.net-buttons/js/dataTables.buttons.min.js"></script>
<script src="./assets/vendor/datatables.net-buttons-bs4/js/buttons.bootstrap4.min.js"></script>
<script src="./assets/vendor/datatables.net-select/js/dataTables.select.min.js"></script>

<!-- Template Main JS File -->
<script src="./assets/js/main.js"></script>

<?php
/* ── Variables globales inyectadas desde PHP ─────────────── */
$simbolo_divisa  = 'L.';
$codigo_divisa   = 'HNL';
$tc_divisa       = 1.0;

try {
    require_once './config/Connection.php';
    $__conn = (new Connection())->dbConnect();
    $__stmt = $__conn->query(
        "SELECT simbolo, codigo, tipo_cambio
         FROM electronicas.Divisas
         WHERE predeterminada = 1 AND activo = 1"
    );
    $__div = $__stmt->fetch(PDO::FETCH_ASSOC);
    if ($__div) {
        $simbolo_divisa = $__div['simbolo'];
        $codigo_divisa  = $__div['codigo'];
        $tc_divisa      = (float)$__div['tipo_cambio'];
    }
} catch (Exception $e) { /* usa defaults */ }
?>
<script>
    window.DIVISA = {
        simbolo:     <?= json_encode($simbolo_divisa) ?>,
        codigo:      <?= json_encode($codigo_divisa)  ?>,
        tipo_cambio: <?= json_encode($tc_divisa)      ?>
    };
    window.USUARIO_ID     = <?= json_encode((int)($_SESSION['id_usuario'] ?? 0)) ?>;
    window.USUARIO_NOMBRE = <?= json_encode($_SESSION['nombre']     ?? 'Usuario') ?>;
    window.USUARIO_ROL    = <?= json_encode($_SESSION['nombre_rol'] ?? null)     ?>;

    /* ── Convertir cualquier monto a Lempiras ── */
    window.aLempiras = function(monto, tipo_cambio) {
        return parseFloat(monto || 0) * parseFloat(tipo_cambio || 1);
    };

    /* ── Formatear como L. 1,234.56 ── */
    window.fmtLps = function(monto, tipo_cambio) {
        const total = window.aLempiras(monto, tipo_cambio);
        return 'L. ' + total.toLocaleString('es-HN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    /* ── Interceptor: sesión expirada ── */
    $(document).ajaxComplete(function(event, xhr) {
        try {
            var resp = JSON.parse(xhr.responseText);
            if (resp && resp.session === false) {
                Swal.fire({
                    icon: 'warning', title: 'Sesión expirada',
                    text: 'Tu sesión ha terminado. Serás redirigido al login.',
                    timer: 2500, showConfirmButton: false, allowOutsideClick: false
                }).then(function() { window.location.href = '/Electronicas/index.php'; });
            }
        } catch(e) {}
    });

    function capitalizeText(text) {
        return text.charAt(0).toUpperCase() + text.slice(1).toLowerCase();
    }
    function eliminarEspacios(cadena) {
        return cadena ? cadena.replace(/\s/g, '') : "";
    }
    function contieneCaracteresEspeciales(cadena) {
        return /[^a-zA-Z0-9_]/.test(cadena);
    }
    function limpiarTodo() {
        $("input[type='text'], input[type='password'], textarea").val("");
        $("select").val("-1");
        $("input[type='checkbox'], input[type='radio']").prop("checked", false);
    }
</script>

<!-- ── JS por módulo ───────────────────────────────────────── -->
<?php
echo '<script src="./modules/dasboard/js/dash.js"></script>';
if (!empty($_GET['module'])) {
    $mod = $_GET['module'];
    $map = [
        'repuestos'      => './modules/Electronicas/Repuestos/js/repuestos.js',
        'maquinas'       => './modules/Electronicas/Maquinas/js/maquinas.js',
        'mantenimientos' => './modules/Electronicas/Mantenimientos/js/mantenimientos.js',
        'marcas'         => './modules/Parametrizacion/Marcas/js/marcas.js',
        'modelos'        => './modules/Parametrizacion/Modelos/js/modelos.js',
        'proveedores'    => './modules/Parametrizacion/Proveedores/js/proveedores.js',
        'tiposRepuestos' => './modules/Parametrizacion/TiposRepuestos/js/tiposRepuestos.js',
        'usuarios'       => './modules/Usuarios/js/usuarios.js',
        'divisas'        => './modules/Parametrizacion/Divisas/js/divisas.js',
        'roles'          => './modules/Roles/js/roles.js',
        'solicitudes'     => './modules/Solicitudes/js/solicitudes.js',
        'compras'         => './modules/SolicitudesCompra/js/solicitudesCompra.js',
        'satake'          => './modules/Mantenimiento/Satake/js/satake.js',
        // ── GPS ──────────────────────────────────────────────
        'gpsCredenciales' => './modules/GPS/GPS/js/gps.js',
        'gpsTransportes'  => './modules/GPS/Transportes/js/transportes.js',
        'gpsCuentas'      => './modules/GPS/CuentasGPS/js/cuentasGPS.js',
        'gpsTiposVehiculo'=> './modules/GPS/Parametrizacion/TiposVehiculo/js/tiposVehiculo.js',
        'gpsDestinos'     => './modules/GPS/Parametrizacion/Destinos/js/destinos.js',
        'gpsPlataformas'  => './modules/GPS/Parametrizacion/Plataformas/js/plataformas.js',
    ];
    if (isset($map[$mod])) {
        echo '<script src="' . $map[$mod] . '"></script>';
    }
}
?>

<script>
/* ── Cambiar contraseña (topnav) — después de jQuery ──────── */
$(function() {
    const CTRL_USR = './modules/Usuarios/controllers/usuariosController.php';

    $('#btnAbrirCambiarPassword').on('click', function(e) {
        e.preventDefault();
        $('#frmCambiarPassword')[0].reset();
        $('#frmCambiarPassword .is-invalid').removeClass('is-invalid');
        const el = document.getElementById('modalCambiarPassword');
        (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show();
    });

    $('#btnGuardarPassword').on('click', function() {
        const actual    = $('#cp_actual').val();
        const nueva     = $('#cp_nueva').val();
        const confirmar = $('#cp_confirmar').val();
        let valido = true;

        $('#cp_actual').toggleClass('is-invalid', !actual);
        if (!actual) valido = false;

        $('#cp_nueva').toggleClass('is-invalid', nueva.length < 6);
        if (nueva.length < 6) valido = false;

        $('#cp_confirmar').toggleClass('is-invalid', nueva !== confirmar);
        if (nueva !== confirmar) valido = false;

        if (!valido) return;

        $('#btnGuardarPassword').prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-1"></span> Guardando...');

        $.post(CTRL_USR, {
            accion:          'cambiarPassword',
            password_actual:  actual,
            password_nueva:   nueva,
            confirmar:        confirmar
        }, function(resp) {
            $('#btnGuardarPassword').prop('disabled', false)
                .html('<i class="bi bi-save me-1"></i> Guardar');
            if (!resp.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: resp.mensaje });
                return;
            }
            bootstrap.Modal.getInstance(document.getElementById('modalCambiarPassword')).hide();
            Swal.fire({ icon: 'success', title: '¡Listo!', text: resp.mensaje,
                        timer: 2000, showConfirmButton: false });
        }, 'json');
    });
});
</script>
