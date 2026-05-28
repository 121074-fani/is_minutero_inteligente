<?php
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['exito'=>false,'mensaje'=>'Método no permitido.']); exit;
}

require_once 'conexion.php';

$body     = file_get_contents('php://input');
$datos    = json_decode($body, true);
$idMinuta = (int)  ($datos['id_minuta'] ?? 0);
$token    = trim($datos['token'] ?? '');

if (!$idMinuta || strlen($token) !== 6) {
    echo json_encode(['exito'=>false,'mensaje'=>'Datos incompletos. Se requiere id_minuta y token de 6 dígitos.']);
    exit;
}

try {
    // Buscar token válido
    $stmt = $pdo->prepare(
        'SELECT id FROM tokens_validacion
         WHERE id_minuta = :id
           AND token     = :token
           AND usado     = 0
           AND fecha_expiracion > NOW()
         LIMIT 1'
    );
    $stmt->execute([':id' => $idMinuta, ':token' => $token]);
    $row = $stmt->fetch();

    if (!$row) {
        // Distinguir entre "no existe" y "expirado"
        $check = $pdo->prepare(
            'SELECT usado, fecha_expiracion FROM tokens_validacion
             WHERE id_minuta=:id AND token=:token LIMIT 1'
        );
        $check->execute([':id'=>$idMinuta,':token'=>$token]);
        $prev = $check->fetch();

        if (!$prev) {
            echo json_encode(['exito'=>false,'mensaje'=>'Token incorrecto.']);
        } elseif ($prev['usado']) {
            echo json_encode(['exito'=>false,'mensaje'=>'Este token ya fue usado.']);
        } else {
            echo json_encode(['exito'=>false,'mensaje'=>'Token expirado. Solicita uno nuevo.']);
        }
        exit;
    }

    // Marcar token como usado
    $pdo->prepare('UPDATE tokens_validacion SET usado=1 WHERE id=:id')
        ->execute([':id' => $row['id']]);

    // Marcar minuta como validada
    $pdo->prepare('UPDATE detalles_minuta SET validada=1 WHERE id_minuta=:id')
        ->execute([':id' => $idMinuta]);

    echo json_encode([
        'exito'   => true,
        'mensaje' => 'Reunión validada correctamente. Ya puedes completar y guardar la minuta.'
    ]);

} catch (PDOException $e) {
    echo json_encode(['exito'=>false,'mensaje'=>'Error BD: ' . $e->getMessage()]);
}
?>
