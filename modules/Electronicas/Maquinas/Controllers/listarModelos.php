<?php
include_once "../../../../config/Connection.php";
include_once "../Models/mdlMaquinas.php";

$id_marca = $_POST['id_marca'];

$maquina = new mdlMaquinas();
$maquina->listarModelos($id_marca);
?>