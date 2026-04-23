<?php
include_once "../../../../config/Connection.php";
include_once "../Models/mdlMantenimientos.php";

$m = new mdlMantenimientos();
$m->listarMantenimientos();
?>