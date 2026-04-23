<?php
include_once "../../../../config/Connection.php";
include_once "../Models/mdlMaquinas.php";

$id_maquina = $_POST['id_maquina'];
$id_estado = $_POST['id_estado'];

$maquina = new mdlMaquinas();
$maquina->cambiarEstadoMaquina($id_maquina, $id_estado);
?>