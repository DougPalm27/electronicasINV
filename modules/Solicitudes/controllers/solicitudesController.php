<?php
require_once '../../../config/auth.php';
requireLogin(true);

header('Content-Type: application/json');
include_once '../models/mdlSolicitudes.php';
require_once '../../../config/Mailer.php';

$model  = new mdlSolicitudes();
$mailer = new Mailer();
$accion = $_POST['accion'] ?? '';

function respS($data = [], bool $error = false, string $msg = ''): void
{
    echo json_encode(['ok' => !$error, 'data' => $data, 'mensaje' => $msg]);
    exit;
}

try {
    switch ($accion) {

        // ── Catálogos ────────────────────────────────────────
        case 'listarMaquinas':
            respS($model->listarMaquinas());
            break;

        case 'listarTipos':
            respS($model->listarTipos());
            break;

        case 'listarTecnicos':
            respS($model->listarTecnicos());
            break;

        case 'listarRepuestos':
            respS($model->listarRepuestos());
            break;

        // ── Listar solicitudes ───────────────────────────────
        case 'listar':
            // Admin ve todas; Técnico solo las suyas
            $esAdmin  = ($_SESSION['nombre_rol'] ?? '') === 'Administrador';
            $id_usu   = (int)$_SESSION['id_usuario'];
            respS($model->listarSolicitudes($esAdmin ? null : $id_usu));
            break;

        // ── Detalle ──────────────────────────────────────────
        case 'detalle':
            $id = (int)($_POST['id_solicitud'] ?? 0);
            if (!$id) respS([], true, 'ID inválido.');
            $det = $model->obtenerDetalle($id);
            if (!$det) respS([], true, 'Solicitud no encontrada.');
            respS($det);
            break;

        // ── Crear ────────────────────────────────────────────
        case 'crear':
            $payload = json_decode($_POST['payload'] ?? '{}', true);
            if (json_last_error() !== JSON_ERROR_NONE)
                respS([], true, 'Datos inválidos.');

            $descripcion     = trim($payload['descripcion']     ?? '');
            $id_tipo         = (int)($payload['id_tipo']        ?? 0);
            $fecha_programada = trim($payload['fecha_programada'] ?? '');
            $id_tecnico      = ($payload['id_tecnico'] ?? '') ?: null;
            $maquinas        = $payload['maquinas'] ?? [];

            if (!$descripcion)     respS([], true, 'La descripción es requerida.');
            if (!$id_tipo)         respS([], true, 'Debes seleccionar el tipo de mantenimiento.');
            if (!$fecha_programada) respS([], true, 'La fecha programada es requerida.');
            if (empty($maquinas))  respS([], true, 'Agrega al menos una máquina.');

            foreach ($maquinas as $i => $maq) {
                if (empty($maq['id_maquina']))
                    respS([], true, "Selecciona la máquina en el bloque " . ($i + 1) . ".");
                if (empty($maq['repuestos']))
                    respS([], true, "Agrega al menos un repuesto a la máquina " . ($i + 1) . ".");
            }

            $id = $model->crearSolicitud([
                'id_usuario'      => (int)$_SESSION['id_usuario'],
                'id_tecnico'      => $id_tecnico ? (int)$id_tecnico : null,
                'id_tipo'         => $id_tipo,
                'descripcion'     => $descripcion,
                'fecha_programada' => $fecha_programada,
                'maquinas'        => $maquinas,
            ]);

            // Notificar a admins
            $mailErr = null;
            try {
                $admins = Mailer::getAdmins($model->getConn());
                if (!empty($admins)) {
                    $tipo_nombre = '';
                    foreach ($model->listarTipos() as $t) {
                        if ((int)$t['id_tipo'] === $id_tipo) { $tipo_nombre = $t['nombre']; break; }
                    }
                    $mailer->send($admins,
                        "Nueva solicitud de repuestos #$id",
                        Mailer::tplNuevaSolicitudRepuesto([
                            'id'          => $id,
                            'solicitante' => $_SESSION['nombre'] ?? '—',
                            'descripcion' => $descripcion,
                            'tipo'        => $tipo_nombre,
                            'fecha'       => date('d/m/Y'),
                        ])
                    );
                } else {
                    $mailErr = 'No hay admins con correo registrado.';
                }
            } catch (Throwable $me) { $mailErr = $me->getMessage(); }

            respS(['id_solicitud' => $id, 'mail_error' => $mailErr], false, "Solicitud #$id creada correctamente.");
            break;

        // ── Aprobar ──────────────────────────────────────────
        case 'aprobar':
            $id = (int)($_POST['id_solicitud'] ?? 0);
            if (!$id) respS([], true, 'ID inválido.');
            $resp = $model->aprobarSolicitud($id, (int)$_SESSION['id_usuario']);
            if (!empty($resp['error'])) respS([], true, $resp['mensaje']);
            $n = count($resp['mantenimientos']);

            // Notificar al solicitante
            $mailErr = null;
            try {
                $sol = $model->obtenerEmailSolicitante($id);
                if (!empty($sol['email'])) {
                    $mailer->send($sol['email'],
                        "Solicitud de repuestos #$id — Aprobada",
                        Mailer::tplRespuestaSolicitudRepuesto([
                            'id'      => $id,
                            'estado'  => 'Aprobado',
                            'revisor' => $_SESSION['nombre'] ?? '—',
                        ])
                    );
                } else {
                    $mailErr = 'El solicitante no tiene correo registrado.';
                }
            } catch (Throwable $me) { $mailErr = $me->getMessage(); }

            $resp['mail_error'] = $mailErr;
            respS($resp, false, "Solicitud aprobada. Se generaron $n mantenimiento(s).");
            break;

        // ── Rechazar ─────────────────────────────────────────
        case 'rechazar':
            $id     = (int)($_POST['id_solicitud'] ?? 0);
            $motivo = trim($_POST['motivo'] ?? '');
            if (!$id)     respS([], true, 'ID inválido.');
            if (!$motivo) respS([], true, 'Debes indicar el motivo del rechazo.');
            $model->rechazarSolicitud($id, (int)$_SESSION['id_usuario'], $motivo);

            // Notificar al solicitante
            $mailErr = null;
            try {
                $sol = $model->obtenerEmailSolicitante($id);
                if (!empty($sol['email'])) {
                    $mailer->send($sol['email'],
                        "Solicitud de repuestos #$id — Rechazada",
                        Mailer::tplRespuestaSolicitudRepuesto([
                            'id'      => $id,
                            'estado'  => 'Rechazado',
                            'revisor' => $_SESSION['nombre'] ?? '—',
                            'motivo'  => $motivo,
                        ])
                    );
                } else {
                    $mailErr = 'El solicitante no tiene correo registrado.';
                }
            } catch (Throwable $me) { $mailErr = $me->getMessage(); }

            respS(['mail_error' => $mailErr], false, 'Solicitud rechazada.');
            break;

        // ── Cancelar (propio técnico) ─────────────────────────
        case 'cancelar':
            $id = (int)($_POST['id_solicitud'] ?? 0);
            if (!$id) respS([], true, 'ID inválido.');
            $model->cancelarSolicitud($id, (int)$_SESSION['id_usuario']);
            respS([], false, 'Solicitud cancelada.');
            break;

        default:
            respS([], true, "Acción no válida: $accion");
    }
} catch (Throwable $e) {
    respS([], true, $e->getMessage());
}
