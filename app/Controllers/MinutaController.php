<?php
/**
 * Controllers/MinutaController.php — Compatible PHP 7.2+
 * admin/minutero → todas las minutas
 * empleado       → solo minutas donde es responsable o participante
 */
class MinutaController {

    private $model;
    private $tareaModel;

    public function __construct() {
        global $pdo;
        $this->model      = new MinutaModel($pdo);
        $this->tareaModel = new TareaModel($pdo);
    }

    public function listar() {
        Auth::requireLogin();
        $u = Auth::usuario();

        // Empleado: solo ve minutas donde aparece su correo
        if (Auth::esEmpleado()) {
            $minutas = $this->model->getByParticipante($u['correo'], $u['nombre']);
        } else {
            $minutas = $this->model->getAll();
        }
        echo json_encode(['exito' => true, 'minutas' => $minutas, 'rol' => $u['rol']]);
    }

    public function detalle() {
        Auth::requireLogin();
        $id = (int) ($_GET['id'] ?? 0);
        $m  = $id ? $this->model->getById($id) : false;

        // Empleado: verificar que tenga acceso
        if ($m && Auth::esEmpleado()) {
            $u = Auth::usuario();
            if (!$this->model->tieneAcceso($id, $u['correo'], $u['nombre'])) {
                http_response_code(403);
                echo json_encode(['exito' => false, 'mensaje' => 'Sin acceso a esta minuta.']);
                return;
            }
        }

        echo $m
            ? json_encode(['exito' => true,  'minuta' => $m])
            : json_encode(['exito' => false, 'mensaje' => 'No encontrada.']);
    }

    public function solicitarToken() {
        Auth::requireRole('admin', 'minutero');
        $d      = json_decode(file_get_contents('php://input'), true) ?? [];
        $correo = trim($d['correo'] ?? '');
        $lugar  = trim($d['lugar']  ?? 'Reunión');

        if (!$correo) {
            echo json_encode(['exito' => false, 'mensaje' => 'Correo requerido.']);
            return;
        }

        $idMinuta = $this->model->crearProvisional($lugar, $correo);
        $token    = $this->model->crearTokenOTP($idMinuta, $correo);

        global $pdo;
        $s = $pdo->prepare('SELECT nombre FROM usuarios WHERE correo = :c LIMIT 1');
        $s->execute([':c' => strtolower($correo)]);
        $u      = $s->fetch();
        $nombre = $u ? $u['nombre'] : 'Responsable';

        $html    = MailService::templateToken($nombre, $token, $lugar);
        $enviado = MailService::enviar($correo, "Token de validación — Minuta #{$idMinuta}", $html);

        echo json_encode([
            'exito'     => true,
            'id_minuta' => $idMinuta,
            'token'     => $token,
            'enviado'   => $enviado,
            'simulado'  => true,
            'mensaje'   => "Token enviado a {$correo}. Válido 30 minutos.",
        ]);
    }

    public function verificarToken() {
        Auth::requireRole('admin', 'minutero');
        $d        = json_decode(file_get_contents('php://input'), true) ?? [];
        $idMinuta = (int) ($d['id_minuta'] ?? 0);
        $token    = trim($d['token'] ?? '');

        if (!$idMinuta || strlen($token) !== 6) {
            echo json_encode(['exito' => false, 'mensaje' => 'Datos incompletos.']);
            return;
        }
        echo json_encode($this->model->verificarTokenOTP($idMinuta, $token));
    }

