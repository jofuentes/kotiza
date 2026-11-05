-- Flujo de estados con aprobación, historial de movimientos y versiones

ALTER TABLE quotes
    MODIFY status ENUM('draft','review','approved','sent','accepted','rejected','expired') NOT NULL DEFAULT 'draft';

ALTER TABLE companies
    ADD COLUMN require_quote_approval TINYINT(1) NOT NULL DEFAULT 1 AFTER hide_costs_from_sellers;

CREATE TABLE quote_status_history (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quote_id    INT UNSIGNED NOT NULL,
    from_status VARCHAR(20) NULL,
    to_status   VARCHAR(20) NOT NULL,
    action      VARCHAR(30) NOT NULL,
    note        VARCHAR(1000) NULL,
    user_id     INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_history_quote (quote_id, id),
    CONSTRAINT fk_history_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historial inicial de las cotizaciones existentes
INSERT INTO quote_status_history (quote_id, from_status, to_status, action, user_id, created_at)
SELECT id, NULL, status, 'create', user_id, created_at FROM quotes;
