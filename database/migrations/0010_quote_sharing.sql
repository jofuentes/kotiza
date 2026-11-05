-- Cotizaciones colaborativas: compartir con compañeros (ver / editar) y control de cambios simultáneos

CREATE TABLE quote_shares (
    quote_id    INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    permission  ENUM('view','edit') NOT NULL DEFAULT 'view',
    shared_by   INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (quote_id, user_id),
    KEY idx_shares_user (user_id),
    CONSTRAINT fk_shares_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    CONSTRAINT fk_shares_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE quotes
    ADD COLUMN lock_version INT UNSIGNED NOT NULL DEFAULT 0 AFTER approved_at,
    ADD COLUMN updated_by INT UNSIGNED NULL AFTER lock_version;
