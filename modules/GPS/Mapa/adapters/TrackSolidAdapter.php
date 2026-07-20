<?php
/**
 * Adaptador TrackSolid Pro (Jimi) — vía LOGIN WEB (Plan B, sin API abierta).
 *
 * TrackSolid no expone API con llave; su web cifra la clave en el navegador.
 * Por eso el token JWT se obtiene con un login HEADLESS (helper Node/Chrome,
 * worker/nodehelper/login.js) que devuelve {token, queryBody, accountId}.
 * El token dura horas → se cachea por usuario (gps.CuentaTokens) y se reusa.
 *
 * Con el token, las posiciones se leen por HTTP (rápido):
 *   POST {base}/v3/new/newEquipment/queryEquipmentList
 *   header Authorization: <token> ; body = queryBody (userId/orgId/userType)
 *   → devuelve equipos con deviceName/imei/lat/lng/acc/direction/speed/gpsTime.
 */
require_once __DIR__ . '/GpsAdapterInterface.php';
require_once __DIR__ . '/TrackSolidTokenStore.php';
require_once __DIR__ . '/../../../../config/env.php';

class TrackSolidAdapter implements GpsAdapterInterface
{
    private string $base;
    private string $usuario;
    private string $pwd;
    private TrackSolidTokenStore $store;
    private ?string $token = null;
    private ?string $queryBody = null;

    private const VIGENCIA = 5400; // 90 min: refrescar el token pasado ese tiempo

    public function __construct(string $apiUrl, string $usuario, string $contrasena)
    {
        $base = trim($apiUrl);
        if (!preg_match('~^https?://~i', $base)) $base = 'https://' . $base;
        $this->base    = rtrim(preg_replace('~(/[^/]*\.\w+)?/?$~', '', $base), '/') ?: $base;
        // Normalización simple: dejar scheme://host
        if (preg_match('~^(https?://[^/]+)~i', $base, $m)) $this->base = $m[1];
        $this->usuario = $usuario;
        $this->pwd     = $contrasena;
        $this->store   = new TrackSolidTokenStore();
    }

    // ── Interfaz ────────────────────────────────────────────────
    public function obtenerPosiciones(): array
    {
        return $this->normalizar($this->consultar());
    }

    public function listarDispositivos(): array
    {
        $out = [];
        foreach ($this->consultar() as $d) {
            $out[] = ['dispositivo' => $this->nombre($d), 'imei' => trim((string)($d['imei'] ?? ''))];
        }
        return $out;
    }

    // ── Token: caché o login headless ───────────────────────────
    private function asegurarToken(): void
    {
        if ($this->token) return;
        $row = $this->store->leer($this->usuario);
        if ($row && !empty($row['token']) && !empty($row['query_body']) && (int)$row['edad'] < self::VIGENCIA) {
            $this->token     = $row['token'];
            $this->queryBody = $row['query_body'];
            return;
        }
        $this->login();
    }

    private function login(): void
    {
        $r = $this->loginHeadless();
        if (empty($r['token']) || empty($r['queryBody'])) {
            throw new RuntimeException('TrackSolid: el login headless no devolvió token/queryBody (revisa usuario/clave).');
        }
        $this->token     = $r['token'];
        $this->queryBody = $r['queryBody'];
        $this->store->guardar($this->usuario, $r['token'], $r['queryBody'], $r['accountId'] ?? null);
    }

    /** Ejecuta el helper Node (Chrome headless) con las credenciales por entorno. */
    private function loginHeadless(): array
    {
        $script = realpath(__DIR__ . '/../worker/nodehelper/login.js');
        if (!$script) throw new RuntimeException('No se encontró el helper Node (login.js).');
        $node = (string) env('NODE_BIN', 'node');

        $env = array_merge(getenv(), ['TS_USER' => $this->usuario, 'TS_PWD' => $this->pwd]);
        $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open([$node, $script], $desc, $pipes, dirname($script), $env);
        if (!is_resource($proc)) throw new RuntimeException('No se pudo iniciar Node para el login de TrackSolid.');

        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        proc_close($proc);

        $data = json_decode(trim($out), true);
        if (!is_array($data) || empty($data['token'])) {
            throw new RuntimeException('TrackSolid: login headless falló. ' . trim($err ?: $out));
        }
        return $data;
    }

    // ── Consulta de equipos+posición (con reintento por token vencido) ──
    private function consultar(): array
    {
        $this->asegurarToken();
        $res = $this->postQuery();
        if ($res === null) {          // token vencido/ inválido → un relogin
            $this->token = null;
            $this->login();
            $res = $this->postQuery();
        }
        return $res ?? [];
    }

    private function postQuery(): ?array
    {
        $ch = curl_init($this->base . '/v3/new/newEquipment/queryEquipmentList');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $this->queryBody,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: ' . $this->token],
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($resp === false) throw new RuntimeException("TrackSolid: fallo de conexión ($err).");

        $data = json_decode($resp, true);
        // Éxito = trae 'data' como arreglo. Si no, el token probablemente venció.
        if (is_array($data) && array_key_exists('data', $data) && is_array($data['data'])) {
            return $data['data'];
        }
        return null;
    }

    // ── Normalización ───────────────────────────────────────────
    private function normalizar(array $lista): array
    {
        $out = [];
        foreach ($lista as $d) {
            if (!isset($d['lat'], $d['lng']) || $d['lat'] === null || $d['lng'] === null) continue;
            $out[] = [
                'dispositivo' => $this->nombre($d),
                'imei'        => trim((string)($d['imei'] ?? '')),
                'lat'         => (float)$d['lat'],
                'lng'         => (float)$d['lng'],
                'velocidad'   => (int)round((float)($d['speed'] ?? 0)),
                'rumbo'       => (int)round((float)($d['direction'] ?? 0)),
                'encendido'   => isset($d['acc']) ? ((int)$d['acc'] ? 1 : 0) : null,
                'fecha'       => $d['gpsTime'] ?? ($d['otherPosTime'] ?? null),
                'direccion'   => null,
            ];
        }
        return $out;
    }

    /** deviceName suele venir "PLACA \n alias" → una línea limpia. */
    private function nombre(array $d): string
    {
        return trim(preg_replace('/\s+/', ' ', (string)($d['deviceName'] ?? $d['imei'] ?? '')));
    }
}
