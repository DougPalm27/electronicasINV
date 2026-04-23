<?php


class mdlMaquinas extends Connection
{
    private $conn;

    public function __construct()
    {
        $database = new Connection();
        $this->conn = $database->dbConnect();
    }

    public function guardarMaquina($losDatos)
    {
        header('Content-Type: application/json');

        $sql = "INSERT INTO electronicas.Maquinas
            (
                nombre,
                id_modelo,
                serie,
                comentarios,
                id_estado,
                costo,
                anio,
                ubicacion
            )
            VALUES
            (
                :nombre,
                :id_modelo,
                :serie,
                :comentarios,
                :id_estado,
                :costo,
                :anio,
                :ubicacion
            )";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":nombre", $losDatos->nombre);
        $stmt->bindParam(":id_modelo", $losDatos->id_modelo);
        $stmt->bindParam(":serie", $losDatos->serie);
        $stmt->bindParam(":comentarios", $losDatos->comentarios);
        $stmt->bindParam(":id_estado", $losDatos->id_estado);
        $stmt->bindParam(":costo", $losDatos->costo);
        $stmt->bindParam(":anio", $losDatos->anio);
        $stmt->bindParam(":ubicacion", $losDatos->ubicacion);

        try {
            $stmt->execute();
            echo json_encode([
                ["status" => "200", "mensaje" => "Máquina registrada correctamente"]
            ]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(["error" => $e->getMessage()]);
            exit;
        }
    }

    public function editarMaquina($losDatos)
    {
        header('Content-Type: application/json');

        $sql = "UPDATE electronicas.Maquinas
            SET nombre = :nombre,
                id_modelo = :id_modelo,
                serie = :serie,
                comentarios = :comentarios,
                id_estado = :id_estado,
                costo = :costo,
                anio = :anio,
                ubicacion = :ubicacion
            WHERE id_maquina = :id_maquina";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id_maquina", $losDatos->id_maquina);
        $stmt->bindParam(":nombre", $losDatos->nombre);
        $stmt->bindParam(":id_modelo", $losDatos->id_modelo);
        $stmt->bindParam(":serie", $losDatos->serie);
        $stmt->bindParam(":comentarios", $losDatos->comentarios);
        $stmt->bindParam(":id_estado", $losDatos->id_estado);
        $stmt->bindParam(":costo", $losDatos->costo);
        $stmt->bindParam(":anio", $losDatos->anio);
        $stmt->bindParam(":ubicacion", $losDatos->ubicacion);

        try {
            $stmt->execute();
            echo json_encode([
                ["status" => "200", "mensaje" => "Máquina actualizada correctamente"]
            ]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(["error" => $e->getMessage()]);
            exit;
        }
    }

    public function listarMaquinas()
    {
        header('Content-Type: application/json');

        $sql = "SELECT 
                mq.id_maquina,
                mq.nombre,
                mq.serie,
                mq.comentarios,
                mq.id_modelo,
                mq.id_estado,
                mq.costo,
                mq.anio,
                mq.ubicacion,
                mo.nombre AS modelo,
                ma.nombre AS marca,
                em.nombre AS estado
            FROM electronicas.Maquinas mq
            INNER JOIN electronicas.Modelos mo
                ON mq.id_modelo = mo.id_modelo
            INNER JOIN electronicas.Marcas ma
                ON mo.id_marca = ma.id_marca
            INNER JOIN electronicas.EstadoMaquina em
                ON mq.id_estado = em.id_estado
            ORDER BY mq.id_maquina DESC";

        $stmt = $this->conn->prepare($sql);

        try {
            $stmt->execute();
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        } catch (PDOException $e) {
            echo json_encode(["error" => $e->getMessage()]);
            exit;
        }
    }

    public function obtenerMaquina($id_maquina)
    {
        header('Content-Type: application/json');

        $sql = "SELECT
                id_maquina,
                nombre,
                id_modelo,
                serie,
                comentarios,
                id_estado,
                costo,
                anio,
                ubicacion
            FROM electronicas.Maquinas
            WHERE id_maquina = :id_maquina";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id_maquina", $id_maquina);

        try {
            $stmt->execute();
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        } catch (PDOException $e) {
            echo json_encode(["error" => $e->getMessage()]);
            exit;
        }
    }
    public function cambiarEstadoMaquina($id_maquina, $id_estado)
    {
        header('Content-Type: application/json');

        $sql = "UPDATE electronicas.Maquinas
                SET id_estado = :id_estado
                WHERE id_maquina = :id_maquina";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id_maquina", $id_maquina);
        $stmt->bindParam(":id_estado", $id_estado);

        try {
            $stmt->execute();
            echo json_encode([
                ["status" => "200", "mensaje" => "Estado actualizado correctamente"]
            ]);
            exit;
        } catch (PDOException $e) {
            echo json_encode([
                "error" => $e->getMessage()
            ]);
            exit;
        }
    }

    public function listarMarcas()
    {
        header('Content-Type: application/json');

        $sql = "SELECT id_marca, nombre
                FROM electronicas.Marcas
                ORDER BY nombre ASC";

        $stmt = $this->conn->prepare($sql);

        try {
            $stmt->execute();
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        } catch (PDOException $e) {
            echo json_encode([
                "error" => $e->getMessage()
            ]);
            exit;
        }
    }

    public function listarModelos($id_marca)
    {
        header('Content-Type: application/json');

        $sql = "SELECT id_modelo, nombre
                FROM electronicas.Modelos
                WHERE id_marca = :id_marca
                ORDER BY nombre ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id_marca", $id_marca);

        try {
            $stmt->execute();
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        } catch (PDOException $e) {
            echo json_encode([
                "error" => $e->getMessage()
            ]);
            exit;
        }
    }

    public function listarEstadosMaquina()
    {
        header('Content-Type: application/json');

        $sql = "SELECT id_estado, nombre
                FROM electronicas.EstadoMaquina
                ORDER BY id_estado ASC";

        $stmt = $this->conn->prepare($sql);

        try {
            $stmt->execute();
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        } catch (PDOException $e) {
            echo json_encode([
                "error" => $e->getMessage()
            ]);
            exit;
        }
    }
    public function obtenerRepuestosMaquina($id_maquina)
    {
        header('Content-Type: application/json');

        $sql = "SELECT 
                r.id_repuesto,
                r.nombre,
                r.costo,
                e.nombre AS estado,
                MAX(m.fecha_mantenimiento) AS ultima_fecha
            FROM electronicas.Repuestos r
            INNER JOIN electronicas.EstadoRepuestos e 
                ON r.id_estado = e.id_estado
            LEFT JOIN electronicas.MantenimientoRepuestos mr 
                ON r.id_repuesto = mr.id_repuesto
            LEFT JOIN electronicas.Mantenimientos m 
                ON mr.id_mantenimiento = m.id_mantenimiento
            WHERE r.id_maquina_actual = :id_maquina
            GROUP BY 
                r.id_repuesto,
                r.nombre,'
                r.costo,
                e.nombre
            ORDER BY r.nombre";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id_maquina", $id_maquina);

        try {
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($data);
            exit;
        } catch (PDOException $e) {
            echo json_encode([
                "error" => $e->getMessage()
            ]);
            exit;
        }
    }
}
