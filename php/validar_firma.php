<?php
header('Content-Type: application/json; charset=UTF-8');
require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Verificar token para mostrar la página de firma
    $token = trim($_GET['token'] ?? '');
    if (!$token) { echo json_encode(['exito'=>false,'mensaje'=>'Token requerido.']); exit; }

    try {
        $stmt = $pdo->prepare(
            'SELECT tf.*, dm.lugar, dm.fecha, dm.area
             FROM tokens_firma tf
             JOIN detalles_minuta dm ON tf.id_minuta = dm.id_minuta
             WHERE tf.token = :token
             LIMIT 1'
        );
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();

        if (!$row) {
            echo json_encode(['exito'=>false,'mensaje'=>'Token inválido. El enlace no existe.']); exit;
        }
        if ($row['firmado']) {
            echo json_encode(['exito'=>false,'mensaje'=>'Este token ya fue usado para firmar.','ya_firmado'=>true]); exit;
        }
        if ($row['fecha_expiracion'] < date('Y-m-d H:i:s')) {
            echo json_encode(['exito'=>false,'mensaje'=>'Token expirado. Han pasado más de 72 horas.']); exit;
        }

        echo json_encode(['exito'=>true,'datos'=>$row]);

    } catch (PDOException $e) {
        echo json_encode(['exito'=>false,'mensaje'=>'Error BD: '.$e->getMessage()]);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Registrar la firma del participante
    $body     = file_get_contents('php://input');
    $datos    = json_decode($body, true);
    $token    = trim($datos['token']  ?? '');
    $camaraOk = (bool) ($datos['camara'] ?? false);

    if (!$token) { echo json_encode(['exito'=>false,'mensaje'=>'Token requerido.']); exit; }

    try {
        // Re-verificar antes de firmar
        $stmt = $pdo->prepare(
            'SELECT id, nombre, firmado, fecha_expiracion FROM tokens_firma WHERE token=:token LIMIT 1'
        );
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();

        if (!$row) {
            echo json_encode(['exito'=>false,'mensaje'=>'Token inválido.']); exit;
        }
        if ($row['firmado']) {
            echo json_encode(['exito'=>false,'mensaje'=>'Ya habías firmado anteriormente.','ya_firmado'=>true]); exit;
        }
        if ($row['fecha_expiracion'] < date('Y-m-d H:i:s')) {
            echo json_encode(['exito'=>false,'mensaje'=>'Token expirado.']); exit;
        }

        // Registrar firma
        $pdo->prepare(
            'UPDATE tokens_firma SET firmado=1, fecha_firma=NOW(), camara_verificada=:cam WHERE token=:token'
        )->execute([':cam' => (int)$camaraOk, ':token' => $token]);

        echo json_encode([
            'exito'   => true,
            'mensaje' => 'Firma registrada correctamente.',
            'nombre'  => $row['nombre']
        ]);

    } catch (PDOException $e) {
        echo json_encode(['exito'=>false,'mensaje'=>'Error BD: '.$e->getMessage()]);
    }

} else {
    http_response_code(405);
    echo json_encode(['exito'=>false,'mensaje'=>'Método no permitido.']);
}
?>
