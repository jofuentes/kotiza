-- Activar/desactivar a un usuario por empresa (sin afectar sus otras empresas)
ALTER TABLE company_user
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER signature_path;
