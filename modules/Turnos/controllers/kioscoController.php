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
// El nombre se muestra luego en el panel: solo letras, números, espacio, punto, guion y guion bajo
if (!preg_match('/^[\p{L}\p{N} _.\-]{1,100}$/u', $estacion)) {
    resp([], true, 'Nombre de estación no válido.');
}

// sync y subirTurnos exponen/aceptan datos de toda la planta: solo con el
// token compartido que se instala en cada PC (KIOSCO_TOKEN en .env).
function exigirToken(): void
{
    $esperado = (string)env('KIOSCO_TOKEN', '');
    $recibido = (string)($_POST['token'] ?? '');
    if (strlen($esperado) < 16 || !hash_equals($esperado, $recibido)) {
        resp([], true, 'Token inválido.');
    }
}

try {
    switch ($accion) {

        case 'sync':
            exigirToken();
            $usuarios = [];
            foreach ($model->operariosParaSync() as $op) {
                $salt = bin2hex(random_bytes(8));
                $usuarios[] = [
                    'id_usuario' => (int)$op['id_usuario'],
                    'nombre'     => $op['nombre'],
                    'salt'       => $salt,
                    'hash'       => hash('sha256', $salt . $op['pin_bloqueo']),
                ];
            }
            resp(['usuarios' => $usuarios]);
            break;

        case 'subirTurnos':
            exigirToken();
            $turnos = json_decode($_POST['turnos'] ?? '[]', true);
            if (!is_array($turnos)) resp([], true, 'Formato inválido.');
            $r = $model->subirTurnos($turnos);

            // Eventos de Delvis del turno: van después, porque necesitan que el turno ya exista
            $eventos = json_decode($_POST['eventos'] ?? '[]', true);
            if (is_array($eventos) && $eventos) {
                $re = $model->subirEventos($eventos);
                $r['eventos_procesados'] = $re['procesados'];
                $r['eventos_rechazados'] = $re['rechazados'];
            }
            resp($r);
            break;

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
