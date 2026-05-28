<?php
/**
 * login_action.php
 * Endpoint AJAX para autenticación de usuarios.
 * Recibe JSON via POST y responde JSON.
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['exito' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

require_once 'conexion.php';   // $pdo disponible
require_once 'Usuario.php';

// Leer cuerpo JSON enviado por fetch()
$body   = file_get_contents('php://input');
$datos  = json_decode($body, true);

// Compatibilidad: también acepta $_POST normal
$correo   = trim($datos['correo']   ?? $_POST['correo']   ?? '');
$password =      $datos['password'] ?? $_POST['password'] ?? '';

$usuario = new Usuario($pdo);
$resultado = $usuario->login($correo, $password);

echo json_encode($resultado);
?>
