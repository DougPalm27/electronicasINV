<?php
require_once '../../../../config/auth.php';
requireLogin(true);
header('Content-Type: application/json');
include_once '../models/mdlCuentasGPS.php';

$model  = new mdlCuentasGPS();
$accion = $_POST['accion'] ?? '';
$uid    = (int)($_SESSION['id_usuario'] ?? 0);

function respC($data = [], bool $error = false, string $msg = ''): void {
    echo json_encode(['ok' => !$error, 'data' => $data, 'mensaje' => $msg]); exit;
}

try {
    switch ($accion) {

        case 'listar':
            respC($model->listar());
            break;

        case 'listarPorTransporte':
            $id = (int)($_POST['id_transporte'] ?? 0);
            if (!$id) respC([], true, 'ID de transporte inválido.');
            respC($model->listarPorTransporte($id));
            break;

        case 'crear':
            $id_tr   = (int)($_POST['id_transporte'] ?? 0);
            $id_pl   = (int)($_POST['id_plataforma'] ?? 0);
            $usuario = trim($_POST['usuario']         ?? '');
            if (!$id_tr) respC([], true, 'Selecciona un transporte.');
            if (!$id_pl) respC([], true, 'Selecciona una plataforma.');
            $id = $model->crear([
                'id_transporte' => $id_tr,
                'id_plataforma' => $id_pl,
                'usuario'       => $usuario,
                'contrasena'    => $_POST['contrasena'] ?? '',
                'creado_por'    => $uid,
            ]);
            respC(['id_cuenta' => $id], false, 'Cuenta GPS creada.');
            break;

        case 'editar':
            $id_c  = (int)($_POST['id_cuenta']     ?? 0);
            $id_tr = (int)($_POST['id_transporte'] ?? 0);
            $id_pl = (int)($_POST['id_plataforma'] ?? 0);
            if (!$id_c)  respC([], true, 'ID inválido.');
            if (!$id_tr) respC([], true, 'Selecciona un transporte.');
            if (!$id_pl) respC([], true, 'Selecciona una plataforma.');
            $model->editar([
                'id_cuenta'      => $id_c,
                'id_transporte'  => $id_tr,
                'id_plataforma'  => $id_pl,
                'usuario'        => trim($_POST['usuario']     ?? ''),
                'contrasena'     => $_POST['contrasena']       ?? '',
                'actualizado_por'=> $uid,
            ]);
            respC([], false, 'Cuenta GPS actualizada.');
            break;

        case 'toggleActivo':
            $id = (int)($_POST['id_cuenta'] ?? 0);
            if (!$id) respC([], true, 'ID inválido.');
            $model->toggleActivo($id, $uid);
            respC([], false, 'Estado actualizado.');
            break;

        default:
            respC([], true, "Acción no válida: $accion");
    }
} catch (Throwable $e) { respC([], true, $e->getMessage()); }
