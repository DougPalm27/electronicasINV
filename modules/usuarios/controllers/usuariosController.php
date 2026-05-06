<?php
require_once '../../../config/auth.php';
requireLogin(true);

header('Content-Type: application/json');
include_once '../models/mdlUsuarios.php';

$model  = new mdlUsuarios();
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

function resp($data = [], bool $error = false, string $msg = ''): void
{
    echo json_encode(['ok' => !$error, 'data' => $data, 'mensaje' => $msg]);
    exit;
}

try {
    switch ($accion) {

        case 'listar':
            resp($model->listar());
            break;

        case 'listarRoles':
            resp($model->listarRoles());
            break;

        case 'crear':
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password']        ?? '';
            $nombre   = trim($_POST['nombre']     ?? '');
            $email    = trim($_POST['email']      ?? '') ?: null;
            $id_rol   = $_POST['id_rol'] ?? null;
            $id_rol   = ($id_rol !== null && $id_rol !== '') ? (int)$id_rol : null;

            if (!$username || !$password || !$nombre) {
                resp([], true, 'Todos los campos son obligatorios.');
            }
            if (strlen($password) < 6) {
                resp([], true, 'La contraseña debe tener al menos 6 caracteres.');
            }
            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                resp([], true, 'El correo electrónico no es válido.');
            }

            $model->crear($username, $password, $nombre, $id_rol, $email);
            resp([], false, 'Usuario creado correctamente.');
            break;

        case 'editarNombre':
            $id     = (int)($_POST['id_usuario'] ?? 0);
            $nombre = trim($_POST['nombre']      ?? '');
            $email  = trim($_POST['email']       ?? '') ?: null;
            $id_rol = $_POST['id_rol'] ?? null;
            $id_rol = ($id_rol !== null && $id_rol !== '') ? (int)$id_rol : null;

            if (!$id || !$nombre) resp([], true, 'Datos incompletos.');
            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                resp([], true, 'El correo electrónico no es válido.');
            }

            $model->editarNombre($id, $nombre, $id_rol, $email);
            resp([], false, 'Usuario actualizado.');
            break;

        case 'asignarRol':
            $id     = (int)($_POST['id_usuario'] ?? 0);
            $id_rol = $_POST['id_rol'] ?? null;
            $id_rol = ($id_rol !== null && $id_rol !== '') ? (int)$id_rol : null;

            if (!$id) resp([], true, 'ID inválido.');
            $model->asignarRol($id, $id_rol);
            resp([], false, 'Rol asignado.');
            break;

        case 'resetPassword':
            $id       = (int)($_POST['id_usuario']      ?? 0);
            $password = $_POST['password']               ?? '';

            if (!$id || strlen($password) < 6) {
                resp([], true, 'La contraseña debe tener al menos 6 caracteres.');
            }

            $model->resetPassword($id, $password);
            resp([], false, 'Contraseña restablecida.');
            break;

        case 'toggleActivo':
            $id = (int)($_POST['id_usuario'] ?? 0);
            if (!$id) resp([], true, 'ID inválido.');
            $model->toggleActivo($id);
            resp([], false, 'Estado actualizado.');
            break;

        case 'cambiarPassword':
            $id     = (int)$_SESSION['id_usuario'];
            $actual = $_POST['password_actual'] ?? '';
            $nueva  = $_POST['password_nueva']  ?? '';
            $conf   = $_POST['confirmar']        ?? '';

            if (!$actual || !$nueva || !$conf) {
                resp([], true, 'Completa todos los campos.');
            }
            if ($nueva !== $conf) {
                resp([], true, 'Las contraseñas nuevas no coinciden.');
            }
            if (strlen($nueva) < 6) {
                resp([], true, 'La contraseña debe tener al menos 6 caracteres.');
            }

            $model->cambiarPassword($id, $actual, $nueva);
            resp([], false, '¡Contraseña actualizada correctamente!');
            break;

        default:
            resp([], true, 'Acción no válida.');
    }
} catch (Throwable $e) {
    resp([], true, $e->getMessage());
}
