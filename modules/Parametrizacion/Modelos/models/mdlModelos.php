<?php

class mdlModelos
{
    private $conn;

    public function __construct()
    {
        require_once "../../../../config/connection.php";
        $conexion = new Connection();
        $this->conn = $conexion->dbConnect();
    }

    public function listar()
    {
        $sql = "SELECT
                    mo.id_modelo,
                    mo.nombre,
                    mo.id_marca,
                    ma.nombre AS marca,
                    mo.id_tipo_modelo,
                    tm.nombre AS tipo_modelo
                FROM electronicas.Modelos mo
                INNER JOIN electronicas.Marcas ma
                    ON mo.id_marca = ma.id_marca
                LEFT JOIN electronicas.TiposModelo tm
                    ON mo.id_tipo_modelo = tm.id_tipo_modelo
                WHERE mo.activo = 1
                ORDER BY ma.nombre, mo.nombre";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarMarcas()
    {
        $sql = "SELECT id_marca, nombre FROM electronicas.Marcas ORDER BY nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarTipos()
    {
        $sql = "SELECT id_tipo_modelo, nombre FROM electronicas.TiposModelo ORDER BY nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardar($data)
    {
        // Duplicado: mismo nombre + misma marca entre activos
        $sqlCheck = "SELECT COUNT(*) FROM electronicas.Modelos
                     WHERE nombre = ? AND id_marca = ? AND activo = 1";
        $stmtCheck = $this->conn->prepare($sqlCheck);
        $stmtCheck->execute([$data["nombre"], $data["id_marca"]]);

        if ($stmtCheck->fetchColumn() > 0) {
            return ["error" => true, "mensaje" => "Ya existe un modelo con ese nombre para la marca seleccionada"];
        }

        $sql = "INSERT INTO electronicas.Modelos (nombre, id_marca, id_tipo_modelo, activo)
                VALUES (?, ?, ?, 1)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            $data["nombre"],
            $data["id_marca"],
            $data["id_tipo_modelo"] ?: null
        ]);

        return ["ok" => true, "id" => $this->conn->lastInsertId()];
    }

    public function editar($data)
    {
        // Duplicado: mismo nombre + misma marca en activos, excluyendo el propio
        $sqlCheck = "SELECT COUNT(*) FROM electronicas.Modelos
                     WHERE nombre = ? AND id_marca = ? AND activo = 1 AND id_modelo != ?";
        $stmtCheck = $this->conn->prepare($sqlCheck);
        $stmtCheck->execute([$data["nombre"], $data["id_marca"], $data["id_modelo"]]);

        if ($stmtCheck->fetchColumn() > 0) {
            return ["error" => true, "mensaje" => "Ya existe un modelo con ese nombre para la marca seleccionada"];
        }

        $sql = "UPDATE electronicas.Modelos SET
                    nombre          = ?,
                    id_marca        = ?,
                    id_tipo_modelo  = ?
                WHERE id_modelo = ? AND activo = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            $data["nombre"],
            $data["id_marca"],
            $data["id_tipo_modelo"] ?: null,
            $data["id_modelo"]
        ]);

        return ["ok" => true];
    }

    public function eliminar($id)
    {
        // Verificar que no tenga máquinas o repuestos activos asignados
        $sqlMaq = "SELECT COUNT(*) FROM electronicas.Maquinas WHERE id_modelo = ?";
        $stmtMaq = $this->conn->prepare($sqlMaq);
        $stmtMaq->execute([$id]);

        if ($stmtMaq->fetchColumn() > 0) {
            return ["error" => true, "mensaje" => "No se puede eliminar: el modelo tiene máquinas registradas"];
        }

        $sqlRep = "SELECT COUNT(*) FROM electronicas.Repuestos
                   WHERE id_modelo = ? AND id_estado != 5";
        $stmtRep = $this->conn->prepare($sqlRep);
        $stmtRep->execute([$id]);

        if ($stmtRep->fetchColumn() > 0) {
            return ["error" => true, "mensaje" => "No se puede eliminar: el modelo tiene repuestos registrados"];
        }

        // Soft delete
        $sql = "UPDATE electronicas.Modelos SET activo = 0 WHERE id_modelo = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);

        return ["ok" => true];
    }
}
