<?php

class mdlDivisas
{
    private $conn;

    public function __construct()
    {
        require_once '../../../../config/Connection.php';
        $this->conn = (new Connection())->dbConnect();
    }

    public function listar(): array
    {
        $sql = "SELECT id_divisa, nombre, codigo, simbolo,
                       tipo_cambio, predeterminada, activo,
                       FORMAT(fecha_actualizacion, 'dd/MM/yyyy HH:mm') AS fecha_actualizacion
                FROM electronicas.Divisas
                ORDER BY predeterminada DESC, nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function predeterminada(): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT id_divisa, nombre, codigo, simbolo, tipo_cambio
             FROM electronicas.Divisas
             WHERE predeterminada = 1 AND activo = 1"
        );
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function crear(string $nombre, string $codigo, string $simbolo, float $tipoCambio): void
    {
        $chk = $this->conn->prepare(
            "SELECT COUNT(*) FROM electronicas.Divisas WHERE codigo = ?"
        );
        $chk->execute([strtoupper($codigo)]);
        if ((int)$chk->fetchColumn() > 0) {
            throw new RuntimeException("El código '$codigo' ya existe.");
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO electronicas.Divisas (nombre, codigo, simbolo, tipo_cambio)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$nombre, strtoupper($codigo), $simbolo, $tipoCambio]);
    }

    public function actualizar(int $id, string $nombre, string $simbolo, float $tipoCambio): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE electronicas.Divisas
             SET nombre = ?, simbolo = ?, tipo_cambio = ?,
                 fecha_actualizacion = GETDATE()
             WHERE id_divisa = ?"
        );
        $stmt->execute([$nombre, $simbolo, $tipoCambio, $id]);
    }

    public function setPredeterminada(int $id): void
    {
        // Quitar predeterminada actual
        $this->conn->exec("UPDATE electronicas.Divisas SET predeterminada = 0");
        // Poner la nueva
        $stmt = $this->conn->prepare(
            "UPDATE electronicas.Divisas SET predeterminada = 1 WHERE id_divisa = ?"
        );
        $stmt->execute([$id]);
    }

    public function toggleActivo(int $id): void
    {
        // No se puede desactivar la predeterminada
        $chk = $this->conn->prepare(
            "SELECT predeterminada FROM electronicas.Divisas WHERE id_divisa = ?"
        );
        $chk->execute([$id]);
        if ((int)$chk->fetchColumn() === 1) {
            throw new RuntimeException('No puedes desactivar la divisa predeterminada.');
        }
        $stmt = $this->conn->prepare(
            "UPDATE electronicas.Divisas SET activo = 1 - activo WHERE id_divisa = ?"
        );
        $stmt->execute([$id]);
    }
}
