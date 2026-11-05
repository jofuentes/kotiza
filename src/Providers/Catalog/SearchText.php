<?php
declare(strict_types=1);

namespace App\Providers\Catalog;

use App\Providers\Product;

/** Utilidades de texto para indexar y buscar en el catálogo local */
final class SearchText
{
    /** Texto indexable de un producto */
    public static function forProduct(Product $p, array $extra = []): string
    {
        $parts = array_merge([$p->model, $p->sku, $p->brand, $p->title, $p->category], $extra);
        return self::clean(implode(' ', array_filter($parts, fn ($x) => is_string($x) && $x !== '')));
    }

    /** Minúsculas, sin acentos ni signos, espacios simples */
    public static function clean(string $s): string
    {
        $s = mb_strtolower(html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s) ?? '');
    }

    /** @return string[] palabras de búsqueda */
    public static function words(string $q): array
    {
        return array_values(array_filter(explode(' ', self::clean($q)), fn ($w) => $w !== ''));
    }

    /** ¿Parece un modelo/clave (una sola "palabra" con dígitos)? */
    public static function looksLikeModel(string $q): bool
    {
        $q = trim($q);
        return $q !== '' && !preg_match('/\s/', $q) && preg_match('/\d/', $q) === 1;
    }
}
