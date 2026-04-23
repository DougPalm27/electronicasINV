<?php
include_once "../../../../config/Connection.php";
include_once "../Models/mdlMaquinas.php";

$id_maquina = $_POST['id_maquina'];

$maquina = new mdlMaquinas();
$maquina->obtenerMaquina($id_maquina);
?>