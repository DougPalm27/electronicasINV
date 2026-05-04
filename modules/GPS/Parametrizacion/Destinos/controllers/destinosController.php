<?php
require_once '../../../../../config/auth.php';
requireLogin(true);
header('Content-Type: application/json');
include_once '../models/mdlDestinos.php';

$model  = new mdlDestinos();
$accion = $_POST['accion'] ?? '';
$uid    = (int)($_SESSION['id_usuario'] ?? 0);

function respD($data = [], bool $error = false, string $msg = ''): void {
    echo json_encode(['ok' => !$error, 'data' => $data, 'mensaje' => $msg]); exit;
}

try {
    switch ($accion) {
        case 'listar':
            respD($model->listar());
            break;
        case 'listarActivos':
            respD($model->listarActivos());
            break;
        case 'crear':
            $nombre = trim($_POST['nombre'] ?? '');
            if (!$nombre) respD([], true, 'El nombre es requerido.');
            $id = $model->crear($nombre, $uid);
            respD(['id' => $id], false, "Destino '$nombre' creado.");
            break;
        case 'editar':
            $id     = (int)($_POST['id_destino'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            if (!$id)     respD([], true, 'ID inválido.');
            if (!$nombre) respD([], true, 'El nombre es requerido.');
            $model->editar($id, $nombre);
            respD([], false, 'Destino actualizado.');
            break;
        case 'toggleActivo':
            $id = (int)($_POST['id_destino'] ?? 0);
            if (!$id) respD([], true, 'ID inválido.');
            $model->toggleActivo($id);
            respD([], false, 'Estado actualizado.');
            break;
        default:
            respD([], true, "Acción no válida: $accion");
    }
} catch (Throwable $e) { respD([], true, $e->getMessage()); }
