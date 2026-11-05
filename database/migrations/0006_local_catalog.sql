-- Catálogo local para proveedores sin búsqueda por texto en su API (ej. TVC).
-- Se sincroniza en segundo plano y se busca con FULLTEXT.

CREATE TABLE provider_catalog (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_account_id INT UNSIGNED NOT NULL,
    provider_product_id VARCHAR(80) NOT NULL,
    model               VARCHAR(120) NOT NULL DEFAULT '',
    model_norm          VARCHAR(120) NOT NULL DEFAULT '',
    sku_norm            VARCHAR(120) NOT NULL DEFAULT '',
    brand               VARCHAR(120) NOT NULL DEFAULT '',
    category            VARCHAR(190) NOT NULL DEFAULT '',
    stock               INT NOT NULL DEFAULT 0,
    search_text         TEXT NOT NULL,
    data                JSON NOT NULL,
    synced_at           DATETIME NOT NULL,
    UNIQUE KEY uq_cat_product (provider_account_id, provider_product_id),
    KEY idx_cat_model (provider_account_id, model_norm),
    KEY idx_cat_sku (provider_account_id, sku_norm),
    KEY idx_cat_brand (provider_account_id, brand),
    FULLTEXT KEY ft_cat_search (search_text),
    CONSTRAINT fk_cat_account FOREIGN KEY (provider_account_id) REFERENCES provider_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_sync (
    provider_account_id INT UNSIGNED NOT NULL PRIMARY KEY,
    status              ENUM('queued','running','done','error') NOT NULL DEFAULT 'queued',
    requested_at        DATETIME NULL,
    started_at          DATETIME NULL,
    finished_at         DATETIME NULL,
    heartbeat_at        DATETIME NULL,
    page                INT UNSIGNED NOT NULL DEFAULT 0,
    last_page           INT UNSIGNED NOT NULL DEFAULT 0,
    items               INT UNSIGNED NOT NULL DEFAULT 0,
    message             VARCHAR(500) NULL,
    CONSTRAINT fk_sync_account FOREIGN KEY (provider_account_id) REFERENCES provider_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
