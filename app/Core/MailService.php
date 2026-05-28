<?php
/**
 * Core/MailService.php
 * Envío de correos con PHPMailer (SMTP) o mail() nativo.
 * Configura SMTP para producción, usa mail() para XAMPP local.
 */
class MailService {

    // ── Configuración SMTP ─────────────────────────────────
    // Cambia estos valores con tu cuenta de correo:
    private static string $smtpHost   = 'smtp.gmail.com';
    private static int    $smtpPort   = 587;
    private static string $smtpUser   = 'tu_correo@gmail.com';   // ← EDITAR
    private static string $smtpPass   = 'tu_app_password';        // ← EDITAR (App Password de Google)
    private static string $smtpFrom   = 'minutero@itm.edu.mx';
    private static string $smtpFromName = 'Minutero Inteligente';

    /**
     * Envía un correo con SMTP usando sockets nativos de PHP.
     * No requiere librerías externas — funciona en XAMPP.
     */
    public static function enviar(string $para, string $asunto, string $cuerpoHtml,
                                   string $adjuntoPdfBase64 = '', string $adjuntoNombre = ''): bool {
        // En XAMPP sin SMTP configurado, log el correo y retorna true (simulación)
        if (self::$smtpUser === 'tu_correo@gmail.com') {
            self::logCorreo($para, $asunto, $cuerpoHtml);
            return true; // Simula envío exitoso
        }

        try {
            return self::enviarSmtp($para, $asunto, $cuerpoHtml, $adjuntoPdfBase64, $adjuntoNombre);
        } catch (Exception $e) {
            error_log("MailService error: " . $e->getMessage());
            self::logCorreo($para, $asunto, $cuerpoHtml);
            return false;
        }
    }

    /** Guarda el correo en un log para XAMPP (carpeta logs/) */
    private static function logCorreo(string $para, string $asunto, string $html): void {
        $dir = __DIR__ . '/../../logs/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $archivo = $dir . 'emails_' . date('Y-m-d') . '.log';
        $linea = "[" . date('Y-m-d H:i:s') . "] PARA: {$para} | ASUNTO: {$asunto}\n";
        // Extraer token del HTML si existe
        if (preg_match('/\b(\d{6})\b/', strip_tags($html), $m)) {
            $linea .= "  TOKEN: {$m[1]}\n";
        }
        if (preg_match('/token=([a-f0-9]{64})/', $html, $m)) {
            $linea .= "  FIRMA_TOKEN: {$m[1]}\n";
        }
        @file_put_contents($archivo, $linea, FILE_APPEND);
    }

    /** SMTP manual con fsockopen */
    private static function enviarSmtp(string $para, string $asunto, string $html,
                                        string $pdfBase64, string $pdfNombre): bool {
        $boundary = md5(uniqid());
        $asuntoEnc = '=?UTF-8?B?' . base64_encode($asunto) . '?=';

        // Construir cuerpo multipart
        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($html)) . "\r\n";
        if ($pdfBase64 && $pdfNombre) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: application/pdf; name=\"{$pdfNombre}\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"{$pdfNombre}\"\r\n\r\n";
            $body .= chunk_split($pdfBase64) . "\r\n";
        }
        $body .= "--{$boundary}--";

        $headers  = "From: " . self::$smtpFromName . " <" . self::$smtpFrom . ">\r\n";
        $headers .= "To: {$para}\r\n";
        $headers .= "Subject: {$asuntoEnc}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

        // Conexión SMTP TLS
        $socket = @fsockopen('ssl://' . self::$smtpHost, self::$smtpPort, $errno, $errstr, 10);
        if (!$socket) throw new Exception("No se pudo conectar al SMTP: {$errstr}");

        $read = fn() => fgets($socket, 512);
        $send = function(string $cmd) use ($socket, $read) {
            fputs($socket, $cmd . "\r\n");
            return $read();
        };

        $read(); // Banner
        $send("EHLO localhost");
        $read(); $read(); $read(); $read(); $read();
        $send("AUTH LOGIN");
        $read();
        $send(base64_encode(self::$smtpUser)); $read();
        $send(base64_encode(self::$smtpPass)); $read();
        $send("MAIL FROM:<" . self::$smtpFrom . ">");  $read();
        $send("RCPT TO:<{$para}>");   $read();
        $send("DATA");   $read();
        fputs($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
        $read();
        $send("QUIT");
        fclose($socket);
        return true;
    }

    // ── Templates de correo ────────────────────────────────
    public static function templateToken(string $nombre, string $token, string $lugar): string {
        return "
        <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;'>
          <div style='background:linear-gradient(135deg,#4a235a,#9b59b6);padding:24px 28px;border-radius:12px 12px 0 0;'>
            <h2 style='color:white;margin:0;font-size:1.3rem;'>📋 Minutero Inteligente</h2>
            <p style='color:rgba(255,255,255,.8);margin:6px 0 0;font-size:.88rem;'>Instituto Tecnológico de Morelia</p>
          </div>
          <div style='background:#f9f5fc;padding:24px 28px;border-radius:0 0 12px 12px;'>
            <p style='color:#4a235a;font-size:1rem;'>Hola, <strong>{$nombre}</strong>:</p>
            <p style='color:#555;'>Tu código de validación para la reunión <strong>\"{$lugar}\"</strong> es:</p>
            <div style='background:white;border:2px solid #9b59b6;border-radius:12px;padding:20px;text-align:center;margin:18px 0;'>
              <div style='font-size:2.4rem;font-weight:700;letter-spacing:12px;color:#4a235a;'>{$token}</div>
              <div style='font-size:.78rem;color:#888;margin-top:8px;'>Válido por 30 minutos</div>
            </div>
            <p style='color:#999;font-size:.82rem;'>Si no solicitaste este código, ignora este mensaje.</p>
          </div>
        </div>";
    }

    public static function templateFirma(string $nombre, int $idMinuta, string $urlFirma, string $lugar, string $fecha): string {
        return "
        <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;'>
          <div style='background:linear-gradient(135deg,#4a235a,#9b59b6);padding:24px 28px;border-radius:12px 12px 0 0;'>
            <h2 style='color:white;margin:0;font-size:1.3rem;'>📋 Minutero Inteligente</h2>
          </div>
          <div style='background:#f9f5fc;padding:24px 28px;border-radius:0 0 12px 12px;'>
            <p style='color:#4a235a;font-size:1rem;'>Hola, <strong>{$nombre}</strong>:</p>
            <p style='color:#555;'>Se te ha compartido la <strong>Minuta #{$idMinuta}</strong> de la reunión <strong>\"{$lugar}\"</strong> del {$fecha}.</p>
            <p style='color:#555;'>El PDF está adjunto. Por favor firma tu acuse de recibo:</p>
            <div style='text-align:center;margin:20px 0;'>
              <a href='{$urlFirma}' style='background:#9b59b6;color:white;padding:12px 28px;border-radius:25px;text-decoration:none;font-weight:700;font-size:.95rem;'>
                ✅ Firmar Acuse de Recibo
              </a>
            </div>
            <p style='color:#aaa;font-size:.78rem;'>Enlace válido 72 horas. Si no puedes hacer clic:<br>
              <a href='{$urlFirma}' style='color:#9b59b6;word-break:break-all;font-size:.76rem;'>{$urlFirma}</a></p>
          </div>
        </div>";
    }
}
