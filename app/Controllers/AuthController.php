<?php
/**
 * Controllers/AuthController.php
 * Compatible PHP 7.2+
 */
class AuthController {

    private $model;

    public function __construct() {
        global $pdo;
        $this->model = new UsuarioModel($pdo);
    }

    public function login() {
        $d        = json_decode(file_get_contents('php://input'), true) ?? [];
        $correo   = trim($d['correo']   ?? $_POST['correo']   ?? '');
        $password =      $d['password'] ?? $_POST['password'] ?? '';

        if (!$correo || !$password) {
            echo json_encode(['exito' => false, 'mensaje' => 'Correo y contraseña requeridos.']);
            return;
        }

        $u = $this->model->findByCorreo($correo);

        if (!$u || !password_verify($password, $u['password'])) {
            echo json_encode(['exito' => false, 'mensaje' => 'Correo o contraseña incorrectos.']);
            return;
        }

        if (session_status() === PHP_SESSION_NONE) session_start();
        session_regenerate_id(true);

        $_SESSION['usuario_id']     = $u['id_usuario'];
        $_SESSION['usuario_nombre'] = $u['nombre'];
        $_SESSION['usuario_correo'] = $u['correo'];
        $_SESSION['usuario_rol']    = $u['rol'];

        echo json_encode([
            'exito'   => true,
            'mensaje' => '¡Bienvenido, ' . $u['nombre'] . '!',
            'usuario' => [
                'id'     => $u['id_usuario'],
                'nombre' => $u['nombre'],
                'correo' => $u['correo'],
                'rol'    => $u['rol'],
            ],
        ]);
    }

    public function logout() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION = [];
        session_destroy();
        echo json_encode(['exito' => true, 'mensaje' => 'Sesión cerrada.']);
    }

    public function registro() {
        Auth::requireRole('admin');

        $d        = json_decode(file_get_contents('php://input'), true) ?? [];
        $nombre   = trim($d['nombre']   ?? '');
        $rfc      = trim($d['rfc']      ?? '');
        $correo   = trim($d['correo']   ?? '');
        $password =      $d['password'] ?? '';
        $rol      =      $d['rol']      ?? 'empleado';
        $tel      = trim($d['telefono'] ?? '');

        if (!$nombre || !$correo || !$password) {
            echo json_encode(['exito' => false, 'mensaje' => 'Nombre, correo y contraseña son obligatorios.']);
            return;
        }
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['exito' => false, 'mensaje' => 'Correo no válido.']);
            return;
        }
        if ($this->model->existeCorreo($correo)) {
            echo json_encode(['exito' => false, 'mensaje' => 'El correo ya está registrado.']);
            return;
        }
        if (!in_array($rol, ['admin', 'minutero', 'empleado'])) {
            echo json_encode(['exito' => false, 'mensaje' => 'Rol no válido.']);
            return;
        }

        $id = $this->model->create($nombre, $rfc, $correo, $password, $rol, $tel);
        echo json_encode(['exito' => true, 'mensaje' => 'Usuario registrado.', 'id' => $id]);
    }

    public function sesionActual() {
        Auth::requireLogin();
        echo json_encode(['exito' => true, 'usuario' => Auth::usuario()]);
    }
}
