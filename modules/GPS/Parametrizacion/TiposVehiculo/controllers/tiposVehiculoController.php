<?php
require_once '../../../../../config/auth.php';
requireLogin(true);
header('Content-Type: application/json');
include_once '../models/mdlTiposVehiculo.php';

$model  = new mdlTiposVehiculo();
$accion = $_POST['accion'] ?? '';
$uid    = (int)($_SESSION['id_usuario'] ?? 0);

function respTV($data = [], bool $error = false, string $msg = ''): void {
    echo json_encode(['ok' => !$error, 'data' => $data, 'mensaje' => $msg]); exit;
}

try {
    switch ($accion) {
        case 'listar':
            respTV($model->listar());
            break;
        case 'listarActivos':
            respTV($model->listarActivos());
            break;
        case 'crear':
            $nombre = trim($_POST['nombre'] ?? '');
            if (!$nombre) respTV([], true, 'El nombre es requerido.');
            $id = $model->crear($nombre, $uid);
            respTV(['id' => $id], false, "Tipo '$nombre' creado.");
            break;
        case 'editar':
            $id     = (int)($_POST['id_tipo_vehiculo'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            if (!$id)     respTV([], true, 'ID inválido.');
            if (!$nombre) respTV([], true, 'El nombre es requerido.');
            $model->editar($id, $nombre);
            respTV([], false, 'Tipo actualizado.');
            break;
        case 'toggleActivo':
            $id = (int)($_POST['id_tipo_vehiculo'] ?? 0);
            if (!$id) respTV([], true, 'ID inválido.');
            $model->toggleActivo($id);
            respTV([], false, 'Estado actualizado.');
            break;
        default:
            respTV([], true, "Acción no válida: $accion");
    }
} catch (Throwable $e) { respTV([], true, $e->getMessage()); }
