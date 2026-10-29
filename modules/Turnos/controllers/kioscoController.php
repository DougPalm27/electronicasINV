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

// El instalador (Inno Setup) no tiene JSON: sus dos acciones contestan en texto plano.
// Primera línea "OK" (o "ERROR|mensaje"); luego, si aplica, una fila por línea con campos separados por "|".
function respTexto(string $primera, array $filas = []): void
{
    header('Content-Type: text/plain; charset=utf-8');
    echo $primera . "\n";
    foreach ($filas as $f) echo $f . "\n";
    exit;
}

function limpiarCampo($v): string
{
    return trim(preg_replace('/[|\r\n]+/u', ' ', (string)$v));
}

// Identificador de la PC (MachineGuid de Windows o, a falta de él, su nombre)
function equipoValido(): string
{
    $id = trim((string)($_POST['equipo_id'] ?? ''));
    if (!preg_match('/^[0-9A-Za-z\-{}]{8,64}$/', $id)) respTexto('ERROR|Identificador de equipo no válido.');
    return $id;
}

// Última versión del candado publicada CON FIRMA: descargas/Setup-CandadoOperario-X.exe y su
// Setup-CandadoOperario-X.exe.firma (lo genera firmar-instalador.ps1 al publicar). El servidor
// solo repite lo que dice el archivo .firma; quien decide si es auténtico es el candado, que
// comprueba la firma con su llave pública. Un instalador sin .firma no se ofrece como actualización.
function ultimaActualizacionFirmada(): array
{
    $dir   = dirname(__DIR__, 3) . '/descargas';
    $mejor = [];
    foreach (glob($dir . '/Setup-CandadoOperario-*.exe.firma') ?: [] as $f) {
        $m = json_decode((string)@file_get_contents($f), true);
        if (!is_array($m)) continue;
        $archivo = (string)($m['archivo'] ?? '');
        $version = (string)($m['version'] ?? '');
        $sha     = (string)($m['sha256'] ?? '');
        $firma   = (string)($m['firma'] ?? '');
        if (!preg_match('/^Setup-CandadoOperario-(\d+(?:\.\d+){1,3})\.exe$/', $archivo, $mm) || $mm[1] !== $version) continue;
        if (!preg_match('/^[0-9a-f]{64}$/', $sha) || !preg_match('#^[A-Za-z0-9+/=]{100,1000}$#', $firma)) continue;
        if (!is_file($dir . '/' . $archivo)) continue;
        if (!$mejor || version_compare($version, $mejor['version'], '>')) {
            $mejor = ['version' => $version, 'archivo' => $archivo, 'sha256' => $sha, 'firma' => $firma];
        }
    }
    return $mejor;
}

try {
    switch ($accion) {

        // PCs de planta: ¿hay una versión nueva firmada? (la pide la tarea de actualización)
        case 'actualizacion':
            exigirToken();
            resp(ultimaActualizacionFirmada());
            break;

        // Instalador: máquinas que esta PC puede elegir (las habilitadas y aún sin candado en otra PC)
        case 'maquinas':
            exigirToken();
            $filas = [];
            foreach ($model->maquinasParaInstalar(equipoValido()) as $m) {
                $filas[] = implode('|', [
                    (int)$m['id_maquina'], limpiarCampo($m['nombre']), limpiarCampo($m['modelo']),
                    limpiarCampo($m['ubicacion']), limpiarCampo($m['serie']), limpiarCampo($m['estacion']),
                    (int)$m['mia'],
                ]);
            }
            respTexto('OK', $filas);
            break;

        // Instalador: antes de continuar, ¿esta estación sigue libre para esta PC?
        case 'estacionDisponible':
            exigirToken();
            $r = $model->estacionDisponible($estacion, equipoValido());
            respTexto($r['ok'] ? 'OK' : 'ERROR|' . $r['mensaje']);
            break;

        // Instalador: anota que esta PC quedó con el candado de la máquina de esa estación
        case 'registrarInstalacion':
            exigirToken();
            $equipo   = equipoValido();
            $nombrePc = mb_substr((string)preg_replace('/[^\p{L}\p{N} _.\-]/u', '', (string)($_POST['equipo_nombre'] ?? '')), 0, 100);
            $version  = (string)($_POST['version'] ?? '');
            if (!preg_match('/^[0-9.]{1,20}$/', $version)) $version = '';
            $r = $model->registrarCandado($estacion, $equipo, $nombrePc, $version);
            respTexto($r['ok'] ? 'OK' : 'ERROR|' . $r['mensaje']);
            break;

        // El candado avisa que alguien entró con el código de emergencia (queda en logs/emergencias.log)
        case 'emergencia':
            exigirToken();
            $fecha = preg_replace('/[^0-9\-: ]/', '', (string)($_POST['fecha'] ?? ''));
            $dir = dirname(__DIR__, 3) . '/logs';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $ok = @file_put_contents($dir . '/emergencias.log',
                date('Y-m-d H:i:s') . ' | estación: ' . $estacion . ' | momento en la PC: ' . $fecha . PHP_EOL,
                FILE_APPEND | LOCK_EX);
            resp([], $ok === false, $ok === false ? 'No se pudo registrar.' : '');
            break;

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
                    'nivel'      => (int)($op['nivel_delvis'] ?? 0),   // nivel de Delvis que el candado escribe por él
                ];
            }
            // Código de emergencia de la planta (KIOSCO_EMERGENCIA en .env): viaja solo su huella.
            $res = ['usuarios' => $usuarios];
            $emer = (string)env('KIOSCO_EMERGENCIA', '');
            if (preg_match('/^\d{6}$/', $emer)) {
                $salt = bin2hex(random_bytes(8));
                $res['emergencia'] = ['salt' => $salt, 'hash' => hash('sha256', $salt . $emer)];
            }
            resp($res);
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
