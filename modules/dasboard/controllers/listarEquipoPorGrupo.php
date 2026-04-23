
 <?php
    include_once "../../../config/Connection.php";
    include_once "../models/dash.php";
    // Instanciamos el modelo y llamamos al método correspondiente
    $conexion = new mdlDash();
    $losDatos = $conexion->equipoPorGrupo();
    echo json_encode($losDatos);
?>