<?php
class mdlCuentasGPS
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
            "SELECT c.id_cuenta, c.usuario, c.contrasena, c.activo,
                    c.fecha_creacion, c.fecha_actualizacion,
                    c.id_transporte, t.nombre AS transporte,
                    c.id_plataforma, p.nombre AS plataforma, p.url_base,
                    uc.nombre AS creado_por_nombre,
                    ua.nombre AS actualizado_por_nombre
             FROM gps.CuentasGPS c
             INNER JOIN gps.Transportes  t  ON t.id_transporte = c.id_transporte
             INNER JOIN gps.Plataformas  p  ON p.id_plataforma = c.id_plataforma
             LEFT  JOIN electronicas.Usuarios uc ON uc.id_usuario = c.creado_por
             LEFT  JOIN electronicas.Usuarios ua ON ua.id_usuario = c.actualizado_por
             ORDER BY t.nombre, p.nombre"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarPorTransporte(int $id_transporte): array
    {
        $stmt = $this->conn->prepare(
            "SELECT c.id_cuenta, c.usuario,
                    p.nombre AS plataforma, p.url_base
             FROM gps.CuentasGPS c
             INNER JOIN gps.Plataformas p ON p.id_plataforma = c.id_plataforma
             WHERE c.id_transporte = ? AND c.activo = 1
             ORDER BY p.nombre"
        );
        $stmt->execute([$id_transporte]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear(array $d): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO gps.CuentasGPS
                (id_transporte, id_plataforma, usuario, contrasena, creado_por)
             OUTPUT INSERTED.id_cuenta
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $d['id_transporte'], $d['id_plataforma'],
            $d['usuario'], $d['contrasena'], $d['creado_por'],
        ]);
        return (int)$stmt->fetchColumn();
    }

    public function editar(array $d): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE gps.CuentasGPS
             SET id_transporte = ?, id_plataforma = ?,
                 usuario = ?, contrasena = ?,
                 fecha_actualizacion = GETDATE(), actualizado_por = ?
             WHERE id_cuenta = ?"
        );
        $stmt->execute([
            $d['id_transporte'], $d['id_plataforma'],
            $d['usuario'], $d['contrasena'],
            $d['actualizado_por'], $d['id_cuenta'],
        ]);
    }

    public function toggleActivo(int $id, int $uid): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE gps.CuentasGPS
             SET activo = 1 - activo,
                 fecha_actualizacion = GETDATE(), actualizado_por = ?
             WHERE id_cuenta = ?"
        );
        $stmt->execute([$uid, $id]);
    }
}
