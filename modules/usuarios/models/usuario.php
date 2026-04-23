<?php
 class mdlUsuario{

    public $conn;

    // Constructores
    public function __construct(){
        $this->conn = new Connection();
        $this->conn = $this->conn->dbConnect();
    
    }

    public function listarCategorias()
    {
        $sql = "SELECT * FROM inventario.categorias";
        $stmt = $this->conn->prepare($sql);

        try {
            $stmt->execute();
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $resultado = $e->getMessage();
        }
        $stmt->closeCursor();
        return $resultado;
    }

    // método para guardar categorias
    public function guardarNuevoUsuario($losDatos){

        $recio ="exec inventario.sp_insertarUsuarios :nombre,:email,:fechaActivo,:ubicacionID";

        $stmt = $this->conn->prepare($recio);
        $stmt->bindParam(":nombre",$losDatos->nombre);
        $stmt->bindParam(":email",$losDatos->email);
        $stmt->bindParam(":fechaActivo",$losDatos->fechaActivo);
        $stmt->bindParam(":ubicacionID",$losDatos->ubicacionID);

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
    
    // listar correos para select
    public function listarCorreos(){
        $sql ="SELECT * FROM ejercicio.correo";
        $stmt = $this->conn->prepare($sql);
        
        try {
            $stmt->execute();
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $resultado = $e->getMessage();
        }
        $stmt->closeCursor();
        return $resultado;
    }
}

?>