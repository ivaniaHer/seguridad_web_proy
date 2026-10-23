-- Tabla del laboratorio vulnerable (Fase 4).
-- MALA PRÁCTICA A PROPÓSITO: contraseñas en texto plano.
CREATE TABLE IF NOT EXISTS lab_usuarios (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    usuario     VARCHAR(30)  NOT NULL,
    contrasena  VARCHAR(50)  NOT NULL,
    correo      VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

TRUNCATE TABLE lab_usuarios;
INSERT INTO lab_usuarios (usuario, contrasena, correo) VALUES
    ('admin',  'Admin2024', 'admin@laboratorio.local'),
    ('maria',  'maria123',  'maria@laboratorio.local'),
    ('carlos', 'qwerty',    'carlos@laboratorio.local');
