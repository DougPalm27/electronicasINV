<?php
/**
 * Adaptador para Detektor GPS (hn.detektorgps.com) — plataforma PHP propia.
 *
 * Flujo simple (como gps-server): login por sesión y una sola llamada de datos.
 *   1. GET  {base}/mapa.php            → cookie de sesión
 *   2. POST {base}/filtro.php          → usuario/password/ingresar (autentica)
 *   3. GET  {base}/mapa-json.php        → arreglo de arreglos con todas las unidades:
 *          [ imei, placa, fecha, lat, lng, ignicion, velocidad, alias ]
 *
 * base = la carpeta "movil", p.ej. https://hn.detektorgps.com/gps_hn/movil
 */
require_once __DIR__ . '/GpsAdapterInterface.php';

class DetektorAdapter implements GpsAdapterInterface
{
    private string $base;
    private string $usuario;
    private string $pwd;
    /** @var resource|\CurlHandle */
    private $ch;

    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
                     . '(KHTML, like Gecko) Chrome/126.0 Safari/537.36';

    public function __construct(string $apiUrl, string $usuario, string $contrasena)
    {
        $base = trim($apiUrl);
        if ($base === '') throw new RuntimeException('Detektor: falta api_url.');
        if (!preg_match('~^https?://~i', $base)) $base = 'https://' . $base;
        $this->base    = rtrim($base, '/');
        $this->usuario = $usuario;
        $this->pwd     = $contrasena;
    }

    public function obtenerPosiciones(): array
    {
        return $this->normalizar($this->traer());
    }

    public function listarDispositivos(): array
    {
        $out = [];
        foreach ($this->traer() as $r) {
            $out[] = ['dispositivo' => trim((string)($r[1] ?? '')), 'imei' => trim((string)($r[0] ?? ''))];
        }
        return $out;
    }

    // ── Login por sesión + lectura ──────────────────────────────
    private function traer(): array
    {
        $this->iniciarCurl();
        try {
            $this->get($this->base . '/mapa.php');          // cookie de sesión
            $resp = $this->post($this->base . '/filtro.php', [
                'usuario'  => $this->usuario,
                'password' => $this->pwd,
                'ingresar' => 'Ingresar',
            ]);
            if (stripos($resp, 'Error de acceso') !== false) {
                throw new RuntimeException('Detektor: usuario/contraseña incorrectos o cuenta deshabilitada.');
            }
            $json = $this->get($this->base . '/mapa-json.php');
            $data = json_decode(trim($json), true);
            if (!is_array($data)) return []; // sin datos / respuesta inesperada
            return $data;
        } finally {
            if ($this->ch) { curl_close($this->ch); $this->ch = null; }
        }
    }

    private function normalizar(array $lista): array
    {
        $out = [];
        foreach ($lista as $r) {
            if (!is_array($r) || !isset($r[3], $r[4])) continue;
            $lat = (float)$r[3]; $lng = (float)$r[4];
            if ($lat == 0.0 && $lng == 0.0) continue;
            $out[] = [
                'dispositivo' => trim((string)($r[1] ?? '')),   // placa limpia
                'imei'        => trim((string)($r[0] ?? '')),
                'lat'         => $lat,
                'lng'         => $lng,
                'velocidad'   => (int)round((float)($r[6] ?? 0)),
                'rumbo'       => 0,                              // Detektor no envía rumbo
                'encendido'   => isset($r[5]) ? ((int)$r[5] ? 1 : 0) : null,
                'fecha'       => $r[2] ?? null,                  // 'Y-m-d H:i:s'
                'direccion'   => trim((string)($r[7] ?? '')) ?: null,
            ];
        }
        return $out;
    }

    // ── cURL con cookies en memoria ─────────────────────────────
    private function iniciarCurl(): void
    {
        $this->ch = curl_init();
        curl_setopt_array($this->ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIEFILE     => '',
            CURLOPT_USERAGENT      => self::UA,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
    }

    private function get(string $url): string
    {
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_HTTPGET, true);
        return $this->ejecutar($url);
    }

    private function post(string $url, array $campos): string
    {
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($campos));
        return $this->ejecutar($url);
    }

    private function ejecutar(string $url): string
    {
        $resp = curl_exec($this->ch);
        if ($resp === false) throw new RuntimeException('Detektor: fallo de conexión con ' . $url . ': ' . curl_error($this->ch));
        return (string)$resp;
    }
}
