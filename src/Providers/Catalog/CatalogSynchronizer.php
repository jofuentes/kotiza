<?php
declare(strict_types=1);

namespace App\Providers\Catalog;

use App\Providers\ProviderException;

/**
 * Descarga el catálogo completo de un proveedor sincronizable, página por página,
 * y elimina al final lo que ya no existe. Si falla a medias no borra nada.
 */
final class CatalogSynchronizer
{
    /** @param \Closure(int $page, int $lastPage, int $items): void|null $progress */
    public function __construct(
        private LocalCatalog $catalog,
        private ?\Closure $progress = null,
        private ?\Closure $sleep = null,
    ) {
    }

    /** @return array{items:int, pages:int, removed:int} */
    public function run(SyncableProvider $provider, int $accountId): array
    {
        $startedAt = time();
        $page = 1;
        $items = 0;
        $maxPages = 2000; // tope de seguridad
        do {
            $result = $provider->fetchCatalogPage($page);
            $this->catalog->upsert($accountId, $result->products, $startedAt);
            $items += count($result->products);
            if ($this->progress) {
                ($this->progress)($page, $result->lastPage, $items);
            }
            $last = $result->lastPage;
            $page++;
            if ($page <= $last) {
                ($this->sleep ?? fn (int $ms) => usleep($ms * 1000))($provider->syncDelayMs());
            }
        } while ($page <= $last && $page <= $maxPages && $result->products);

        if ($items === 0) {
            throw new ProviderException('El proveedor devolvió un catálogo vacío; se conserva el catálogo anterior.');
        }
        // Solo si se recorrieron todas las páginas se borra lo que ya no existe
        $complete = $page > $last;
        $removed = $complete ? $this->catalog->prune($accountId, $startedAt) : 0;
        return ['items' => $items, 'pages' => $page - 1, 'removed' => $removed];
    }
}
