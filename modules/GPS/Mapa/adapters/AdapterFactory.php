<?php
/**
 * Fábrica de adaptadores por motor de rastreo.
 *
 * Distingue dos capacidades:
 *  - POSICIONES_EN_VIVO: motores cuyas posiciones se obtienen ya por REST/HTTP
 *    (para el mapa). Optimus NO entra aquí todavía (usa WebSocket → worker pendiente).
 *  - LISTADO: motores que pueden enumerar los equipos de una cuenta por REST
 *    (para el selector "Agregar vehículos"). Optimus SÍ entra aquí.
 *
 * Agregar un motor nuevo = una clase adaptadora + un case aquí.
 */
require_once __DIR__ . '/GpsAdapterInterface.php';
require_once __DIR__ . '/GpsServerAdapter.php';
require_once __DIR__ . '/OptimusAdapter.php';
require_once __DIR__ . '/TrackSolidAdapter.php';
require_once __DIR__ . '/DetektorAdapter.php';
require_once __DIR__ . '/SkytekAdapter.php';
require_once __DIR__ . '/GpsWoxAdapter.php';
require_once __DIR__ . '/GlobaltechnAdapter.php';

class AdapterFactory
{
    /** Motores cuya posición se obtiene por REST/HTTP en la misma petición (adapter-fetch). */
    public const POSICIONES_EN_VIVO = ['gps-server', 'tracksolid', 'detektor', 'skytek', 'gpswox', 'globaltechn'];

    /** Motores cuya posición la alimenta un worker de fondo en gps.Posiciones (la vista lee caché). */
    public const POSICIONES_WORKER = ['optimus'];

    /** Motores que pueden listar los equipos de una cuenta (alimentan el selector). */
    public const LISTADO = ['gps-server', 'optimus', 'tracksolid', 'detektor', 'skytek', 'gpswox', 'globaltechn'];

    public static function crear(string $tipo, string $apiUrl, string $usuario, string $contrasena): ?GpsAdapterInterface
    {
        switch (strtolower(trim($tipo))) {
            case 'gps-server':
                return new GpsServerAdapter($apiUrl, $usuario, $contrasena);
            case 'optimus':
                return new OptimusAdapter($apiUrl, $usuario, $contrasena);
            case 'tracksolid':
                return new TrackSolidAdapter($apiUrl, $usuario, $contrasena);
            case 'detektor':
                return new DetektorAdapter($apiUrl, $usuario, $contrasena);
            case 'skytek':
                return new SkytekAdapter($apiUrl, $usuario, $contrasena);
            case 'gpswox':
                return new GpsWoxAdapter($apiUrl, $usuario, $contrasena);
            case 'globaltechn':
                return new GlobaltechnAdapter($apiUrl, $usuario, $contrasena);
            // case 'gpswox':     return new GpsWoxAdapter(...);      // pendiente
            default:
                return null;
        }
    }

    public static function soportaPosiciones(?string $tipo): bool
    {
        return in_array(strtolower(trim((string)$tipo)), self::POSICIONES_EN_VIVO, true);
    }

    public static function soportaListado(?string $tipo): bool
    {
        return in_array(strtolower(trim((string)$tipo)), self::LISTADO, true);
    }

    /** ¿El motor lo alimenta un worker de fondo (posición vía caché)? */
    public static function soportaWorker(?string $tipo): bool
    {
        return in_array(strtolower(trim((string)$tipo)), self::POSICIONES_WORKER, true);
    }

    /** ¿El motor tiene ALGUNA fuente de posición en vivo (adapter o worker)? */
    public static function tieneVivo(?string $tipo): bool
    {
        return self::soportaPosiciones($tipo) || self::soportaWorker($tipo);
    }
}
