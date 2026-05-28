-- ============================================================
-- minutero_inteligente — Script de creación de tablas
-- Compatible con MySQL / MariaDB (XAMPP)
-- Ejecutar en phpMyAdmin o consola MySQL
-- ============================================================

-- Usar la base de datos del proyecto
USE minutero_inteligente;

-- ============================================================
-- TABLA: usuarios
-- ============================================================
CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario      INT AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(100)  NOT NULL,
    rfc             VARCHAR(13)   NOT NULL,
    correo          VARCHAR(100)  NOT NULL UNIQUE,
    password        VARCHAR(255)  NOT NULL,          -- almacena hash bcrypt
    rol             ENUM('admin','gerente','empleado') NOT NULL DEFAULT 'empleado',
    telefono        VARCHAR(15)   DEFAULT NULL,
    fecha_registro  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_correo (correo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TABLA: detalles_minuta
-- ============================================================
CREATE TABLE IF NOT EXISTS detalles_minuta (
    id_minuta           INT AUTO_INCREMENT PRIMARY KEY,
    lugar               VARCHAR(100)  NOT NULL,
    fecha               DATE          NOT NULL,
    hora                TIME          DEFAULT NULL,
    correo_responsable  VARCHAR(100)  NOT NULL,
    tipo                ENUM('presencial','virtual') NOT NULL DEFAULT 'presencial',
    area                VARCHAR(60)   DEFAULT NULL,
    temas_json          LONGTEXT      DEFAULT NULL,   -- JSON con array de temas
    fecha_proxima       DATE          DEFAULT NULL,
    hora_proxima        TIME          DEFAULT NULL,
    fecha_registro      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TABLA: tarea
-- ============================================================
CREATE TABLE IF NOT EXISTS tarea (
    id_tarea            INT AUTO_INCREMENT PRIMARY KEY,
    titulo              VARCHAR(200)  NOT NULL,
    responsable         VARCHAR(100)  DEFAULT NULL,
    departamento        VARCHAR(60)   DEFAULT NULL,
    fecha_compromiso    DATE          DEFAULT NULL,
    estado              ENUM('pendiente','en_progreso','completada','cancelada')
                                      NOT NULL DEFAULT 'pendiente',
    id_minuta           INT           DEFAULT NULL,   -- FK a detalles_minuta
    fecha_creacion      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_estado (estado),
    INDEX idx_fecha_compromiso (fecha_compromiso),
    CONSTRAINT fk_tarea_minuta
        FOREIGN KEY (id_minuta) REFERENCES detalles_minuta(id_minuta)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- USUARIO ADMINISTRADOR DE PRUEBA
-- contraseña: Admin123
-- (hash generado con password_hash('Admin123', PASSWORD_BCRYPT))
-- ============================================================
INSERT IGNORE INTO usuarios (nombre, rfc, correo, password, rol)
VALUES (
    'Administrador',
    'XAXX010101000',
    'admin@minutero.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin'
);
