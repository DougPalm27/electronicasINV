<?php

require_once '../../../../config/auth.php';
requireLogin(true);

header('Content-Type: application/json');

include_once "../models/mdlMantenimientos.php";

$model              = new mdlMantenimientos();
$accion             = $_POST['accion'] ?? $_GET['accion'] ?? '';
$puedeMantenimiento = !empty($_SESSION['puede_ejecutar_mantenimiento']);
$esAdmin            = ($_SESSION['nombre_rol'] ?? '') === 'Administrador';

function response($data = [], $error = false, $mensaje = "")
{
    echo json_encode(["ok" => !$error, "data" => $data, "mensaje" => $mensaje]);
    exit;
}

try {

    switch ($accion) {

        case 'listar':
            response($model->listarMantenimientos());
            break;

        case 'detalle':
            $id = (int)($_POST['id_mantenimiento'] ?? 0);
            if (!$id) response([], true, "ID de mantenimiento inválido");
            response([
                "tareas"     => $model->obtenerTareasDescripcion($id),
                "tareas_completas" => $model->obtenerTareas($id),
                "instalados" => $model->obtenerDetalleMantenimiento($id),
                "retiros"    => $model->obtenerRetirosMantenimiento($id)
            ]);
            break;

        case 'obtenerEdicion':
            if (!$puedeMantenimiento) response([], true, 'Sin permisos.');
            $id = (int)($_POST['id_mantenimiento'] ?? 0);
            if (!$id) response([], true, 'ID inválido.');
            response($model->obtenerParaEdicion($id));
            break;

        case 'maquinas':
            response($model->listarMaquinas());
            break;

        case 'tipos':
            response($model->listarTipos());
            break;

        case 'tecnicos':
            response($model->listarTecnicos());
            break;

        case 'repuestos':
            response($model->listarRepuestosDisponibles());
            break;

        case 'tareasActivas':
            response($model->listarTareasActivasSatake());
            break;

        case 'series':
            $id_rep = (int)($_POST['id_repuesto'] ?? 0);
            if (!$id_rep) response([], true, 'ID de repuesto inválido.');
            response($model->obtenerSeriesDisponibles($id_rep));
            break;

        case 'instalados':
            $id_maq = (int)($_POST['id_maquina'] ?? 0);
            if (!$id_maq) response([], true, 'ID de máquina inválido.');
            response($model->obtenerInstalados($id_maq));
            break;

        case 'solicitudesDisponibles':
            $id_maq = (int)($_POST['id_maquina'] ?? 0);
            if (!$id_maq) response([], true, 'ID de máquina inválido.');
            response($model->listarSolicitudesDisponibles($id_maq));
            break;

        case 'repuestosSolicitud':
            $id_sm = (int)($_POST['id_solicitud_maquina'] ?? 0);
            if (!$id_sm) response([], true, 'ID de solicitud inválido.');
            response($model->obtenerRepuestosSolicitudMaquina($id_sm));
            break;

        case 'guardar':
            if (!$puedeMantenimiento) response([], true, 'Sin permisos para registrar mantenimientos.');
            $payload = $_POST["losDatos"] ?? null;
            if (!$payload) response([], true, "No se recibieron datos");

            $obj = json_decode($payload);
            if (!$obj) response([], true, "Payload inválido");

            // Extraer tareas antes de pasar el objeto al modelo
            $tareas = isset($obj->tareas) ? (array)$obj->tareas : [];
            unset($obj->tareas);

            // Asegurar que retiros sea array (puede venir del payload o vacío)
            if (!isset($obj->retiros) || !is_array($obj->retiros)) $obj->retiros = [];

            $resp = $model->guardarMantenimiento($obj);

            if (isset($resp["error"]) && $resp["error"] === true) {
                response([], true, $resp["mensaje"]);
            }

            // Guardar tareas vinculadas al mantenimiento recién creado
            if (!empty($tareas)) {
                $model->guardarTareas((int)$resp["id_mantenimiento"], $tareas);
            }

            response($resp, false, "Mantenimiento guardado correctamente");
            break;

        case 'actualizar':
            if (!$puedeMantenimiento) response([], true, 'Sin permisos para actualizar mantenimientos.');
            $id_mant = (int)($_POST['id_mantenimiento'] ?? 0);
            $payload = $_POST["losDatos"] ?? null;
            if (!$id_mant) response([], true, 'ID inválido.');
            if (!$payload) response([], true, "No se recibieron datos");

            $obj = json_decode($payload);
            if (!$obj) response([], true, "Payload inválido");

            // Extraer tareas
            $tareas = isset($obj->tareas) ? (array)$obj->tareas : [];
            unset($obj->tareas);

            // Asegurar que retiros sea array
            if (!isset($obj->retiros) || !is_array($obj->retiros)) $obj->retiros = [];

            $resp = $model->actualizarMantenimiento($id_mant, $obj);

            if (isset($resp["error"]) && $resp["error"] === true) {
                response([], true, $resp["mensaje"]);
            }

            // Actualizar tareas
            if (!empty($tareas)) {
                $model->guardarTareas($id_mant, $tareas);
            }

            response($resp, false, "Mantenimiento actualizado correctamente");
            break;

        case 'verificarAnulacion':
            if (!$esAdmin) response([], true, 'Sin permisos.');
            $id = (int)($_POST['id_mantenimiento'] ?? 0);
            if (!$id) response([], true, 'ID inválido.');
            response($model->verificarAnulacion($id));
            break;

        case 'anular':
            if (!$esAdmin) response([], true, 'Solo el administrador puede anular mantenimientos.');
            $id     = (int)($_POST['id_mantenimiento'] ?? 0);
            $motivo = trim($_POST['motivo'] ?? '');
            if (!$id)     response([], true, 'ID inválido.');
            if (!$motivo) response([], true, 'Debes indicar el motivo de anulación.');
            $model->ejecutarAnulacion($id, $motivo);
            response([], false, 'Mantenimiento anulado correctamente.');
            break;

        default:
            response([], true, "Acción no válida: " . $accion);
    }

} catch (Throwable $e) {
    response([], true, $e->getMessage());
}
