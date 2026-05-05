<?php

class mdlGPS
{
    private $conn;

    public function __construct()
    {
        require_once '../../../../config/Connection.php';
        $this->conn = (new Connection())->dbConnect();
    }

    public function listar(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT g.id_gps, g.placa, g.estado,
                    g.id_cuenta, g.id_tipo_vehiculo, g.id_destino,
                    g.fecha_creacion, g.fecha_actualizacion,
                    tv.nombre AS tipo_vehiculo,
                    d.nombre  AS destino,
                    t.nombre  AS transporte,
                    t.id_transporte,
                    p.nombre  AS plataforma,
                    p.url_base,
                    c.usuario, c.contrasena,
                    uc.nombre AS creado_por_nombre,
                    ua.nombre AS actualizado_por_nombre
             FROM gps.GPS g
             INNER JOIN gps.CuentasGPS   c  ON c.id_cuenta      = g.id_cuenta
             INNER JOIN gps.Transportes  t  ON t.id_transporte  = c.id_transporte
             INNER JOIN gps.Plataformas  p  ON p.id_plataforma  = c.id_plataforma
             LEFT  JOIN gps.TiposVehiculo tv ON tv.id_tipo_vehiculo = g.id_tipo_vehiculo
             LEFT  JOIN gps.Destinos      d  ON d.id_destino        = g.id_destino
             LEFT  JOIN electronicas.Usuarios uc ON uc.id_usuario = g.creado_por
             LEFT  JOIN electronicas.Usuarios ua ON ua.id_usuario = g.actualizado_por
             ORDER BY g.placa"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function placaExiste(string $placa, int $excluir_id = 0): bool
    {
        $stmt = $this->conn->prepare(
            "SELECT 1 FROM gps.GPS WHERE placa = ? AND id_gps <> ?"
        );
        $stmt->execute([$placa, $excluir_id]);
        return (bool)$stmt->fetchColumn();
    }

    // Recibe array de placas, inserta todas en una transacción
    public function crear(array $d): array
    {
        // Verificar duplicados antes de iniciar transacción
        foreach ($d['placas'] as $placa) {
            if ($this->placaExiste($placa)) {
                throw new RuntimeException("La placa \"$placa\" ya está registrada.");
            }
        }

        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO gps.GPS (id_cuenta, id_tipo_vehiculo, id_destino, placa, creado_por)
                 OUTPUT INSERTED.id_gps
                 VALUES (?, ?, ?, ?, ?)"
            );

            $ids = [];
            foreach ($d['placas'] as $placa) {
                $stmt->execute([
                    $d['id_cuenta'],
                    $d['id_tipo_vehiculo'] ?: null,
                    $d['id_destino']       ?: null,
                    $placa,
                    $d['creado_por'],
                ]);
                $ids[] = (int)$stmt->fetchColumn();
            }

            $this->conn->commit();
            return $ids;
        } catch (Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function editar(array $d): void
    {
        if ($this->placaExiste($d['placa'], $d['id_gps'])) {
            throw new RuntimeException("La placa \"{$d['placa']}\" ya está registrada en otro vehículo.");
        }

        $stmt = $this->conn->prepare(
            "UPDATE gps.GPS
             SET id_cuenta = ?, id_tipo_vehiculo = ?, id_destino = ?,
                 placa = ?, fecha_actualizacion = GETDATE(), actualizado_por = ?
             WHERE id_gps = ?"
        );
        $stmt->execute([
            $d['id_cuenta'],
            $d['id_tipo_vehiculo'] ?: null,
            $d['id_destino']       ?: null,
            $d['placa'],
            $d['actualizado_por'],
            $d['id_gps'],
        ]);
    }

    public function toggleEstado(int $id, int $uid): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE gps.GPS
             SET estado = 1 - estado,
                 fecha_actualizacion = GETDATE(), actualizado_por = ?
             WHERE id_gps = ?"
        );
        $stmt->execute([$uid, $id]);
    }
}
