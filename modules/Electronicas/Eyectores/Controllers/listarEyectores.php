<?php
require_once "../../../../config/auth.php";
requireLogin(true);

include_once "../../../../config/Connection.php";
include_once "../Models/mdlEyectores.php";

$id_maquina = (int) ($_POST['id_maquina'] ?? 0);

if (!$id_maquina) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'mensaje' => 'ID de máquina inválido.']);
    exit;
}

$modelo = new mdlEyectores();
$modelo->listar($id_maquina);
?>