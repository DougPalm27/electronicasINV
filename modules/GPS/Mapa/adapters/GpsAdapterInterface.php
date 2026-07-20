<?php
/**
 * Contrato de un adaptador de motor de rastreo GPS.
 *
 * Cada motor (gps-server.net, GPSWOX, TrackSolid/Jimi, …) implementa esta
 * interfaz. La idea del agregador es tener UN adaptador por *motor*, no por
 * proveedor: muchas plataformas regionales son white-label del mismo motor,
 * así que un adaptador cubre todas las cuentas de ese tipo.
 *
 * `obtenerPosiciones()` devuelve un arreglo de posiciones NORMALIZADAS, cada
 * una con esta forma:
 *
 *   [
 *     'dispositivo' => string,   // nombre del equipo en la plataforma (suele ser la placa)
 *     'imei'        => string,   // identificador único del rastreador
 *     'lat'         => float,
 *     'lng'         => float,
 *     'velocidad'   => int,      // km/h
 *     'rumbo'       => int,      // 0-359 grados
 *     'encendido'   => ?int,     // 1 = motor encendido, 0 = apagado, null = desconocido
 *     'fecha'       => ?string,  // 'Y-m-d H:i:s' del último reporte del equipo
 *     'direccion'   => ?string,  // dirección geocodificada si la plataforma la da
 *   ]
 *
 * Debe lanzar una excepción si falla la autenticación o la comunicación, para
 * que el controlador reporte el error de esa cuenta sin tumbar todo el mapa.
 *
 * `listarDispositivos()` devuelve el catálogo de equipos de la cuenta (para el
 * selector "Agregar vehículos"), cada uno como:
 *
 *   [ 'dispositivo' => string,   // nombre en la plataforma (suele ser la placa)
 *     'imei'        => string ]
 *
 * El listado es REST simple en todos los motores; las posiciones en vivo no
 * siempre (Optimus las manda por WebSocket). Por eso van en métodos separados.
 */
interface GpsAdapterInterface
{
    public function obtenerPosiciones(): array;
    public function listarDispositivos(): array;
}
