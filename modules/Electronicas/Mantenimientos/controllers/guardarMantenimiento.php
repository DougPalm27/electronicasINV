<?php
include_once "../../../../config/Connection.php";
include_once "../Models/mdlMantenimientos.php";

$datos = (object) $_POST['losDatos'];

$m = new mdlMantenimientos();
$m->guardarMantenimiento($datos);
?>