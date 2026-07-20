<?php
/**
 * Adaptador para el motor GPSWOX (white-label). Cubre varias plataformas que
 * usan este motor: davisgps, bigsoluthn, y cualquier otra sobre GPSWOX.
 *
 * API estándar de GPSWOX:
 *   1. POST {base}/api/login  (email, password)      → { user_api_hash }
 *   2. GET  {base}/api/get_devices?user_api_hash=…    → grupos con items (dispositivos+posición)
 *
 * Cada dispositivo trae: lat, lng, course, speed, y device_data con
 * plate_number (placa limpia), imei, time, y la ignición dentro del XML traccar.other.
 *
 * base = scheme://host (ej. https://davisgps.com). El "usuario" es el email.
 */
require_once __DIR__ . '/GpsAdapterInterface.php';

class GpsWoxAdapter implements GpsAdapterInterface
{
    private string $base;
    private string $usuario;
    private string $pwd;
    private ?string $hash = null;

    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
                     . '(KHTML, like Gecko) Chrome/126.0 Safari/537.36';

    public function __construct(string $apiUrl, string $usuario, string $contrasena)
    {
        $base = trim($apiUrl);
        if ($base === '') throw new RuntimeException('GPSWOX: falta api_url.');
        if (!preg_match('~^https?://~i', $base)) $base = 'https://' . $base;
        if (preg_match('~^(https?://[^/]+)~i', $base, $m)) $base = $m[1];
        $this->base    = rtrim($base, '/');
        $this->usuario = $usuario;
        $this->pwd     = $contrasena;
    }

    public function obtenerPosiciones(): array
    {
        return $this->normalizar($this->getDevices());
    }

    public function listarDispositivos(): array
    {
        $out = [];
        foreach ($this->getDevices() as $d) {
            $out[] = ['dispositivo' => $this->nombre($d), 'imei' => trim((string)($d['device_data']['imei'] ?? ''))];
        }
        return $out;
    }

    /** Placa preferida: plate_number → name → imei (no vacío). */
    private function nombre(array $d): string
    {
        $dd = $d['device_data'] ?? [];
        foreach ([$dd['plate_number'] ?? '', $d['name'] ?? '', $dd['name'] ?? '', $dd['imei'] ?? ''] as $v) {
            $v = trim((string)$v);
            if ($v !== '') return $v;
        }
        return '';
    }

    // ── API ─────────────────────────────────────────────────────
    private function login(): void
    {
        $resp = $this->http('POST', $this->base . '/api/login', [
            'email'    => $this->usuario,
            'password' => $this->pwd,
        ]);
        $data = json_decode($resp, true);
        if (empty($data['user_api_hash'])) {
            throw new RuntimeException('GPSWOX: login fallido (revisa email/clave).');
        }
        $this->hash = $data['user_api_hash'];
    }

    /** Devuelve la lista PLANA de dispositivos (aplana los grupos). */
    private function getDevices(): array
    {
        if (!$this->hash) $this->login();
        $resp = $this->http('GET', $this->base . '/api/get_devices?lang=en&user_api_hash=' . rawurlencode($this->hash));
        $data = json_decode($resp, true);
        if (!is_array($data)) return [];

        $devices = [];
        foreach ($data as $g) {
            if (isset($g['items']) && is_array($g['items'])) {
                foreach ($g['items'] as $it) $devices[] = $it;
            } elseif (is_array($g)) {
                $devices[] = $g;
            }
        }
        return $devices;
    }

    private function normalizar(array $lista): array
    {
        $out = [];
        foreach ($lista as $d) {
            $lat = isset($d['lat']) ? (float)$d['lat'] : null;
            $lng = isset($d['lng']) ? (float)$d['lng'] : null;
            if ($lat === null || $lng === null || ($lat == 0.0 && $lng == 0.0)) continue;

            $dd  = $d['device_data'] ?? [];
            $enc = null;
            $other = $dd['traccar']['other'] ?? '';
            if (is_string($other) && preg_match('~<ignition>(true|false)</ignition>~i', $other, $m)) {
                $enc = strtolower($m[1]) === 'true' ? 1 : 0;
            }

            $out[] = [
                'dispositivo' => $this->nombre($d),
                'imei'        => trim((string)($dd['imei'] ?? '')),
                'lat'         => $lat,
                'lng'         => $lng,
                'velocidad'   => (int)round((float)($d['speed'] ?? 0)),
                'rumbo'       => (int)round((float)($d['course'] ?? 0)),
                'encendido'   => $enc,
                'fecha'       => $dd['time'] ?? ($dd['traccar']['time'] ?? null),
                'direccion'   => $dd['traccar']['address'] ?? null,
            ];
        }
        return $out;
    }

    // ── HTTP ────────────────────────────────────────────────────
    private function http(string $metodo, string $url, ?array $body = null): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => self::UA,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        if ($metodo === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body ?? []));
        }
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($resp === false) throw new RuntimeException("GPSWOX: fallo de conexión ($err).");
        return (string)$resp;
    }
}
