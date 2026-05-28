<?php
header('Content-Type: application/json; charset=UTF-8');
require_once 'conexion.php';
require_once 'Tarea.php';

$departamento = trim($_GET['departamento'] ?? '');
$responsable  = trim($_GET['responsable']  ?? '');
$fecha        = trim($_GET['fecha']        ?? '');

$tarea    = new Tarea($pdo);
$lista    = $tarea->obtenerTodas($departamento, $responsable, $fecha);
$metricas = $tarea->obtenerMetricas();

echo json_encode(['exito' => true, 'tareas' => $lista, 'metricas' => $metricas]);
?>
