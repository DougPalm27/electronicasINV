<?php
require_once "../../../../config/auth.php";
requireLogin(true);

include_once "../../../../config/Connection.php";
include_once "../Models/mdlMaquinas.php";

// Liberar la máquina permite instalar su candado en otra PC: solo administradores.
if (($_SESSION['nombre_rol'] ?? '') !== 'Administrador') {
    header('Content-Type: application/json');
    echo json_encode([["status" => "403", "mensaje" => "Solo un administrador puede liberar el candado de una máquina."]]);
    exit;
}

$id_maquina = (int) ($_POST['id_maquina'] ?? 0);
if ($id_maquina <= 0) {
    header('Content-Type: application/json');
    echo json_encode([["status" => "400", "mensaje" => "Máquina no válida."]]);
    exit;
}

$maquina = new mdlMaquinas();
$maquina->liberarCandado($id_maquina);
?>
