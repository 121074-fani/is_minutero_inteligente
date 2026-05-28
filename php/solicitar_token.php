<?php
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['exito'=>false,'mensaje'=>'Método no permitido.']); exit;
}

require_once 'conexion.php';

$body   = file_get_contents('php://input');
$datos  = json_decode($body, true);
$correo = trim($datos['correo'] ?? '');
$lugar  = trim($datos['lugar']  ?? 'Reunión');

if (!$correo) {
    echo json_encode(['exito'=>false,'mensaje'=>'El correo es requerido.']); exit;
}

try {
    // 1. Crear registro provisional de minuta
    $stmt = $pdo->prepare(
        "INSERT INTO detalles_minuta
            (lugar, fecha, hora, correo_responsable, tipo, area, fecha_registro)
         VALUES
            (:lugar, CURDATE(), CURTIME(), :correo, 'presencial', '', NOW())"
    );
    $stmt->execute([
        ':lugar'  => htmlspecialchars($lugar),
        ':correo' => strtolower($correo)
    ]);
    $idMinuta = (int) $pdo->lastInsertId();

    // 2. Generar token de 6 dígitos
    $token  = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expira = date('Y-m-d H:i:s', strtotime('+30 minutes'));

    // 3. Invalidar tokens anteriores para este correo
    $pdo->prepare('UPDATE tokens_validacion SET usado=1 WHERE correo=:c')
        ->execute([':c' => strtolower($correo)]);

    // 4. Insertar nuevo token
    $ins = $pdo->prepare(
        'INSERT INTO tokens_validacion (id_minuta, correo, token, fecha_expiracion)
         VALUES (:id, :correo, :token, :expira)'
    );
    $ins->execute([
        ':id'     => $idMinuta,
        ':correo' => strtolower($correo),
        ':token'  => $token,
        ':expira' => $expira
    ]);

    // 5. Guardar referencia en la minuta
    $pdo->prepare('UPDATE detalles_minuta SET token_validacion=:t WHERE id_minuta=:id')
        ->execute([':t' => $token, ':id' => $idMinuta]);

    echo json_encode([
        'exito'     => true,
        'id_minuta' => $idMinuta,
        'token'     => $token,
        'expira'    => $expira,
        'mensaje'   => "Token generado para {$correo}. Válido 30 minutos.",
        'simulado'  => true
    ]);

} catch (PDOException $e) {
    echo json_encode(['exito'=>false,'mensaje'=>'Error BD: ' . $e->getMessage()]);
}
?>
