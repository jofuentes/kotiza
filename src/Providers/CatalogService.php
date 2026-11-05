<?php
declare(strict_types=1);

namespace App\Providers;

use App\Core\Database;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\Utils;

/**
 * Búsqueda unificada y detalle de productos sobre todos los proveedores activos de una empresa:
 *  - consulta en paralelo
 *  - caché local con vigencia configurable (precios y fichas)
 *  - si un proveedor falla, usa el último resultado en caché e indica su antigüedad
 */
final class CatalogService
{
    public function __construct(
        private int $companyId,
        private ProviderAccountRepository $accounts,
        private ProviderFactory $factory,
    ) {
    }

    public static function forCompany(int $companyId): self
    {
        return new self($companyId, ProviderAccountRepository::default(), ProviderFactory::default());
    }

    /**
     * @param string[]|null $codes limitar a estos proveedores
     * @return array{products: array[], providers: array<string, array>}
     */
    public function search(string $query, array $filters = [], ?array $codes = null): array
    {
        [$priceTtl] = $this->ttls();
        $filters = array_intersect_key($filters, array_flip(['in_stock', 'brand', 'category', 'page', 'limit']));
        $key = hash('sha256', json_encode([mb_strtolower(trim($query)), $filters]));

        $accounts = array_filter($this->accounts->active($this->companyId),
            fn (ProviderAccount $a) => $codes === null || in_array($a->providerCode, $codes, true));

        $promises = [];
        $meta = [];
        $cached = [];
        foreach ($accounts as $acc) {
            $class = ProviderRegistry::get($acc->providerCode);
            $meta[$acc->providerCode] = ['name' => $class::name(), 'ok' => true, 'error' => null, 'cached' => false,
                'stale' => false, 'age_minutes' => 0, 'total' => 0, 'ms' => 0];
            $row = Database::fetch('SELECT result, fetched_at FROM search_cache WHERE provider_account_id = ? AND query_hash = ?', [$acc->id, $key]);
            if ($row && strtotime($row['fetched_at']) >= time() - $priceTtl) {
                $cached[$acc->providerCode] = [SearchResult::fromArray(json_decode($row['result'], true)), $row['fetched_at']];
                continue;
            }
            $start = microtime(true);
            try {
                $promises[$acc->providerCode] = $this->factory->make($acc)->searchAsync($query, $filters)
                    ->then(function ($r) use ($start, $acc, &$meta) {
                        $meta[$acc->providerCode]['ms'] = (int) round((microtime(true) - $start) * 1000);
                        return $r;
                    });
            } catch (\Throwable $e) {
                $promises[$acc->providerCode] = Create::rejectionFor($e);
            }
        }

        $settled = $promises ? Utils::settle($promises)->wait() : [];
        $products = [];
        foreach ($accounts as $acc) {
            $code = $acc->providerCode;
            $result = null;
            if (isset($cached[$code])) {
                [$result, $at] = $cached[$code];
                $meta[$code]['cached'] = true;
                $meta[$code]['age_minutes'] = (int) floor((time() - strtotime($at)) / 60);
            } elseif (($settled[$code]['state'] ?? null) === 'fulfilled') {
                $result = $settled[$code]['value'];
                $this->storeSearch($acc, $key, $query, $result);
            } else {
                $reason = $settled[$code]['reason'] ?? null;
                $meta[$code]['ok'] = false;
                $meta[$code]['error'] = $reason instanceof ProviderException ? $reason->getMessage()
                    : 'Error inesperado al consultar ' . $meta[$code]['name'] . '.';
                if (!$reason instanceof ProviderException) {
                    error_log((string) $reason);
                }
                // Respaldo: último resultado guardado, aunque esté vencido
                $row = Database::fetch('SELECT result, fetched_at FROM search_cache WHERE provider_account_id = ? AND query_hash = ?', [$acc->id, $key]);
                if ($row) {
                    $result = SearchResult::fromArray(json_decode($row['result'], true));
                    $meta[$code]['stale'] = true;
                    $meta[$code]['age_minutes'] = (int) floor((time() - strtotime($row['fetched_at'])) / 60);
                }
            }
            if ($result) {
                $meta[$code]['total'] = $result->total;
                $meta[$code]['pages'] = $result->pages;
                foreach ($result->products as $p) {
                    $products[] = $p->toArray() + ['providerName' => $meta[$code]['name'], 'priority' => $acc->priority];
                }
            }
        }

        return ['products' => self::markCheapest($products), 'providers' => $meta];
    }