    public function guardar() {
        Auth::requireRole('admin', 'minutero');
        $d        = json_decode(file_get_contents('php://input'), true) ?? [];
        $idMinuta = (int) ($d['id_minuta'] ?? 0);
        $temas    = $d['temas']    ?? [];
        $acuerdos = $d['acuerdos'] ?? [];

        if (!$idMinuta) {
            echo json_encode(['exito' => false, 'mensaje' => 'id_minuta requerido.']);
            return;
        }

        global $pdo;
        try {
            $pdo->beginTransaction();
            $this->model->actualizar($idMinuta, $d, $temas);
            $pdo->prepare('DELETE FROM tarea WHERE id_minuta = :id')->execute([':id' => $idMinuta]);
            foreach ($acuerdos as $a) {
                if (empty($a['actividad']) || empty($a['responsable'])) continue;
                $this->tareaModel->create(
                    $a['actividad'], $a['responsable'],
                    $d['area'] ?? '', $a['fecha_acuerdo'] ?? '',
                    $idMinuta
                );
            }
            $pdo->commit();
            $cnt = $pdo->prepare('SELECT COUNT(*) FROM tarea WHERE id_minuta = :id');
            $cnt->execute([':id' => $idMinuta]);
            echo json_encode([
                'exito'          => true,
                'id_minuta'      => $idMinuta,
                'tareas_creadas' => (int) $cnt->fetchColumn(),
                'mensaje'        => 'Minuta guardada.',
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['exito' => false, 'mensaje' => 'Error BD: ' . $e->getMessage()]);
        }
    }

    public function enviarFirmas() {
        Auth::requireRole('admin', 'minutero');
        $d             = json_decode(file_get_contents('php://input'), true) ?? [];
        $idMinuta      = (int)   ($d['id_minuta']    ?? 0);
        $participantes = (array) ($d['participantes'] ?? []);
        $pdfBase64     =          $d['pdf_base64']   ?? '';

        if (!$idMinuta || empty($participantes)) {
            echo json_encode(['exito' => false, 'mensaje' => 'Faltan datos.']);
            return;
        }

        $minuta  = $this->model->getById($idMinuta);
        $tokens  = [];
        $enviados = 0;

        foreach ($participantes as $p) {
            $correo = trim($p['correo'] ?? '');
            $nombre = trim($p['nombre'] ?? $correo);
            if (!$correo || !filter_var($correo, FILTER_VALIDATE_EMAIL)) continue;
            $token = $this->model->crearTokenFirma($idMinuta, $correo, $nombre);
            $url   = "http://localhost/minutero/firmar_minuta.html?token={$token}";
            $html  = MailService::templateFirma($nombre, $idMinuta, $url, $minuta['lugar'] ?? '—', $minuta['fecha'] ?? '—');
            MailService::enviar($correo, "Minuta #{$idMinuta} — Firma tu acuse", $html, $pdfBase64, "Minuta_{$idMinuta}.pdf");
            $tokens[] = ['nombre' => $nombre, 'correo' => $correo, 'token' => $token, 'url' => $url];
            $enviados++;
        }

        echo json_encode(['exito' => true, 'mensaje' => "{$enviados} token(s) generados.", 'tokens_generados' => $tokens]);
    }

    public function estadoFirmas() {
        Auth::requireRole('admin', 'minutero');
        $id = (int) ($_GET['id_minuta'] ?? 0);
        echo json_encode(['exito' => true, 'firmas' => $this->model->estadoFirmas($id)]);
    }

    public function validarFirma() {
        $token = trim($_GET['token'] ?? '');
        echo json_encode($this->model->verificarTokenFirma($token));
    }

    public function registrarFirma() {
        $d      = json_decode(file_get_contents('php://input'), true) ?? [];
        $token  = trim($d['token']   ?? '');
        $camara = (bool) ($d['camara'] ?? false);
        echo json_encode($this->model->registrarFirma($token, $camara));
    }

    public function eliminar() {
        if (!Auth::puedeEliminar()) {
            http_response_code(403);
            echo json_encode(['exito' => false, 'mensaje' => 'Solo administradores pueden eliminar minutas.']);
            return;
        }
        $d  = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int) ($d['id_minuta'] ?? 0);
        echo json_encode(['exito' => $this->model->delete($id), 'mensaje' => 'Minuta eliminada.']);
    }
}
