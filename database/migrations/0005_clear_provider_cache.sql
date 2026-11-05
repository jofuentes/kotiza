-- El costo de Syscom ahora incluye IVA y descuento financiero por defecto:
-- se descartan los precios en caché calculados con la configuración anterior
DELETE FROM search_cache;
DELETE FROM product_cache;
