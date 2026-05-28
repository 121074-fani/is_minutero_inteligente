<?php
/**
 * Clase Usuario
 * Maneja registro, autenticación y sesiones con PDO y consultas preparadas.
 * PHP orientado a objetos — requisito académico.
 */
class Usuario {

    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // -----------------------------------------------------------------------
    // REGISTRO DE NUEVO USUARIO
    // -----------------------------------------------------------------------
    public function registrar(string $nombre, string $rfc, string $correo,
                               string $password, string $rol,
                               string $telefono = ''): array {

        // 1. Validaciones básicas del lado del servidor
        if (empty($nombre) || empty($correo) || empty($password) || empty($rol)) {
            return ['exito' => false, 'mensaje' => 'Todos los campos obligatorios deben llenarse.'];
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return ['exito' => false, 'mensaje' => 'El correo electrónico no es válido.'];
        }

        if (strlen($password) < 6) {
            return ['exito' => false, 'mensaje' => 'La contraseña debe tener al menos 6 caracteres.'];
        }

        // 2. Verificar si el correo ya existe (consulta preparada)
        $stmt = $this->pdo->prepare('SELECT id_usuario FROM usuarios WHERE correo = :correo LIMIT 1');
        $stmt->execute([':correo' => $correo]);
        if ($stmt->fetch()) {
            return ['exito' => false, 'mensaje' => 'Ya existe una cuenta con ese correo electrónico.'];
        }

        // 3. Hash seguro de la contraseña (nunca texto plano)
        $hash = password_hash($password, PASSWORD_BCRYPT);

        // 4. Inserción con consulta preparada
        $insert = $this->pdo->prepare(
            'INSERT INTO usuarios (nombre, rfc, correo, password, rol, telefono, fecha_registro)
             VALUES (:nombre, :rfc, :correo, :password, :rol, :telefono, NOW())'
        );

        $insert->execute([
            ':nombre'   => htmlspecialchars(trim($nombre)),
            ':rfc'      => strtoupper(trim($rfc)),
            ':correo'   => strtolower(trim($correo)),
            ':password' => $hash,
            ':rol'      => $rol,
            ':telefono' => $telefono,
        ]);

        return ['exito' => true, 'mensaje' => 'Usuario registrado exitosamente.', 'id' => $this->pdo->lastInsertId()];
    }

    // -----------------------------------------------------------------------
    // LOGIN — AUTENTICACIÓN Y APERTURA DE SESIÓN
    // -----------------------------------------------------------------------
    public function login(string $correo, string $password): array {

        if (empty($correo) || empty($password)) {
            return ['exito' => false, 'mensaje' => 'Correo y contraseña son obligatorios.'];
        }

        // Consulta preparada — previene SQL injection
        $stmt = $this->pdo->prepare(
            'SELECT id_usuario, nombre, correo, password, rol FROM usuarios WHERE correo = :correo LIMIT 1'
        );
        $stmt->execute([':correo' => strtolower(trim($correo))]);
        $usuario = $stmt->fetch();

        // Verificar contraseña con hash (password_verify)
        if (!$usuario || !password_verify($password, $usuario['password'])) {
            return ['exito' => false, 'mensaje' => 'Correo o contraseña incorrectos.'];
        }

        // Iniciar sesión PHP segura
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Regenerar ID de sesión para prevenir Session Fixation
        session_regenerate_id(true);

        $_SESSION['usuario_id']     = $usuario['id_usuario'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        $_SESSION['usuario_correo'] = $usuario['correo'];
        $_SESSION['usuario_rol']    = $usuario['rol'];
        $_SESSION['inicio_sesion']  = time();

        return [
            'exito'   => true,
            'mensaje' => '¡Bienvenido, ' . $usuario['nombre'] . '!',
            'usuario' => [
                'id'     => $usuario['id_usuario'],
                'nombre' => $usuario['nombre'],
                'correo' => $usuario['correo'],
                'rol'    => $usuario['rol'],
            ]
        ];
    }

    // -----------------------------------------------------------------------
    // CERRAR SESIÓN
    // -----------------------------------------------------------------------
    public function logout(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        session_destroy();
    }

    // -----------------------------------------------------------------------
    // VERIFICAR SI HAY SESIÓN ACTIVA (útil para proteger páginas)
    // -----------------------------------------------------------------------
    public static function verificarSesion(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['usuario_id']);
    }

    // -----------------------------------------------------------------------
    // OBTENER TODOS LOS USUARIOS (para catálogo)
    // -----------------------------------------------------------------------
    public function obtenerTodos(): array {
        $stmt = $this->pdo->prepare(
            'SELECT id_usuario, nombre, rfc, correo, rol, telefono, fecha_registro FROM usuarios ORDER BY nombre ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // -----------------------------------------------------------------------
    // OBTENER USUARIO POR ID
    // -----------------------------------------------------------------------
    public function obtenerPorId(int $id): array|false {
        $stmt = $this->pdo->prepare(
            'SELECT id_usuario, nombre, rfc, correo, rol, telefono, fecha_registro FROM usuarios WHERE id_usuario = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
}
?>
