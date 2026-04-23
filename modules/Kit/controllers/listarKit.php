<?php
include_once "../../../config/Connection.php";
include_once "../models/mdl_kit.php";

$listarPro = new mdlKit();
$losDatos = $listarPro->listarKit();
echo json_encode($losDatos);
