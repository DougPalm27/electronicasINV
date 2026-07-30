<?php

require_once '../../../config/auth.php';
requireLogin(true);

header('Content-Type: application/json');
include_once '../models/mdlSolicitudesCompra.php';
require_once '../../../config/Mailer.php';

$model    = new mdlSolicitudesCompra();
$mailer   = new Mailer();
$accion   = $_POST['accion'] ?? $_GET['accion'] ?? '';
$esAdmin  = ($_SESSION['nombre_rol'] ?? '') === 'Administrador';
$idUsuario = (int)$_SESSION['id_usuario'];

function resp($data = [], $error = false, $msg = '')
{
    echo json_encode(['ok' => !$error, 'data' => $data, 'mensaje' => $msg]);
    exit;
}

/**
 * Avisa a los admins que una solicitud aún pendiente fue editada
 * y necesita otra revisión. Devuelve el error de correo o null.
 */
function notificarEdicionPendiente(mdlSolicitudesCompra $model, Mailer $mailer, int $id): ?string
{
    try {
        $admins = Mailer::getAdmins($model->getConn());
        if (empty($admins)) return 'No hay admins con correo registrado.';

        $det    = $model->obtenerDetalle($id);
        $codigo = $det['codigo'] ?? "#$id";

        $mailer->send($admins,
            "Solicitud de compra $codigo editada — favor revisar de nuevo",
            Mailer::tplSolicitudCompraEditada([
                'id'          => $id,
                'codigo'      => $codigo,
                'solicitante' => $det['solicitante'] ?? '—',
                'descripcion' => $det['descripcion'] ?? '—',
                'divisa'      => $det['divisa_simbolo'] ?? '',
                'fecha'       => date('d/m/Y H:i'),
                'items'       => $det['items'] ?? [],
            ])
        );
        return null;
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

try {
    switch ($accion) {

        // ── Catálogos ──────────────────────────────────────
        case 'listarProveedores':
            resp($model->listarProveedores());
            break;

        case 'listarDivisas':
            resp($model->listarDivisas());
            break;

        case 'listarRepuestos':
            resp($model->listarRepuestos());
            break;

        // ── Listado principal ──────────────────────────────
        case 'listar':
            $filtroUsuario = $esAdmin ? null : $idUsuario;
            resp($model->listarSolicitudes($filtroUsuario));
            break;

        case 'enviarCorreoManual':
            if (!$esAdmin) resp([], true, 'Sin permisos.');
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) resp([], true, 'ID invalido.');
            $correoManual = trim($_POST['correo'] ?? '');
            if ($correoManual !== '' && !filter_var($correoManual, FILTER_VALIDATE_EMAIL)) {
                resp([], true, 'El correo no tiene un formato valido.');
            }

            $det = $model->obtenerDetalle($id);
            if (empty($det)) resp([], true, 'Solicitud no encontrada.');

            $destinatarios = $correoManual !== ''
                ? [['email' => $correoManual, 'name' => '']]
                : Mailer::getAdmins($model->getConn());
            if (empty($destinatarios)) resp([], true, 'No hay admins con correo registrado.');

            $mailer->send($destinatarios,
                "Solicitud de compra para revision #$id",
                Mailer::tplNuevaSolicitudCompra([
                    'id'          => $id,
                    'solicitante' => $det['solicitante'] ?? '---',
                    'descripcion' => $det['descripcion'] ?? '---',
                    'proveedor'   => $det['proveedor_nombre'] ?? 'Sin especificar',
                    'divisa'      => $det['divisa_simbolo'] ?? '',
                    'fecha'       => date('d/m/Y'),
                    'items'       => $det['items'] ?? [],
                ])
            );
            resp([
                'destinatarios' => array_map(fn($d) => $d['email'] ?? '', $destinatarios)
            ], false, 'Correo enviado correctamente.');
            break;

        // ── Detalle ────────────────────────────────────────
        case 'detalle':
            $id  = (int)($_POST['id'] ?? 0);
            if (!$id) resp([], true, 'ID inválido.');
            $det = $model->obtenerDetalle($id);
            if (empty($det)) resp([], true, 'Solicitud no encontrada.');
            // Técnico solo puede ver las suyas
            if (!$esAdmin && (int)$det['id_usuario'] !== $idUsuario) {
                resp([], true, 'Acceso denegado.');
            }
            resp($det);
            break;

        // ── Guardar borrador ───────────────────────────────
        case 'guardar':
            $payload = json_decode($_POST['payload'] ?? '', true);
            if (!$payload) resp([], true, 'Datos inválidos.');

            $payload['id_usuario'] = $idUsuario;
            $idExistente = !empty($payload['id_solicitud_compra'])
                ? (int)$payload['id_solicitud_compra'] : null;

            $estadoPrevio = null;
            $id = $model->guardarBorrador($payload, $idExistente, $estadoPrevio);

            // Si ya estaba enviada, sigue Pendiente: avisar a los admins que cambió
            if ($estadoPrevio === 'Pendiente') {
                resp(
                    ['id_solicitud_compra' => $id, 'estado' => 'Pendiente',
                     'mail_error' => notificarEdicionPendiente($model, $mailer, $id)],
                    false,
                    'Cambios guardados. Se notificó a los administradores para que la revisen de nuevo.'
                );
            }

            resp(['id_solicitud_compra' => $id, 'estado' => 'Borrador'], false,
                $idExistente ? 'Borrador actualizado.' : 'Borrador guardado.');
            break;

        // ── Guardar y enviar directamente ──────────────────
        case 'guardarEnviar':
            $payload = json_decode($_POST['payload'] ?? '', true);
            if (!$payload) resp([], true, 'Datos inválidos.');

            $payload['id_usuario'] = $idUsuario;
            $idExistente = !empty($payload['id_solicitud_compra'])
                ? (int)$payload['id_solicitud_compra'] : null;

            $estadoPrevio = null;
            $id = $model->guardarBorrador($payload, $idExistente, $estadoPrevio);

            // Ya estaba enviada: no se re-envía, solo se avisa que fue editada
            if ($estadoPrevio === 'Pendiente') {
                resp(
                    ['id_solicitud_compra' => $id, 'estado' => 'Pendiente',
                     'mail_error' => notificarEdicionPendiente($model, $mailer, $id)],
                    false,
                    'Cambios guardados. Se notificó a los administradores para que la revisen de nuevo.'
                );
            }

            $model->enviarSolicitud($id, $idUsuario);

            // Notificar admins
            $mailErr = null;
            try {
                $admins = Mailer::getAdmins($model->getConn());
                if (!empty($admins)) {
                    $det = $model->obtenerDetalle($id);
                    $mailer->send($admins,
                        "Solicitud de compra para revision #$id",
                        Mailer::tplNuevaSolicitudCompra([
                            'id'          => $id,
                            'solicitante' => $_SESSION['nombre'] ?? '—',
                            'descripcion' => $det['descripcion'] ?? '—',
                            'proveedor'   => $det['proveedor_nombre'] ?? 'Sin especificar',
                            'divisa'      => $det['divisa_simbolo'] ?? '',
                            'fecha'       => date('d/m/Y'),
                            'items'       => $det['items'] ?? [],
                        ])
                    );
                } else {
                    $mailErr = 'No hay admins con correo registrado.';
                }
            } catch (Throwable $me) { $mailErr = $me->getMessage(); }

            resp(['id_solicitud_compra' => $id, 'mail_error' => $mailErr], false, 'Solicitud enviada correctamente.');
            break;

        // ── Enviar borrador existente ──────────────────────
        case 'enviar':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) resp([], true, 'ID inválido.');
            $model->enviarSolicitud($id, $idUsuario);

            // Notificar admins
            $mailErr = null;
            try {
                $admins = Mailer::getAdmins($model->getConn());
                if (!empty($admins)) {
                    $det = $model->obtenerDetalle($id);
                    $mailer->send($admins,
                        "Solicitud de compra para revision #$id",
                        Mailer::tplNuevaSolicitudCompra([
                            'id'          => $id,
                            'solicitante' => $_SESSION['nombre'] ?? '—',
                            'descripcion' => $det['descripcion'] ?? '—',
                            'proveedor'   => $det['proveedor_nombre'] ?? 'Sin especificar',
                            'divisa'      => $det['divisa_simbolo'] ?? '',
                            'fecha'       => date('d/m/Y'),
                            'items'       => $det['items'] ?? [],
                        ])
                    );
                } else {
                    $mailErr = 'No hay admins con correo registrado.';
                }
            } catch (Throwable $me) { $mailErr = $me->getMessage(); }

            resp(['mail_error' => $mailErr], false, 'Solicitud enviada.');
            break;

        // ── Aprobar ────────────────────────────────────────
        case 'aprobar':
            if (!$esAdmin) resp([], true, 'Sin permisos.');
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) resp([], true, 'ID inválido.');
            $model->aprobarSolicitud($id, $idUsuario);

            // Notificar al solicitante
            $mailErr = null;
            try {
                $sol = $model->obtenerEmailSolicitante($id);
                if (!empty($sol['email'])) {
                    $mailer->send($sol['email'],
                        "Solicitud de compra #$id — Aprobada",
                        Mailer::tplRespuestaSolicitudCompra([
                            'id'      => $id,
                            'estado'  => 'Aprobada',
                            'revisor' => $_SESSION['nombre'] ?? '—',
                        ])
                    );
                } else {
                    $mailErr = 'El solicitante no tiene correo registrado.';
                }
            } catch (Throwable $me) { $mailErr = $me->getMessage(); }

            resp(['mail_error' => $mailErr], false, 'Solicitud aprobada.');
            break;

        // ── Rechazar ───────────────────────────────────────
        case 'rechazar':
            if (!$esAdmin) resp([], true, 'Sin permisos.');
            $id     = (int)($_POST['id'] ?? 0);
            $motivo = trim($_POST['motivo'] ?? '');
            if (!$id || !$motivo) resp([], true, 'ID o motivo faltante.');
            $model->rechazarSolicitud($id, $idUsuario, $motivo);

            // Notificar al solicitante
            $mailErr = null;
            try {
                $sol = $model->obtenerEmailSolicitante($id);
                if (!empty($sol['email'])) {
                    $mailer->send($sol['email'],
                        "Solicitud de compra #$id — Rechazada",
                        Mailer::tplRespuestaSolicitudCompra([
                            'id'      => $id,
                            'estado'  => 'Rechazada',
                            'revisor' => $_SESSION['nombre'] ?? '—',
                            'motivo'  => $motivo,
                        ])
                    );
                } else {
                    $mailErr = 'El solicitante no tiene correo registrado.';
                }
            } catch (Throwable $me) { $mailErr = $me->getMessage(); }

            resp(['mail_error' => $mailErr], false, 'Solicitud rechazada.');
            break;

        // ── Registrar orden al proveedor ───────────────────
        case 'registrarOrden':
            if (!$esAdmin) resp([], true, 'Sin permisos.');
            $id           = (int)($_POST['id'] ?? 0);
            $numero_orden = trim($_POST['numero_orden'] ?? '');
            $fechas_items = json_decode($_POST['fechas_items'] ?? '[]', true);
            if (!$id) resp([], true, 'ID inválido.');
            $model->registrarOrden($id, $numero_orden, is_array($fechas_items) ? $fechas_items : []);
            resp([], false, 'Orden registrada correctamente.');
            break;

        // ── Registrar recepción ────────────────────────────
        case 'registrarRecepcion':
            if (!$esAdmin) resp([], true, 'Sin permisos.');
            $id          = (int)($_POST['id'] ?? 0);
            $recepciones = json_decode($_POST['recepciones'] ?? '[]', true);
            if (!$id || !is_array($recepciones)) resp([], true, 'Datos inválidos.');

            $result = $model->registrarRecepcion($id, $idUsuario, $recepciones);

            if (!empty($result['error'])) {
                resp([], true, $result['mensaje']);
            }

            // Notificar al solicitante
            $mailErr = null;
            try {
                $sol = $model->obtenerEmailSolicitante($id);
                if (!empty($sol['email'])) {
                    $estadoRec = $result['estado'] ?? 'Recibida parcial';
                    $mailer->send($sol['email'],
                        "Recepción registrada — Solicitud de compra #$id",
                        Mailer::tplRecepcionCompra(['id' => $id, 'estado' => $estadoRec])
                    );
                } else {
                    $mailErr = 'El solicitante no tiene correo registrado.';
                }
            } catch (Throwable $me) { $mailErr = $me->getMessage(); }

            $msg = 'Recepción registrada. Inventario actualizado.';
            if (!empty($result['warnings'])) {
                $msg .= ' Avisos: ' . implode(' | ', $result['warnings']);
            }
            resp(['mail_error' => $mailErr], false, $msg);
            break;

        // ── Cerrar recepción parcial ───────────────────────
        case 'cerrarRecepcion':
            if (!$esAdmin) resp([], true, 'Sin permisos.');
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) resp([], true, 'ID inválido.');
            $model->cerrarRecepcion($id, $idUsuario);
            resp([], false, 'Recepción cerrada. La solicitud quedó marcada como Recibida.');
            break;

        // ── Cancelar ───────────────────────────────────────
        case 'cancelar':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) resp([], true, 'ID inválido.');
            $model->cancelarSolicitud($id, $idUsuario, $esAdmin);
            resp([], false, 'Solicitud cancelada.');
            break;

        default:
            resp([], true, "Acción no válida: $accion");
    }

} catch (Throwable $e) {
    resp([], true, $e->getMessage());
}
