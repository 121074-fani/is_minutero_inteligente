<?php
/**
 * Core/Auth.php — Gestión de sesión y permisos por rol
 *
 * Roles:
 *   admin     → acceso total
 *   minutero  → acceso total EXCEPTO eliminar usuarios y minutas
 *   empleado  → solo sus propias tareas, seguimiento y calendario
 */
class Auth {

    // Verificar si hay sesión activa; si no, devolver 401
    public static function requireLogin(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['usuario_id'])) {
            http_response_code(401);
            echo json_encode(['exito'=>false,'mensaje'=>'Sesión no activa.','redirect'=>'login.html']);
            exit;
        }
    }

    // Verificar rol mínimo requerido; si no cumple, devolver 403
    public static function requireRole(string ...$roles): void {
        self::requireLogin();
        $rol = $_SESSION['usuario_rol'] ?? 'empleado';
        if (!in_array($rol, $roles)) {
            http_response_code(403);
            echo json_encode(['exito'=>false,'mensaje'=>'No tienes permiso para esta acción.','rol'=>$rol]);
            exit;
        }
    }

    // ¿Puede eliminar usuarios/minutas? Solo admin
    public static function puedeEliminar(): bool {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return ($_SESSION['usuario_rol'] ?? '') === 'admin';
    }

    // Obtener datos de sesión
    public static function usuario(): array {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return [
            'id'     => $_SESSION['usuario_id']     ?? null,
            'nombre' => $_SESSION['usuario_nombre']  ?? '',
            'correo' => $_SESSION['usuario_correo']  ?? '',
            'rol'    => $_SESSION['usuario_rol']     ?? 'empleado',
        ];
    }

    // ¿Es empleado básico?
    public static function esEmpleado(): bool {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return ($_SESSION['usuario_rol'] ?? '') === 'empleado';
    }
}
