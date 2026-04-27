<?php
require_once '../../../config/auth.php';
requireLogin(true);

header('Content-Type: application/json');
include_once '../models/mdlRoles.php';

$model  = new mdlRoles();
$accion = $_POST['accion'] ?? '';

function respR($data = [], bool $error = false, string $msg = ''): void
{
    echo json_encode(['ok' => !$error, 'data' => $data, 'mensaje' => $msg]);
    exit;
}

try {
    switch ($accion) {

        // ── Listar roles ────────────────────────────────────
        case 'listar':
            respR($model->listarRoles());
            break;

        // ── Listar módulos ──────────────────────────────────
        case 'listarModulos':
            respR($model->listarModulos());
            break;

        // ── Permisos de un rol ──────────────────────────────
        case 'obtenerPermisos':
            $id = (int)($_POST['id_rol'] ?? 0);
            if (!$id) respR([], true, 'ID de rol inválido.');
            respR([
                'asignados' => $model->obtenerPermisosRol($id),
                'modulos'   => $model->listarModulos()
            ]);
            break;

        // ── Guardar permisos ────────────────────────────────
        case 'guardarPermisos':
            $id_rol      = (int)($_POST['id_rol'] ?? 0);
            $ids_modulos = $_POST['ids_modulos'] ?? [];

            if (!$id_rol) respR([], true, 'ID de rol inválido.');

            if (!is_array($ids_modulos)) {
                $ids_modulos = $ids_modulos ? explode(',', $ids_modulos) : [];
            }

            $model->guardarPermisosRol($id_rol, $ids_modulos);
            respR([], false, 'Permisos actualizados.');
            break;

        // ── Crear rol ───────────────────────────────────────
        case 'crear':
            $nombre      = trim($_POST['nombre']      ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');

            if (!$nombre) respR([], true, 'El nombre del rol es requerido.');

            $id = $model->crearRol($nombre, $descripcion);
            respR(['id_rol' => $id], false, "Rol '$nombre' creado.");
            break;

        // ── Editar rol ──────────────────────────────────────
        case 'editar':
            $id          = (int)($_POST['id_rol']       ?? 0);
            $nombre      = trim($_POST['nombre']         ?? '');
            $descripcion = trim($_POST['descripcion']    ?? '');

            if (!$id)     respR([], true, 'ID inválido.');
            if (!$nombre) respR([], true, 'El nombre es requerido.');

            $model->editarRol($id, $nombre, $descripcion);
            respR([], false, 'Rol actualizado.');
            break;

        // ── Toggle activo ───────────────────────────────────
        case 'toggleActivo':
            $id = (int)($_POST['id_rol'] ?? 0);
            if (!$id) respR([], true, 'ID inválido.');
            $model->toggleActivoRol($id);
            respR([], false, 'Estado actualizado.');
            break;

        default:
            respR([], true, "Acción no válida: $accion");
    }
} catch (Throwable $e) {
    respR([], true, $e->getMessage());
}
