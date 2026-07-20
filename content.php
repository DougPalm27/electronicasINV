<?php

/**
 * Router principal del sistema.
 * Incluye la vista correspondiente al módulo solicitado,
 * respetando los permisos del rol del usuario en sesión.
 */

// ── Verificación de permiso por módulo ────────────────────
function moduloPermitido(string $clave): bool {
    $permitidos = $_SESSION['modulos'] ?? null;
    if ($permitidos === null) return true; // sin restricción (legado / admin sin rol)
    return in_array($clave, $permitidos);
}

function accesoDenegado(): void {
    echo '<section class="section">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6 text-center">
                <div class="card border-danger">
                    <div class="card-body py-5">
                        <i class="bi bi-shield-x text-danger" style="font-size:3rem"></i>
                        <h4 class="mt-3 text-danger">Acceso denegado</h4>
                        <p class="text-muted">No tienes permiso para ver este módulo.<br>
                           Contacta al administrador del sistema.</p>
                        <a href="?module=dasboard" class="btn btn-primary mt-2">
                            <i class="bi bi-house me-1"></i> Volver al inicio
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>';
}

// ── Enrutamiento ──────────────────────────────────────────
if (empty($_GET['module'])) {
    if (moduloPermitido('dasboard')) {
        include "./modules/dasboard/views/dash.php";
    } else {
        // Redirigir al primer módulo permitido
        $permitidos = $_SESSION['modulos'] ?? [];
        if (!empty($permitidos)) {
            header("Location: ?module=" . htmlspecialchars($permitidos[0]));
            exit;
        }
        accesoDenegado();
    }
} else {
    $module = $_GET['module'];

    // Mapa de módulos → archivos
    $rutas = [
        'dasboard'       => './modules/dasboard/views/dash.php',
        'repuestos'      => './modules/Electronicas/Repuestos/views/vw_repuestos.php',
        'maquinas'       => './modules/Electronicas/Maquinas/views/maquinas.php',
        'componentes'    => './modules/Electronicas/Componentes/views/componentes.php',
        'mantenimientos' => './modules/Electronicas/Mantenimientos/views/mantenimientos.php',
        'marcas'         => './modules/Parametrizacion/Marcas/views/vw_marcas.php',
        'modelos'        => './modules/Parametrizacion/Modelos/views/vw_modelos.php',
        'proveedores'    => './modules/Parametrizacion/Proveedores/views/vw_proveedores.php',
        'tiposRepuestos' => './modules/Parametrizacion/TiposRepuestos/views/vw_tiposRepuestos.php',
        'usuarios'       => './modules/Usuarios/views/vw_usuarios.php',
        'divisas'        => './modules/Parametrizacion/Divisas/views/vw_divisas.php',
        'roles'            => './modules/Roles/views/vw_roles.php',
        'solicitudes'      => './modules/Solicitudes/views/vw_solicitudes.php',
        'compras'          => './modules/SolicitudesCompra/views/vw_solicitudesCompra.php',
        'satake'           => './modules/Mantenimiento/Satake/views/vw_satake.php',
        // ── GPS ──────────────────────────────────────────────
        'gpsMapa'          => './modules/GPS/Mapa/views/vw_mapa.php',
        'gpsCredenciales'  => './modules/GPS/GPS/views/vw_gps.php',
        'gpsTransportes'   => './modules/GPS/Transportes/views/vw_transportes.php',
        'gpsCuentas'       => './modules/GPS/CuentasGPS/views/vw_cuentasGPS.php',
        'gpsTiposVehiculo' => './modules/GPS/Parametrizacion/TiposVehiculo/views/vw_tiposVehiculo.php',
        'gpsDestinos'      => './modules/GPS/Parametrizacion/Destinos/views/vw_destinos.php',
        'gpsPlataformas'       => './modules/GPS/Parametrizacion/Plataformas/views/vw_plataformas.php',
        // ── RRHH ─────────────────────────────────────────────
        'horariosMonitoreo'    => './modules/HorariosMonitoreo/views/vw_horario.php',
    ];

    if (isset($rutas[$module])) {
        if (moduloPermitido($module)) {
            include $rutas[$module];
        } else {
            accesoDenegado();
        }
    } else {
        echo '<section class="section">
            <div class="row justify-content-center mt-5">
                <div class="col-md-6 text-center">
                    <div class="card">
                        <div class="card-body py-5">
                            <i class="bi bi-question-circle text-muted" style="font-size:3rem"></i>
                            <h4 class="mt-3">Módulo no encontrado</h4>
                            <p class="text-muted">La página solicitada no existe o fue movida.</p>
                            <a href="?module=dasboard" class="btn btn-primary mt-2">
                                <i class="bi bi-house me-1"></i> Volver al inicio
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>';
    }
}
