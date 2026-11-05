-- Clientes y cotizaciones (con secciones y partidas)

CREATE TABLE clients (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id      INT UNSIGNED NOT NULL,
    name            VARCHAR(160) NOT NULL,
    company_name    VARCHAR(200) NULL,
    rfc             VARCHAR(13) NULL,
    email           VARCHAR(190) NULL,
    phone           VARCHAR(80) NULL,
    address         TEXT NULL,
    notes           TEXT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_clients_company (company_id, name),
    CONSTRAINT fk_clients_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quotes (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id              INT UNSIGNED NOT NULL,
    client_id               INT UNSIGNED NULL,
    user_id                 INT UNSIGNED NOT NULL,
    folio                   VARCHAR(40) NOT NULL,
    version                 SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    root_quote_id           INT UNSIGNED NULL,
    status                  ENUM('draft','sent','accepted','rejected','expired') NOT NULL DEFAULT 'draft',
    title                   VARCHAR(200) NULL,
    issue_date              DATE NOT NULL,
    valid_until             DATE NOT NULL,
    currency                ENUM('MXN','USD') NOT NULL DEFAULT 'MXN',
    exchange_rate           DECIMAL(10,4) NULL,
    exchange_source         VARCHAR(120) NULL,
    vat_rate                DECIMAL(5,2) NOT NULL DEFAULT 16.00,
    global_discount_type    ENUM('percent','amount') NOT NULL DEFAULT 'percent',
    global_discount_value   DECIMAL(14,2) NOT NULL DEFAULT 0,
    subtotal                DECIMAL(14,2) NOT NULL DEFAULT 0,
    discount_total          DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_total               DECIMAL(14,2) NOT NULL DEFAULT 0,
    total                   DECIMAL(14,2) NOT NULL DEFAULT 0,
    cost_total              DECIMAL(14,2) NULL,
    notes                   TEXT NULL,
    terms                   TEXT NULL,
    client_snapshot         JSON NULL,
    pdf_options             JSON NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_quotes_folio (company_id, folio, version),
    KEY idx_quotes_company_status (company_id, status, issue_date),
    KEY idx_quotes_user (user_id),
    CONSTRAINT fk_quotes_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_quotes_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    CONSTRAINT fk_quotes_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_quotes_root FOREIGN KEY (root_quote_id) REFERENCES quotes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quote_sections (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quote_id    INT UNSIGNED NOT NULL,
    title       VARCHAR(160) NOT NULL,
    position    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_sections_quote (quote_id, position),
    CONSTRAINT fk_sections_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quote_items (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quote_id            INT UNSIGNED NOT NULL,
    section_id          INT UNSIGNED NULL,
    position            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    type                ENUM('product','manual') NOT NULL,
    -- Copia del producto al momento de cotizar
    provider_code       VARCHAR(40) NULL,
    provider_product_id VARCHAR(80) NULL,
    model               VARCHAR(120) NULL,
    brand               VARCHAR(120) NULL,
    sku                 VARCHAR(120) NULL,
    description         TEXT NOT NULL,
    image_url           VARCHAR(500) NULL,
    unit                VARCHAR(40) NOT NULL DEFAULT 'Pieza',
    sat_key             VARCHAR(20) NULL,
    sat_unit            VARCHAR(10) NULL,
    qty                 DECIMAL(12,3) NOT NULL DEFAULT 1,
    -- Costo en su moneda original
    cost                DECIMAL(14,4) NULL,
    cost_currency       ENUM('MXN','USD') NULL,
    cost_includes_vat   TINYINT(1) NOT NULL DEFAULT 0,
    cost_fetched_at     DATETIME NULL,
    -- Precios en la moneda de la cotización
    price_calculated    DECIMAL(14,2) NULL,
    price_manual        DECIMAL(14,2) NULL,
    price_rule          VARCHAR(200) NULL,
    discount_type       ENUM('percent','amount') NOT NULL DEFAULT 'percent',
    discount_value      DECIMAL(14,2) NOT NULL DEFAULT 0,
    line_total          DECIMAL(14,2) NOT NULL DEFAULT 0,
    notes               TEXT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_items_quote (quote_id, position),
    CONSTRAINT fk_items_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    CONSTRAINT fk_items_section FOREIGN KEY (section_id) REFERENCES quote_sections(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
