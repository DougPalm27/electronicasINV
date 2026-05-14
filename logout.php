<?php
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Eliminar la cookie de sesión del navegador
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Vaciar y destruir la sesión en el servidor
$_SESSION = [];
session_destroy();

ob_end_clean();

// Calcula la ruta base según donde esté instalado el proyecto
// En local:  /Electronicas/index.php
// En server: /index.php  (o la subcarpeta que corresponda)
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
header('Location: ' . $basePath . '/index.php');
exit;
