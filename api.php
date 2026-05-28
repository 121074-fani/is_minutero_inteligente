<?php
/**
 * api.php — Punto de entrada único MVC
 * Sin namespaces — carga explícita con require_once.
 * Los "Undefined type" de VS Code son falsos positivos:
 * PHP carga las clases antes de usarlas mediante require_once.
 */

// Capturar errores fatales y devolverlos como JSON
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode([
            'exito'   => false,
            'mensaje' => 'Error PHP: ' . $e['message'],
            'archivo' => $e['file'],
            'linea'   => $e['line'],
        ]);
    }
});

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── Rutas absolutas ────────────────────────────────────────
$base = __DIR__;

// ── 1. Conexión PDO ────────────────────────────────────────
require_once $base . '/php/conexion.php'; // define $pdo

// ── 2. Core ────────────────────────────────────────────────
require_once $base . '/app/Core/Router.php';
require_once $base . '/app/Core/Auth.php';
require_once $base . '/app/Core/MailService.php';

// ── 3. Models ──────────────────────────────────────────────
require_once $base . '/app/Models/UsuarioModel.php';
require_once $base . '/app/Models/TareaModel.php';
require_once $base . '/app/Models/MinutaModel.php';

// ── 4. Controllers ─────────────────────────────────────────
require_once $base . '/app/Controllers/AuthController.php';
require_once $base . '/app/Controllers/TareaController.php';
require_once $base . '/app/Controllers/MinutaController.php';
require_once $base . '/app/Controllers/UsuarioController.php';

// ── 5. Acción y método ─────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$action = trim($_GET['action'] ?? '');

// Sin acción → info de la API
if ($action === '') {
    echo json_encode([
        'exito'   => true,
        'api'     => 'Minutero Inteligente MVC v2.0',
        'estado'  => 'activo',
    ]);
    exit;
}

// ── 6. Feriados (servicio web externo, sin auth) ───────────
if ($action === 'feriados') {
    $anio = (int) ($_GET['año'] ?? date('Y'));
    $url  = "https://date.nager.at/api/v3/PublicHolidays/{$anio}/MX";
    $resp = @file_get_contents($url, false, stream_context_create([
        'http' => ['timeout' => 6],
    ]));
    $lista = $resp
        ? json_decode($resp, true)
        : [
            ['date' => "{$anio}-01-01", 'localName' => 'Año Nuevo'],
            ['date' => "{$anio}-02-05", 'localName' => 'Día de la Constitución'],
            ['date' => "{$anio}-03-21", 'localName' => 'Natalicio de Juárez'],
            ['date' => "{$anio}-05-01", 'localName' => 'Día del Trabajo'],
            ['date' => "{$anio}-09-16", 'localName' => 'Independencia'],
            ['date' => "{$anio}-11-20", 'localName' => 'Revolución'],
            ['date' => "{$anio}-12-25", 'localName' => 'Navidad'],
        ];
    echo json_encode(['exito' => true, 'feriados' => $lista]);
    exit;
}

// ── 7. Router ──────────────────────────────────────────────
$router = new Router();

// Auth
$router->post('login',    ['AuthController', 'login']);
$router->post('logout',   ['AuthController', 'logout']);
$router->post('registro', ['AuthController', 'registro']);
$router->get ('sesion',   ['AuthController', 'sesionActual']);

// Tareas
$router->get ('tareas.listar',   ['TareaController', 'listar']);
$router->post('tareas.crear',    ['TareaController', 'crear']);
$router->post('tareas.estado',   ['TareaController', 'actualizarEstado']);
$router->post('tareas.eliminar', ['TareaController', 'eliminar']);

// Minutas
$router->get ('minutas.listar',         ['MinutaController', 'listar']);
$router->get ('minutas.detalle',        ['MinutaController', 'detalle']);
$router->post('minutas.token',          ['MinutaController', 'solicitarToken']);
$router->post('minutas.verificar',      ['MinutaController', 'verificarToken']);
$router->post('minutas.guardar',        ['MinutaController', 'guardar']);
$router->post('minutas.firmas',         ['MinutaController', 'enviarFirmas']);
$router->get ('minutas.estadoFirmas',   ['MinutaController', 'estadoFirmas']);
$router->get ('minutas.validarFirma',   ['MinutaController', 'validarFirma']);
$router->post('minutas.registrarFirma', ['MinutaController', 'registrarFirma']);
$router->post('minutas.eliminar',       ['MinutaController', 'eliminar']);

// Usuarios
$router->get ('usuarios.listar',   ['UsuarioController', 'listar']);
$router->get ('usuarios.detalle',  ['UsuarioController', 'detalle']);
$router->post('usuarios.telefono', ['UsuarioController', 'actualizarTelefono']);
$router->post('usuarios.eliminar', ['UsuarioController', 'eliminar']);
$router->get ('usuarios.permisos', ['UsuarioController', 'permisos']);

$router->dispatch($method, $action);
