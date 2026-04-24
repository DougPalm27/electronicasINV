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

        case 'crear':
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password']        ?? '';
            $nombre   = trim($_POST['nombre']     ?? '');

            if (!$username || !$password || !$nombre) {
                resp([], true, 'Todos los campos son obligatorios.');
            }
            if (strlen($password) < 6) {
                resp([], true, 'La contraseña debe tener al menos 6 caracteres.');
            }

            $model->crear($username, $password, $nombre);
            resp([], false, 'Usuario creado correctamente.');
            break;

        case 'editarNombre':
            $id     = (int)($_POST['id_usuario'] ?? 0);
            $nombre = trim($_POST['nombre']      ?? '');

            if (!$id || !$nombre) resp([], true, 'Datos incompletos.');

            $model->editarNombre($id, $nombre);
            resp([], false, 'Nombre actualizado.');
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
