<?php
header('Content-Type: application/json; charset=UTF-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['exito'=>false,'mensaje'=>'Método no permitido.']); exit; }
require_once 'conexion.php';

$body       = file_get_contents('php://input');
$datos      = json_decode($body, true);
$id_usuario = (int) ($datos['id_usuario'] ?? 0);
$telefono   = trim($datos['telefono'] ?? '');

if (!$id_usuario) { echo json_encode(['exito'=>false,'mensaje'=>'ID inválido.']); exit; }

$stmt = $pdo->prepare('UPDATE usuarios SET telefono = :tel WHERE id_usuario = :id');
$stmt->execute([':tel' => $telefono, ':id' => $id_usuario]);
echo json_encode(['exito' => true, 'mensaje' => 'Teléfono actualizado.']);
?>
