-- Recuperación de contraseña por correo y configuración de la instalación

-- Cambia al modificar la contraseña: invalida las sesiones abiertas con la contraseña anterior
ALTER TABLE users ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 0 AFTER is_active;

-- Ajustes de toda la instalación (no de una empresa)
CREATE TABLE app_settings (
    setting_key     VARCHAR(60) NOT NULL PRIMARY KEY,
    setting_value   TEXT NOT NULL,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Solicitudes de recuperación. El token solo se guarda como hash.
-- pending: espera envío · sent: enlace vigente · used/cancelled/failed: ya no sirve
-- ignored: correo inexistente o sobre el límite (solo cuenta para limitar por IP)
CREATE TABLE password_resets (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NULL,
    ip              VARCHAR(45) NOT NULL,
    token_hash      CHAR(64) NULL,
    status          ENUM('pending','sent','used','cancelled','failed','ignored') NOT NULL,
    expires_at      DATETIME NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_password_resets_token (token_hash),
    KEY idx_password_resets_status (status, created_at),
    KEY idx_password_resets_user (user_id, created_at),
    KEY idx_password_resets_ip (ip, created_at),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
