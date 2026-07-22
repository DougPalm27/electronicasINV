<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    private array $cfg;
    private static string $logFile = __DIR__ . '/../logs/mail.log';

    public function __construct()
    {
        $this->cfg = require __DIR__ . '/mail.php';
        // Crear carpeta logs si no existe
        $dir = dirname(self::$logFile);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
    }

    /**
     * Envía un correo HTML.
     *
     * @param string|array $to      Correo(s) destinatario. String o array de ['email' => ..., 'name' => ...]
     * @param string       $subject
     * @param string       $body    HTML completo del mensaje
     * @throws \PHPMailer\PHPMailer\Exception si falla el envío
     */
    public function send(string|array $to, string $subject, string $body): void
    {
        if (($this->cfg['driver'] ?? 'smtp') === 'sendgrid') {
            $this->sendSendGrid($to, $subject, $body);
            return;
        }

        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = $this->cfg['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $this->cfg['username'];
        $mail->Password   = $this->cfg['password'];
        $mail->Port       = $this->cfg['port'];
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = $this->cfg['timeout'] ?? 20;

        $encryption = strtolower((string)($this->cfg['encryption'] ?? 'tls'));
        if ($encryption === 'tls' || $encryption === 'starttls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($encryption === 'ssl' || $encryption === 'smtps') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = false;
            $mail->SMTPAutoTLS = false;
        }

        $mail->setFrom($this->cfg['from_email'], $this->cfg['from_name']);

        $destinatarios = [];
        if (is_string($to)) {
            $mail->addAddress($to);
            $destinatarios[] = $to;
        } else {
            foreach ($to as $recipient) {
                $mail->addAddress($recipient['email'], $recipient['name'] ?? '');
                $destinatarios[] = $recipient['email'];
            }
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

        try {
            $mail->send();
            self::log('OK', $subject, $destinatarios);
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            self::log('ERROR', $subject, $destinatarios, $mail->ErrorInfo);
            throw $e;
        }
    }

    private function sendSendGrid(string|array $to, string $subject, string $body): void
    {
        $apiKey = (string)($this->cfg['sendgrid']['api_key'] ?? '');
        if ($apiKey === '') {
            throw new RuntimeException('SENDGRID_API_KEY no esta configurado.');
        }

        $destinatarios = $this->normalizarDestinatarios($to);
        if (!$destinatarios) {
            throw new RuntimeException('No hay destinatarios para enviar el correo.');
        }

        $fromEmail = $this->cfg['sendgrid']['from_email'] ?? $this->cfg['from_email'];
        $fromName  = $this->cfg['sendgrid']['from_name']  ?? $this->cfg['from_name'];

        $payload = [
            'personalizations' => [[
                'to' => array_map(
                    fn($d) => ['email' => $d['email'], 'name' => $d['name'] ?? ''],
                    $destinatarios
                ),
            ]],
            'from' => [
                'email' => $fromEmail,
                'name'  => $fromName,
            ],
            'subject' => $subject,
            'content' => [
                [
                    'type'  => 'text/plain',
                    'value' => strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body)),
                ],
                [
                    'type'  => 'text/html',
                    'value' => $body,
                ],
            ],
        ];

        [$status, $response] = $this->postSendGrid($apiKey, $payload);
        $emails   = array_column($destinatarios, 'email');

        if ($status === 202) {
            self::log('OK', $subject, $emails);
            return;
        }

        $error = $response !== false ? trim($response) : 'No se pudo conectar con SendGrid.';
        self::log('ERROR', $subject, $emails, "HTTP $status $error");
        throw new RuntimeException("SendGrid no acepto el correo. HTTP $status $error");
    }

    private function postSendGrid(string $apiKey, array $payload): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $url  = 'https://api.sendgrid.com/v3/mail/send';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $this->cfg['timeout'] ?? 20,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS     => $json,
            ]);
            $response = curl_exec($ch);
            $status   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error    = curl_error($ch);
            curl_close($ch);
            return [$status, $response !== false ? $response : $error];
        }

        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                ],
                'content'       => $json,
                'ignore_errors' => true,
                'timeout'       => $this->cfg['timeout'] ?? 20,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        $status   = $this->extraerHttpStatus($http_response_header ?? []);
        return [$status, $response];
    }

    private function normalizarDestinatarios(string|array $to): array
    {
        if (is_string($to)) {
            return [['email' => $to, 'name' => '']];
        }

        $destinatarios = [];
        foreach ($to as $recipient) {
            if (empty($recipient['email'])) continue;
            $destinatarios[] = [
                'email' => $recipient['email'],
                'name'  => $recipient['name'] ?? '',
            ];
        }
        return $destinatarios;
    }

    private function extraerHttpStatus(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $m)) {
                return (int)$m[1];
            }
        }
        return 0;
    }

    private static function log(string $estado, string $subject, array $to, string $error = ''): void
    {
        $fecha = date('Y-m-d H:i:s');
        $dest  = implode(', ', $to);
        $linea = "[$fecha] [$estado] Asunto: \"$subject\" | Para: $dest";
        if ($error) $linea .= " | Error: $error";
        file_put_contents(self::$logFile, $linea . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Devuelve los admins activos con correo registrado.
     * Se usa para notificar al crear una solicitud.
     */
    public static function getAdmins(\PDO $conn): array
    {
        $stmt = $conn->prepare(
            "SELECT u.nombre, u.email
             FROM electronicas.Usuarios u
             INNER JOIN electronicas.Roles r ON r.id_rol = u.id_rol
             WHERE r.nombre = 'Administrador'
               AND u.activo = 1
               AND u.email IS NOT NULL
               AND u.email <> ''"
        );
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Templates ──────────────────────────────────────────

    public static function tplNuevaSolicitudRepuesto(array $d): string
    {
        $fecha = htmlspecialchars($d['fecha'] ?? date('d/m/Y'));
        $solicitante = htmlspecialchars($d['solicitante'] ?? '—');
        $descripcion = htmlspecialchars($d['descripcion'] ?? '—');
        $tipo = htmlspecialchars($d['tipo'] ?? '—');
        $id   = (int)($d['id'] ?? 0);

        return self::wrap(
            '🔧 Nueva solicitud de repuestos',
            "#2563eb",
            "Nueva solicitud de repuestos <strong>#$id</strong>",
            "<p>Se ha registrado una nueva solicitud de repuestos en el sistema.</p>
             <table style='width:100%;border-collapse:collapse;margin:16px 0'>
               <tr><td style='padding:6px 12px;background:#f1f5f9;font-weight:600;width:40%'>Solicitante</td><td style='padding:6px 12px;border-bottom:1px solid #e2e8f0'>$solicitante</td></tr>
               <tr><td style='padding:6px 12px;background:#f1f5f9;font-weight:600'>Tipo</td><td style='padding:6px 12px;border-bottom:1px solid #e2e8f0'>$tipo</td></tr>
               <tr><td style='padding:6px 12px;background:#f1f5f9;font-weight:600'>Descripción</td><td style='padding:6px 12px;border-bottom:1px solid #e2e8f0'>$descripcion</td></tr>
               <tr><td style='padding:6px 12px;background:#f1f5f9;font-weight:600'>Fecha</td><td style='padding:6px 12px'>$fecha</td></tr>
             </table>
             <p>Ingresa al sistema para revisar y aprobar la solicitud.</p>"
        );
    }

    public static function tplRespuestaSolicitudRepuesto(array $d): string
    {
        $id      = (int)($d['id'] ?? 0);
        $estado  = htmlspecialchars($d['estado'] ?? '—');
        $revisor = htmlspecialchars($d['revisor'] ?? '—');
        $motivo  = htmlspecialchars($d['motivo'] ?? '');
        $color   = ($d['estado'] ?? '') === 'Aprobado' ? '#16a34a' : '#dc2626';
        $icono   = ($d['estado'] ?? '') === 'Aprobado' ? '✅' : '❌';

        $motivoRow = $motivo
            ? "<tr><td style='padding:6px 12px;background:#f1f5f9;font-weight:600'>Motivo</td><td style='padding:6px 12px'>$motivo</td></tr>"
            : '';

        return self::wrap(
            "$icono Solicitud de repuestos #$id — $estado",
            $color,
            "Tu solicitud <strong>#$id</strong> fue <strong>$estado</strong>",
            "<p>El administrador ha revisado tu solicitud de repuestos.</p>
             <table style='width:100%;border-collapse:collapse;margin:16px 0'>
               <tr><td style='padding:6px 12px;background:#f1f5f9;font-weight:600;width:40%'>Estado</td><td style='padding:6px 12px;border-bottom:1px solid #e2e8f0'><strong style='color:$color'>$estado</strong></td></tr>
               <tr><td style='padding:6px 12px;background:#f1f5f9;font-weight:600'>Revisado por</td><td style='padding:6px 12px;border-bottom:1px solid #e2e8f0'>$revisor</td></tr>
               $motivoRow
             </table>"
        );
    }

    public static function tplNuevaSolicitudCompra(array $d): string
    {
        $id          = (int)($d['id'] ?? 0);
        $solicitante = htmlspecialchars($d['solicitante'] ?? '—');
        $descripcion = htmlspecialchars($d['descripcion'] ?? '—');
        $proveedor   = htmlspecialchars($d['proveedor'] ?? 'Sin especificar');
        $fecha       = htmlspecialchars($d['fecha'] ?? date('d/m/Y'));
        $items       = is_array($d['items'] ?? null) ? $d['items'] : [];

        $itemsRows = '';
        foreach ($items as $it) {
            $repuesto = htmlspecialchars($it['repuesto'] ?? '---');
            $cantidad = htmlspecialchars((string)($it['cantidad_solicitada'] ?? '---'));
            $provItem = htmlspecialchars($it['proveedor_item'] ?? '---');
            $enlace   = trim((string)($it['enlace_externo'] ?? ''));
            $enlaceHtml = $enlace !== ''
                ? '<a href="' . htmlspecialchars($enlace) . '" style="color:#0f766e;text-decoration:underline">Abrir enlace</a>'
                : '<span style="color:#64748b">No aplica</span>';
            $obs      = trim((string)($it['observacion'] ?? ''));
            $obsHtml  = $obs !== '' ? htmlspecialchars($obs) : '<span style="color:#64748b">Sin observacion</span>';
            $itemsRows .= "<tr>
                <td style='padding:8px 10px;border-bottom:1px solid #e2e8f0'>$repuesto</td>
                <td style='padding:8px 10px;border-bottom:1px solid #e2e8f0;text-align:center'>$cantidad</td>
                <td style='padding:8px 10px;border-bottom:1px solid #e2e8f0'>$provItem</td>
                <td style='padding:8px 10px;border-bottom:1px solid #e2e8f0'>$enlaceHtml</td>
                <td style='padding:8px 10px;border-bottom:1px solid #e2e8f0'>$obsHtml</td>
              </tr>";
        }
        $itemsTable = $itemsRows !== ''
            ? "<p style='margin-top:18px;margin-bottom:8px'><strong>Detalle de repuestos solicitados</strong></p>
               <table style='width:100%;border-collapse:collapse;margin:0 0 16px 0;font-size:13px'>
                 <thead>
                   <tr>
                     <th style='padding:8px 10px;background:#f1f5f9;text-align:left'>Repuesto</th>
                     <th style='padding:8px 10px;background:#f1f5f9;text-align:center;width:70px'>Cant.</th>
                     <th style='padding:8px 10px;background:#f1f5f9;text-align:left'>Proveedor</th>
                     <th style='padding:8px 10px;background:#f1f5f9;text-align:left'>Enlace</th>
                     <th style='padding:8px 10px;background:#f1f5f9;text-align:left'>Observacion</th>
                   </tr>
                 </thead>
                 <tbody>$itemsRows</tbody>
               </table>"
            : '';

        return self::wrap(
            'Solicitud de compra para revision',
            "#0f766e",
            "Solicitud de compra <strong>#$id</strong>",
            "<p>Estimados,</p>
             <p>Se solicita la revision de la siguiente compra de repuestos para su aprobacion y gestion de pago.</p>
             <table style='width:100%;border-collapse:collapse;margin:16px 0'>
               <tr><td style='padding:6px 12px;background:#f1f5f9;font-weight:600;width:40%'>Solicitante</td><td style='padding:6px 12px;border-bottom:1px solid #e2e8f0'>$solicitante</td></tr>
               <tr><td style='padding:6px 12px;background:#f1f5f9;font-weight:600'>Proveedor</td><td style='padding:6px 12px;border-bottom:1px solid #e2e8f0'>$proveedor</td></tr>
               <tr><td style='padding:6px 12px;background:#f1f5f9;font-weight:600'>Descripción</td><td style='padding:6px 12px;border-bottom:1px solid #e2e8f0'>$descripcion</td></tr>
               <tr><td style='padding:6px 12px;background:#f1f5f9;font-weight:600'>Fecha</td><td style='padding:6px 12px'>$fecha</td></tr>
             </table>
              $itemsTable
              <p style='color:#475569'>Favor revisar cantidades, proveedor, enlaces y observaciones antes de proceder.</p>"
        );
    }

    public static function tplRespuestaSolicitudCompra(array $d): string
    {
        $id      = (int)($d['id'] ?? 0);
        $estado  = htmlspecialchars($d['estado'] ?? '—');
        $revisor = htmlspecialchars($d['revisor'] ?? '—');
        $motivo  = htmlspecialchars($d['motivo'] ?? '');
        $color   = ($d['estado'] ?? '') === 'Aprobada' ? '#16a34a' : '#dc2626';
        $icono   = ($d['estado'] ?? '') === 'Aprobada' ? '✅' : '❌';

        $motivoRow = $motivo
            ? "<tr><td style='padding:6px 12px;background:#f1f5f9;font-weight:600'>Motivo</td><td style='padding:6px 12px'>$motivo</td></tr>"
            : '';

        return self::wrap(
            "$icono Solicitud de compra #$id — $estado",
            $color,
            "Tu solicitud de compra <strong>#$id</strong> fue <strong>$estado</strong>",
            "<p>El administrador ha revisado tu solicitud de compra.</p>
             <table style='width:100%;border-collapse:collapse;margin:16px 0'>
               <tr><td style='padding:6px 12px;background:#f1f5f9;font-weight:600;width:40%'>Estado</td><td style='padding:6px 12px;border-bottom:1px solid #e2e8f0'><strong style='color:$color'>$estado</strong></td></tr>
               <tr><td style='padding:6px 12px;background:#f1f5f9;font-weight:600'>Revisado por</td><td style='padding:6px 12px;border-bottom:1px solid #e2e8f0'>$revisor</td></tr>
               $motivoRow
             </table>"
        );
    }

    public static function tplRecepcionCompra(array $d): string
    {
        $id     = (int)($d['id'] ?? 0);
        $estado = htmlspecialchars($d['estado'] ?? '—');
        $color  = ($d['estado'] ?? '') === 'Recibida' ? '#16a34a' : '#ea580c';

        return self::wrap(
            "📦 Recepción de compra #$id",
            $color,
            "Recepción registrada — solicitud <strong>#$id</strong>",
            "<p>Se ha registrado la recepción de tu solicitud de compra.</p>
             <table style='width:100%;border-collapse:collapse;margin:16px 0'>
               <tr><td style='padding:6px 12px;background:#f1f5f9;font-weight:600;width:40%'>Estado actual</td>
                   <td style='padding:6px 12px'><strong style='color:$color'>$estado</strong></td></tr>
             </table>
             <p>Ingresa al sistema para ver el detalle de los ítems recibidos.</p>"
        );
    }

    // ── Layout base ────────────────────────────────────────

    private static function wrap(string $title, string $headerColor, string $heading, string $content): string
    {
        $year = date('Y');
        return <<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <head><meta charset="UTF-8"><title>$title</title></head>
        <body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,sans-serif;color:#1e293b">
          <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:32px 0">
            <tr><td align="center">
              <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.1)">
                <tr>
                  <td style="background:$headerColor;padding:24px 32px">
                    <h1 style="margin:0;color:#fff;font-size:20px;font-weight:700">$heading</h1>
                  </td>
                </tr>
                <tr>
                  <td style="padding:28px 32px;font-size:15px;line-height:1.6">
                    $content
                  </td>
                </tr>
                <tr>
                  <td style="background:#f1f5f9;padding:16px 32px;font-size:12px;color:#64748b;text-align:center">
                    Sistema Electronicas &copy; $year — Este es un mensaje automático, no responder.
                  </td>
                </tr>
              </table>
            </td></tr>
          </table>
        </body>
        </html>
        HTML;
    }
}
