<?php
/**
 * TokenService.php
 * Maneja generación, envío y validación de tokens de minutas.
 * Usa PHP mail() — en XAMPP configurar sendmail o usar SMTP externo.
 */
class TokenService {

    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // -----------------------------------------------------------------------
    // Genera token numérico de 6 dígitos para el responsable de la minuta
    // -----------------------------------------------------------------------
    public function generarTokenValidacion(int $idMinuta, string $correo): string {
        $token = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expira = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        // Invalidar tokens anteriores para esta minuta
        $this->pdo->prepare('UPDATE tokens_validacion SET usado=1 WHERE id_minuta=:id')
                  ->execute([':id' => $idMinuta]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO tokens_validacion (id_minuta, correo, token, fecha_expiracion)
             VALUES (:id_minuta, :correo, :token, :expira)'
        );
        $stmt->execute([':id_minuta'=>$idMinuta,':correo'=>$correo,':token'=>$token,':expira'=>$expira]);

        // Guardar token en la minuta
        $this->pdo->prepare('UPDATE detalles_minuta SET token_validacion=:t WHERE id_minuta=:id')
                  ->execute([':t'=>$token,':id'=>$idMinuta]);

        return $token;
    }

    // -----------------------------------------------------------------------
    // Valida el token del responsable y marca la minuta como validada
    // -----------------------------------------------------------------------
    public function validarTokenResponsable(int $idMinuta, string $token): array {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tokens_validacion
             WHERE id_minuta=:id AND token=:token AND usado=0
               AND fecha_expiracion > NOW()
             LIMIT 1'
        );
        $stmt->execute([':id'=>$idMinuta,':token'=>$token]);
        $row = $stmt->fetch();

        if (!$row) {
            return ['exito'=>false,'mensaje'=>'Token inválido o expirado. Solicita uno nuevo.'];
        }

        // Marcar como usado y minuta como validada
        $this->pdo->prepare('UPDATE tokens_validacion SET usado=1 WHERE id=:id')
                  ->execute([':id'=>$row['id']]);
        $this->pdo->prepare('UPDATE detalles_minuta SET validada=1 WHERE id_minuta=:id')
                  ->execute([':id'=>$idMinuta]);