    /**
     * Ficha completa de un producto (usa caché si está vigente).
     * @return array{product: ?array, stale: bool, age_minutes: int, error: ?string}
     */
    public function product(string $code, string $id, bool $refresh = false): array
    {
        [$priceTtl, $detailTtl] = $this->ttls();
        $acc = $this->accounts->find($this->companyId, $code);
        if (!$acc || !$acc->isActive) {
            return ['product' => null, 'stale' => false, 'age_minutes' => 0, 'error' => 'El proveedor no está activo.'];
        }
        $row = Database::fetch('SELECT * FROM product_cache WHERE provider_account_id = ? AND provider_product_id = ?', [$acc->id, $id]);
        $fresh = $row && $row['detail_fetched_at']
            && strtotime($row['price_fetched_at']) >= time() - $priceTtl
            && strtotime($row['detail_fetched_at']) >= time() - $detailTtl;
        if ($fresh && !$refresh) {
            return ['product' => json_decode($row['data'], true), 'stale' => false,
                'age_minutes' => (int) floor((time() - strtotime($row['price_fetched_at'])) / 60), 'error' => null];
        }
        try {
            $p = $this->factory->make($acc)->getProduct($id);
            if (!$p) {
                return ['product' => null, 'stale' => false, 'age_minutes' => 0, 'error' => 'El proveedor ya no ofrece este producto.'];
            }
            $this->storeProducts($acc, [$p], true);
            return ['product' => $p->toArray(), 'stale' => false, 'age_minutes' => 0, 'error' => null];
        } catch (ProviderException $e) {
            if ($row) {
                return ['product' => json_decode($row['data'], true), 'stale' => true,
                    'age_minutes' => (int) floor((time() - strtotime($row['price_fetched_at'])) / 60), 'error' => $e->getMessage()];
            }
            return ['product' => null, 'stale' => false, 'age_minutes' => 0, 'error' => $e->getMessage()];
        }
    }

    /** Marca el costo más bajo por modelo cuando el mismo modelo aparece en varios proveedores */
    public static function markCheapest(array $products): array
    {
        $groups = [];
        foreach ($products as $i => $p) {
            $products[$i]['cheapest'] = false;
            if ($p['cost'] !== null && $p['model'] !== '') {
                $groups[Product::normalizeModel($p['model']) . '|' . $p['currency']][] = $i;
            }
        }
        foreach ($groups as $idx) {
            $providers = array_unique(array_map(fn ($i) => $products[$i]['provider'], $idx));
            if (count($providers) < 2) {
                continue;
            }
            usort($idx, fn ($a, $b) => $products[$a]['cost'] <=> $products[$b]['cost']);
            $products[$idx[0]]['cheapest'] = true;
        }
        return $products;
    }

    private function storeSearch(ProviderAccount $acc, string $key, string $query, SearchResult $r): void
    {
        Database::query(
            'INSERT INTO search_cache (provider_account_id, query_hash, query, result, fetched_at) VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE result = VALUES(result), fetched_at = NOW()',
            [$acc->id, $key, mb_substr($query, 0, 255), json_encode($r->toArray(), JSON_UNESCAPED_UNICODE)]
        );
        $this->storeProducts($acc, $r->products, false);
        if (random_int(1, 50) === 1) {
            Database::query('DELETE FROM search_cache WHERE fetched_at < NOW() - INTERVAL 30 DAY');
        }
    }

    /**
     * Guarda productos en caché. Si vienen de una búsqueda (sin ficha completa) se actualizan
     * solo precio y existencias, conservando la ficha ya guardada.
     * @param Product[] $products
     */
    private function storeProducts(ProviderAccount $acc, array $products, bool $isDetail): void
    {
        if (!$products) {
            return;
        }
        $existing = [];
        if (!$isDetail) {
            $ids = array_map(fn (Product $p) => $p->providerId, $products);
            $in = implode(',', array_fill(0, count($ids), '?'));
            foreach (Database::fetchAll(
                "SELECT provider_product_id, data FROM product_cache WHERE provider_account_id = ? AND detail_fetched_at IS NOT NULL AND provider_product_id IN ({$in})",
                array_merge([$acc->id], $ids)
            ) as $r) {
                $existing[$r['provider_product_id']] = json_decode($r['data'], true);
            }
        }
        foreach ($products as $p) {
            $data = $p->toArray();
            if (isset($existing[$p->providerId])) {
                $data = array_merge($existing[$p->providerId], array_intersect_key($data, array_flip(['cost', 'currency', 'stock', 'stockByWarehouse', 'note'])));
            }
            Database::query(
                'INSERT INTO product_cache (provider_account_id, provider_product_id, model, data, price_fetched_at, detail_fetched_at)
                 VALUES (?, ?, ?, ?, NOW(), ' . ($isDetail ? 'NOW()' : 'NULL') . ')
                 ON DUPLICATE KEY UPDATE model = VALUES(model), data = VALUES(data), price_fetched_at = NOW()'
                 . ($isDetail ? ', detail_fetched_at = NOW()' : ''),
                [$acc->id, $p->providerId, mb_substr($p->model, 0, 120), json_encode($data, JSON_UNESCAPED_UNICODE)]
            );
        }
    }

    /** @return array{0:int,1:int} vigencia de precios y de fichas, en segundos */
    private function ttls(): array
    {
        $c = Database::fetch('SELECT cache_price_minutes, cache_detail_hours FROM companies WHERE id = ?', [$this->companyId]);
        return [(int) ($c['cache_price_minutes'] ?? 360) * 60, (int) ($c['cache_detail_hours'] ?? 168) * 3600];
    }
}
