<?php
/**
 * Adaptador para skytekgps (www.skytekgps1.com) — plataforma PHP/IIS propia.
 *
 * Login por sesión y una lectura que devuelve una TABLA HTML (no JSON):
 *   1. GET  {base}/AWEBSITE/index.php     → cookie de sesión
 *   2. POST {base}/index.php              → txtUsuario/txtClave (autentica → localizar.php)
 *   3. GET  {base}/tabla_localizar.php    → <table> con filas por unidad:
 *        [ ID, Alias, Ubicacion(dir), GPS Time, Lat, Lng, Velocidad, Bateria, Rumbo(NE/SO...), Km ]
 *
 * No hay IMEI: se empareja por placa (último token del alias).
 * base = https://www.skytekgps1.com
 */
require_once __DIR__ . '/GpsAdapterInterface.php';

class SkytekAdapter implements GpsAdapterInterface
{
    private string $base;
    private string $usuario;
    private string $pwd;
    /** @var resource|\CurlHandle */
    private $ch;

    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
                     . '(KHTML, like Gecko) Chrome/126.0 Safari/537.36';
    private const CARD = ['N'=>0,'NE'=>45,'E'=>90,' E'=>90,'SE'=>135,'S'=>180,'SO'=>225,'O'=>270,'NO'=>315];

    public function __construct(string $apiUrl, string $usuario, string $contrasena)
    {
        $base = trim($apiUrl);
        if ($base === '') throw new RuntimeException('skytek: falta api_url.');
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
        foreach ($this->traer() as $f) {
            $out[] = ['dispositivo' => $this->placa($f['alias']), 'imei' => ''];
        }
        return $out;
    }

    // ── Login + lectura de la tabla ─────────────────────────────
    private function traer(): array
    {
        $this->iniciarCurl();
        try {
            $this->get($this->base . '/AWEBSITE/index.php');    // cookie
            $this->post($this->base . '/index.php', ['txtUsuario' => $this->usuario, 'txtClave' => $this->pwd]);
            $html = $this->get($this->base . '/tabla_localizar.php');
            return $this->parsearTabla($html);
        } finally {
            if ($this->ch) { curl_close($this->ch); $this->ch = null; }
        }
    }

    /** Extrae las filas de la tabla HTML → arreglos de celdas. */
    private function parsearTabla(string $html): array
    {
        if (stripos($html, '<td') === false) {
            throw new RuntimeException('skytek: sin datos (¿usuario/contraseña inválidos?).');
        }
        $html = preg_replace('/^\xEF\xBB\xBF/', '', $html);
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        $xp = new DOMXPath($doc);

        $filas = [];
        foreach ($xp->query('//tr[td]') as $tr) {
            $c = [];
            foreach ($xp->query('td', $tr) as $td) $c[] = trim($td->textContent);
            if (count($c) < 10) continue;
            $filas[] = [
                'alias'     => $c[1],
                'ubicacion' => $c[2],
                'fecha'     => $c[3],
                'lat'       => $c[4],
                'lng'       => $c[5],
                'velocidad' => $c[6],
                'rumbo'     => $c[8],
            ];
        }
        return $filas;
    }

    private function normalizar(array $filas): array
    {
        $out = [];
        foreach ($filas as $f) {
            $lat = (float)$f['lat']; $lng = (float)$f['lng'];
            if ($lat == 0.0 && $lng == 0.0) continue;
            $ub = trim($f['ubicacion']);
            $out[] = [
                'dispositivo' => $this->placa($f['alias']),
                'imei'        => '',
                'lat'         => $lat,
                'lng'         => $lng,
                'velocidad'   => (int)round((float)$f['velocidad']),
                'rumbo'       => self::CARD[strtoupper(trim($f['rumbo']))] ?? 0,
                'encendido'   => null,   // skytek no reporta ignición
                'fecha'       => $this->fecha($f['fecha']),
                'direccion'   => (stripos($ub, 'Lat:') === 0 || $ub === '') ? null : $ub,
            ];
        }
        return $out;
    }

    /** "JC FREIGH TCH2720" → "TCH2720" (último token). */
    private function placa(string $alias): string
    {
        $p = preg_split('/\s+/', trim($alias));
        return strtoupper(end($p) ?: trim($alias));
    }

    /** "Jul 19 2026  4:09PM" → "Y-m-d H:i:s". */
    private function fecha(string $s): ?string
    {
        $ts = strtotime(trim($s));
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
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
        if ($resp === false) throw new RuntimeException('skytek: fallo de conexión con ' . $url . ': ' . curl_error($this->ch));
        return (string)$resp;
    }
}
