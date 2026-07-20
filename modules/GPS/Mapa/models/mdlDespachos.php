<?php
/**
 * Modelo de Despachos — listas de seguimiento temporales.
 *
 * Un despacho agrupa los carros que salen en una operación. Se arma cuando se
 * necesita, se le suman/quitan carros, y se cierra (queda como historial).
 * El mapa gira en torno a los despachos ACTIVOS.
 *
 * Las posiciones viven en gps.Posiciones (clave id_cuenta + imei) y se cruzan
 * con los vehículos del despacho por esa misma clave.
 */
class mdlDespachos
{
    private $conn;

    public function __construct()
    {
        require_once '../../../../config/Connection.php';
        $this->conn = (new Connection())->dbConnect();
    }

    // ── CRUD de despachos ───────────────────────────────────────
    public function crear(string $nombre, int $uid): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO gps.Despachos (nombre, creado_por)
             OUTPUT INSERTED.id_despacho VALUES (?, ?)"
        );
        $stmt->execute([$nombre, $uid ?: null]);
        return (int)$stmt->fetchColumn();
    }

    public function cerrar(int $id_despacho): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE gps.Despachos SET estado = 'cerrado', fecha_cierre = GETDATE()
             WHERE id_despacho = ? AND estado = 'activo'"
        );
        $stmt->execute([$id_despacho]);
    }

    public function reabrir(int $id_despacho): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE gps.Despachos SET estado = 'activo', fecha_cierre = NULL WHERE id_despacho = ?"
        );
        $stmt->execute([$id_despacho]);
    }

    /** Despachos por estado ('activo' | 'cerrado'), con conteo de carros. */
    public function listar(string $estado = 'activo'): array
    {
        $stmt = $this->conn->prepare(
            "SELECT d.id_despacho, d.nombre, d.estado,
                    CONVERT(varchar(19), d.fecha_apertura, 120) AS fecha_apertura,
                    CONVERT(varchar(19), d.fecha_cierre,   120) AS fecha_cierre,
                    (SELECT COUNT(*) FROM gps.DespachoVehiculos dv
                     WHERE dv.id_despacho = d.id_despacho AND dv.activo = 1) AS carros
             FROM gps.Despachos d
             WHERE d.estado = ?
             ORDER BY d.fecha_apertura DESC"
        );
        $stmt->execute([$estado]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Vehículos del despacho ──────────────────────────────────
    /**
     * Agrega carros a un despacho. $items = [['id_cuenta','placa','imei','dispositivo'], ...]
     * Omite los que ya estén activos en ESE despacho (misma placa o imei en la cuenta).
     */
    public function agregarVehiculos(int $id_despacho, array $items, int $uid): array
    {
        $existe = $this->conn->prepare(
            "SELECT COUNT(*) FROM gps.DespachoVehiculos
             WHERE id_despacho = ? AND activo = 1 AND id_cuenta = ?
               AND (placa = ? OR (imei IS NOT NULL AND imei = ?))"
        );
        $insert = $this->conn->prepare(
            "INSERT INTO gps.DespachoVehiculos (id_despacho, id_cuenta, placa, imei, dispositivo, agregado_por)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $agregados = 0; $omitidos = 0;
        $this->conn->beginTransaction();
        try {
            foreach ($items as $it) {
                $id_cuenta = (int)($it['id_cuenta'] ?? 0);
                $placa = strtoupper(trim($it['placa'] ?? ''));
                $imei  = trim($it['imei'] ?? '');
                if (!$id_cuenta || $placa === '') { $omitidos++; continue; }

                $existe->execute([$id_despacho, $id_cuenta, $placa, $imei]);
                if ((int)$existe->fetchColumn() > 0) { $omitidos++; continue; }

                $insert->execute([$id_despacho, $id_cuenta, $placa, $imei ?: null,
                                  trim($it['dispositivo'] ?? '') ?: null, $uid ?: null]);
                $agregados++;
            }
            $this->conn->commit();
        } catch (Throwable $e) { $this->conn->rollBack(); throw $e; }
        return ['agregados' => $agregados, 'omitidos' => $omitidos];
    }

    /** Quita un carro del despacho (deja de seguirlo). No borra: historial. */
    public function quitarVehiculo(int $id_dv): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE gps.DespachoVehiculos SET activo = 0, fecha_removido = GETDATE()
             WHERE id_dv = ? AND activo = 1"
        );
        $stmt->execute([$id_dv]);
    }

    /** Placas/imeis ya activos en un despacho (para marcar en el selector). */
    public function vinculadosDeDespacho(int $id_despacho): array
    {
        $stmt = $this->conn->prepare(
            "SELECT placa, imei, id_cuenta FROM gps.DespachoVehiculos
             WHERE id_despacho = ? AND activo = 1"
        );
        $stmt->execute([$id_despacho]);
        $placas = []; $imeis = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $placas[$r['id_cuenta'] . '|' . strtoupper(trim($r['placa']))] = true;
            if (!empty($r['imei'])) $imeis[$r['id_cuenta'] . '|' . trim($r['imei'])] = true;
        }
        return ['placas' => $placas, 'imeis' => $imeis];
    }

    /**
     * Vehículos (activos) de uno o todos los despachos activos, con su última
     * posición en caché. Si $id_despacho es null → todos los despachos activos.
     */
    public function vehiculos(?int $id_despacho): array
    {
        $where = "d.estado = 'activo' AND dv.activo = 1";
        $params = [];
        if ($id_despacho !== null) { $where = "dv.id_despacho = ? AND dv.activo = 1"; $params = [$id_despacho]; }

        $stmt = $this->conn->prepare(
            "SELECT dv.id_dv, dv.id_despacho, d.nombre AS despacho,
                    dv.placa, dv.imei, dv.id_cuenta, dv.dispositivo,
                    p.nombre AS plataforma, p.tipo_integracion, t.nombre AS transporte,
                    CAST(pos.lat AS FLOAT) AS lat, CAST(pos.lng AS FLOAT) AS lng,
                    pos.velocidad, pos.rumbo, pos.encendido, pos.direccion,
                    CONVERT(varchar(19), pos.fecha_posicion, 120) AS fecha
             FROM gps.DespachoVehiculos dv
             INNER JOIN gps.Despachos    d ON d.id_despacho  = dv.id_despacho
             INNER JOIN gps.CuentasGPS   c ON c.id_cuenta    = dv.id_cuenta
             INNER JOIN gps.Plataformas  p ON p.id_plataforma = c.id_plataforma
             INNER JOIN gps.Transportes  t ON t.id_transporte = c.id_transporte
             LEFT  JOIN gps.Posiciones pos ON pos.id_cuenta = dv.id_cuenta AND pos.imei = dv.imei
             WHERE $where
             ORDER BY dv.placa"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Para el worker: imeis de Optimus en despachos activos. imei => [id_cuenta,placa,dispositivo]. */
    public function imeisOptimusActivos(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT DISTINCT dv.imei, dv.id_cuenta, dv.placa, dv.dispositivo
             FROM gps.DespachoVehiculos dv
             INNER JOIN gps.Despachos   d ON d.id_despacho  = dv.id_despacho AND d.estado = 'activo'
             INNER JOIN gps.CuentasGPS  c ON c.id_cuenta    = dv.id_cuenta
             INNER JOIN gps.Plataformas p ON p.id_plataforma = c.id_plataforma
             WHERE dv.activo = 1 AND p.tipo_integracion = 'optimus'
               AND dv.imei IS NOT NULL AND dv.imei <> ''"
        );
        $stmt->execute();
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[trim($r['imei'])] = [
                'id_cuenta' => (int)$r['id_cuenta'],
                'placa'     => $r['placa'],
            ];
        }
        return $map;
    }
}
