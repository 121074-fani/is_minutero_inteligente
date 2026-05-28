-- ============================================================
-- EJECUTA ESTO EN phpMyAdmin → minutero_inteligente → SQL
-- ============================================================
USE minutero_inteligente;

-- Tabla para tokens del responsable (validación previa)
CREATE TABLE IF NOT EXISTS tokens_validacion (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    id_minuta        INT          NOT NULL,
    correo           VARCHAR(100) NOT NULL,
    token            VARCHAR(10)  NOT NULL,
    usado            TINYINT(1)   NOT NULL DEFAULT 0,
    fecha_creacion   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion DATETIME     NOT NULL,
    INDEX idx_token   (token),
    INDEX idx_minuta  (id_minuta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla para tokens de firma de participantes (72 horas)
CREATE TABLE IF NOT EXISTS tokens_firma (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    id_minuta         INT          NOT NULL,
    correo            VARCHAR(100) NOT NULL,
    nombre            VARCHAR(100) NOT NULL,
    token             VARCHAR(64)  NOT NULL UNIQUE,
    firmado           TINYINT(1)   NOT NULL DEFAULT 0,
    fecha_firma       DATETIME     DEFAULT NULL,
    camara_verificada TINYINT(1)   NOT NULL DEFAULT 0,
    fecha_creacion    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion  DATETIME     NOT NULL,
    INDEX idx_token_firma  (token),
    INDEX idx_minuta_firma (id_minuta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Agregar columnas a detalles_minuta (ignorar si ya existen)
ALTER TABLE detalles_minuta
    ADD COLUMN IF NOT EXISTS participantes_json LONGTEXT    DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS token_validacion   VARCHAR(10) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS validada           TINYINT(1)  NOT NULL DEFAULT 0;
