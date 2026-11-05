<?php
declare(strict_types=1);

namespace App\Providers\Catalog;

use App\Providers\Product;
use App\Providers\SearchResult;

/** Catálogo local en memoria (pruebas): busca que todas las palabras aparezcan */
final class MemoryLocalCatalog implements LocalCatalog
{
    /** @var array<int, array<string, array{p: Product, at: int}>> */
    public array $items = [];

    public function upsert(int $accountId, array $products, int $syncedAt): void
    {
        foreach ($products as $p) {
            $this->items[$accountId][$p->providerId] = ['p' => $p, 'at' => $syncedAt];
        }
    }

    public function search(int $accountId, string $query, array $filters = []): SearchResult
    {
        $words = SearchText::words($query);
        $norm = Product::normalizeModel($query);
        $found = [];
        foreach ($this->items[$accountId] ?? [] as $row) {
            $p = $row['p'];
            $text = SearchText::forProduct($p);
            $byModel = $norm !== '' && (str_contains(Product::normalizeModel($p->model), $norm) || str_contains(Product::normalizeModel((string) $p->sku), $norm));
            $byWords = $words && !array_filter($words, fn ($w) => !str_contains($text, $w));
            if (($byModel || $byWords) && (empty($filters['in_stock']) || $p->stock > 0)) {
                $found[] = $p;
            }
        }
        return new SearchResult($found, count($found), 1, 1, $this->lastSyncedAt($accountId));
    }

    public function prune(int $accountId, int $before): int
    {
        $n = 0;
        foreach ($this->items[$accountId] ?? [] as $id => $row) {
            if ($row['at'] < $before) {
                unset($this->items[$accountId][$id]);
                $n++;
            }
        }
        return $n;
    }

    public function count(int $accountId): int
    {
        return count($this->items[$accountId] ?? []);
    }

    public function lastSyncedAt(int $accountId): ?int
    {
        $at = array_column($this->items[$accountId] ?? [], 'at');
        return $at ? max($at) : null;
    }
}
