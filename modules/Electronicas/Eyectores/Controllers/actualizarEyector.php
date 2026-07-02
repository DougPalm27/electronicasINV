<?php
require_once "../../../../config/auth.php";
requireLogin(true);

header('Content-Type: application/json');

$esEditor = in_array($_SESSION['nombre_rol'] ?? '', ['Administrador', 'Técnico'], true);
if (!$esEditor) {
    echo json_encode(['ok' => false, 'mensaje' => 'No tienes permiso para modificar eyectores.']);
    exit;
}

include_once "../../../../config/Connection.php";
include_once "../Models/mdlEyectores.php";

$id_eyector  = (int) ($_POST['id_eyector'] ?? 0);
$id_estado   = (int) ($_POST['id_estado'] ?? 0);
$observacion = trim($_POST['observacion'] ?? '');
$id_usuario  = (int) ($_SESSION['id_usuario'] ?? 0);

if (!$id_eyector || !$id_estado) {
    echo json_encode(['ok' => false, 'mensaje' => 'Datos incompletos.']);
    exit;
}

$modelo = new mdlEyectores();
$modelo->actualizar($id_eyector, $id_estado, $observacion, $id_usuario);
?>