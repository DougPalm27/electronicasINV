<?php
require_once "../../../../config/auth.php";
requireLogin(true);

include_once "../../../../config/Connection.php";
include_once "../Models/mdlMaquinas.php";

$maquina = new mdlMaquinas();
$maquina->listarMaquinas();
?>