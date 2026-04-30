<?php
require_once '../../../../config/auth.php';
requireLogin(true);

header('Content-Type: application/json');
include_once '../models/mdlTransportes.php';

$model  = new mdlTransportes();
$accion = $_POST['accion'] ?? '';

function respT($data = [], bool $error = false, string $msg = ''): void
{
    echo json_encode(['ok' => !$error, 'data' => $data, 'mensaje' => $msg]);
    exit;
}

try {
    switch ($accion) {

        case 'listar':
            respT($model->listar());
            break;

        case 'listarActivos':
            respT($model->listarActivos());
            break;

        case 'crear':
            $nombre   = trim($_POST['nombre']   ?? '');
            $contacto = trim($_POST['contacto'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');

            if (!$nombre) respT([], true, 'El nombre es requerido.');

            $id = $model->crear($nombre, $contacto, $telefono);
            respT(['id_transporte' => $id], false, "Transporte '$nombre' creado.");
            break;

        case 'editar':
            $id       = (int)($_POST['id_transporte'] ?? 0);
            $nombre   = trim($_POST['nombre']         ?? '');
            $contacto = trim($_POST['contacto']       ?? '');
            $telefono = trim($_POST['telefono']       ?? '');

            if (!$id)     respT([], true, 'ID inválido.');
            if (!$nombre) respT([], true, 'El nombre es requerido.');

            $model->editar($id, $nombre, $contacto, $telefono);
            respT([], false, 'Transporte actualizado.');
            break;

        case 'toggleActivo':
            $id = (int)($_POST['id_transporte'] ?? 0);
            if (!$id) respT([], true, 'ID inválido.');
            $model->toggleActivo($id);
            respT([], false, 'Estado actualizado.');
            break;

        default:
            respT([], true, "Acción no válida: $accion");
    }
} catch (Throwable $e) {
    respT([], true, $e->getMessage());
}
