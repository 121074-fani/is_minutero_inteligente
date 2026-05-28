<?php
/**
 * Controllers/UsuarioController.php
 * Compatible PHP 7.2+
 */
class UsuarioController {

    private $model;

    public function __construct() {
        global $pdo;
        $this->model = new UsuarioModel($pdo);
    }

    public function listar() {
        Auth::requireRole('admin', 'minutero');
        echo json_encode(['exito' => true, 'usuarios' => $this->model->getAll()]);
    }

    public function detalle() {
        Auth::requireLogin();
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            echo json_encode(['exito' => false, 'mensaje' => 'ID requerido.']);
            return;
        }
        if (Auth::esEmpleado() && Auth::usuario()['id'] !== $id) {
            http_response_code(403);
            echo json_encode(['exito' => false, 'mensaje' => 'Acceso denegado.']);
            return;
        }
        $u = $this->model->findById($id);
        echo $u
            ? json_encode(['exito' => true, 'usuario' => $u])
            : json_encode(['exito' => false, 'mensaje' => 'No encontrado.']);
    }

    public function actualizarTelefono() {
        Auth::requireLogin();
        $d   = json_decode(file_get_contents('php://input'), true) ?? [];
        $id  = (int) ($d['id_usuario'] ?? 0);
        $tel = trim($d['telefono'] ?? '');
        $u   = Auth::usuario();

        if ($u['rol'] !== 'admin' && $u['id'] !== $id) {
            http_response_code(403);
            echo json_encode(['exito' => false, 'mensaje' => 'No autorizado.']);
            return;
        }
        echo json_encode([
            'exito'   => $this->model->updateTelefono($id, $tel),
            'mensaje' => 'Teléfono actualizado.',
        ]);
    }

    public function eliminar() {
        if (!Auth::puedeEliminar()) {
            http_response_code(403);
            echo json_encode(['exito' => false, 'mensaje' => 'Solo administradores pueden eliminar usuarios.']);
            return;
        }
        $d  = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int) ($d['id_usuario'] ?? 0);
        echo json_encode(['exito' => $this->model->delete($id), 'mensaje' => 'Usuario eliminado.']);
    }

    public function permisos() {
        Auth::requireLogin();
        $u = Auth::usuario();
        echo json_encode([
            'exito'  => true,
            'rol'    => $u['rol'],
            'nombre' => $u['nombre'],
            'puede'  => [
                'verTodo'          => in_array($u['rol'], ['admin', 'minutero']),
                'crearMinutas'     => in_array($u['rol'], ['admin', 'minutero']),
                'eliminarUsuarios' => $u['rol'] === 'admin',
                'eliminarMinutas'  => $u['rol'] === 'admin',
                'soloPropias'      => $u['rol'] === 'empleado',
            ],
        ]);
    }
}
