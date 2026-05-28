<?php
header('Content-Type: application/json; charset=UTF-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['exito'=>false,'mensaje'=>'Método no permitido.']); exit; }
require_once 'conexion.php';
require_once 'Tarea.php';

$body     = file_get_contents('php://input');
$datos    = json_decode($body, true);
$id_tarea = (int) ($datos['id_tarea'] ?? $_POST['id_tarea'] ?? 0);
$estado   =        $datos['estado']   ?? $_POST['estado']   ?? '';

if (!$id_tarea || empty($estado)) { echo json_encode(['exito'=>false,'mensaje'=>'id_tarea y estado son obligatorios.']); exit; }

$tarea     = new Tarea($pdo);
$resultado = $tarea->actualizarEstado($id_tarea, $estado);
echo json_encode($resultado);
?>
