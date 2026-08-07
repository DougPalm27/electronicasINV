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
    private string $ultimoError = '';

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
            $out[] = ['dispositivo' => $this->nombre($d), 'imei' => $this->imei($d)];
        }
        return $out;
    }

    /**
     * Para el worker refrescador: renueva el token si tiene más de $maxEdadSeg.
     * Devuelve 'vigente' (no hizo falta) o 'renovado' (login headless nuevo).
     * Mantener $maxEdadSeg < VIGENCIA para que la web siempre encuentre caché.
     */
    public function renovarToken(int $maxEdadSeg = 2700): string
    {
        $row = $this->store->leer($this->usuario);
        if ($row && !empty($row['token']) && !empty($row['query_body']) && (int)$row['edad'] < $maxEdadSeg) {
            return 'vigente';
        }
        $this->login();
        return 'renovado';
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

    /**
     * Corta antes de abrir Chrome si la cuenta está bloqueada por credenciales
     * o en espera por fallos técnicos. Un login que se sabe que va a fallar
     * cuesta minutos de CPU y llena el disco de perfiles temporales.
     */
    private function verificarPuedeIntentar(): void
    {
        $st = $this->store->estadoLogin($this->usuario);
        if ($st['bloqueada']) {
            throw new RuntimeException(
                "TrackSolid: cuenta \"{$this->usuario}\" bloqueada tras {$st['fallos']} intentos con credenciales inválidas. " .
                "Actualiza la contraseña en Cuentas GPS y se reactiva sola."
            );
        }
        if ($st['espera'] > 0) {
            throw new RuntimeException(
                "TrackSolid: login en espera por fallos previos (reintenta en {$st['espera']} min)."
            );
        }
    }

    private function login(): void
    {
        $this->verificarPuedeIntentar();   // no abrir Chrome si se sabe que va a fallar
        try {
            $r = $this->loginHeadless();
            if (empty($r['token']) || empty($r['queryBody'])) {
                throw new RuntimeException('TrackSolid: el login headless no devolvió token/queryBody (revisa usuario/clave).');
            }
        } catch (Throwable $e) {
            $this->store->registrarFallo($this->usuario, $e->getMessage());
            throw $e;
        }
        $this->token     = $r['token'];
        $this->queryBody = $r['queryBody'];
        $this->store->guardar($this->usuario, $r['token'], $r['queryBody'], $r['accountId'] ?? null);
        $this->store->registrarExito($this->usuario);
    }

    /** Ejecuta el helper Node (Chrome headless) con las credenciales por entorno. */
    private function loginHeadless(): array
    {
        $script = realpath(__DIR__ . '/../worker/nodehelper/login.js');
        if (!$script) throw new RuntimeException('No se encontró el helper Node (login.js).');
        $node = (string) env('NODE_BIN', 'node');
        $timeout = max(30, (int) env('TRACKSOLID_LOGIN_TIMEOUT', 150));

        // Carpeta de perfil propia de esta corrida: si hay que matar el proceso,
        // PHP la borra aquí mismo (Node ya no alcanza a limpiarla y llenaría el disco).
        $perfil = rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'ts-chrome-profile';
        $env = array_merge(getenv(), [
            'TS_USER' => $this->usuario,
            'TS_PWD'  => $this->pwd,
            'CHROME_USER_DATA' => $perfil,
        ]);
        $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open([$node, $script], $desc, $pipes, dirname($script), $env);
        if (!is_resource($proc)) throw new RuntimeException('No se pudo iniciar Node para el login de TrackSolid.');

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = '';
        $err = '';
        $inicio = time();
        while (true) {
            $out .= stream_get_contents($pipes[1]);
            $err .= stream_get_contents($pipes[2]);
            $st = proc_get_status($proc);
            if (!$st['running']) break;
            if ((time() - $inicio) > $timeout) {
                proc_terminate($proc);
                fclose($pipes[1]); fclose($pipes[2]);
                proc_close($proc);
                // Node murio sin limpiar: borrar sus perfiles para no llenar el disco
                $this->limpiarPerfiles($perfil, 0);
                throw new RuntimeException("TrackSolid: login headless excedio {$timeout}s. Revisa Chrome/TrackSolid o captcha/2FA.");
            }
            usleep(100000);
        }
        $out .= stream_get_contents($pipes[1]);
        $err .= stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        proc_close($proc);

        $this->limpiarPerfiles($perfil, 30);

        $data = json_decode(trim($out), true);
        if (!is_array($data) || empty($data['token'])) {
            throw new RuntimeException('TrackSolid: login headless falló. ' . trim($err ?: $out));
        }
        return $data;
    }

    /**
     * Borra los perfiles de Chrome que dejó el helper Node.
     * $maxEdadMin = 0 borra todos; >0 respeta los recién creados (pueden estar en uso).
     */
    private function limpiarPerfiles(string $raiz, int $maxEdadMin): void
    {
        if (!is_dir($raiz)) return;
        $limite = time() - ($maxEdadMin * 60);
        foreach ((glob($raiz . DIRECTORY_SEPARATOR . 'run-*') ?: []) as $dir) {
            if (!is_dir($dir)) continue;
            if ($maxEdadMin > 0 && @filemtime($dir) > $limite) continue;
            $this->borrarRecursivo($dir);
        }
    }

    private function borrarRecursivo(string $ruta): void
    {
        if (!is_dir($ruta)) { @unlink($ruta); return; }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($ruta, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
        @rmdir($ruta);
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
        if ($res === null) {
            throw new RuntimeException('TrackSolid: no se pudo leer la lista de equipos. ' . $this->ultimoError);
        }
        return $res;
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
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp === false) throw new RuntimeException("TrackSolid: fallo de conexión ($err).");
        if ($http >= 400) {
            $this->ultimoError = "HTTP $http";
            return null;
        }

        $data = json_decode($resp, true);
        if (!is_array($data)) {
            $this->ultimoError = 'Respuesta no JSON.';
            return null;
        }

        $lista = $this->extraerLista($data);
        if ($lista !== null) {
            return $lista;
        }

        $msg = $data['msg'] ?? $data['message'] ?? $data['error'] ?? $data['code'] ?? '';
        $this->ultimoError = $msg ? ('Respuesta inesperada: ' . $msg) : 'Respuesta inesperada de queryEquipmentList.';
        return null;
    }

    private function extraerLista(array $data): ?array
    {
        if ($this->pareceListaEquipos($data)) return $data;
        foreach (['data', 'list', 'rows', 'records', 'result'] as $k) {
            if (!array_key_exists($k, $data) || !is_array($data[$k])) continue;
            if ($this->pareceListaEquipos($data[$k])) return $data[$k];
            $nested = $this->extraerLista($data[$k]);
            if ($nested !== null) return $nested;
        }
        return null;
    }

    private function pareceListaEquipos(array $v): bool
    {
        if ($v === []) return true;
        $first = reset($v);
        return is_array($first) && (
            isset($first['imei']) || isset($first['deviceName']) || isset($first['deviceId'])
        );
    }

    // ── Normalización ───────────────────────────────────────────
    private function normalizar(array $lista): array
    {
        $out = [];
        foreach ($lista as $d) {
            $lat = $this->campoFloat($d, ['lat', 'latitude', 'gpsLat', 'mapLat', 'bdLat', 'latGps']);
            $lng = $this->campoFloat($d, ['lng', 'lon', 'longitude', 'gpsLng', 'mapLng', 'bdLng', 'lngGps']);
            if ($lat === null || $lng === null) continue;
            $out[] = [
                'dispositivo' => $this->nombre($d),
                'imei'        => $this->imei($d),
                'lat'         => $lat,
                'lng'         => $lng,
                'velocidad'   => (int)round($this->campoFloat($d, ['speed', 'velocity', 'gpsSpeed']) ?? 0),
                'rumbo'       => (int)round($this->campoFloat($d, ['direction', 'course', 'angle', 'bearing']) ?? 0),
                'encendido'   => $this->encendido($d),
                'fecha'       => $this->fecha($d),
                'direccion'   => $this->direccion($d),
            ];
        }
        return $out;
    }

    private function campoFloat(array $d, array $keys): ?float
    {
        foreach ($keys as $k) {
            if (!array_key_exists($k, $d) || $d[$k] === null || $d[$k] === '') continue;
            $v = str_replace(',', '.', (string)$d[$k]);
            if (is_numeric($v)) return (float)$v;
        }
        return null;
    }

    private function encendido(array $d): ?int
    {
        foreach (['acc', 'accStatus', 'ignition', 'engine'] as $k) {
            if (!array_key_exists($k, $d) || $d[$k] === null || $d[$k] === '') continue;
            $v = strtolower((string)$d[$k]);
            if (is_numeric($v)) return ((int)$v) ? 1 : 0;
            if (in_array($v, ['true', 'on', 'open', 'encendido'], true)) return 1;
            if (in_array($v, ['false', 'off', 'close', 'apagado'], true)) return 0;
        }
        return null;
    }

    private function fecha(array $d): ?string
    {
        foreach (['gpsTime', 'otherPosTime', 'posTime', 'lastTime', 'updateTime'] as $k) {
            if (empty($d[$k])) continue;
            $v = $d[$k];
            if (is_numeric($v)) {
                $n = (int)$v;
                if ($n > 9999999999) $n = (int)floor($n / 1000);
                return date('Y-m-d H:i:s', $n);
            }
            return (string)$v;
        }
        return null;
    }

    private function direccion(array $d): ?string
    {
        $keys = [
            'address', 'addr', 'location', 'position', 'gpsAddress', 'addressDesc',
            'detailAddress', 'formattedAddress', 'locationName', 'locationDesc',
            'currentAddress', 'lastAddress', 'mapAddress', 'poi'
        ];
        foreach ($keys as $k) {
            if (!empty($d[$k]) && is_scalar($d[$k])) {
                $txt = trim(preg_replace('/\s+/', ' ', (string)$d[$k]));
                if ($txt !== '') return $txt;
            }
        }
        foreach (['gps', 'positionInfo', 'locationInfo', 'lastPosition', 'position'] as $parent) {
            if (!empty($d[$parent]) && is_array($d[$parent])) {
                $txt = $this->direccion($d[$parent]);
                if ($txt) return $txt;
            }
        }
        return null;
    }

    /** deviceName suele venir "PLACA \n alias" → una línea limpia. */
    private function nombre(array $d): string
    {
        foreach (['deviceName', 'name', 'terminalName', 'carNumber', 'plateNumber', 'plateNo'] as $k) {
            if (!empty($d[$k])) return trim(preg_replace('/\s+/', ' ', (string)$d[$k]));
        }
        return $this->imei($d);
    }

    private function imei(array $d): string
    {
        foreach (['imei', 'imeiNo', 'deviceImei', 'terminalNo', 'deviceNo', 'sn'] as $k) {
            if (!empty($d[$k])) return trim((string)$d[$k]);
        }
        return '';
    }
}
