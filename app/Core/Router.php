<?php
/**
 * Core/Router.php — Enrutador MVC sin namespaces
 * Usa strings de nombre de clase en lugar de ::class
 */
class Router {

    private $routes = [];

    public function post(string $action, array $handler): void {
        $this->routes['POST'][$action] = $handler;
    }

    public function get(string $action, array $handler): void {
        $this->routes['GET'][$action] = $handler;
    }

    public function dispatch(string $method, string $action): void {
        $handler = $this->routes[$method][$action] ?? null;

        if (!$handler) {
            http_response_code(404);
            echo json_encode([
                'exito'   => false,
                'mensaje' => "Acción '{$action}' no encontrada (método {$method}).",
            ]);
            return;
        }

        [$className, $methodName] = $handler;

        if (!class_exists($className)) {
            http_response_code(500);
            echo json_encode([
                'exito'   => false,
                'mensaje' => "Clase '{$className}' no cargada. Revisa los require_once en api.php.",
            ]);
            return;
        }

        $controller = new $className();

        if (!method_exists($controller, $methodName)) {
            http_response_code(500);
            echo json_encode([
                'exito'   => false,
                'mensaje' => "Método '{$methodName}' no existe en {$className}.",
            ]);
            return;
        }

        $controller->$methodName();
    }
}
