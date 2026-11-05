-- Existencia del producto al momento de cotizar (opción "mostrar existencias" del PDF)
ALTER TABLE quote_items ADD COLUMN stock INT NULL AFTER list_price;
