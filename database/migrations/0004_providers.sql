-- Cuentas de proveedor por empresa (credenciales cifradas), tokens, log de llamadas y cachés

CREATE TABLE provider_accounts (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id          INT UNSIGNED NOT NULL,
    provider_code       VARCHAR(40) NOT NULL,
    is_active           TINYINT(1) NOT NULL DEFAULT 0,
    priority            SMALLINT NOT NULL DEFAULT 10,
    default_margin      DECIMAL(6,2) NULL,
    credentials_enc     TEXT NULL,
    settings            JSON NULL,
    last_test_at        DATETIME NULL,
    last_test_ok        TINYINT(1) NULL,
    last_test_message   VARCHAR(500) NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_provider_company (company_id, provider_code),
    CONSTRAINT fk_pa_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_tokens (
    provider_account_id INT UNSIGNED NOT NULL PRIMARY KEY,
    token_enc           TEXT NOT NULL,
    expires_at          DATETIME NOT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pt_account FOREIGN KEY (provider_account_id) REFERENCES provider_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_logs (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_account_id INT UNSIGNED NOT NULL,
    method              VARCHAR(10) NOT NULL,
    endpoint            VARCHAR(500) NOT NULL,
    http_code           SMALLINT NULL,
    duration_ms         INT UNSIGNED NOT NULL DEFAULT 0,
    error               VARCHAR(500) NULL,
    created_at          DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    KEY idx_pl_account (provider_account_id, created_at),
    CONSTRAINT fk_pl_account FOREIGN KEY (provider_account_id) REFERENCES provider_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Producto normalizado por cuenta (los precios dependen del nivel de distribuidor de cada cuenta)
CREATE TABLE product_cache (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_account_id INT UNSIGNED NOT NULL,
    provider_product_id VARCHAR(80) NOT NULL,
    model               VARCHAR(120) NULL,
    data                JSON NOT NULL,
    price_fetched_at    DATETIME NOT NULL,
    detail_fetched_at   DATETIME NULL,
    UNIQUE KEY uq_pc_product (provider_account_id, provider_product_id),
    KEY idx_pc_model (model),
    CONSTRAINT fk_pc_account FOREIGN KEY (provider_account_id) REFERENCES provider_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE search_cache (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_account_id INT UNSIGNED NOT NULL,
    query_hash          CHAR(64) NOT NULL,
    query               VARCHAR(255) NOT NULL,
    result              JSON NOT NULL,
    fetched_at          DATETIME NOT NULL,
    UNIQUE KEY uq_sc_query (provider_account_id, query_hash),
    CONSTRAINT fk_sc_account FOREIGN KEY (provider_account_id) REFERENCES provider_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE exchange_rates (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source              VARCHAR(40) NOT NULL,
    rate                DECIMAL(10,4) NOT NULL,
    fetched_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_er_source (source, fetched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vigencia de cachés configurable por empresa
ALTER TABLE companies
    ADD COLUMN cache_price_minutes  INT UNSIGNED NOT NULL DEFAULT 360 AFTER hide_costs_from_sellers,
    ADD COLUMN cache_detail_hours   INT UNSIGNED NOT NULL DEFAULT 168 AFTER cache_price_minutes;
