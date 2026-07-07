<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../model/mdlLogin.php';

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$acceso = false;

if ($username !== '' && $password !== '') {
    try {
        $model   = new mdlLogin();
        $usuario = $model->buscarUsuario($username);

        if ($usuario && password_verify($password, $usuario['password_hash'])) {
            $_SESSION['logueado']   = true;
            $_SESSION['usuario']    = $usuario['username'];
            $_SESSION['nombre']     = $usuario['nombre'];
            $_SESSION['id_usuario'] = (int)$usuario['id_usuario'];
            $_SESSION['foto']       = $usuario['foto'] ?? null;
            $_SESSION['id_rol']                       = $usuario['id_rol'] ? (int)$usuario['id_rol'] : null;
            $_SESSION['nombre_rol']                   = $usuario['nombre_rol'] ?? null;
            $_SESSION['puede_ejecutar_mantenimiento'] = (bool)(int)($usuario['puede_ejecutar_mantenimiento'] ?? 0);

            // Cargar módulos permitidos según el rol asignado
            if (!empty($usuario['id_rol'])) {
                $_SESSION['modulos'] = $model->obtenerModulosRol((int)$usuario['id_rol']);
            } else {
                $_SESSION['modulos'] = null; // sin restricción (usuario legacy)
            }

            $acceso = true;
        }
    } catch (Throwable $e) {
        // Error de BD: tratarlo como credenciales inválidas (no exponer detalle)
        $acceso = false;
    }
}

if ($acceso) {
    header('Location: ../../../selector.php');
} else {
    header('Location: ../../../index.php?error=invalid');
}
exit;
