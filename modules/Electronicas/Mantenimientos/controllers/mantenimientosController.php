<?php

header('Content-Type: application/json');

include_once "../models/mdlMantenimientos.php";

$model = new mdlMantenimientos();

// Soporta POST y GET para compatibilidad con los controladores legacy
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

function response($data = [], $error = false, $mensaje = "")
{
    echo json_encode([
        "ok"      => !$error,
        "data"    => $data,
        "mensaje" => $mensaje
    ]);
    exit;
}

try {

    switch ($accion) {

        //////////////////////////////////////////////////////////
        // TABLA PRINCIPAL
        //////////////////////////////////////////////////////////
        case 'listar':
            response($model->listarMantenimientos());
            break;

        //////////////////////////////////////////////////////////
        // DETALLE DE UN MANTENIMIENTO (repuestos usados)
        //////////////////////////////////////////////////////////
        case 'detalle':
            $id = $_POST['id_mantenimiento'] ?? null;
            if (!$id) response([], true, "ID de mantenimiento inválido");
            response($model->obtenerDetalleMantenimiento($id));
            break;

        //////////////////////////////////////////////////////////
        // SELECTS
        //////////////////////////////////////////////////////////
        case 'maquinas':
            response($model->listarMaquinas());
            break;

        case 'tipos':
            response($model->listarTipos());
            break;

        case 'tecnicos':
            response($model->listarTecnicos());
            break;

        case 'repuestosDisponibles':
            response($model->listarRepuestosDisponibles());
            break;

        case 'seriesDisponibles':
            $id_repuesto = $_POST["id_repuesto"] ?? null;
            if (!$id_repuesto) response([], true, "ID de repuesto inválido");
            response($model->obtenerSeriesDisponibles($id_repuesto));
            break;

        //////////////////////////////////////////////////////////
        // VALIDAR STOCK ANTES DE AGREGAR AL DETALLE
        //////////////////////////////////////////////////////////
        case 'validarStock':
            $id_repuesto  = $_POST['id_repuesto']  ?? null;
            $maneja_serie = $_POST['maneja_serie']  ?? 0;
            $cantidad     = $_POST['cantidad']      ?? 1;

            if (!$id_repuesto) response([], true, "ID de repuesto inválido");

            if ((int)$maneja_serie === 1) {
                // Para series el stock es la cantidad de series disponibles
                $series = $model->obtenerSeriesDisponibles($id_repuesto);
                $stock  = count($series);
            } else {
                $stock = $model->obtenerStockPublico($id_repuesto);
            }

            if ($stock <= 0) {
                response(["stock" => $stock], true, "Sin stock disponible para este repuesto");
            }

            if ((int)$maneja_serie === 0 && (int)$cantidad > $stock) {
                response(["stock" => $stock], true, "Stock insuficiente. Disponible: {$stock}");
            }

            response(["stock" => $stock], false, "Stock disponible: {$stock}");
            break;

        //////////////////////////////////////////////////////////
        // GUARDAR MANTENIMIENTO
        //////////////////////////////////////////////////////////
        case 'guardar':
            $payload = $_POST["losDatos"] ?? null;
            if (!$payload) response([], true, "No se recibieron datos");

            $payload = is_string($payload) ? json_decode($payload) : json_decode(json_encode($payload));
            if (!$payload) response([], true, "Payload inválido");

            $resp = $model->guardarMantenimiento($payload);

            if (isset($resp["error"]) && $resp["error"] === true) {
                response([], true, $resp["mensaje"]);
            }

            response($resp, false, "Mantenimiento guardado correctamente");
            break;

        default:
            response([], true, "Acción no válida: " . $accion);
            break;
    }

} catch (Throwable $e) {
    response([], true, $e->getMessage());
}
