<?php
header('Content-Type: application/json; charset=UTF-8');
require_once 'conexion.php';
require_once 'Minuta.php';

$minuta = new Minuta($pdo);
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $resultado = $minuta->obtenerPorId((int) $_GET['id']);
    echo $resultado ? json_encode(['exito'=>true,'minuta'=>$resultado]) : json_encode(['exito'=>false,'mensaje'=>'No encontrada.']);
} else {
    $lista = $minuta->obtenerTodas();
    echo json_encode(['exito' => true, 'minutas' => $lista]);
}
?>
