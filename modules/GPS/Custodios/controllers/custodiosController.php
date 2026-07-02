<?php
require_once '../../../../config/auth.php';
requireLogin(true);

header('Content-Type: application/json');
include_once '../models/mdlCustodios.php';

$model  = new mdlCustodios();
$accion = $_POST['accion'] ?? '';
$uid    = (int)($_SESSION['id_usuario'] ?? 0);

function respC($data = [], bool $error = false, string $msg = ''): void
{
    echo json_encode(['ok' => !$error, 'data' => $data, 'mensaje' => $msg]);
    exit;
}

try {
    switch ($accion) {

        case 'listar':
            respC($model->listar());
            break;

        case 'crear':
            $nombre   = trim($_POST['nombre']   ?? '');
            $telefono = trim($_POST['telefono'] ?? '');

            if (!$nombre) respC([], true, 'El nombre es requerido.');

            $id = $model->crear($nombre, $telefono, $uid);
            respC(['id_custodio' => $id], false, "Custodio '$nombre' agregado.");
            break;

        case 'editar':
            $id       = (int)($_POST['id_custodio'] ?? 0);
            $nombre   = trim($_POST['nombre']       ?? '');
            $telefono = trim($_POST['telefono']     ?? '');

            if (!$id)     respC([], true, 'ID inválido.');
            if (!$nombre) respC([], true, 'El nombre es requerido.');

            $model->editar($id, $nombre, $telefono, $uid);
            respC([], false, 'Custodio actualizado.');
            break;

        case 'toggleActivo':
            $id = (int)($_POST['id_custodio'] ?? 0);
            if (!$id) respC([], true, 'ID inválido.');
            $model->toggleActivo($id, $uid);
            respC([], false, 'Estado actualizado.');
            break;

        default:
            respC([], true, "Acción no válida: $accion");
    }
} catch (Throwable $e) {
    respC([], true, $e->getMessage());
}
