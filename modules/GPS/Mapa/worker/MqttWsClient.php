<?php
/**
 * Cliente MQTT 3.1.1 sobre WebSocket, en PHP puro (sin dependencias).
 *
 * Pensado para el worker de Optimus, cuyo motor (CloudAMQP Web-MQTT) entrega las
 * posiciones por `wss://host:443/ws/mqtt`. Hace lo mismo que el navegador:
 * handshake WebSocket + CONNECT/SUBSCRIBE, y entrega cada PUBLISH a un callback.
 *
 * Uso:
 *   $c = new MqttWsClient('brisk-...cloudamqp.com', 443, '/ws/mqtt', $user, $pass);
 *   $c->conectar();
 *   $c->suscribir(['868...018/production', ...]);
 *   $c->escuchar(function($topic, $payload) { ... }, 20); // segundos por ciclo
 */
class MqttWsClient
{
    private string $host;
    private int    $port;
    private string $path;
    private string $user;
    private string $pass;
    /** @var resource|null */
    private $fp = null;
    private int $pid = 0;
    private int $ultimoPing = 0;
    private int $keepalive = 30;

    public function __construct(string $host, int $port, string $path, string $user, string $pass)
    {
        $this->host = $host; $this->port = $port; $this->path = $path;
        $this->user = $user; $this->pass = $pass;
    }

    // ── Conexión: TLS + handshake WebSocket + MQTT CONNECT ──────
    public function conectar(): void
    {
        $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $fp  = @stream_socket_client("tls://{$this->host}:{$this->port}", $e, $s, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) throw new RuntimeException("No se pudo conectar al broker: $s");
        $this->fp = $fp;

        // Handshake WebSocket
        $key = base64_encode(random_bytes(16));
        fwrite($fp,
            "GET {$this->path} HTTP/1.1\r\nHost: {$this->host}\r\n" .
            "Upgrade: websocket\r\nConnection: Upgrade\r\n" .
            "Sec-WebSocket-Key: $key\r\nSec-WebSocket-Version: 13\r\n" .
            "Sec-WebSocket-Protocol: mqtt\r\n\r\n");
        $resp = '';
        while (($l = fgets($fp)) !== false) { $resp .= $l; if ($l === "\r\n") break; }
        if (strpos($resp, '101') === false) throw new RuntimeException('Handshake WebSocket rechazado.');

        // MQTT CONNECT (usuario+contraseña+clean session, keepalive)
        $cid = 'elec-gps-' . substr(md5(gethostname() . getmypid()), 0, 10);
        $vh  = "\x00\x04MQTT\x04" . chr(0xC2) . pack('n', $this->keepalive);
        $pl  = $this->str($cid) . $this->str($this->user) . $this->str($this->pass);
        $this->wsSend("\x10" . $this->mlen(strlen($vh . $pl)) . $vh . $pl);

        $r = $this->wsRead();
        if (!$r || ord($r[1][0]) >> 4 !== 2) throw new RuntimeException('Sin CONNACK del broker.');
        $rc = ord($r[1][3]);
        if ($rc !== 0) throw new RuntimeException("CONNECT rechazado por el broker (rc=$rc).");
        $this->ultimoPing = time();
    }

    /** Suscribe a una lista de topics (QoS 0). */
    public function suscribir(array $topics): void
    {
        foreach (array_chunk($topics, 40) as $lote) {
            $body = pack('n', ++$this->pid);
            foreach ($lote as $t) $body .= $this->str($t) . "\x00";
            $this->wsSend("\x82" . $this->mlen(strlen($body)) . $body);
            $this->wsRead(); // SUBACK
        }
    }

    /**
     * Escucha PUBLISH durante $segundos, entregando cada uno a $cb($topic,$payload).
     * Envía PINGREQ según keepalive. Lanza excepción si el socket se cae.
     */
    public function escuchar(callable $cb, int $segundos): void
    {
        $fin = time() + $segundos;
        while (time() < $fin) {
            if (time() - $this->ultimoPing >= $this->keepalive - 5) $this->ping();

            $r = $this->wsRead();
            if ($r === null) continue;              // timeout de lectura: seguir
            [$op, $data] = $r;
            if ($op === 0x8) throw new RuntimeException('El broker cerró la conexión.');
            if ($op === 0x9) { $this->wsSend($data, 0xA); continue; } // ping WS → pong
            if (strlen($data) < 2 || (ord($data[0]) >> 4) !== 3) continue; // solo PUBLISH

            $qos = (ord($data[0]) >> 1) & 3;
            $i = 1; $mult = 1; $rl = 0;
            do { $b = ord($data[$i++]); $rl += ($b & 127) * $mult; $mult *= 128; } while ($b & 128);
            $tl = (ord($data[$i]) << 8) | ord($data[$i + 1]); $i += 2;
            $topic = substr($data, $i, $tl); $i += $tl;
            if ($qos > 0) $i += 2;
            $payload = substr($data, $i);
            $cb($topic, $payload);
        }
    }

    public function cerrar(): void
    {
        if ($this->fp) { @fwrite($this->fp, "\xE0\x00"); @fclose($this->fp); $this->fp = null; } // DISCONNECT
    }

    private function ping(): void
    {
        $this->wsSend("\xC0\x00");
        $this->ultimoPing = time();
    }

    // ── WebSocket (cliente enmascara; servidor no) ──────────────
    private function wsSend(string $p, int $op = 0x2): void
    {
        $len = strlen($p);
        $f = chr(0x80 | $op);
        $mask = random_bytes(4);
        if ($len < 126)        $f .= chr(0x80 | $len);
        elseif ($len < 65536)  $f .= chr(0x80 | 126) . pack('n', $len);
        else                   $f .= chr(0x80 | 127) . pack('J', $len);
        $f .= $mask;
        $out = '';
        for ($i = 0; $i < $len; $i++) $out .= $p[$i] ^ $mask[$i % 4];
        fwrite($this->fp, $f . $out);
    }

    /** Lee un frame WebSocket. Devuelve [opcode, data] o null si no hay datos. */
    private function wsRead(): ?array
    {
        stream_set_timeout($this->fp, 2);
        $h = $this->readN(2);
        if (strlen($h) < 2) return null;
        $op  = ord($h[0]) & 0x0f;
        $b2  = ord($h[1]);
        $len = $b2 & 0x7f;
        $mk  = $b2 & 0x80;
        if ($len === 126)      $len = unpack('n', $this->readN(2))[1];
        elseif ($len === 127)  $len = unpack('J', $this->readN(8))[1];
        $mask = $mk ? $this->readN(4) : '';
        $data = $this->readN($len);
        if ($mk) { $u = ''; for ($i = 0; $i < strlen($data); $i++) $u .= $data[$i] ^ $mask[$i % 4]; $data = $u; }
        return [$op, $data];
    }

    private function readN(int $n): string
    {
        $b = ''; $t = time() + 6;
        while (strlen($b) < $n) {
            if (time() > $t) break;
            $c = fread($this->fp, $n - strlen($b));
            if ($c === false) break;
            if ($c === '') { $m = stream_get_meta_data($this->fp); if ($m['timed_out']) break; usleep(30000); continue; }
            $b .= $c;
        }
        return $b;
    }

    private function str(string $s): string { return pack('n', strlen($s)) . $s; }

    private function mlen(int $n): string
    {
        $s = '';
        do { $b = $n % 128; $n = intdiv($n, 128); if ($n > 0) $b |= 0x80; $s .= chr($b); } while ($n > 0);
        return $s;
    }
}
