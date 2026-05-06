<?php
/**
 * Script de prueba SMTP — ejecutar desde el navegador o CLI.
 * ELIMINAR este archivo antes de subir a producción.
 *
 * URL: http://localhost/Electronicas/test_mail.php?to=tucorreo@empresa.com
 */

// Bloquear acceso si no es localhost
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    http_response_code(403); exit('Acceso denegado.');
}

require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$cfg = require __DIR__ . '/config/mail.php';
$to  = trim($_GET['to'] ?? '');

echo '<pre style="font-family:monospace;font-size:13px;padding:20px">';
echo "=== TEST SMTP — Sistema Electronicas ===\n\n";

// ── 1. Mostrar configuración cargada ──────────────────────
echo "[ CONFIGURACIÓN ]\n";
echo "  host       : {$cfg['host']}\n";
echo "  port       : {$cfg['port']}\n";
echo "  encryption : {$cfg['encryption']}\n";
echo "  username   : {$cfg['username']}\n";
echo "  from_email : {$cfg['from_email']}\n";
echo "  from_name  : {$cfg['from_name']}\n\n";

if (!$to) {
    echo "⚠  Pasa el destinatario en la URL: ?to=tucorreo@empresa.com\n";
    echo '</pre>';
    exit;
}

echo "  destinatario: $to\n\n";

// ── 2. Verificar extensiones PHP requeridas ───────────────
echo "[ EXTENSIONES PHP ]\n";
$exts = ['openssl', 'sockets', 'mbstring'];
foreach ($exts as $ext) {
    $ok = extension_loaded($ext);
    echo "  " . ($ok ? '✓' : '✗') . " $ext" . ($ok ? '' : '  <-- FALTA') . "\n";
}
echo "\n";

// ── 3. Intentar conexión SMTP con debug completo ──────────
echo "[ LOG DE CONEXIÓN SMTP ]\n";
echo str_repeat('-', 60) . "\n";

$mail = new PHPMailer(true);

try {
    $mail->SMTPDebug  = SMTP::DEBUG_SERVER;    // Muestra toda la conversación SMTP
    $mail->Debugoutput = function($str, $level) {
        echo htmlspecialchars("[$level] $str");
    };

    $mail->isSMTP();
    $mail->Host       = $cfg['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $cfg['username'];
    $mail->Password   = $cfg['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $cfg['port'];
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($cfg['from_email'], $cfg['from_name']);
    $mail->addAddress($to);

    $mail->isHTML(true);
    $mail->Subject = 'Prueba SMTP — Sistema Electronicas';
    $mail->Body    = '<h2 style="color:#16a34a">✓ Correo de prueba</h2>
                      <p>Si ves este mensaje, la configuración SMTP está funcionando correctamente.</p>
                      <p><small>Enviado desde: ' . htmlspecialchars($cfg['from_email']) . '</small></p>';
    $mail->AltBody = 'Prueba SMTP exitosa. Enviado desde: ' . $cfg['from_email'];

    $mail->send();

    echo str_repeat('-', 60) . "\n\n";
    echo "✓ CORREO ENVIADO EXITOSAMENTE a: $to\n";

} catch (Exception $e) {
    echo str_repeat('-', 60) . "\n\n";
    echo "✗ ERROR AL ENVIAR:\n";
    echo "  Mensaje    : " . htmlspecialchars($e->getMessage()) . "\n";
    echo "  PHPMailer  : " . htmlspecialchars($mail->ErrorInfo) . "\n\n";

    echo "[ POSIBLES CAUSAS ]\n";
    if (str_contains($mail->ErrorInfo, 'AUTH')) {
        echo "  → Credenciales incorrectas o SMTP AUTH no habilitado para esta cuenta.\n";
        echo "    Solución: En el admin de M365, habilitar 'SMTP AUTH' para el usuario.\n";
    } elseif (str_contains($mail->ErrorInfo, 'connect') || str_contains($mail->ErrorInfo, 'timed out')) {
        echo "  → No se pudo conectar al servidor SMTP.\n";
        echo "    Verificar que el firewall/antivirus no bloquee el puerto {$cfg['port']}.\n";
    } elseif (str_contains($mail->ErrorInfo, 'certificate') || str_contains($mail->ErrorInfo, 'SSL')) {
        echo "  → Problema con certificado SSL/TLS.\n";
        echo "    Verificar que openssl esté habilitado en php.ini.\n";
    }
}

echo '</pre>';
