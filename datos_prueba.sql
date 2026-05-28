-- ============================================================
-- DATOS DE PRUEBA — Minutero Inteligente
-- Ejecutar en phpMyAdmin → BD minutero_inteligente → pestaña SQL
-- ============================================================

USE minutero_inteligente;

-- ============================================================
-- USUARIOS (password de todos: Admin123)
-- ============================================================
INSERT INTO usuarios (nombre, rfc, correo, password, rol, telefono) VALUES
('Carlos Mendoza',   'MECC850312ABC', 'carlos.mendoza@itm.edu.mx',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',   '4431234567'),
('Ana Ramírez',      'RAGA920615DEF', 'ana.ramirez@itm.edu.mx',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'gerente', '4439876543'),
('Juan Pérez',       'PEPJ880101GHI', 'juan.perez@itm.edu.mx',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'empleado','4435551234'),
('Lucía Torres',     'TOLS950720JKL', 'lucia.torres@itm.edu.mx',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'empleado','4432223333'),
('Roberto Guzmán',   'GUZR900403MNO', 'roberto.guzman@itm.edu.mx',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'empleado','4437778899'),
('Sofía Herrera',    'HERS010918PQR', 'sofia.herrera@itm.edu.mx',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'gerente', '4434445566');

-- ============================================================
-- MINUTAS
-- ============================================================
INSERT INTO detalles_minuta (lugar, fecha, hora, correo_responsable, tipo, area, temas_json, fecha_proxima, hora_proxima) VALUES
(
  'Sala de Juntas A', '2026-04-10', '09:00:00', 'carlos.mendoza@itm.edu.mx',
  'presencial', 'Dirección General',
  '[{"titulo":"Planeación anual 2026","descripcion":"Revisión de objetivos estratégicos"},{"titulo":"Presupuesto por área","descripcion":"Asignación de recursos para Q2"}]',
  '2026-05-10', '09:00:00'
),
(
  'Sala Virtual Zoom', '2026-04-18', '10:30:00', 'ana.ramirez@itm.edu.mx',
  'virtual', 'TI',
  '[{"titulo":"Migración de servidores","descripcion":"Plan de migración al cloud"},{"titulo":"Seguridad informática","descripcion":"Actualización de políticas de acceso"}]',
  '2026-05-18', '10:30:00'
),
(
  'Aula 301', '2026-04-25', '11:00:00', 'carlos.mendoza@itm.edu.mx',
  'presencial', 'Recursos Humanos',
  '[{"titulo":"Evaluación de desempeño","descripcion":"Resultados del primer trimestre"},{"titulo":"Capacitaciones pendientes","descripcion":"Cursos asignados al personal"}]',
  '2026-05-25', '11:00:00'
),
(
  'Sala de Juntas B', '2026-05-05', '08:00:00', 'ana.ramirez@itm.edu.mx',
  'presencial', 'Ventas',
  '[{"titulo":"Metas de ventas Q2","descripcion":"Estrategia para alcanzar objetivos"},{"titulo":"Nuevos clientes","descripcion":"Seguimiento de prospectos activos"}]',
  '2026-06-05', '08:00:00'
),
(
  'Sala Virtual Teams', '2026-05-12', '14:00:00', 'carlos.mendoza@itm.edu.mx',
  'virtual', 'Administración',
  '[{"titulo":"Cierre contable abril","descripcion":"Revisión de estados financieros"},{"titulo":"Auditoría interna","descripcion":"Preparación de documentación"}]',
  '2026-06-12', '14:00:00'
),
(
  'Sala de Juntas A', '2026-05-20', '09:30:00', 'sofia.herrera@itm.edu.mx',
  'presencial', 'TI',
  '[{"titulo":"Nuevo sistema ERP","descripcion":"Avances de implementación"},{"titulo":"Capacitación usuarios","descripcion":"Plan de formación para el personal"}]',
  '2026-06-20', '09:30:00'
);

-- ============================================================
-- TAREAS (acuerdos de las minutas)
-- ============================================================
INSERT INTO tarea (titulo, responsable, departamento, fecha_compromiso, estado, id_minuta) VALUES
-- Minuta 1 - Dirección General
('Elaborar informe de objetivos Q2',        'Carlos Mendoza',  'Dirección General', '2026-04-30', 'completada',  1),
('Enviar propuesta de presupuesto a áreas', 'Ana Ramírez',     'Dirección General', '2026-04-28', 'completada',  1),
('Presentar resultados al consejo',         'Carlos Mendoza',  'Dirección General', '2026-05-15', 'en_progreso', 1),

-- Minuta 2 - TI
('Generar diagrama de arquitectura cloud',  'Roberto Guzmán',  'TI',                '2026-05-02', 'completada',  2),
('Actualizar políticas de contraseñas',     'Lucía Torres',    'TI',                '2026-05-05', 'completada',  2),
('Prueba de migración en ambiente de QA',   'Roberto Guzmán',  'TI',                '2026-05-20', 'en_progreso', 2),
('Documentar nuevo esquema de red',         'Juan Pérez',      'TI',                '2026-06-01', 'pendiente',   2),

-- Minuta 3 - Recursos Humanos
('Entregar evaluaciones firmadas',          'Sofía Herrera',   'Recursos Humanos',  '2026-05-01', 'completada',  3),
('Inscribir personal a plataforma Moodle',  'Lucía Torres',    'Recursos Humanos',  '2026-05-10', 'en_progreso', 3),
('Publicar calendario de capacitaciones',   'Ana Ramírez',     'Recursos Humanos',  '2026-05-22', 'pendiente',   3),

-- Minuta 4 - Ventas
('Actualizar CRM con nuevos prospectos',    'Juan Pérez',      'Ventas',            '2026-05-12', 'completada',  4),
('Preparar presentación de producto',       'Roberto Guzmán',  'Ventas',            '2026-05-19', 'pendiente',   4),
('Agendar visitas con clientes clave',      'Sofía Herrera',   'Ventas',            '2026-05-30', 'pendiente',   4),

-- Minuta 5 - Administración
('Conciliar cuentas de abril',              'Ana Ramírez',     'Administración',    '2026-05-15', 'completada',  5),
('Preparar carpeta para auditoría',         'Lucía Torres',    'Administración',    '2026-05-25', 'en_progreso', 5),
('Revisar contratos de proveedores',        'Carlos Mendoza',  'Administración',    '2026-06-05', 'pendiente',   5),

-- Minuta 6 - TI / ERP
('Instalar módulo de inventarios ERP',      'Roberto Guzmán',  'TI',                '2026-06-01', 'pendiente',   6),
('Capacitar a usuarios del área contable',  'Sofía Herrera',   'TI',                '2026-06-10', 'pendiente',   6),
('Configurar respaldos automáticos',        'Juan Pérez',      'TI',                '2026-06-15', 'pendiente',   6);
