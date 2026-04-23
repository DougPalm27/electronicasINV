<?php
include_once "../../../../config/Connection.php";
include_once "../Models/mdlMaquinas.php";

$id_maquina = $_POST['id_maquina'] ?? null;

if (!$id_maquina) {
    echo json_encode(["error" => "ID de máquina no recibido"]);
    exit;
}

$modelo = new mdlMaquinas();
$modelo->obtenerRepuestosMaquina($id_maquina);
?>