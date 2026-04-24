<?php
require_once '../../../config/auth.php';
requireLogin(true);

header('Content-Type: application/json');

include_once "../models/mdlDash.php";

$model  = new mdlDash();
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

function response($data = [], $error = false, $mensaje = "")
{
    echo json_encode(["ok" => !$error, "data" => $data, "mensaje" => $mensaje]);
    exit;
}

try {
    switch ($accion) {
        case 'kpis':
            response($model->kpis());
            break;
        case 'mantenimientosPorMes':
            response($model->mantenimientosPorMes());
            break;
        case 'repuestosPorTipo':
            response($model->repuestosPorTipo());
            break;
        case 'ultimosMantenimientos':
            response($model->ultimosMantenimientos());
            break;
        case 'stockBajo':
            response($model->repuestosStockBajo());
            break;
        default:
            response([], true, "Acción no válida");
    }
} catch (Throwable $e) {
    response([], true, $e->getMessage());
}
