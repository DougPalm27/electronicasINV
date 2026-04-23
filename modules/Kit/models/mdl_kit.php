<?php
class mdlKit
{
    public $conn;

    public function __construct()
    {
        $this->conn = new Connection();
        $this->conn = $this->conn->dbConnect();
    }

    public function listarKit()
    {
        $Equipo = "SELECT kitID, k.descripcion as kit, precio,codigoProyecto, e.descripcion as estado, codigoSAP,nombreProyecto FROM inventario.kit AS k
            INNER JOIN inventario.proyectos AS p ON k.proyectoID = p.proyectoID
            INNER JOIN inventario.estados AS e ON k.estadoID = e.estadoID
			WHERE k.kitID NOT IN (SELECT kitID FROM inventario.kitAsignaciones as kt WHERE k.kitID = kt.kitID AND estadoID = 3)";
        $stmt = $this->conn->prepare($Equipo);
        try {
            $stmt->execute();
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $resultado = $e->getMessage();
        }
        $stmt->closeCursor();
        return $resultado;
    }
    public function EliminarKit($ID)
    {
        $sql = "UPDATE inventario.kit SET estadoID = 2 WHERE kitID = :kitID";
        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(":kitID", $kitID);

        try {
            $stmt->execute();
            $response[0] = array(
                'status'  => '200',
                'mensaje' => 'Actualización exitosa',
            );
            $resultado = json_encode($response);
            echo $resultado;
        } catch (PDOException $e) {
            $resultado = $e->getMessage();
            echo $resultado;
        }
        $stmt->closeCursor();
        return $resultado;
    }
    public function guardarKit($losDatos)
    {

        $recio = "	INSERT INTO inventario.kit
        (descripcion,precio,proyectoID,estadoID,fechaCompra,codigoSAP) 
        VALUES 
        (:descripcion,:precio,:proyectoID,1,:fecha,:sap)";

        $stmt = $this->conn->prepare($recio);
        $stmt->bindParam(":descripcion", $losDatos->descripcion);
        $stmt->bindParam(":precio", $losDatos->precio);
        $stmt->bindParam(":proyectoID", $losDatos->proyecto);
        $stmt->bindParam(":fecha", $losDatos->fecha);
        $stmt->bindParam(":sap", $losDatos->sap);



        try {
            $stmt->execute();
            $response[0] = array(
                'status' => '200',
                'mensaje' => 'Insertado correctamente',
            );

            $resultado = json_encode($response);
        } catch (PDOException $e) {
            $res = $stmt->errorInfo();
            $resultado  = json_encode($res);
        }

        echo $resultado;
        return $resultado;
    }
    public function asgKit($losDatos)
    {

        $recio = "INSERT INTO inventario.kitAsignaciones
        (kitID,usuarioID,fechaAsignacion,estadoID,observaciones) 
        VALUES 
        (:kit,:usuario,:fecha,3,:observaciones)";
        $stmt = $this->conn->prepare($recio);
        $stmt->bindParam(":kit", $losDatos->kit);
        $stmt->bindParam(":usuario", $losDatos->usuario);
        $stmt->bindParam(":fecha", $losDatos->fecha);
        $stmt->bindParam(":observaciones", $losDatos->observaciones);
        try {
            $stmt->execute();
            $response[0] = array(
                'status' => '200',
                'mensaje' => 'Insertado correctamente',
            );

            $resultado = json_encode($response);
        } catch (PDOException $e) {
            $res = $stmt->errorInfo();
            $resultado  = json_encode($res);
        }

        echo $resultado;
        return $resultado;
    }

    public function listarKitAsignacion()
    {
        $Equipo = "SELECT ka.asignacionID,k.codigoSAP,em.nombreCompleto ,k.descripcion as kitDes, p.nombreProyecto   FROM inventario.kitAsignaciones ka
            INNER JOIN inventario.kit AS k ON ka.kitID = k.kitID
            INNER JOIN inventario.proyectos AS p ON k.proyectoID = p.proyectoID
            INNER JOIN DBSIMFCOH.rrhh.vw_empleadosActivos as em ON ka.usuarioID = em.idEmpleado
        ";
        $stmt = $this->conn->prepare($Equipo);
        try {
            $stmt->execute();
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $resultado = $e->getMessage();
        }
        $stmt->closeCursor();
        return $resultado;
    }
    public function listarKitDisponibles()
    {
        $Equipo = "SELECT k.codigoSAP,k.descripcion,p.nombreProyecto FROM inventario.kit as k
        INNER JOIN inventario.proyectos as p ON k.proyectoID = p.proyectoID
        inner join inventario.estados AS ES on k.estadoID = es.estadoID
        WHERE k.kitID NOT IN (SELECT kitID FROM inventario.kitAsignaciones as kt WHERE k.kitID = kt.kitID AND estadoID = 3)
        ";
        $stmt = $this->conn->prepare($Equipo);
        try {
            $stmt->execute();
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $resultado = $e->getMessage();
        }
        $stmt->closeCursor();
        return $resultado;
    }
    public function listarKitTodo()
    {
        $Equipo = "SELECT kitID, k.descripcion as kit, precio,codigoProyecto, e.descripcion as estado, codigoSAP,nombreProyecto FROM inventario.kit AS k
            INNER JOIN inventario.proyectos AS p ON k.proyectoID = p.proyectoID
            INNER JOIN inventario.estados AS e ON k.estadoID = e.estadoID
        ";
        $stmt = $this->conn->prepare($Equipo);
        try {
            $stmt->execute();
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $resultado = $e->getMessage();
        }
        $stmt->closeCursor();
        return $resultado;
    }
    public function EliminarKitAsignacion($asignacionID)
    {
        $sql = "UPDATE inventario.kitAsignaciones SET estadoID = 4 WHERE asignacionID = :asignacionID";
        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(":asignacionID", $asignacionID);

        try {
            $stmt->execute();
            $response[0] = array(
                'status'  => '200',
                'mensaje' => 'Actualización exitosa',
            );
            $resultado = json_encode($response);
            echo $resultado;
        } catch (PDOException $e) {
            $resultado = $e->getMessage();
            echo $resultado;
        }
        $stmt->closeCursor();

        return $resultado;
    }
}
