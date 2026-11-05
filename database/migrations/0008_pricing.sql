-- Motor de precios: reglas por producto/marca/categoría/proveedor, estrategia de precio
-- (costo + %, margen sobre venta o precio de lista del proveedor), redondeo y aprobación de descuentos

CREATE TABLE pricing_rules (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id      INT UNSIGNED NOT NULL,
    scope           ENUM('product','brand','category','provider') NOT NULL,
    scope_value     VARCHAR(190) NOT NULL,
    provider_code   VARCHAR(40) NULL,
    method          ENUM('markup','margin','list') NOT NULL DEFAULT 'markup',
    percent         DECIMAL(7,2) NOT NULL DEFAULT 0,
    list_discount   DECIMAL(5,2) NOT NULL DEFAULT 0,
    min_price       DECIMAL(14,2) NULL,
    rounding        ENUM('none','multiple','integer','ending90') NOT NULL DEFAULT 'none',
    rounding_value  DECIMAL(10,2) NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    notes           VARCHAR(255) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_rules_company (company_id, scope, is_active),
    CONSTRAINT fk_rules_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Regla global de la empresa (la de menor prioridad)
ALTER TABLE companies
    ADD COLUMN price_method ENUM('markup','margin','list') NOT NULL DEFAULT 'markup' AFTER global_margin,
    ADD COLUMN list_discount DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER price_method,
    ADD COLUMN price_rounding ENUM('none','multiple','integer','ending90') NOT NULL DEFAULT 'none' AFTER list_discount,
    ADD COLUMN price_rounding_value DECIMAL(10,2) NULL AFTER price_rounding;

-- El margen por proveedor pasa a ser una regla de proveedor
INSERT INTO pricing_rules (company_id, scope, scope_value, provider_code, method, percent)
SELECT company_id, 'provider', provider_code, provider_code, 'markup', default_margin
  FROM provider_accounts WHERE default_margin IS NOT NULL;

-- Partidas: precio de lista y descuento sugerido por la regla
ALTER TABLE quote_items
    ADD COLUMN list_price DECIMAL(14,4) NULL AFTER cost_fetched_at,
    ADD COLUMN category VARCHAR(190) NULL AFTER brand,
    ADD COLUMN rule_discount DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER price_rule;

-- Aprobación de descuentos que exceden el límite del vendedor
ALTER TABLE quotes
    ADD COLUMN approval_status ENUM('none','pending','approved') NOT NULL DEFAULT 'none' AFTER pdf_options,
    ADD COLUMN approval_note VARCHAR(500) NULL AFTER approval_status,
    ADD COLUMN approved_by INT UNSIGNED NULL AFTER approval_note,
    ADD COLUMN approved_at DATETIME NULL AFTER approved_by;

-- Los precios en caché no traen precio de lista: se refrescan
DELETE FROM search_cache;
DELETE FROM product_cache;
