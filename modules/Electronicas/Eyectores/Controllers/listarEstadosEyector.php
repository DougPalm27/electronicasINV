<?php
require_once "../../../../config/auth.php";
requireLogin(true);

include_once "../../../../config/Connection.php";
include_once "../Models/mdlEyectores.php";

$modelo = new mdlEyectores();
$modelo->listarEstados();
?>