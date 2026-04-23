<?php
include_once "../../../../config/Connection.php";
include_once "../Models/mdlMaquinas.php";

$datosObtenidos = $_POST['losDatos'];
$losDatos = (object) $datosObtenidos;

$maquina = new mdlMaquinas();
$maquina->editarMaquina($losDatos);
?>