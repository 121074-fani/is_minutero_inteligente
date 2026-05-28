<?php
header('Content-Type: application/json; charset=UTF-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['exito'=>false,'mensaje'=>'Método no permitido.']); exit; }
require_once 'conexion.php';
require_once 'Tarea.php';

$body  = file_get_contents('php://input');
$datos = json_decode($body, true);

$titulo           = $datos['titulo']           ?? $_POST['titulo']           ?? '';
$responsable      = $datos['responsable']      ?? $_POST['responsable']      ?? '';
$departamento     = $datos['departamento']     ?? $_POST['departamento']     ?? '';
$fecha_compromiso = $datos['fecha_compromiso'] ?? $_POST['fecha_compromiso'] ?? '';

$tarea     = new Tarea($pdo);
$resultado = $tarea->crear($titulo, $responsable, $departamento, $fecha_compromiso);
echo json_encode($resultado);
?>
