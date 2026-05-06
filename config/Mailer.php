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
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = $this->cfg['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $this->cfg['username'];
        $mail->Password   = $this->cfg['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $this->cfg['port'];
        $mail->CharSet    = 'UTF-8';

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

        return self::wrap(
            '🛒 Nueva solicitud de compra',
            "#7c3aed",
            "Nueva solicitud de compra <strong>#$id</strong>",
            "<p>Se ha enviado una solicitud de compra pendiente de aprobación.</p>
             <table style='width:100%;border-collapse:collapse;margin:16px 0'>
               <tr><td style='padding:6px 12px;background:#f1f5f9;font-weight:600;width:40%'>Solicitante</td><td style='padding:6px 12px;border-bottom:1px solid #e2e8f0'>$solicitante</td></tr>
               <tr><td style='padding:6px 12px;background:#f1f5f9;font-weight:600'>Proveedor</td><td style='padding:6px 12px;border-bottom:1px solid #e2e8f0'>$proveedor</td></tr>
               <tr><td style='padding:6px 12px;background:#f1f5f9;font-weight:600'>Descripción</td><td style='padding:6px 12px;border-bottom:1px solid #e2e8f0'>$descripcion</td></tr>
               <tr><td style='padding:6px 12px;background:#f1f5f9;font-weight:600'>Fecha</td><td style='padding:6px 12px'>$fecha</td></tr>
             </table>
             <p>Ingresa al sistema para aprobar o rechazar la solicitud.</p>"
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
