<?php
require_once "../../../../config/auth.php";
requireLogin(true);

header('Content-Type: application/json');

$esEditor = in_array($_SESSION['nombre_rol'] ?? '', ['Administrador', 'Técnico'], true);
if (!$esEditor) {
    echo json_encode(['ok' => false, 'mensaje' => 'No tienes permiso para esta acción.']);
    exit;
}

include_once "../../../../config/Connection.php";
include_once "../Models/mdlEyectores.php";

$id_maquina = (int) ($_POST['id_maquina'] ?? 0);
if (!$id_maquina) {
    echo json_encode(['ok' => false, 'mensaje' => 'ID de máquina inválido.']);
    exit;
}

$modelo = new mdlEyectores();
$modelo->inicializarBarra($id_maquina);
?>