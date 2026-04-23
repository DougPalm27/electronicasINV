<?php
include_once "../../../../config/Connection.php";
include_once "../Models/mdlMaquinas.php";

$maquina = new mdlMaquinas();
$maquina->listarMaquinas();
?>