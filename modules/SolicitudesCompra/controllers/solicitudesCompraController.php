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

            $id = $model->guardarBorrador($payload, $idExistente);
            resp(['id_solicitud_compra' => $id], false,
                $idExistente ? 'Borrador actualizado.' : 'Borrador guardado.');
            break;

        // ── Guardar y enviar directamente ──────────────────
        case 'guardarEnviar':
            $payload = json_decode($_POST['payload'] ?? '', true);
            if (!$payload) resp([], true, 'Datos inválidos.');

            $payload['id_usuario'] = $idUsuario;
            $idExistente = !empty($payload['id_solicitud_compra'])
                ? (int)$payload['id_solicitud_compra'] : null;

            $id = $model->guardarBorrador($payload, $idExistente);
            $model->enviarSolicitud($id, $idUsuario);

            // Notificar admins
            try {
                $admins = Mailer::getAdmins($model->getConn());
                if (!empty($admins)) {
                    $det = $model->obtenerDetalle($id);
                    $mailer->send($admins,
                        "Nueva solicitud de compra #$id",
                        Mailer::tplNuevaSolicitudCompra([
                            'id'          => $id,
                            'solicitante' => $_SESSION['nombre'] ?? '—',
                            'descripcion' => $det['descripcion'] ?? '—',
                            'proveedor'   => $det['proveedor']   ?? 'Sin especificar',
                            'fecha'       => date('d/m/Y'),
                        ])
                    );
                }
            } catch (Throwable) {}

            resp(['id_solicitud_compra' => $id], false, 'Solicitud enviada correctamente.');
            break;

        // ── Enviar borrador existente ──────────────────────
        case 'enviar':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) resp([], true, 'ID inválido.');
            $model->enviarSolicitud($id, $idUsuario);

            // Notificar admins
            try {
                $admins = Mailer::getAdmins($model->getConn());
                if (!empty($admins)) {
                    $det = $model->obtenerDetalle($id);
                    $mailer->send($admins,
                        "Nueva solicitud de compra #$id",
                        Mailer::tplNuevaSolicitudCompra([
                            'id'          => $id,
                            'solicitante' => $_SESSION['nombre'] ?? '—',
                            'descripcion' => $det['descripcion'] ?? '—',
                            'proveedor'   => $det['proveedor']   ?? 'Sin especificar',
                            'fecha'       => date('d/m/Y'),
                        ])
                    );
                }
            } catch (Throwable) {}

            resp([], false, 'Solicitud enviada.');
            break;

        // ── Aprobar ────────────────────────────────────────
        case 'aprobar':
            if (!$esAdmin) resp([], true, 'Sin permisos.');
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) resp([], true, 'ID inválido.');
            $model->aprobarSolicitud($id, $idUsuario);

            // Notificar al solicitante
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
                }
            } catch (Throwable) {}

            resp([], false, 'Solicitud aprobada.');
            break;

        // ── Rechazar ───────────────────────────────────────
        case 'rechazar':
            if (!$esAdmin) resp([], true, 'Sin permisos.');
            $id     = (int)($_POST['id'] ?? 0);
            $motivo = trim($_POST['motivo'] ?? '');
            if (!$id || !$motivo) resp([], true, 'ID o motivo faltante.');
            $model->rechazarSolicitud($id, $idUsuario, $motivo);

            // Notificar al solicitante
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
                }
            } catch (Throwable) {}

            resp([], false, 'Solicitud rechazada.');
            break;

        // ── Registrar orden al proveedor ───────────────────
        case 'registrarOrden':
            if (!$esAdmin) resp([], true, 'Sin permisos.');
            $id              = (int)($_POST['id'] ?? 0);
            $numero_orden    = trim($_POST['numero_orden'] ?? '');
            $fecha_entrega   = trim($_POST['fecha_entrega_est'] ?? '');
            if (!$id) resp([], true, 'ID inválido.');
            $model->registrarOrden($id, $numero_orden, $fecha_entrega ?: null);
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
            try {
                $sol = $model->obtenerEmailSolicitante($id);
                if (!empty($sol['email'])) {
                    $estadoRec = $result['estado'] ?? 'Recibida parcial';
                    $mailer->send($sol['email'],
                        "Recepción registrada — Solicitud de compra #$id",
                        Mailer::tplRecepcionCompra(['id' => $id, 'estado' => $estadoRec])
                    );
                }
            } catch (Throwable) {}

            $msg = 'Recepción registrada. Inventario actualizado.';
            if (!empty($result['warnings'])) {
                $msg .= ' Avisos: ' . implode(' | ', $result['warnings']);
            }
            resp([], false, $msg);
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
