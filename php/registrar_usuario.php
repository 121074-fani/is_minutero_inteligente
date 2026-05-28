<?php
/**
 * registrar_usuario.php
 * Endpoint AJAX para registrar nuevos usuarios.
 * Recibe JSON via POST y responde JSON.
 */

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['exito' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

require_once 'conexion.php';
require_once 'Usuario.php';

$body  = file_get_contents('php://input');
$datos = json_decode($body, true);

// Compatibilidad con formulario tradicional $_POST
$nombre   = trim($datos['nombre']    ?? $_POST['nombre']    ?? '');
$rfc      = trim($datos['rfc']       ?? $_POST['rfc']       ?? '');
$correo   = trim($datos['correo']    ?? $_POST['correo']    ?? '');
$password =      $datos['password']  ?? $_POST['password']  ?? '';
$rol      =      $datos['rol']       ?? $_POST['rol']       ?? 'empleado';
$telefono = trim($datos['telefono']  ?? $_POST['telefono']  ?? '');

$usuario   = new Usuario($pdo);
$resultado = $usuario->registrar($nombre, $rfc, $correo, $password, $rol, $telefono);

echo json_encode($resultado);
?>
