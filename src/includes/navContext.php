<?php
/**
 * Contexto de navegación compartido.
 * Deriva, a partir del módulo activo (?module=), el título legible del módulo
 * y la sección del sistema (Electrónicas / GPS).
 *
 * Lo usan: inicio.php (título de la pestaña) y topnav.php (marca del header).
 */

$MODULOS_TITULOS = [
    'dasboard'          => 'Inicio',
    'repuestos'         => 'Repuestos',
    'maquinas'          => 'Máquinas',
    'mantenimientos'    => 'Mantenimientos',
    'marcas'            => 'Marcas',
    'modelos'           => 'Modelos',
    'proveedores'       => 'Proveedores',
    'tiposRepuestos'    => 'Tipos de Repuestos',
    'usuarios'          => 'Usuarios',
    'divisas'           => 'Divisas',
    'roles'             => 'Roles',
    'solicitudes'       => 'Solicitudes',
    'compras'           => 'Solicitudes de Compra',
    'satake'            => 'Satake',
    'gpsMapa'           => 'Mapa GPS',
    'gpsCredenciales'   => 'Credenciales',
    'gpsTransportes'    => 'Transportes',
    'gpsCuentas'        => 'Cuentas',
    'gpsTiposVehiculo'  => 'Tipos de Vehículo',
    'gpsDestinos'       => 'Destinos',
    'gpsPlataformas'    => 'Plataformas',
    'horariosMonitoreo' => 'Horarios de Monitoreo',
];

/** Clave del módulo activo (con fallback al dashboard). */
function moduloActual(): string
{
    return $_GET['module'] ?? 'dasboard';
}

/** Título legible del módulo. */
function tituloModulo(string $mod): string
{
    global $MODULOS_TITULOS;
    return $MODULOS_TITULOS[$mod] ?? 'Sistema';
}

/** Sección del sistema a la que pertenece el módulo: 'gps' | 'electronicas'. */
function seccionActual(string $mod): string
{
    return strncmp($mod, 'gps', 3) === 0 ? 'gps' : 'electronicas';
}

/** Nombre legible de la sección. */
function nombreSeccion(string $mod): string
{
    return seccionActual($mod) === 'gps' ? 'Sistema GPS' : 'Sistema Electrónicas';
}
