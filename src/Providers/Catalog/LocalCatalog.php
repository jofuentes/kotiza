<?php
declare(strict_types=1);

namespace App\Providers\Catalog;

use App\Providers\Product;
use App\Providers\SearchResult;

/** Copia local del catálogo de un proveedor, para búsqueda por texto */
interface LocalCatalog
{
    /** @param Product[] $products */
    public function upsert(int $accountId, array $products, int $syncedAt): void;

    /** Filtros: in_stock, brand, category, page, limit */
    public function search(int $accountId, string $query, array $filters = []): SearchResult;

    /** Elimina productos que no aparecieron en la última sincronización completa */
    public function prune(int $accountId, int $before): int;

    public function count(int $accountId): int;

    public function lastSyncedAt(int $accountId): ?int;
}
