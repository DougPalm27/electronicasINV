<?php
require_once '../../../../config/auth.php';
requireLogin(true);
header('Content-Type: application/json; charset=UTF-8');
include_once '../models/mdlMapa.php';
include_once '../models/mdlDespachos.php';
require_once '../adapters/AdapterFactory.php';

$model  = new mdlMapa();       // adaptadores, credenciales de cuenta, caché
$desp   = new mdlDespachos();  // despachos
$accion = $_POST['accion'] ?? '';
$uid    = (int)($_SESSION['id_usuario'] ?? 0);

const FRESCURA_SEG = 1800; // 30 min

function respM($data = [], bool $error = false, string $msg = ''): void {
    echo json_encode(
        ['ok' => !$error, 'data' => $data, 'mensaje' => $msg],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}
function esFresco(?string $fecha): bool {
    if (!$fecha) return false;
    $ts = strtotime($fecha);
    return $ts && (time() - $ts) <= FRESCURA_SEG;
}
function estadoSeg(string $motor, ?array $pos): string {
    if ($pos && esFresco($pos['fecha'] ?? null)) return 'live';
    if (AdapterFactory::tieneVivo($motor)) return 'sin_senal';
    return 'pendiente';
}
function posDeFila(array $r): ?array {
    if ($r['lat'] === null || $r['lng'] === null) return null;
    return ['lat'=>(float)$r['lat'],'lng'=>(float)$r['lng'],'velocidad'=>$r['velocidad'],
            'rumbo'=>$r['rumbo'],'encendido'=>$r['encendido'],'direccion'=>$r['direccion'],'fecha'=>$r['fecha']];
}
function armarRegistro(array $v, ?array $pos, bool $historico = false): array {
    $motor = (string)($v['tipo_integracion'] ?? '');
    return [
        'id_dv'         => (int)$v['id_dv'],
        'id_despacho'   => (int)$v['id_despacho'],
        'despacho'      => $v['despacho'] ?? null,
        'estado_despacho' => $v['estado_despacho'] ?? null,
        'fecha_apertura'=> $v['fecha_apertura'] ?? null,
        'fecha_cierre'  => $v['fecha_cierre'] ?? null,
        'placa'         => $v['placa'],
        'imei'          => $v['imei'] ?? null,
        'transporte'    => $v['transporte'],
        'plataforma'    => $v['plataforma'],
        'motor'         => $motor,
        'soporta_vivo'  => !$historico && AdapterFactory::tieneVivo($motor),
        'historico'     => $historico,
        'estado_seg'    => $historico ? 'historial' : estadoSeg($motor, $pos),
        'lat'           => $pos['lat']       ?? null,
        'lng'           => $pos['lng']       ?? null,
        'velocidad'     => $pos['velocidad'] ?? null,
        'rumbo'         => $pos['rumbo']     ?? null,
        'encendido'     => $pos['encendido'] ?? null,
        'direccion'     => $pos['direccion'] ?? null,
        'fecha'         => $pos['fecha']     ?? null,
    ];
}
function guardarRecorridoSeguro(mdlDespachos $desp, array $vehiculo, ?array $pos): void {
    if (!$pos || ($pos['lat'] ?? null) === null || ($pos['lng'] ?? null) === null) return;
    try { $desp->guardarPuntoRecorrido($vehiculo, $pos); } catch (Throwable $e) { /* migracion pendiente: no bloquea el mapa */ }
}

try {
    switch ($accion) {

        // ── Despachos ───────────────────────────────────────────
        case 'despachos':
            $estado = ($_POST['estado'] ?? 'activo') === 'cerrado' ? 'cerrado' : 'activo';
            respM(['despachos' => $desp->listar($estado)]);
            break;

        case 'crearDespacho':
            $nombre = trim($_POST['nombre'] ?? '');
            if ($nombre === '') respM([], true, 'Ponle un nombre al despacho.');
            $id = $desp->crear($nombre, $uid);
            respM(['id_despacho' => $id, 'nombre' => $nombre], false, "Despacho \"$nombre\" creado.");
            break;

        case 'cerrarDespacho':
            $id = (int)($_POST['id_despacho'] ?? 0);
            if (!$id) respM([], true, 'Despacho no especificado.');
            $desp->cerrar($id);
            respM([], false, 'Despacho cerrado. Se detuvo el seguimiento.');
            break;

        case 'quitarVehiculo':
            $id_dv = (int)($_POST['id_dv'] ?? 0);
            if (!$id_dv) respM([], true, 'Vehículo no especificado.');
            $desp->quitarVehiculo($id_dv);
            respM([], false, 'Vehículo quitado del despacho.');
            break;

        case 'recorrido':
            $id_dv = (int)($_POST['id_dv'] ?? 0);
            if (!$id_dv) respM([], true, 'Vehículo no especificado.');
            respM(['puntos' => $desp->recorridoVehiculo($id_dv)]);
            break;

        case 'agregarADespacho':
            $id_despacho = (int)($_POST['id_despacho'] ?? 0);
            $items       = json_decode($_POST['items'] ?? '[]', true);
            if (!$id_despacho)                       respM([], true, 'Despacho no especificado.');
            if (!is_array($items) || !count($items)) respM([], true, 'No seleccionaste vehículos.');
            $r = $desp->agregarVehiculos($id_despacho, $items, $uid);
            $msg = $r['agregados'] === 1 ? '1 vehículo agregado al despacho.'
                 : "{$r['agregados']} vehículos agregados al despacho.";
            if ($r['omitidos']) $msg .= " ({$r['omitidos']} ya estaban).";
            respM($r, false, $msg);
            break;

        // ── Posiciones del despacho (o de todos los activos) ────
        case 'cache':
            $id_despacho = ($_POST['id_despacho'] ?? '') === '' ? null : (int)$_POST['id_despacho'];
            $historico = ($_POST['historico'] ?? '0') === '1';
            $out = [];
            foreach ($desp->vehiculos($id_despacho, $historico) as $r) $out[] = armarRegistro($r, posDeFila($r), $historico);
            respM(['vehiculos' => $out]);
            break;

        case 'posiciones':
            @set_time_limit(120);
            $id_despacho = ($_POST['id_despacho'] ?? '') === '' ? null : (int)$_POST['id_despacho'];
            $historico = ($_POST['historico'] ?? '0') === '1';
            if ($historico) {
                $out = [];
                foreach ($desp->vehiculos($id_despacho, true) as $r) $out[] = armarRegistro($r, posDeFila($r), true);
                respM(['vehiculos' => $out]);
            }
            $vehiculos = $desp->vehiculos($id_despacho);

            // Agrupar por cuenta
            $grupos = [];
            foreach ($vehiculos as $v) $grupos[$v['id_cuenta']][] = $v;

            $out = [];
            $resumen = ['cuentas'=>0,'ok'=>0,'error'=>0,'en_vivo'=>0,'pendientes'=>0,'errores'=>[]];

            foreach ($grupos as $id_cuenta => $vs) {
                $motor = (string)($vs[0]['tipo_integracion'] ?? '');

                // (a) gps-server: consultar plataforma en vivo
                if (AdapterFactory::soportaPosiciones($motor)) {
                    $resumen['cuentas']++;
                    $c = $model->cuentaCredenciales((int)$id_cuenta);
                    $adapter = AdapterFactory::crear($motor, $c['api_url'], $c['usuario'], $c['contrasena']);
                    try {
                        $lecturas = $adapter->obtenerPosiciones();
                        $resumen['ok']++;
                        $porImei = []; $porPlaca = [];
                        foreach ($lecturas as $p) {
                            if (($p['imei'] ?? '') !== '') $porImei[$p['imei']] = $p;
                            $porPlaca[strtoupper(trim($p['dispositivo'] ?? ''))] = $p;
                        }
                        foreach ($vs as $v) {
                            $pos = null;
                            if (!empty($v['imei']) && isset($porImei[$v['imei']]))   $pos = $porImei[$v['imei']];
                            elseif (isset($porPlaca[strtoupper(trim($v['placa']))])) $pos = $porPlaca[strtoupper(trim($v['placa']))];
                            if ($pos) {
                                $model->guardarPosicion([
                                    'id_cuenta'=>(int)$id_cuenta,'id_gps'=>null,
                                    'dispositivo'=>$pos['dispositivo'],'imei'=>$pos['imei'],'placa'=>$v['placa'],
                                    'lat'=>$pos['lat'],'lng'=>$pos['lng'],'velocidad'=>$pos['velocidad'],
                                    'rumbo'=>$pos['rumbo'],'encendido'=>$pos['encendido'],
                                    'direccion'=>$pos['direccion'],'fecha'=>$pos['fecha'],
                                ]);
                                guardarRecorridoSeguro($desp, $v, $pos);
                                $resumen['en_vivo']++;
                            }
                            $out[] = armarRegistro($v, $pos);
                        }
                    } catch (Throwable $e) {
                        $resumen['error']++;
                        $resumen['errores'][] = ['plataforma'=>$vs[0]['plataforma'],'transporte'=>$vs[0]['transporte'],'mensaje'=>$e->getMessage()];
                        foreach ($vs as $v) $out[] = armarRegistro($v, posDeFila($v));
                    }
                }
                // (b) Optimus u otros worker-fed: usar caché
                elseif (AdapterFactory::soportaWorker($motor)) {
                    foreach ($vs as $v) {
                        $reg = armarRegistro($v, posDeFila($v));
                        guardarRecorridoSeguro($desp, $v, posDeFila($v));
                        if ($reg['estado_seg'] === 'live') $resumen['en_vivo']++;
                        $out[] = $reg;
                    }
                }
                // (c) sin integración
                else {
                    foreach ($vs as $v) { $out[] = armarRegistro($v, null); $resumen['pendientes']++; }
                }
            }
            respM(['vehiculos' => $out, 'resumen' => $resumen]);
            break;

        // ── Selector ────────────────────────────────────────────
        case 'cuentas':
            $out = [];
            foreach ($model->cuentasSelector() as $c) {
                if (!AdapterFactory::soportaListado($c['tipo_integracion'])) continue;
                $out[] = $c;
            }
            respM(['cuentas' => $out]);
            break;

        case 'dispositivos':
            $id_cuenta   = (int)($_POST['id_cuenta'] ?? 0);
            $id_despacho = (int)($_POST['id_despacho'] ?? 0);
            if (!$id_cuenta) respM([], true, 'Cuenta no especificada.');

            $c = $model->cuentaCredenciales($id_cuenta);
            if (!$c) respM([], true, 'Cuenta no encontrada.');
            if (!AdapterFactory::soportaListado($c['tipo_integracion']))
                respM([], true, 'El listado de equipos no está disponible para esta plataforma.');

            $adapter = AdapterFactory::crear($c['tipo_integracion'], $c['api_url'], $c['usuario'], $c['contrasena']);
            $devs    = $adapter->listarDispositivos();
            $vin     = $id_despacho ? $desp->vinculadosDeDespacho($id_despacho) : ['placas'=>[], 'imeis'=>[]];

            $conNombreCompleto = !in_array($c['tipo_integracion'], ['optimus', 'tracksolid'], true);
            $out = [];
            foreach ($devs as $d) {
                $nombre = trim($d['dispositivo']);
                // Optimus/TrackSolid: el nombre es "PLACA alias" → placa = 1er token.
                // Los demás: el nombre ES la etiqueta (quitar sufijo de batería " (NN%)").
                $placa = $conNombreCompleto
                    ? strtoupper(trim(preg_replace('/\s*\(\d+%\)\s*$/', '', $nombre)))
                    : strtoupper(trim(strtok($nombre, ' ')));
                $imei  = trim($d['imei']);
                // Marcar "ya vinculado" por IMEI (único) si el equipo lo tiene;
                // solo por placa cuando no hay IMEI (evita colisión de equipos "gps").
                $ya = $imei !== ''
                    ? isset($vin['imeis'][$id_cuenta . '|' . $imei])
                    : isset($vin['placas'][$id_cuenta . '|' . $placa]);
                $out[] = ['id_cuenta'=>$id_cuenta, 'dispositivo'=>$nombre, 'placa'=>$placa, 'imei'=>$imei, 'ya'=>$ya];
            }
            respM([
                'cuenta' => [
                    'id_cuenta'  => $id_cuenta,
                    'transporte' => $c['transporte'],
                    'plataforma' => $c['plataforma'],
                    'usuario'    => $c['usuario'],
                    'motor'      => $c['tipo_integracion'],
                ],
                'plataforma' => $c['plataforma'],
                'transporte' => $c['transporte'],
                'usuario'    => $c['usuario'],
                'dispositivos' => $out
            ]);
            break;

        default:
            respM([], true, "Acción no válida: $accion");
    }
} catch (Throwable $e) { respM([], true, $e->getMessage()); }
