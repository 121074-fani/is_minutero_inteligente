<?php
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['exito'=>false,'mensaje'=>'Método no permitido.']); exit;
}

require_once 'conexion.php';

$body          = file_get_contents('php://input');
$datos         = json_decode($body, true);
$idMinuta      = (int)   ($datos['id_minuta']      ?? 0);
$participantes = (array) ($datos['participantes']   ?? []);
$pdfBase64     =          $datos['pdf_base64']      ?? '';

if (!$idMinuta || empty($participantes)) {
    echo json_encode(['exito'=>false,'mensaje'=>'Faltan id_minuta o participantes.']); exit;
}

try {
    // Guardar lista de participantes en la minuta
    $pdo->prepare('UPDATE detalles_minuta SET participantes_json=:p WHERE id_minuta=:id')
        ->execute([
            ':p'  => json_encode($participantes, JSON_UNESCAPED_UNICODE),
            ':id' => $idMinuta
        ]);

    $tokens   = [];
    $enviados = 0;
    $expira   = date('Y-m-d H:i:s', strtotime('+72 hours'));

    $insStmt = $pdo->prepare(
        'INSERT INTO tokens_firma
            (id_minuta, correo, nombre, token, fecha_expiracion)
         VALUES
            (:id_minuta, :correo, :nombre, :token, :expira)
         ON DUPLICATE KEY UPDATE
            token=VALUES(token), firmado=0, fecha_firma=NULL,
            camara_verificada=0, fecha_expiracion=VALUES(fecha_expiracion)'
    );

    foreach ($participantes as $p) {
        $correo = trim($p['correo'] ?? '');
        $nombre = trim($p['nombre'] ?? $correo);
        if (!$correo || !filter_var($correo, FILTER_VALIDATE_EMAIL)) continue;

        $token = bin2hex(random_bytes(32)); // 64 chars hex

        $insStmt->execute([
            ':id_minuta' => $idMinuta,
            ':correo'    => strtolower($correo),
            ':nombre'    => $nombre,
            ':token'     => $token,
            ':expira'    => $expira
        ]);

        $url = "http://localhost/minutero/firmar_minuta.html?token={$token}";
        $tokens[] = [
            'nombre'   => $nombre,
            'correo'   => $correo,
            'token'    => $token,
            'url'      => $url,
            'expira'   => $expira
        ];
        $enviados++;

        // Intentar enviar correo (funciona si XAMPP tiene Sendmail configurado)
        @mail(
            $correo,
            "=?UTF-8?B?" . base64_encode("Minuta #{$idMinuta} — Firma tu acuse de recibo") . "?=",
            "Hola {$nombre},\n\nSe te ha compartido la Minuta #{$idMinuta}.\n\nFirma tu acuse de recibo aquí (válido 72h):\n{$url}\n\nMinutero Inteligente · ITM",
            "From: minutero@itm.edu.mx\r\nContent-Type: text/plain; charset=UTF-8\r\n"
        );
    }

    echo json_encode([
        'exito'             => true,
        'mensaje'           => "{$enviados} token(s) generado(s) correctamente.",
        'tokens_generados'  => $tokens
    ]);

} catch (PDOException $e) {
    echo json_encode(['exito'=>false,'mensaje'=>'Error BD: ' . $e->getMessage()]);
}
?>
