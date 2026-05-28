-- Agregar rol "minutero" al ENUM de usuarios
-- Ejecutar en phpMyAdmin → minutero_inteligente → SQL
USE minutero_inteligente;

ALTER TABLE usuarios
  MODIFY COLUMN rol ENUM('admin','minutero','empleado') NOT NULL DEFAULT 'empleado';

-- Insertar usuario de prueba con rol minutero
INSERT IGNORE INTO usuarios (nombre, rfc, correo, password, rol, telefono) VALUES
('Laura Minutero', 'MILL900101STU', 'laura.minutero@itm.edu.mx',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'minutero', '4436667788');
