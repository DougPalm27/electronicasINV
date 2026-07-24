<?php
/**
 * Worker de Optimus — pone las posiciones en vivo en gps.Posiciones.
 *
 * Optimus entrega las posiciones por MQTT sobre WebSocket (CloudAMQP). Este
 * proceso se mantiene conectado, suscrito al topic `<imei>/production` de cada
 * vehículo Optimus vinculado (gps.GPS con IMEI), y hace upsert de cada reporte.
 *
 * El mapa lee esa caché → los carros de Optimus pasan de "pendiente" a "en vivo".
 *
 * Ejecutar (CLI, proceso permanente):
 *     php modules/GPS/Mapa/worker/optimus_worker.php
 * En Windows se recomienda el Programador de tareas al inicio de sesión
 * (ver start_optimus_worker.bat).
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo CLI.'); }

date_default_timezone_set('America/Tegucigalpa');
chdir(__DIR__);
require_once __DIR__ . '/MqttWsClient.php';
require_once __DIR__ . '/../models/mdlMapa.php';
require_once __DIR__ . '/../models/mdlDespachos.php';
require_once __DIR__ . '/../../../../config/env.php';

// Broker de Optimus (credenciales públicas de su frontend; overridables en .env)
$HOST   = env('OPTIMUS_MQTT_HOST', 'brisk-azure-macaw.rmq5.cloudamqp.com');
$PORT   = (int) env('OPTIMUS_MQTT_PORT', 443);
$PATH   = env('OPTIMUS_MQTT_PATH', '/ws/mqtt');
$USER   = env('OPTIMUS_MQTT_USER', 'app_optimus_app');
$PASS   = env('OPTIMUS_MQTT_PASS', 'vnEeigCQMXNESR3n');
$SUFFIX = env('OPTIMUS_MQTT_SUFFIX', 'production');
$RECARGA_SEG = 300; // recargar lista de vehículos cada 5 min

function wlog(string $m): void { echo '[' . date('Y-m-d H:i:s') . "] $m\n"; }

function utcALocal(?string $utc): ?string
{
    if (!$utc) return null;
    try {
        $dt = new DateTime($utc, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Tegucigalpa'));
        return $dt->format('Y-m-d H:i:s');
    } catch (Throwable $e) { return null; }
}

function direccionDesdePayload($data): ?string
{
    if (!is_array($data)) return null;
    $keys = [
        'address', 'addr', 'direccion', 'location', 'locationName', 'locationDesc',
        'gpsAddress', 'formattedAddress', 'currentAddress', 'lastAddress',
        'mapAddress', 'positionAddress', 'descriptionAddress', 'poi'
    ];
    foreach ($keys as $k) {
        if (!empty($data[$k]) && is_scalar($data[$k])) {
            $txt = trim(preg_replace('/\s+/', ' ', (string)$data[$k]));
            if ($txt !== '') return $txt;
        }
    }
    foreach ($data as $v) {
        if (is_array($v)) {
            $txt = direccionDesdePayload($v);
            if ($txt) return $txt;
        }
    }
    return null;
}

wlog('Worker Optimus iniciado.');

$errAnt = ''; $errCont = 0; // para no llenar el log durante un corte largo
while (true) {
    $cli = null;
    $desp = null;
    try {
        $model = new mdlMapa();                       // conexión fresca cada ciclo (auto-recuperación)
        $desp  = new mdlDespachos();
        $desp->registrarHeartbeat('optimus', 'ok', 'Iniciado.');
        $veh = $desp->imeisOptimusActivos();          // imei => [id_cuenta,placa] (solo despachos activos)
        if (!$veh) {
            $desp->registrarHeartbeat('optimus', 'ok', 'Sin carros Optimus en despachos activos.');
            wlog('Sin carros Optimus en despachos activos. Reintento en 60s.');
            sleep(60);
            continue;
        }

        $topics = array_map(fn($imei) => $imei . '/' . $SUFFIX, array_keys($veh));
        wlog('Vehículos Optimus con IMEI: ' . count($veh) . '. Conectando al broker...');
        $desp->registrarHeartbeat('optimus', 'ok', 'Conectando al broker con ' . count($veh) . ' unidades.');

        $cli = new MqttWsClient($HOST, $PORT, $PATH, $USER, $PASS);
        $cli->conectar();
        $cli->suscribir($topics);
        wlog('Conectado y suscrito a ' . count($topics) . ' unidades. Escuchando...');
        $desp->registrarHeartbeat('optimus', 'ok', 'Escuchando ' . count($topics) . ' unidades.');

        $onMsg = function (string $topic, string $payload) use ($model, $desp, $veh) {
            $imei = strtok($topic, '/');
            if (!isset($veh[$imei])) return;

            $d   = json_decode($payload, true);
            $pos = $d['data']['position'] ?? null;
            if (!$pos || !isset($pos['latitude'], $pos['longitude'])) return;
            $direccion = direccionDesdePayload($pos) ?? direccionDesdePayload($d['data'] ?? []) ?? direccionDesdePayload($d);

            $v = $veh[$imei];
            $model->guardarPosicion([
                'id_cuenta'   => $v['id_cuenta'],
                'id_gps'      => null,
                'dispositivo' => $v['placa'],
                'imei'        => $imei,
                'placa'       => $v['placa'],
                'lat'         => (float)$pos['latitude'],
                'lng'         => (float)$pos['longitude'],
                'velocidad'   => (int)round($pos['speed'] ?? 0),
                'rumbo'       => (int)round($pos['azimuth'] ?? 0),
                'encendido'   => isset($pos['isOn']) ? ($pos['isOn'] ? 1 : 0) : null,
                'direccion'   => $direccion,
                'fecha'       => utcALocal($pos['utcDate'] ?? null),
            ]);
            $desp->registrarHeartbeat('optimus', 'ok', 'Ultimo reporte: ' . $v['placa']);
        };

        $recargar = time() + $RECARGA_SEG;
        while (time() < $recargar) {
            $desp->registrarHeartbeat('optimus', 'ok', 'Escuchando ' . count($topics) . ' unidades.');
            $cli->escuchar($onMsg, 20);
        }
        $cli->cerrar();
        wlog('Recargando lista de vehículos...');
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if ($msg === $errAnt) { $errCont++; } else { $errCont = 0; $errAnt = $msg; }
        if ($errCont % 12 === 0) wlog('ERROR: ' . $msg . ' — reintentando cada 5s...'); // 1a vez y luego ~cada min
        try {
            if ($desp instanceof mdlDespachos) $desp->registrarHeartbeat('optimus', 'error', $msg);
        } catch (Throwable $ignored) {}
        @$cli?->cerrar();
        sleep(5);
    }
}
