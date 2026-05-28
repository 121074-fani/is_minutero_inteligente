<?php
header('Content-Type: application/json; charset=UTF-8');
require_once 'conexion.php';
require_once 'Usuario.php';

$usuario = new Usuario($pdo);
$lista   = $usuario->obtenerTodos();
echo json_encode(['exito' => true, 'usuarios' => $lista]);
?>
