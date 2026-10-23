-- Esquema de la aplicación (Fase 3). Se ejecuta con instalar.sh.

CREATE TABLE IF NOT EXISTS usuarios (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario            VARCHAR(30)  NOT NULL UNIQUE,
    contrasena_hash    VARCHAR(255) NOT NULL,          -- hash bcrypt ($2y$12$...), nunca la contraseña
    rol                ENUM('admin', 'usuario') NOT NULL DEFAULT 'usuario',
    activo             TINYINT(1)   NOT NULL DEFAULT 1,
    intentos_fallidos  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_hasta    DATETIME     NULL,
    ultimo_acceso      DATETIME     NULL,
    creado_en          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auditoria (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fecha       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    evento      VARCHAR(30)  NOT NULL,
    usuario     VARCHAR(50)  NULL,
    ip          VARCHAR(45)  NOT NULL,
    user_agent  VARCHAR(255) NULL,
    detalle     VARCHAR(255) NULL,
    INDEX idx_fecha (fecha),
    INDEX idx_evento (evento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
