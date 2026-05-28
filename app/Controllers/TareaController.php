<?php
/**
 * Controllers/TareaController.php
 * Compatible PHP 7.2+
 */
class TareaController {

    private $model;

    public function __construct() {
        global $pdo;
        $this->model = new TareaModel($pdo);
    }

    public function listar() {
        Auth::requireLogin();
        $u     = Auth::usuario();
        $depto = trim($_GET['departamento'] ?? '');
        $resp  = trim($_GET['responsable']  ?? '');
        $fecha = trim($_GET['fecha']        ?? '');

        if (Auth::esEmpleado()) {
            $tareas   = $this->model->getByResponsable($u['nombre'], $depto, $fecha);
            $metricas = $this->model->metricasPorResponsable($u['nombre']);
        } else {
            $tareas   = $this->model->getAll($depto, $resp, $fecha);
            $metricas = $this->model->metricas();
        }

        echo json_encode(['exito' => true, 'tareas' => $tareas, 'metricas' => $metricas]);
    }

    public function crear() {
        Auth::requireRole('admin', 'minutero');
        $d     = json_decode(file_get_contents('php://input'), true) ?? [];
        $titulo = $d['titulo']           ?? '';
        $resp   = $d['responsable']      ?? '';
        $depto  = $d['departamento']     ?? '';
        $fecha  = $d['fecha_compromiso'] ?? '';

        if (!$titulo || !$fecha) {
            echo json_encode(['exito' => false, 'mensaje' => 'Título y fecha son obligatorios.']);
            return;
        }
        $id = $this->model->create($titulo, $resp, $depto, $fecha);
        echo json_encode(['exito' => true, 'id' => $id, 'mensaje' => 'Tarea creada.']);
    }

    public function actualizarEstado() {
        Auth::requireLogin();
        $d      = json_decode(file_get_contents('php://input'), true) ?? [];
        $id     = (int) ($d['id_tarea'] ?? 0);
        $estado =        $d['estado']   ?? '';

        if (!$id || !$estado) {
            echo json_encode(['exito' => false, 'mensaje' => 'id_tarea y estado requeridos.']);
            return;
        }

        if (Auth::esEmpleado()) {
            $u = Auth::usuario();
            $t = $this->model->getById($id);
            if (!$t || strpos($t['responsable'], $u['nombre']) === false) {
                http_response_code(403);
                echo json_encode(['exito' => false, 'mensaje' => 'No puedes editar tareas ajenas.']);
                return;
            }
        }

        $ok = $this->model->updateEstado($id, $estado);
        echo json_encode(['exito' => $ok, 'mensaje' => $ok ? 'Estado actualizado.' : 'No se pudo actualizar.']);
    }

    public function eliminar() {
        Auth::requireRole('admin');
        $d  = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int) ($d['id_tarea'] ?? 0);
        if (!$id) {
            echo json_encode(['exito' => false, 'mensaje' => 'ID requerido.']);
            return;
        }
        echo json_encode(['exito' => $this->model->delete($id), 'mensaje' => 'Tarea eliminada.']);
    }
}