        return ['exito'=>true,'mensaje'=>'Minuta validada correctamente.'];
    }

    // -----------------------------------------------------------------------
    // Genera tokens de firma para cada participante (72 horas de validez)
    // y envía el PDF por correo a cada uno
    // -----------------------------------------------------------------------
    public function enviarPDFYTokensFirma(int $idMinuta, array $participantes, string $pdfBase64): array {
        $enviados = [];
        $errores  = [];

        foreach ($participantes as $p) {
            $correo = trim($p['correo'] ?? '');
            $nombre = trim($p['nombre'] ?? $correo);
            if (!$correo) continue;

            // Generar token único de 64 chars
            $token  = bin2hex(random_bytes(32));
            $expira = date('Y-m-d H:i:s', strtotime('+72 hours'));

            // Guardar token en BD
            $stmt = $this->pdo->prepare(
                'INSERT INTO tokens_firma (id_minuta, correo, nombre, token, fecha_expiracion)
                 VALUES (:id_minuta, :correo, :nombre, :token, :expira)
                 ON DUPLICATE KEY UPDATE token=:token2, firmado=0, fecha_firma=NULL,
                   camara_verificada=0, fecha_expiracion=:expira2'
            );
            $stmt->execute([
                ':id_minuta'=>$idMinuta, ':correo'=>$correo, ':nombre'=>$nombre,
                ':token'=>$token, ':expira'=>$expira,
                ':token2'=>$token, ':expira2'=>$expira
            ]);

            // URL de firma
            $urlBase = 'http://localhost/minutero/firmar_minuta.html';
            $urlFirma= "{$urlBase}?token={$token}&minuta={$idMinuta}";

            // Enviar correo con PDF adjunto
            $ok = $this->enviarCorreoFirma($correo, $nombre, $idMinuta, $urlFirma, $pdfBase64);

            if ($ok) $enviados[] = $correo;
            else      $errores[]  = $correo;
        }

        return [
            'exito'   => count($errores) === 0,
            'enviados'=> $enviados,
            'errores' => $errores,
            'mensaje' => count($errores) === 0
                ? 'PDF enviado a ' . count($enviados) . ' participante(s).'
                : 'Enviado a ' . count($enviados) . '. Fallaron: ' . implode(', ', $errores)
        ];
    }

    // -----------------------------------------------------------------------
    // Valida token de firma de un participante
    // -----------------------------------------------------------------------
    public function validarTokenFirma(string $token): array {
        $stmt = $this->pdo->prepare(
            'SELECT tf.*, dm.lugar, dm.fecha, dm.area
             FROM tokens_firma tf
             JOIN detalles_minuta dm ON tf.id_minuta = dm.id_minuta
             WHERE tf.token=:token AND tf.fecha_expiracion > NOW()
             LIMIT 1'
        );
        $stmt->execute([':token'=>$token]);
        $row = $stmt->fetch();

        if (!$row) return ['exito'=>false,'mensaje'=>'Token inválido o expirado (72h).'];
        if ($row['firmado']) return ['exito'=>false,'mensaje'=>'Este token ya fue usado para firmar.','ya_firmado'=>true];

        return ['exito'=>true,'datos'=>$row];
    }

    // -----------------------------------------------------------------------
    // Registra la firma de un participante
    // -----------------------------------------------------------------------
    public function registrarFirma(string $token, bool $camaraOk = false): array {
        $val = $this->validarTokenFirma($token);
        if (!$val['exito']) return $val;

        $this->pdo->prepare(
            'UPDATE tokens_firma SET firmado=1, fecha_firma=NOW(), camara_verificada=:cam WHERE token=:token'
        )->execute([':cam'=>(int)$camaraOk, ':token'=>$token]);

        return ['exito'=>true,'mensaje'=>'Firma registrada correctamente.','nombre'=>$val['datos']['nombre']];
    }

    // -----------------------------------------------------------------------
    // Estado de firmas de una minuta
    // -----------------------------------------------------------------------
    public function estadoFirmas(int $idMinuta): array {
        $stmt = $this->pdo->prepare(
            'SELECT nombre, correo, firmado, fecha_firma, camara_verificada, fecha_expiracion
             FROM tokens_firma WHERE id_minuta=:id ORDER BY nombre ASC'
        );
        $stmt->execute([':id'=>$idMinuta]);
        return $stmt->fetchAll();
    }

    // -----------------------------------------------------------------------
    // ENVÍO DE CORREO (PHP mail + adjunto PDF base64)
    // -----------------------------------------------------------------------
    private function enviarCorreoFirma(string $para, string $nombre, int $idMinuta,
                                        string $urlFirma, string $pdfBase64): bool {
        $asunto  = "=?UTF-8?B?" . base64_encode("Minuta #{$idMinuta} — Por favor firma tu asistencia") . "?=";
        $boundary= md5(uniqid());

        $cuerpoHtml = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto;'>
          <div style='background:linear-gradient(135deg,#4a235a,#9b59b6);padding:28px 32px;border-radius:12px 12px 0 0;'>
            <h2 style='color:white;margin:0;'>📋 Minutero Inteligente</h2>
            <p style='color:rgba(255,255,255,.8);margin:8px 0 0;'>Instituto Tecnológico de Morelia</p>
          </div>
          <div style='background:#f9f5fc;padding:28px 32px;border-radius:0 0 12px 12px;'>
            <p style='color:#4a235a;font-size:1.1rem;'>Hola, <strong>{$nombre}</strong>:</p>
            <p style='color:#555;'>Se te ha compartido la <strong>Minuta de Reunión #{$idMinuta}</strong> para tu revisión y firma de acuse de recibo.</p>
            <p style='color:#555;'>El PDF de la minuta se encuentra adjunto a este correo.</p>
            <div style='background:white;border-left:4px solid #9b59b6;border-radius:8px;padding:16px 20px;margin:20px 0;'>
              <p style='margin:0 0 8px;color:#4a235a;font-weight:bold;'>🔐 Firma tu acuse de recibo</p>
              <p style='margin:0 0 12px;color:#666;font-size:.9rem;'>Este enlace es válido por <strong>72 horas</strong>. Se requiere verificación con cámara.</p>
              <a href='{$urlFirma}'
                 style='display:inline-block;background:#9b59b6;color:white;padding:12px 24px;border-radius:25px;text-decoration:none;font-weight:bold;'>
                ✅ Firmar Acuse de Recibo
              </a>
            </div>
            <p style='color:#999;font-size:.82rem;'>Si no puedes hacer clic en el botón, copia este enlace en tu navegador:<br>
              <a href='{$urlFirma}' style='color:#9b59b6;word-break:break-all;'>{$urlFirma}</a>
            </p>
            <hr style='border:none;border-top:1px solid #e8daef;margin:20px 0;'>
            <p style='color:#aaa;font-size:.8rem;margin:0;'>Minutero Inteligente · ITM · Este correo es generado automáticamente.</p>
          </div>
        </div>";

        // Construir email multipart con adjunto PDF
        $headers  = "From: minutero@itm.edu.mx\r\n";
        $headers .= "Reply-To: minutero@itm.edu.mx\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

        $cuerpo  = "--{$boundary}\r\n";
        $cuerpo .= "Content-Type: text/html; charset=UTF-8\r\n";
        $cuerpo .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $cuerpo .= chunk_split(base64_encode($cuerpoHtml)) . "\r\n";

        // Adjunto PDF
        if ($pdfBase64) {
            $cuerpo .= "--{$boundary}\r\n";
            $cuerpo .= "Content-Type: application/pdf; name=\"Minuta_{$idMinuta}.pdf\"\r\n";
            $cuerpo .= "Content-Transfer-Encoding: base64\r\n";
            $cuerpo .= "Content-Disposition: attachment; filename=\"Minuta_{$idMinuta}.pdf\"\r\n\r\n";
            $cuerpo .= chunk_split($pdfBase64) . "\r\n";
        }
        $cuerpo .= "--{$boundary}--";

        return @mail($para, $asunto, $cuerpo, $headers);
    }
}
?>
