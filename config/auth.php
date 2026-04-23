<?php
/**
 * Guard de autenticación reutilizable.
 * - En páginas HTML: redirige a index.php si no hay sesión.
 * - En controladores JSON: devuelve {"ok":false,"session":false} para que el JS intercepte.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin(bool $asJson = false): void
{
    if (!empty($_SESSION['logueado'])) return;

    if ($asJson) {
        header('Content-Type: application/json');
        echo json_encode([
            'ok'      => false,
            'session' => false,
            'mensaje' => 'Sesión expirada. Por favor vuelve a iniciar sesión.'
        ]);
        exit;
    }

    header('Location: /Electronicas/index.php');
    exit;
}
