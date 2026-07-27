<?php
/**
 * Worker de tokens TrackSolid — renueva los JWT de todas las cuentas.
 *
 * El login de TrackSolid necesita Chrome headless y bajo IIS ese login falla o
 * agota el tiempo de la petición. Este worker corre FUERA de IIS (Programador
 * de tareas) y deja los tokens frescos en gps.CuentaTokens; la web solo lee
 * caché y nunca lanza Chrome.
 *
 * Ejecutar (CLI, corrida única — programar cada 30 min):
 *     php modules/GPS/Mapa/worker/tracksolid_token_worker.php
 * Ver start_tracksolid_tokens.bat y DESPLIEGUE.md.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo CLI.'); }

date_default_timezone_set('America/Tegucigalpa');
chdir(__DIR__);
require_once __DIR__ . '/../adapters/TrackSolidAdapter.php';
require_once __DIR__ . '/../models/mdlDespachos.php';
require_once __DIR__ . '/../../../../config/Connection.php';

// Renovar cuando el token pase de 45 min (la web lo acepta hasta 90).
$MAX_EDAD_SEG = 2700;

function wlog(string $m): void { echo '[' . date('Y-m-d H:i:s') . "] $m\n"; }

$desp = new mdlDespachos();
$conn = (new Connection())->dbConnect();
$stmt = $conn->prepare(
    "SELECT c.id_cuenta, c.usuario, c.contrasena, p.api_url, t.nombre AS transporte
     FROM gps.CuentasGPS c
     INNER JOIN gps.Plataformas p ON p.id_plataforma = c.id_plataforma
     INNER JOIN gps.Transportes t ON t.id_transporte = c.id_transporte
     WHERE c.activo = 1 AND p.tipo_integracion = 'tracksolid'
     ORDER BY c.usuario"
);
$stmt->execute();
$cuentas = $stmt->fetchAll(PDO::FETCH_ASSOC);

wlog('Cuentas TrackSolid activas: ' . count($cuentas));
$renovados = 0; $vigentes = 0; $fallidos = 0; $errores = [];

foreach ($cuentas as $c) {
    $u = $c['usuario'];
    try {
        $adapter = new TrackSolidAdapter($c['api_url'], $u, $c['contrasena']);
        $r = $adapter->renovarToken($MAX_EDAD_SEG);
        if ($r === 'renovado') { $renovados++; wlog("$u ({$c['transporte']}): token RENOVADO."); }
        else                   { $vigentes++;  wlog("$u ({$c['transporte']}): token vigente, sin cambios."); }
    } catch (Throwable $e) {
        $fallidos++;
        $errores[] = "$u: " . $e->getMessage();
        wlog("$u ({$c['transporte']}): FALLO — " . $e->getMessage());
    }
}

$detalle = count($cuentas) . " cuentas: $renovados renovados, $vigentes vigentes, $fallidos fallidos"
         . ($errores ? '. ' . mb_substr(implode(' | ', $errores), 0, 250) : '');
try {
    $desp->registrarHeartbeat('tracksolid_tokens', $fallidos ? ($renovados + $vigentes ? 'parcial' : 'error') : 'ok', $detalle);
} catch (Throwable $e) { wlog('Heartbeat no registrado: ' . $e->getMessage()); }

wlog("Fin. $detalle");
exit($fallidos && !($renovados + $vigentes) ? 1 : 0);
