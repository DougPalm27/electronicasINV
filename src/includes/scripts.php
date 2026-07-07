<!-- ── Librerías que NO dependen de jQuery ─────────────────── -->
<script src="./assets/vendor/apexcharts/apexcharts.min.js"></script>
<script src="./assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

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

<!-- JS del sistema -->
<script src="./assets/js/app.js"></script>

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
                }).then(function() { window.location.href = './index.php'; });
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

<?php
/* ── Bienvenida al iniciar sesión (se muestra una sola vez) ── */
$mostrarBienvenida = !empty($_SESSION['bienvenida_pendiente']);
if ($mostrarBienvenida) {
    unset($_SESSION['bienvenida_pendiente']);

    $horaActual = (int)date('G');
    if ($horaActual < 12)      $saludoBienvenida = 'Buenos días';
    elseif ($horaActual < 18)  $saludoBienvenida = 'Buenas tardes';
    else                       $saludoBienvenida = 'Buenas noches';

    $nombreBienvenida = $_SESSION['nombre'] ?? 'Usuario';
    $fotoBienvenida   = $_SESSION['foto']   ?? null;

    $inicialesBienvenida = '';
    foreach (preg_split('/\s+/', trim($nombreBienvenida)) as $palabraB) {
        if ($palabraB === '') continue;
        $inicialesBienvenida .= mb_strtoupper(mb_substr($palabraB, 0, 1));
        if (mb_strlen($inicialesBienvenida) >= 2) break;
    }
    if ($inicialesBienvenida === '') $inicialesBienvenida = 'U';

    $avatarBienvenida = $fotoBienvenida
        ? '<img src="./' . htmlspecialchars($fotoBienvenida) . '" alt="" '
          . 'style="width:140px;height:140px;border-radius:50%;object-fit:cover;'
          . 'border:4px solid #e9f3ee;box-shadow:0 6px 22px rgba(21,107,69,.22)">'
        : '<span style="width:140px;height:140px;border-radius:50%;background:#e9f3ee;color:#156b45;'
          . 'font-size:2.8rem;font-weight:600;display:inline-flex;align-items:center;'
          . 'justify-content:center;letter-spacing:.02em">'
          . htmlspecialchars($inicialesBienvenida) . '</span>';
?>
<script>
$(function () {
    Swal.fire({
        html: <?= json_encode(
            '<div style="padding:.75rem 0 .5rem">'
            . $avatarBienvenida
            . '<h3 style="margin:1.25rem 0 0;font-weight:600;font-size:1.45rem;color:#1c2128">'
            . htmlspecialchars($saludoBienvenida) . ', ' . htmlspecialchars($nombreBienvenida)
            . '</h3>'
            . '<p style="margin:.45rem 0 0;color:#8a919c;font-size:1rem">Qué bueno verte de nuevo</p>'
            . '</div>'
        ) ?>,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        width: 480,
        padding: '1.75rem'
    });
});
</script>
<?php } ?>

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
        'gpsPlataformas'      => './modules/GPS/Parametrizacion/Plataformas/js/plataformas.js',
        // ── RRHH ─────────────────────────────────────────────
        'horariosMonitoreo'   => './modules/HorariosMonitoreo/js/horario.js',
    ];
    if (isset($map[$mod])) {
        echo '<script src="' . $map[$mod] . '"></script>';
    }

    // Módulo de eyectores: se usa dentro de la vista de Máquinas
    if ($mod === 'maquinas') {
        echo '<script src="./modules/Electronicas/Eyectores/js/eyectores.js"></script>';
    }

    // Directorio de custodios: se usa dentro de la vista de Credenciales GPS
    if ($mod === 'gpsCredenciales') {
        echo '<script src="./modules/GPS/Custodios/js/custodios.js"></script>';
    }
}
?>

<script>
/* ── Perfil y cambiar contraseña (topnav) — después de jQuery ── */
$(function() {
    const CTRL_USR = './modules/Usuarios/controllers/usuariosController.php';

    /* ── Mi perfil: foto ── */
    $('#btnAbrirPerfil').on('click', function(e) {
        e.preventDefault();
        const el = document.getElementById('modalPerfil');
        (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show();
    });

    $('#btnCambiarFoto').on('click', function() {
        $('#perfilInputFoto').trigger('click');
    });

    $('#perfilInputFoto').on('change', function() {
        const archivo = this.files[0];
        if (!archivo) return;

        if (archivo.size > 2 * 1024 * 1024) {
            Swal.fire({ icon: 'warning', title: 'Imagen muy pesada', text: 'Máximo 2 MB.' });
            this.value = '';
            return;
        }

        const fd = new FormData();
        fd.append('accion', 'actualizarFoto');
        fd.append('foto', archivo);

        $('#btnCambiarFoto').prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-1"></span> Subiendo...');

        $.ajax({
            url: CTRL_USR,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(resp) {
                if (!resp.ok) {
                    $('#btnCambiarFoto').prop('disabled', false)
                        .html('<i class="bi bi-camera me-1"></i>Cambiar foto');
                    Swal.fire({ icon: 'error', title: 'Error', text: resp.mensaje });
                    return;
                }
                Swal.fire({ icon: 'success', title: '¡Listo!', text: resp.mensaje,
                            timer: 1200, showConfirmButton: false })
                    .then(function() { window.location.reload(); });
            },
            error: function() {
                $('#btnCambiarFoto').prop('disabled', false)
                    .html('<i class="bi bi-camera me-1"></i>Cambiar foto');
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error en el servidor.' });
            }
        });
    });

    $('#btnQuitarFoto').on('click', function() {
        Swal.fire({
            title: '¿Quitar tu foto de perfil?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, quitar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545'
        }).then(function(r) {
            if (!r.isConfirmed) return;
            $.post(CTRL_USR, { accion: 'quitarFoto' }, function(resp) {
                if (!resp.ok) {
                    Swal.fire({ icon: 'error', title: 'Error', text: resp.mensaje });
                    return;
                }
                window.location.reload();
            }, 'json');
        });
    });

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
