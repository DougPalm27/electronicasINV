<?php
require_once '../../../../config/auth.php';
requireLogin(true);

header('Content-Type: application/json');
include_once '../models/mdlGPS.php';

$model  = new mdlGPS();
$accion = $_POST['accion'] ?? '';

function respG($data = [], bool $error = false, string $msg = ''): void
{
    echo json_encode(['ok' => !$error, 'data' => $data, 'mensaje' => $msg]);
    exit;
}

try {
    switch ($accion) {

        case 'listar':
            respG($model->listar());
            break;

        case 'crear':
            $placa = trim($_POST['placa'] ?? '');
            if (!$placa) respG([], true, 'La placa es requerida.');

            $id = $model->crear([
                'id_transporte' => (int)($_POST['id_transporte'] ?? 0),
                'tipo_vehiculo' => trim($_POST['tipo_vehiculo'] ?? ''),
                'placa'         => $placa,
                'plataforma'    => trim($_POST['plataforma']    ?? ''),
                'destino'       => trim($_POST['destino']       ?? ''),
                'usuario'       => trim($_POST['usuario']       ?? ''),
                'contrasena'    => $_POST['contrasena']         ?? '',
            ]);
            respG(['id_gps' => $id], false, "GPS '$placa' registrado.");
            break;

        case 'editar':
            $id    = (int)($_POST['id_gps'] ?? 0);
            $placa = trim($_POST['placa']   ?? '');

            if (!$id)    respG([], true, 'ID inválido.');
            if (!$placa) respG([], true, 'La placa es requerida.');

            $model->editar([
                'id_gps'        => $id,
                'id_transporte' => (int)($_POST['id_transporte'] ?? 0),
                'tipo_vehiculo' => trim($_POST['tipo_vehiculo'] ?? ''),
                'placa'         => $placa,
                'plataforma'    => trim($_POST['plataforma']    ?? ''),
                'destino'       => trim($_POST['destino']       ?? ''),
                'usuario'       => trim($_POST['usuario']       ?? ''),
                'contrasena'    => $_POST['contrasena']         ?? '',
            ]);
            respG([], false, 'GPS actualizado.');
            break;

        case 'toggleEstado':
            $id = (int)($_POST['id_gps'] ?? 0);
            if (!$id) respG([], true, 'ID inválido.');
            $model->toggleEstado($id);
            respG([], false, 'Estado actualizado.');
            break;

        default:
            respG([], true, "Acción no válida: $accion");
    }
} catch (Throwable $e) {
    respG([], true, $e->getMessage());
}
