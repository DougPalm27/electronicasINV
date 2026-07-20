<?php
/**
 * Adaptador para el motor gps-server.net (white-label).
 *
 * Plataformas que lo usan en el sistema: NavSat, VTG — y cualquier otra que
 * corra el mismo motor. Se autentica igual que el navegador (sesión + CSRF) y
 * lee las posiciones del mismo endpoint interno que usa el cliente web:
 * `GetInfo.php`, que devuelve un JSON con todos los vehículos de la cuenta.
 *
 * No usa API oficial ni requiere trámite con el proveedor: solo el usuario y
 * la contraseña que ya viven en gps.CuentasGPS.
 */
require_once __DIR__ . '/GpsAdapterInterface.php';

class GpsServerAdapter implements GpsAdapterInterface
{
    private string $base;
    private string $usuario;
    private string $contrasena;
    /** @var resource|\CurlHandle */
    private $ch;

    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
                     . '(KHTML, like Gecko) Chrome/126.0 Safari/537.36';

    public function __construct(string $apiUrl, string $usuario, string $contrasena)
    {
        $this->base       = $this->normalizarBase($apiUrl);
        $this->usuario    = $usuario;
        $this->contrasena = $contrasena;
    }

    /** Deja la URL como scheme://host(/subruta), sin /main.php ni /index.php. */
    private function normalizarBase(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            throw new RuntimeException('La plataforma no tiene api_url configurada.');
        }
        if (!preg_match('~^https?://~i', $url)) {
            $url = 'https://' . $url;
        }
        // Quitar el archivo final (main.php / index.php) y la barra sobrante.
        $url = preg_replace('~/(main|index)\.php.*$~i', '', $url);
        return rtrim($url, '/');
    }

    public function obtenerPosiciones(): array
    {
        return $this->normalizar($this->traerTracking());
    }

    public function listarDispositivos(): array
    {
        $out = [];
        foreach ($this->traerTracking() as $v) {
            $out[] = [
                'dispositivo' => trim((string)($v['Vehicle_Name'] ?? '')),
                'imei'        => trim((string)($v['Vehicle_Imei'] ?? '')),
            ];
        }
        return $out;
    }

    /** Login + GetInfo.php → arreglo TRACKING crudo (base de posiciones y listado). */
    private function traerTracking(): array
    {
        $this->iniciarCurl();
        try {
            $this->login();
            $json = $this->get($this->base . '/GetInfo.php');
            $data = json_decode($json, true);

            if (!is_array($data) || !isset($data['TRACKING'])) {
                throw new RuntimeException('Respuesta inesperada de GetInfo.php (¿sesión no válida?).');
            }
            return $data['TRACKING'];
        } finally {
            if ($this->ch) { curl_close($this->ch); $this->ch = null; }
        }
    }

    // ── Autenticación ───────────────────────────────────────────
    private function login(): void
    {
        // 1. Página de login → cookie de sesión + token CSRF atado a esa sesión.
        $html = $this->get($this->base . '/main.php');
        if (!preg_match("/CSRF_TOKEN\\s*=\\s*'([a-f0-9]+)'/i", $html, $m)) {
            throw new RuntimeException('No se encontró el token CSRF en la página de login.');
        }
        $csrf = $m[1];

        // 2. POST de credenciales. Éxito = regeneración de sesión y redirección
        //    a la app; lo confirmamos luego al pedir GetInfo.php.
        $this->post($this->base . '/index.php', [
            'username'   => $this->usuario,
            'password'   => $this->contrasena,
            'csrf_token' => $csrf,
        ]);
    }

    // ── Normalización ───────────────────────────────────────────
    private function normalizar(array $tracking): array
    {
        $out = [];
        foreach ($tracking as $v) {
            $lat = isset($v['Vehicle_Latitude'])  ? (float)$v['Vehicle_Latitude']  : null;
            $lng = isset($v['Vehicle_Longitude']) ? (float)$v['Vehicle_Longitude'] : null;
            if ($lat === null || $lng === null) continue; // sin coordenadas, no va al mapa

            $out[] = [
                'dispositivo' => trim((string)($v['Vehicle_Name'] ?? '')),
                'imei'        => trim((string)($v['Vehicle_Imei'] ?? '')),
                'lat'         => $lat,
                'lng'         => $lng,
                'velocidad'   => (int)($v['Vehicle_Speed']  ?? 0),
                'rumbo'       => (int)($v['Vehicle_Course'] ?? 0),
                'encendido'   => isset($v['Vehicle_Ignition']) ? (int)$v['Vehicle_Ignition'] : null,
                'fecha'       => $this->fecha($v['Vehicle_Datetime'] ?? null),
                'direccion'   => isset($v['Vehicle_Location']) ? trim((string)$v['Vehicle_Location']) : null,
            ];
        }
        return $out;
    }

    /** 'YYYYMMDDHHMMSS' (hora local del servidor) → 'Y-m-d H:i:s'. */
    private function fecha($raw): ?string
    {
        $raw = (string)$raw;
        if (!preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})$/', $raw, $m)) {
            return null;
        }
        return "$m[1]-$m[2]-$m[3] $m[4]:$m[5]:$m[6]";
    }

    // ── cURL con cookies en memoria (una sesión por adaptador) ───
    private function iniciarCurl(): void
    {
        $this->ch = curl_init();
        curl_setopt_array($this->ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIEFILE     => '',   // habilita el manejo de cookies en memoria
            CURLOPT_USERAGENT      => self::UA,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_CONNECTTIMEOUT => 12,
            // Algunas instalaciones tienen cadenas de certificados incompletas;
            // el dato es de bajo riesgo (posición de flota propia) y el objetivo
            // es el host de la propia cuenta, no un tercero.
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
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'X-Requested-With: XMLHttpRequest',
        ]);
        return $this->ejecutar($url);
    }

    private function ejecutar(string $url): string
    {
        $resp = curl_exec($this->ch);
        if ($resp === false) {
            throw new RuntimeException('Fallo de conexión con ' . $url . ': ' . curl_error($this->ch));
        }
        $code = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        if ($code >= 400) {
            throw new RuntimeException("La plataforma respondió HTTP $code al pedir $url.");
        }
        return (string)$resp;
    }
}
