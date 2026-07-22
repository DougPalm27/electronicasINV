<?php
/**
 * Modelo del mapa GPS (curado).
 *
 * El mapa gira en torno a los vehículos que el usuario ya vinculó en gps.GPS
 * (su lista de rastreo). Este modelo:
 *  - entrega esa lista con las credenciales/motor de su cuenta,
 *  - alimenta el selector "Agregar vehículos" (listar equipos de una cuenta y vincularlos),
 *  - guarda/lee la caché de últimas posiciones.
 *
 * El emparejamiento posición↔vehículo se hace por IMEI (exacto entre motores) y,
 * como respaldo, por placa.
 */
class mdlMapa
{
    private $conn;

    public function __construct()
    {
        require_once '../../../../config/Connection.php';
        $this->conn = (new Connection())->dbConnect();
    }

    /** Lista de rastreo: vehículos activos en gps.GPS + datos de su cuenta/motor. */
    public function vehiculosVinculados(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT g.id_gps, g.placa, g.imei, g.id_cuenta,
                    c.usuario, c.contrasena,
                    p.nombre AS plataforma, p.tipo_integracion, p.api_url,
                    t.nombre AS transporte,
                    tv.nombre AS tipo_vehiculo, d.nombre AS destino
             FROM gps.GPS g
             INNER JOIN gps.CuentasGPS   c  ON c.id_cuenta      = g.id_cuenta
             INNER JOIN gps.Plataformas  p  ON p.id_plataforma  = c.id_plataforma
             INNER JOIN gps.Transportes  t  ON t.id_transporte  = c.id_transporte
             LEFT  JOIN gps.TiposVehiculo tv ON tv.id_tipo_vehiculo = g.id_tipo_vehiculo
             LEFT  JOIN gps.Destinos      d  ON d.id_destino        = g.id_destino
             WHERE g.estado = 1
             ORDER BY t.nombre, g.placa"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Cuentas activas con integración, para el selector (dropdown). */
    public function cuentasSelector(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT c.id_cuenta, c.usuario, p.nombre AS plataforma, p.tipo_integracion,
                    t.nombre AS transporte,
                    (SELECT COUNT(*) FROM gps.GPS g WHERE g.id_cuenta = c.id_cuenta AND g.estado = 1) AS vinculados
             FROM gps.CuentasGPS c
             INNER JOIN gps.Plataformas p ON p.id_plataforma = c.id_plataforma
             INNER JOIN gps.Transportes t ON t.id_transporte = c.id_transporte
             WHERE c.activo = 1 AND p.tipo_integracion IS NOT NULL
             ORDER BY t.nombre, p.nombre, c.usuario"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Credenciales y motor de una cuenta (para instanciar su adaptador). */
    public function cuentaCredenciales(int $id_cuenta): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT c.id_cuenta, c.usuario, c.contrasena,
                    p.nombre AS plataforma, p.tipo_integracion, p.api_url,
                    t.nombre AS transporte
             FROM gps.CuentasGPS c
             INNER JOIN gps.Plataformas p ON p.id_plataforma = c.id_plataforma
             INNER JOIN gps.Transportes t ON t.id_transporte = c.id_transporte
             WHERE c.id_cuenta = ?"
        );
        $stmt->execute([$id_cuenta]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Placas e IMEIs ya vinculados a una cuenta (para marcar en el selector). */
    public function vinculadosDeCuenta(int $id_cuenta): array
    {
        $stmt = $this->conn->prepare(
            "SELECT placa, imei FROM gps.GPS WHERE id_cuenta = ? AND estado = 1"
        );
        $stmt->execute([$id_cuenta]);
        $placas = []; $imeis = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $placas[strtoupper(trim($r['placa']))] = true;
            if (!empty($r['imei'])) $imeis[trim($r['imei'])] = true;
        }
        return ['placas' => $placas, 'imeis' => $imeis];
    }

    /**
     * Vincula equipos a gps.GPS. $items = [ ['placa'=>..,'imei'=>..], ... ].
     * Omite los que ya existen (misma placa en la cuenta, o mismo imei).
     * Devuelve ['agregados'=>n, 'omitidos'=>n].
     */
    public function agregarVehiculos(int $id_cuenta, array $items, int $uid): array
    {
        $existe = $this->conn->prepare(
            "SELECT COUNT(*) FROM gps.GPS
             WHERE id_cuenta = ? AND (placa = ? OR (imei IS NOT NULL AND imei = ?))"
        );
        $insert = $this->conn->prepare(
            "INSERT INTO gps.GPS (id_cuenta, placa, imei, creado_por)
             VALUES (?, ?, ?, ?)"
        );

        $agregados = 0; $omitidos = 0;
        $this->conn->beginTransaction();
        try {
            foreach ($items as $it) {
                $placa = strtoupper(trim($it['placa'] ?? ''));
                $imei  = trim($it['imei'] ?? '');
                if ($placa === '') { $omitidos++; continue; }

                $existe->execute([$id_cuenta, $placa, $imei]);
                if ((int)$existe->fetchColumn() > 0) { $omitidos++; continue; }

                $insert->execute([$id_cuenta, $placa, $imei ?: null, $uid]);
                $agregados++;
            }
            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
        return ['agregados' => $agregados, 'omitidos' => $omitidos];
    }

    /**
     * Vehículos vinculados de un motor dado que tienen IMEI (para el worker).
     * Devuelve mapa imei => ['id_gps'=>, 'id_cuenta'=>, 'placa'=>].
     */
    public function vehiculosConImeiPorMotor(string $motor): array
    {
        $stmt = $this->conn->prepare(
            "SELECT g.id_gps, g.id_cuenta, g.imei, g.placa
             FROM gps.GPS g
             INNER JOIN gps.CuentasGPS  c ON c.id_cuenta     = g.id_cuenta
             INNER JOIN gps.Plataformas p ON p.id_plataforma = c.id_plataforma
             WHERE g.estado = 1 AND c.activo = 1
               AND p.tipo_integracion = ?
               AND g.imei IS NOT NULL AND g.imei <> ''"
        );
        $stmt->execute([$motor]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[trim($r['imei'])] = [
                'id_gps'    => (int)$r['id_gps'],
                'id_cuenta' => (int)$r['id_cuenta'],
                'placa'     => $r['placa'],
            ];
        }
        return $map;
    }

    /** Inserta/actualiza la última posición conocida de un vehículo (por cuenta + imei). */
    public function guardarPosicion(array $p): void
    {
        if (($p['imei'] ?? '') === '') return;

        $stmt = $this->conn->prepare(
            "MERGE gps.Posiciones AS tgt
             USING (SELECT ? AS id_cuenta, ? AS imei) AS src
                ON tgt.id_cuenta = src.id_cuenta AND tgt.imei = src.imei
             WHEN MATCHED THEN UPDATE SET
                    id_gps = ?, dispositivo = ?, placa = ?,
                    lat = ?, lng = ?, velocidad = ?, rumbo = ?, encendido = ?,
                    direccion = ?, fecha_posicion = ?, fecha_captura = GETDATE()
             WHEN NOT MATCHED THEN INSERT
                    (id_cuenta, id_gps, dispositivo, imei, placa,
                     lat, lng, velocidad, rumbo, encendido, direccion, fecha_posicion)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);"
        );
        $stmt->execute([
            $p['id_cuenta'], $p['imei'],
            $p['id_gps'], $p['dispositivo'], $p['placa'],
            $p['lat'], $p['lng'], $p['velocidad'], $p['rumbo'], $p['encendido'],
            $p['direccion'], $p['fecha'],
            $p['id_cuenta'], $p['id_gps'], $p['dispositivo'], $p['imei'], $p['placa'],
            $p['lat'], $p['lng'], $p['velocidad'], $p['rumbo'], $p['encendido'],
            $p['direccion'], $p['fecha'],
        ]);
    }

    /** Lista de rastreo con su última posición en caché (carga instantánea del mapa). */
    public function leerVinculadosConCache(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT g.id_gps, g.placa, g.imei, g.id_cuenta,
                    p.nombre AS plataforma, p.tipo_integracion,
                    t.nombre AS transporte, tv.nombre AS tipo_vehiculo, d.nombre AS destino,
                    CAST(pos.lat AS FLOAT) AS lat, CAST(pos.lng AS FLOAT) AS lng,
                    pos.velocidad, pos.rumbo, pos.encendido, pos.direccion,
                    CONVERT(varchar(19), pos.fecha_posicion, 120) AS fecha
             FROM gps.GPS g
             INNER JOIN gps.CuentasGPS  c  ON c.id_cuenta      = g.id_cuenta
             INNER JOIN gps.Plataformas p  ON p.id_plataforma  = c.id_plataforma
             INNER JOIN gps.Transportes t  ON t.id_transporte  = c.id_transporte
             LEFT  JOIN gps.TiposVehiculo tv ON tv.id_tipo_vehiculo = g.id_tipo_vehiculo
             LEFT  JOIN gps.Destinos      d  ON d.id_destino        = g.id_destino
             LEFT  JOIN gps.Posiciones   pos ON pos.id_gps = g.id_gps
             WHERE g.estado = 1
             ORDER BY t.nombre, g.placa"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
