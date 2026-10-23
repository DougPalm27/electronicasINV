<?php
require_once "../../../../config/auth.php";
requireLogin(true);

include_once "../../../../config/Connection.php";
include_once "../Models/mdlMaquinas.php";

// Baja un archivo .vnc para abrir la pantalla de la PC de la máquina con el visor VNC
// (TightVNC / UltraVNC). Va por GET para poder usarse como enlace, también desde el visor 3D del plano.
// Sin contraseña: la pide el visor al conectar. Solo administradores.
function salirTexto(int $codigo, string $mensaje): void
{
    http_response_code($codigo);
    header('Content-Type: text/plain; charset=utf-8');
    echo $mensaje;
    exit;
}

if (($_SESSION['nombre_rol'] ?? '') !== 'Administrador') {
    salirTexto(403, 'Solo un administrador puede conectarse a las PCs de planta.');
}

$id_maquina = (int) ($_GET['id'] ?? 0);
if ($id_maquina <= 0) {
    salirTexto(400, 'Máquina no válida.');
}

$maquina = new mdlMaquinas();
$r = $maquina->datosRemoto($id_maquina);
if ($r === null) {
    salirTexto(404, 'Esta máquina no tiene equipo remoto: escribe su nombre de PC o IP en Máquinas > Editar.');
}

$host   = preg_replace('/[^A-Za-z0-9.\-]/', '', (string) $r['host']);
$puerto = (int) $r['puerto'];
$archivo = preg_replace('/[^A-Za-z0-9_\-]+/', '_', (string) $r['nombre']) ?: 'maquina';

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $archivo . '.vnc"');
header('Cache-Control: no-store');
echo "[connection]\r\nhost={$host}\r\nport={$puerto}\r\n[options]\r\nviewonly=0\r\nshared=1\r\n";
