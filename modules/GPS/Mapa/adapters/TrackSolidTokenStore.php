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
        } catch (Throwable $e) { /* migración pendiente: no bloquea */ }
    }

    /**
     * ¿Cuántos minutos faltan para poder reintentar? 0 = se puede ya.
     * La espera crece con los fallos seguidos: 30 min, 1 h, 2 h… hasta 12 h.
     */
    public function esperaReintento(string $usuario): int
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT ISNULL(intentos_fallidos, 0) AS fallos,
                        DATEDIFF(minute, fecha_error, GETDATE()) AS min_desde
                 FROM gps.CuentaTokens WHERE usuario = ? AND fecha_error IS NOT NULL"
            );
            $stmt->execute([$usuario]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$r || (int)$r['fallos'] < 2) return 0;   // el primer fallo se reintenta normal
            $espera = min(720, 30 * (2 ** min(8, (int)$r['fallos'] - 1)));
            return max(0, $espera - (int)$r['min_desde']);
        } catch (Throwable $e) {
            return 0;
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
