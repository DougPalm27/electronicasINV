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
