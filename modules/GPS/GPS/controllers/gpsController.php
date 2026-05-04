<?php
require_once '../../../../config/auth.php';
requireLogin(true);
header('Content-Type: application/json');
include_once '../models/mdlGPS.php';

$model  = new mdlGPS();
$accion = $_POST['accion'] ?? '';
$uid    = (int)($_SESSION['id_usuario'] ?? 0);

function respG($data = [], bool $error = false, string $msg = ''): void {
    echo json_encode(['ok' => !$error, 'data' => $data, 'mensaje' => $msg]); exit;
}

try {
    switch ($accion) {

        case 'listar':
            respG($model->listar());
            break;

        case 'crear':
            $id_cuenta = (int)($_POST['id_cuenta'] ?? 0);
            $placa     = trim($_POST['placa']       ?? '');
            if (!$id_cuenta) respG([], true, 'Selecciona una cuenta GPS.');
            if (!$placa)     respG([], true, 'La placa es requerida.');
            $id = $model->crear([
                'id_cuenta'        => $id_cuenta,
                'id_tipo_vehiculo' => (int)($_POST['id_tipo_vehiculo'] ?? 0),
                'id_destino'       => (int)($_POST['id_destino']       ?? 0),
                'placa'            => strtoupper($placa),
                'creado_por'       => $uid,
            ]);
            respG(['id_gps' => $id], false, "Vehículo '$placa' registrado.");
            break;

        case 'editar':
            $id_gps    = (int)($_POST['id_gps']    ?? 0);
            $id_cuenta = (int)($_POST['id_cuenta'] ?? 0);
            $placa     = trim($_POST['placa']       ?? '');
            if (!$id_gps)    respG([], true, 'ID inválido.');
            if (!$id_cuenta) respG([], true, 'Selecciona una cuenta GPS.');
            if (!$placa)     respG([], true, 'La placa es requerida.');
            $model->editar([
                'id_gps'           => $id_gps,
                'id_cuenta'        => $id_cuenta,
                'id_tipo_vehiculo' => (int)($_POST['id_tipo_vehiculo'] ?? 0),
                'id_destino'       => (int)($_POST['id_destino']       ?? 0),
                'placa'            => strtoupper($placa),
                'actualizado_por'  => $uid,
            ]);
            respG([], false, 'Vehículo actualizado.');
            break;

        case 'toggleEstado':
            $id = (int)($_POST['id_gps'] ?? 0);
            if (!$id) respG([], true, 'ID inválido.');
            $model->toggleEstado($id, $uid);
            respG([], false, 'Estado actualizado.');
            break;

        default:
            respG([], true, "Acción no válida: $accion");
    }
} catch (Throwable $e) { respG([], true, $e->getMessage()); }
