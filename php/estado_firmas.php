<?php
header('Content-Type: application/json; charset=UTF-8');
require_once 'conexion.php';

$idMinuta = (int) ($_GET['id_minuta'] ?? 0);
if (!$idMinuta) { echo json_encode(['exito'=>false,'mensaje'=>'id_minuta requerido.']); exit; }

try {
    $stmt = $pdo->prepare(
        'SELECT nombre, correo, firmado, fecha_firma, camara_verificada, fecha_expiracion
         FROM tokens_firma
         WHERE id_minuta = :id
         ORDER BY nombre ASC'
    );
    $stmt->execute([':id' => $idMinuta]);
    $firmas = $stmt->fetchAll();
    echo json_encode(['exito'=>true,'firmas'=>$firmas]);
} catch (PDOException $e) {
    echo json_encode(['exito'=>false,'mensaje'=>'Error BD: '.$e->getMessage()]);
}
?>
