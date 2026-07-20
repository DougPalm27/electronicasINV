<?php
/**
 * Adaptador para el motor Optimus (optimushn.com).
 *
 * Optimus es una SPA React sobre un backend LoopBack. A diferencia de
 * gps-server, las POSICIONES en vivo se transmiten por WebSocket (RabbitMQ/STOMP),
 * no por REST — eso requiere un worker de fondo aparte (fase siguiente).
 *
 * Lo que SÍ es REST simple y ya sirve para el selector "Agregar vehículos":
 *  - Login:  POST {base}/v2/auth/login  {username|email, password} → accessToken (JWT) + clientId
 *  - Equipos: GET {base}/Devices/?filter={where:{clientId}}  (Authorization: <token> SIN "Bearer")
 *             En Optimus, `description` del equipo = la placa.
 */
require_once __DIR__ . '/GpsAdapterInterface.php';

class OptimusAdapter implements GpsAdapterInterface
{
    private string $base;
    private string $usuario;
    private string $contrasena;
    private ?string $token = null;
    private ?int $clientId = null;

    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
                     . '(KHTML, like Gecko) Chrome/126.0 Safari/537.36';

    public function __construct(string $apiUrl, string $usuario, string $contrasena)
    {
        $base = trim($apiUrl);
        if ($base === '') throw new RuntimeException('La plataforma Optimus no tiene api_url configurada.');
        if (!preg_match('~^https?://~i', $base)) $base = 'https://' . $base;
        $this->base       = rtrim($base, '/');
        $this->usuario    = $usuario;
        $this->contrasena = $contrasena;
    }

    /** Las posiciones en vivo de Optimus llegan por WebSocket → aún no por REST. */
    public function obtenerPosiciones(): array
    {
        throw new RuntimeException(
            'Optimus entrega posiciones por WebSocket (RabbitMQ); requiere el worker de fondo (pendiente).'
        );
    }

    public function listarDispositivos(): array
    {
        $this->login();
        $filter = rawurlencode(json_encode([
            'where'  => ['clientId' => $this->clientId],
            'fields' => ['id' => true, 'description' => true, 'imei' => true, 'active' => true],
            'order'  => 'description ASC',
        ]));
        $json = $this->http('GET', $this->base . '/Devices/?filter=' . $filter);
        $devs = json_decode($json, true);
        if (!is_array($devs)) {
            throw new RuntimeException('Respuesta inesperada al listar dispositivos de Optimus.');
        }

        $out = [];
        foreach ($devs as $d) {
            $out[] = [
                'dispositivo' => trim((string)($d['description'] ?? '')),
                'imei'        => trim((string)($d['imei'] ?? '')),
            ];
        }
        return $out;
    }

    // ── Autenticación LoopBack ──────────────────────────────────
    private function login(): void
    {
        if ($this->token) return;

        $campo = strpos($this->usuario, '@') !== false ? 'email' : 'username';
        $resp  = $this->http('POST', $this->base . '/v2/auth/login', [
            $campo     => $this->usuario,
            'password' => $this->contrasena,
        ]);
        $data = json_decode($resp, true);

        if (!isset($data['accessToken'])) {
            throw new RuntimeException('Login de Optimus fallido (credenciales inválidas o respuesta inesperada).');
        }
        $this->token    = $data['accessToken'];
        $this->clientId = $data['account']['clientId'] ?? null;
        if ($this->clientId === null) {
            throw new RuntimeException('El login de Optimus no devolvió clientId.');
        }
    }

    // ── HTTP JSON ───────────────────────────────────────────────
    private function http(string $metodo, string $url, ?array $body = null): string
    {
        $ch = curl_init($url);
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        if ($this->token) $headers[] = 'Authorization: ' . $this->token; // LoopBack: token plano, sin "Bearer"

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $metodo,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERAGENT      => self::UA,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false) throw new RuntimeException("Fallo de conexión con Optimus: $err");
        if ($code >= 400)   throw new RuntimeException("Optimus respondió HTTP $code en $url.");
        return (string)$resp;
    }
}
