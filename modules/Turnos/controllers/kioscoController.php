<?php
// Endpoint público del kiosco de planta (bloqueo.php).
// A propósito NO pasa por config/auth.php: esta pantalla corre
// en la PC de la máquina sin que nadie haya iniciado sesión todavía;
// identifica al operario por PIN, no por sesión de back-office.

header('Content-Type: application/json');
include_once '../models/mdlTurnos.php';

$model   = new mdlTurnos();
$accion  = $_POST['accion'] ?? $_GET['accion'] ?? '';
$estacion = trim($_POST['estacion'] ?? $_GET['estacion'] ?? '');

function resp($data = [], bool $error = false, string $msg = ''): void
{
    echo json_encode(['ok' => !$error, 'data' => $data, 'mensaje' => $msg]);
    exit;
}

if (!$estacion) {
    resp([], true, 'Falta identificar la estación (parámetro "estacion").');
}

try {
    switch ($accion) {

        case 'estado':
            $activa = $model->sesionActivaEn($estacion);
            resp(['activa' => $activa]);
            break;

        case 'desbloquear':
            $pin = trim($_POST['pin'] ?? '');
            if (!preg_match('/^\d{4,6}$/', $pin)) {
                resp([], true, 'Código inválido.');
            }
            $usuario = $model->buscarPorPin($pin);
            if (!$usuario) {
                resp([], true, 'Código incorrecto.');
            }
            $model->abrirSesion((int)$usuario['id_usuario'], $estacion);
            resp(['nombre' => $usuario['nombre'], 'foto' => $usuario['foto']]);
            break;

        case 'bloquear':
            $model->cerrarSesionEnEstacion($estacion);
            resp([], false, 'Estación bloqueada.');
            break;

        default:
            resp([], true, 'Acción no válida.');
    }
} catch (Throwable $e) {
    resp([], true, $e->getMessage());
}
