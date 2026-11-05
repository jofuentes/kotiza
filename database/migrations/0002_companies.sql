-- Empresas (multi-empresa) y pertenencia de usuarios con rol
CREATE TABLE companies (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    -- Datos fiscales y de contacto
    legal_name              VARCHAR(200) NOT NULL,
    trade_name              VARCHAR(200) NOT NULL,
    rfc                     VARCHAR(13) NULL,
    tax_regime              VARCHAR(120) NULL,
    address                 TEXT NULL,
    phone                   VARCHAR(80) NULL,
    email                   VARCHAR(190) NULL,
    website                 VARCHAR(190) NULL,
    -- Identidad visual
    logo_path               VARCHAR(255) NULL,
    letterhead_path         VARCHAR(255) NULL,
    letterhead_type         ENUM('pdf','image') NULL,
    color_primary           CHAR(7) NOT NULL DEFAULT '#1E40AF',
    color_secondary         CHAR(7) NOT NULL DEFAULT '#64748B',
    pdf_margin_top          DECIMAL(5,1) NOT NULL DEFAULT 35.0,
    pdf_margin_right        DECIMAL(5,1) NOT NULL DEFAULT 15.0,
    pdf_margin_bottom       DECIMAL(5,1) NOT NULL DEFAULT 25.0,
    pdf_margin_left         DECIMAL(5,1) NOT NULL DEFAULT 15.0,
    -- Textos comerciales
    bank_details            TEXT NULL,
    terms                   TEXT NULL,
    footer_notes            TEXT NULL,
    signature_path          VARCHAR(255) NULL,
    default_validity_days   SMALLINT UNSIGNED NOT NULL DEFAULT 15,
    -- Folios: PREFIJO-AAAA-0001. folio_next se puede editar para continuar un consecutivo existente.
    folio_prefix            VARCHAR(20) NOT NULL DEFAULT 'COT',
    folio_include_year      TINYINT(1) NOT NULL DEFAULT 1,
    folio_reset_yearly      TINYINT(1) NOT NULL DEFAULT 1,
    folio_padding           TINYINT UNSIGNED NOT NULL DEFAULT 4,
    folio_next              INT UNSIGNED NOT NULL DEFAULT 1,
    folio_year              SMALLINT UNSIGNED NULL,
    -- Moneda, impuestos y precios
    default_currency        ENUM('MXN','USD') NOT NULL DEFAULT 'MXN',
    vat_rate                DECIMAL(5,2) NOT NULL DEFAULT 16.00,
    global_margin           DECIMAL(6,2) NOT NULL DEFAULT 25.00,
    min_margin              DECIMAL(6,2) NOT NULL DEFAULT 10.00,
    exchange_mode           ENUM('provider','manual','provider_buffer') NOT NULL DEFAULT 'provider',
    exchange_manual         DECIMAL(10,4) NULL,
    exchange_buffer         DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
    hide_costs_from_sellers TINYINT(1) NOT NULL DEFAULT 0,
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE company_user (
    company_id          INT UNSIGNED NOT NULL,
    user_id             INT UNSIGNED NOT NULL,
    role                ENUM('admin','seller') NOT NULL DEFAULT 'seller',
    max_discount_pct    DECIMAL(5,2) NULL,
    display_name        VARCHAR(120) NULL,
    signature_path      VARCHAR(255) NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (company_id, user_id),
    KEY idx_company_user_user (user_id),
    CONSTRAINT fk_cu_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_cu_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
