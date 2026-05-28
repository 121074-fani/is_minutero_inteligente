<?php
/**
 * logout.php
 * Cierra la sesión PHP y redirige al login.
 */

require_once 'conexion.php';
require_once 'Usuario.php';

$usuario = new Usuario($pdo);
$usuario->logout();

// Si la petición es AJAX responde JSON, si no redirige
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode(['exito' => true, 'mensaje' => 'Sesión cerrada.']);
} else {
    header('Location: ../login.html');
}
exit;
?>
