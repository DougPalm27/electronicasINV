<?php
/**
 * Caché de tokens de TrackSolid (tabla gps.CuentaTokens), por usuario.
 *
 * Degrada con elegancia: si la tabla aún no existe (migración sin correr), las
 * operaciones no fallan — simplemente no hay caché y el adaptador hará login
 * fresco cada vez. Cuando la tabla exista, el token se reutiliza.
 */
class TrackSolidTokenStore
{
    private $conn;

    public function __construct()
    {
        require_once __DIR__ . '/../../../../config/Connection.php';
        $this->conn = (new Connection())->dbConnect();
    }

    /** Devuelve ['token','query_body','account_id','edad'] o null. */
    public function leer(string $usuario): ?array
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT token, query_body, account_id,
                        DATEDIFF(second, fecha_token, GETDATE()) AS edad
                 FROM gps.CuentaTokens WHERE usuario = ?"
            );
            $stmt->execute([$usuario]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            return $r ?: null;
        } catch (Throwable $e) {
            return null; // tabla ausente / error → sin caché
        }
    }

    /** Login exitoso: borra el historial de fallos. Va aparte de guardar() para
     *  que un token se siga cacheando aunque la migración de backoff no se haya corrido. */
    public function registrarExito(string $usuario): void
    {
        try {
            $stmt = $this->conn->prepare(
                "UPDATE gps.CuentaTokens
                    SET intentos_fallidos = 0, fecha_error = NULL, ultimo_error = NULL
                  WHERE usuario = ?"
            );
            $stmt->execute([$usuario]);
        } catch (Throwable $e) { /* migración pendiente: no bloquea */ }
    }

    /**
     * Registra un login fallido para espaciar los reintentos.
     * Sin esto, una cuenta con clave vencida se reintenta cada 30 min para siempre
     * y cada intento deja basura en el disco del servidor.
     */
    public function registrarFallo(string $usuario, string $error): void
    {
        $tipo = self::clasificarError($error);
        try {
            $stmt = $this->conn->prepare(
                "MERGE gps.CuentaTokens AS t
                 USING (SELECT ? AS usuario) AS s ON t.usuario = s.usuario
                 WHEN MATCHED THEN UPDATE SET intentos_fallidos = ISNULL(t.intentos_fallidos, 0) + 1,
                                             fecha_error = GETDATE(), ultimo_error = ?, tipo_error = ?
                 WHEN NOT MATCHED THEN INSERT (usuario, intentos_fallidos, fecha_error, ultimo_error, tipo_error)
                    VALUES (?, 1, GETDATE(), ?, ?);"
            );
            $err = mb_substr($error, 0, 300);
            $stmt->execute([$usuario, $err, $tipo, $usuario, $err, $tipo]);
        } catch (Throwable $e) {
            // Sin la columna tipo_error (migración pendiente): guardar sin ella
            try {
                $stmt = $this->conn->prepare(
                    "MERGE gps.CuentaTokens AS t
                     USING (SELECT ? AS usuario) AS s ON t.usuario = s.usuario
                     WHEN MATCHED THEN UPDATE SET intentos_fallidos = ISNULL(t.intentos_fallidos, 0) + 1,
                                                 fecha_error = GETDATE(), ultimo_error = ?
                     WHEN NOT MATCHED THEN INSERT (usuario, intentos_fallidos, fecha_error, ultimo_error)
                        VALUES (?, 1, GETDATE(), ?);"
                );
                $err = mb_substr($error, 0, 300);
                $stmt->execute([$usuario, $err, $usuario, $err]);
            } catch (Throwable $e2) { /* tabla ausente: no bloquea */ }
        }
    }

    /**
     * ¿El fallo fue por credenciales o por un problema técnico?
     * Ante la duda devuelve 'tecnico': bloquear una cuenta buena por error
     * dejaría carros invisibles sin que nadie sepa por qué.
     */
    public static function clasificarError(string $error): string
    {
        $e = mb_strtolower($error);
        $tecnicos = ['timed out', 'timeout', 'excedio', 'no se encontro chrome', 'no se pudo iniciar node',
                     'ws endpoint', 'econnrefused', 'enotfound', 'net::', 'socket', 'protocol error',
                     'target closed', 'session closed', 'navigation'];
        foreach ($tecnicos as $t) if (strpos($e, $t) !== false) return 'tecnico';

        $credenciales = ['no se obtuvo token despues de enviar el login', 'no devolvió token',
                         'no devolvio token', 'revisa usuario/clave', 'captcha', 'contraseña', 'password'];
        foreach ($credenciales as $c) if (strpos($e, $c) !== false) return 'credenciales';

        return 'tecnico';
    }

    /** Fallos de credenciales seguidos tras los cuales se deja de intentar. */
    public const MAX_FALLOS_CREDENCIALES = 4;

    /**
     * Estado del login de una cuenta:
     *   ['bloqueada' => bool, 'espera' => minutos, 'fallos' => int, 'motivo' => string]
     *
     * bloqueada = la clave es inválida y ya no se intenta más (hasta corregirla).
     * espera    = fallo técnico: se reintenta, pero cada vez más espaciado.
     */
    public function estadoLogin(string $usuario): array
    {
        $libre = ['bloqueada' => false, 'espera' => 0, 'fallos' => 0, 'motivo' => ''];
        try {
            $stmt = $this->conn->prepare(
                "SELECT ISNULL(intentos_fallidos, 0) AS fallos,
                        DATEDIFF(minute, fecha_error, GETDATE()) AS min_desde,
                        ISNULL(ultimo_error, '') AS ultimo_error,
                        ISNULL(tipo_error, 'tecnico') AS tipo_error
                 FROM gps.CuentaTokens WHERE usuario = ? AND fecha_error IS NOT NULL"
            );
            $stmt->execute([$usuario]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return $libre;   // migración pendiente: no estorbar
        }
        if (!$r) return $libre;

        $fallos = (int)$r['fallos'];
        if ($r['tipo_error'] === 'credenciales' && $fallos >= self::MAX_FALLOS_CREDENCIALES) {
            return ['bloqueada' => true, 'espera' => 0, 'fallos' => $fallos, 'motivo' => $r['ultimo_error']];
        }
        if ($fallos < 2) return $libre;   // el primer fallo se reintenta normal
        $espera = min(720, 30 * (2 ** min(8, $fallos - 1)));
        return [
            'bloqueada' => false,
            'espera'    => max(0, $espera - (int)$r['min_desde']),
            'fallos'    => $fallos,
            'motivo'    => $r['ultimo_error'],
        ];
    }

    /** Compatibilidad: minutos que faltan para reintentar (0 = ya se puede). */
    public function esperaReintento(string $usuario): int
    {
        return $this->estadoLogin($usuario)['espera'];
    }

    /**
     * Borra el historial de fallos de una cuenta (se corrigió la contraseña).
     * También limpia el token viejo para forzar un login fresco.
     */
    public function reiniciarCuenta(string $usuario): void
    {
        try {
            $stmt = $this->conn->prepare("DELETE FROM gps.CuentaTokens WHERE usuario = ?");
            $stmt->execute([$usuario]);
        } catch (Throwable $e) { /* tabla ausente: no bloquea */ }
    }

    /** Cuentas que dejaron de intentarse por credenciales inválidas. */
    public function bloqueadas(): array
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT usuario, intentos_fallidos, ultimo_error,
                        CONVERT(varchar(19), fecha_error, 120) AS fecha_error
                 FROM gps.CuentaTokens
                 WHERE tipo_error = 'credenciales' AND ISNULL(intentos_fallidos,0) >= ?
                 ORDER BY usuario"
            );
            $stmt->execute([self::MAX_FALLOS_CREDENCIALES]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    public function guardar(string $usuario, string $token, ?string $queryBody, ?string $accountId): void
    {
        try {
            $stmt = $this->conn->prepare(
                "MERGE gps.CuentaTokens AS t
                 USING (SELECT ? AS usuario) AS s ON t.usuario = s.usuario
                 WHEN MATCHED THEN UPDATE SET token = ?, query_body = ?, account_id = ?, fecha_token = GETDATE()
                 WHEN NOT MATCHED THEN INSERT (usuario, token, query_body, account_id)
                    VALUES (?, ?, ?, ?);"
            );
            $stmt->execute([$usuario, $token, $queryBody, $accountId, $usuario, $token, $queryBody, $accountId]);
        } catch (Throwable $e) { /* sin caché, no bloquea */ }
    }
}
