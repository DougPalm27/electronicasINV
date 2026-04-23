<?php

header('Content-Type: application/json');

include_once "../models/mdlRepuestos.php";

$model = new mdlRepuestos();

$accion = $_POST['accion'] ?? '';

/**
 * 🔧 Helper para responder SIEMPRE JSON válido
 */
function response($data = [], $error = false, $mensaje = "")
{
    echo json_encode([
        "ok" => !$error,
        "data" => $data,
        "mensaje" => $mensaje
    ]);
    exit;
}

try {

    switch ($accion) {


        case 'proveedores':
            response($model->listarProveedores());
            break;
        //////////////////////////////////////////////////////////
        // 📊 LISTAR REPUESTOS
        //////////////////////////////////////////////////////////

        case 'tipos':
            response($model->listarTipos());
            break;

        case 'marcas':
            response($model->listarMarcas());
            break;

        case 'modelos':

            $id_marca = $_POST['id_marca'] ?? null;
            $id_tipo_modelo = $_POST['id_tipo_modelo'] ?? null;

            response($model->listarModelos($id_marca, $id_tipo_modelo));

            break;

        case 'listar':

            $data = $model->listarRepuestos();

            response($data);
            break;

        case 'seriesDisponibles':

            $id = $_POST["id_repuesto"] ?? 0;

            if (!$id) {
                response([], true, "ID de repuesto inválido");
            }

            $resp = $model->obtenerSeriesDisponibles($id);
            response($resp);
            break;


        case 'salida':

            $data = [
                "id_repuesto" => $_POST["id_repuesto"] ?? null,
                "cantidad" => $_POST["cantidad"] ?? 0,
                "costo" => $_POST["costo"] ?? 0,
                "id_maquina" => $_POST["id_maquina"] ?? null,
                "referencia" => $_POST["referencia"] ?? 'MANTENIMIENTO'
            ];

            $resp = $model->salidaRepuesto($data);

            if (isset($resp["error"]) && $resp["error"] === true) {
                response([], true, $resp["mensaje"]);
            }

            response($resp, false, "Salida registrada");
            break;


        case 'salidaSerie':

            $id_repuesto = $_POST["id_repuesto"] ?? null;
            $id_maquina = $_POST["id_maquina"] ?? null;
            $referencia = $_POST["referencia"] ?? 'MANTENIMIENTO';
            $series = $_POST["series"] ?? [];

            // FIX
            if (!is_array($series)) {
                $series = explode(",", $series);
            }

            if (!$id_repuesto || !$id_maquina || empty($series)) {
                response([], true, "Datos incompletos para salida por serie");
            }

            $resp = $model->salidaSerie($id_repuesto, $id_maquina, $series, $referencia);

            if (isset($resp["error"]) && $resp["error"] === true) {
                response([], true, $resp["mensaje"]);
            }

            response($resp, false, "Salida por serie registrada");
            break;
        //////////////////////////////////////////////////////////
        // CATÁLOGO DE REPUESTOS
        //////////////////////////////////////////////////////////

        case 'guardar':

            $data = [
                "nombre" => $_POST["nombre"] ?? '',
                "numero_parte" => $_POST["numero_parte"] ?? '',
                "id_proveedor" => $_POST["id_proveedor"] ?? null,
                "costo" => $_POST["costo"] ?? 0,
                "comentarios" => $_POST["comentarios"] ?? '',
                "id_tipo" => $_POST["id_tipo"] ?? null,
                "id_marca" => $_POST["id_marca"] ?? null,
                "id_modelo" => $_POST["id_modelo"] ?? null,
                "maneja_serie" => $_POST["maneja_serie"] ?? 0
            ];

            if (!$data["nombre"]) {
                response([], true, "Nombre requerido");
            }

            $resp = $model->guardarRepuesto($data);

            response($resp, false, "Repuesto creado");
            break;


        case 'editar':

            $data = [
                "id_repuesto" => $_POST["id_repuesto"] ?? null,
                "nombre" => $_POST["nombre"] ?? '',
                "numero_parte" => $_POST["numero_parte"] ?? '',
                "id_proveedor" => $_POST["id_proveedor"] ?? null,
                "costo" => $_POST["costo"] ?? 0,
                "comentarios" => $_POST["comentarios"] ?? '',
                "id_tipo" => $_POST["id_tipo"] ?? null,
                "id_marca" => $_POST["id_marca"] ?? null,
                "id_modelo" => $_POST["id_modelo"] ?? null,
                "maneja_serie" => $_POST["maneja_serie"] ?? 0
            ];

            if (!$data["id_repuesto"]) {
                response([], true, "ID inválido");
            }

            $resp = $model->editarRepuesto($data);

            response($resp, false, "Repuesto actualizado");
            break;


        case 'eliminar':

            $id = $_POST["id_repuesto"] ?? null;

            if (!$id) {
                response([], true, "ID inválido");
            }

            $resp = $model->eliminarRepuesto($id);

            response($resp, false, "Repuesto eliminado");
            break;

        //////////////////////////////////////////////////////////
        // ENTRADA
        //////////////////////////////////////////////////////////
        case 'entrada':

            $data = [
                "id_repuesto" => $_POST["id_repuesto"] ?? null,
                "cantidad" => $_POST["cantidad"] ?? 0,
                "costo" => $_POST["costo"] ?? 0,
                "referencia" => $_POST["referencia"] ?? 'COMPRA'
            ];

            $resp = $model->entradaRepuesto($data);

            response($resp, false, "Entrada registrada");
            break;

        case 'entradaSerie':

            $id = $_POST['id_repuesto'];
            $series = $_POST['series'];

            response($model->entradaSerie($id, $series));

            break;

        //////////////////////////////////////////////////////////
        // 📤 SALIDA
        //////////////////////////////////////////////////////////
        case 'salida':

            $data = [
                "id_repuesto" => $_POST["id_repuesto"] ?? null,
                "cantidad" => $_POST["cantidad"] ?? 0,
                "costo" => $_POST["costo"] ?? 0,
                "id_maquina" => $_POST["id_maquina"] ?? null
            ];

            $resp = $model->salidaRepuesto($data);

            response($resp, false, "Salida registrada");
            break;


        //////////////////////////////////////////////////////////
        // 📊 KARDEX
        //////////////////////////////////////////////////////////
        case 'kardex':

            $id = $_POST["id_repuesto"] ?? 0;

            $data = $model->obtenerKardex($id);

            response($data);
            break;


        //////////////////////////////////////////////////////////
        //  EDITAR REPUESTO
        //////////////////////////////////////////////////////////
        case 'editar':

            $data = [
                "id_repuesto" => $_POST["id_repuesto"] ?? null,
                "nombre" => $_POST["nombre"] ?? '',
                "numero_parte" => $_POST["numero_parte"] ?? '',
                "id_proveedor" => $_POST["id_proveedor"] ?? null,
                "costo" => $_POST["costo"] ?? 0,
                "comentarios" => $_POST["comentarios"] ?? ''
            ];

            $resp = $model->editarRepuesto($data);

            response($resp, false, "Repuesto actualizado");
            break;


        //////////////////////////////////////////////////////////
        // 📦 DETALLE
        //////////////////////////////////////////////////////////
        case 'listarDetalle':

            $id = $_POST["id_repuesto"] ?? 0;

            $data = $model->obtenerDetalle($id);

            response($data);
            break;


        case 'editarDetalle':

            $data = [
                "id" => $_POST["id_detalle_repuesto"] ?? null,
                "serie" => $_POST["serie"] ?? '',
                "estado" => $_POST["id_estado_repuesto"] ?? null,
                "maquina" => $_POST["id_maquina_actual"] ?? null
            ];

            $resp = $model->editarDetalle($data);

            response($resp, false, "Detalle actualizado");
            break;


        case 'cambiarEstadoDetalle':

            $data = [
                "id" => $_POST["id_detalle_repuesto"] ?? null,
                "estado" => $_POST["id_estado_repuesto"] ?? null,
                "maquina" => $_POST["id_maquina_actual"] ?? null
            ];

            $resp = $model->cambiarEstadoDetalle($data);

            response($resp, false, "Estado actualizado");
            break;


        //////////////////////////////////////////////////////////
        // DEFAULT
        //////////////////////////////////////////////////////////
        default:

            response([], true, "Acción no válida: " . $accion);
            break;
    }
} catch (Throwable $e) {

    response([], true, $e->getMessage());
}
