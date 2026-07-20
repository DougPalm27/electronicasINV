<?php
/**
 * Adaptador para globaltechn (mapas.globaltechn.net) — motor "service24gps".
 *
 * Flujo (sesión, como gps-server):
 *   1. GET  {base}/login                                  → cookie de sesión
 *   2. POST {base}/login  (nick, emailPswd + campos de dispositivo)
 *   3. GET  {base}/Moviles/getVehiculos?nueva_vista_activos=1
 *          → JSON indexado por IMEI:
 *            { "<imei>": { Placa, Nombre, Latitud, Longitud, Velocidad,
 *                          Direccion(rumbo), Ignicion("Apagado"/"Encendido"),
 *                          FechaHora, Address, ... }, ... }
 *
 * NOTA sobre el captcha: la plataforma NO pide captcha en un login normal;
 * solo lo activa tras varios intentos fallidos (anti fuerza bruta). Por eso es
 * indispensable que la contraseña guardada sea la correcta — si falla repetido,
 * la cuenta empezará a exigir captcha y el adaptador no podrá entrar solo.
 */
require_once __DIR__ . '/GpsAdapterInterface.php';

class GlobaltechnAdapter implements GpsAdapterInterface
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
        if ($base === '') throw new RuntimeException('globaltechn: falta api_url.');
        if (!preg_match('~^https?://~i', $base)) $base = 'https://' . $base;
        if (preg_match('~^(https?://[^/]+)~i', $base, $m)) $base = $m[1];
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
        foreach ($this->traer() as $imei => $v) {
            $out[] = [
                'dispositivo' => trim((string)($v['Placa'] ?? $v['Nombre'] ?? $imei)),
                'imei'        => (string)$imei,
            ];
        }
        return $out;
    }

    // ── Login por sesión + lectura ──────────────────────────────
    private function traer(): array
    {
        $this->iniciarCurl();
        try {
            $this->get($this->base . '/login');   // cookie de sesión
            $resp = $this->post($this->base . '/login', [
                'nick'           => $this->usuario,
                'passwd'         => $this->pwd,   // el campo real es 'passwd' (no 'emailPswd')
                // campos ocultos que envía el formulario (info del navegador)
                'txt-appVersion' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'txt-platform'   => 'Win32',
                'txt-product'    => 'Gecko',
                'txt-vendor'     => 'Google Inc.',
                'txt-browser'    => 'Chrome',
                'cbLang'         => 'es',
                'cbPaisLang'     => '',
            ]);
            if (stripos($resp, 'name="nick"') !== false) {
                $msg = 'globaltechn: no se pudo iniciar sesión (revisa usuario/contraseña).';
                if (stripos($resp, 'captcha') !== false) {
                    $msg .= ' La cuenta está pidiendo captcha por intentos fallidos previos: '
                          . 'corrige la contraseña guardada y entra una vez desde el navegador para limpiarlo.';
                }
                throw new RuntimeException($msg);
            }

            $json = $this->get($this->base . '/Moviles/getVehiculos?nueva_vista_activos=1');
            $data = json_decode(trim($json), true);
            return is_array($data) ? $data : [];
        } finally {
            if ($this->ch) { curl_close($this->ch); $this->ch = null; }
        }
    }

    private function normalizar(array $equipos): array
    {
        $out = [];
        foreach ($equipos as $imei => $v) {
            if (!is_array($v)) continue;
            $lat = isset($v['Latitud'])  ? (float)$v['Latitud']  : null;
            $lng = isset($v['Longitud']) ? (float)$v['Longitud'] : null;
            if ($lat === null || $lng === null || ($lat == 0.0 && $lng == 0.0)) continue;

            $ign = strtolower(trim((string)($v['Ignicion'] ?? '')));
            $out[] = [
                'dispositivo' => trim((string)($v['Placa'] ?? $v['Nombre'] ?? $imei)),
                'imei'        => (string)$imei,
                'lat'         => $lat,
                'lng'         => $lng,
                'velocidad'   => (int)round((float)($v['Velocidad'] ?? 0)),
                'rumbo'       => (int)round((float)($v['Direccion'] ?? 0)),
                'encendido'   => $ign === '' ? null : (str_contains($ign, 'encend') ? 1 : 0),
                'fecha'       => $v['FechaHora'] ?? null,     // 'Y-m-d H:i:s'
                'direccion'   => $v['Address'] ?? null,
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
        if ($resp === false) throw new RuntimeException('globaltechn: fallo de conexión con ' . $url . ': ' . curl_error($this->ch));
        return (string)$resp;
    }
}
