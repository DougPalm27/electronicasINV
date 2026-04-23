<?php

include_once "../../../config/Connection.php";
include_once "../models/usuario.php";

$datosObtenidos = $_POST['losDatos'];
$losDatos = (object) $datosObtenidos;

$usuario = new mdlUsuario();
$guardarUsuario = $usuario->guardarNuevoUsuario($losDatos);
?>