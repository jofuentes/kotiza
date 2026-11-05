<?php
declare(strict_types=1);

namespace App\Providers\Catalog;

use App\Core\Database;
use App\Providers\Product;
use App\Providers\SearchResult;

/** Catálogo local en la tabla provider_catalog (búsqueda FULLTEXT + coincidencia por modelo) */
final class DbLocalCatalog implements LocalCatalog
{
    /** InnoDB no indexa palabras de menos de 3 caracteres */
    private const MIN_FT_WORD = 3;

    public function upsert(int $accountId, array $products, int $syncedAt): void
    {
        if (!$products) {
            return;
        }
        $rows = [];
        $params = [];
        foreach ($products as $p) {
            /** @var Product $p */
            $rows[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            array_push($params,
                $accountId, $p->providerId, mb_substr($p->model, 0, 120), mb_substr(Product::normalizeModel($p->model), 0, 120),
                mb_substr(Product::normalizeModel((string) $p->sku), 0, 120), mb_substr($p->brand, 0, 120),
                mb_substr((string) $p->category, 0, 190), $p->stock, SearchText::forProduct($p),
                json_encode($p->toArray(), JSON_UNESCAPED_UNICODE), date('Y-m-d H:i:s', $syncedAt)
            );
        }
        Database::query(
            'INSERT INTO provider_catalog (provider_account_id, provider_product_id, model, model_norm, sku_norm, brand, category, stock, search_text, data, synced_at)
             VALUES ' . implode(', ', $rows) . '
             ON DUPLICATE KEY UPDATE model = VALUES(model), model_norm = VALUES(model_norm), sku_norm = VALUES(sku_norm),
                brand = VALUES(brand), category = VALUES(category), stock = VALUES(stock), search_text = VALUES(search_text),
                data = VALUES(data), synced_at = VALUES(synced_at)',
            $params
        );
    }

    public function search(int $accountId, string $query, array $filters = []): SearchResult
    {
        $limit = max(1, min(100, (int) ($filters['limit'] ?? 60)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $norm = Product::normalizeModel($query);

        // Cada marcador se usa una sola vez (PDO con prepares nativos no permite repetirlos)
        $where = ['provider_account_id = :acc'];
        $params = ['acc' => $accountId];
        $match = [];
        $matchParams = [];
        $score = [];
        $scoreParams = [];

        if (mb_strlen($norm) >= 3) {
            $match[] = 'model_norm LIKE :mp1 OR sku_norm LIKE :mp2';
            $matchParams += ['mp1' => $norm . '%', 'mp2' => $norm . '%'];
            if (SearchText::looksLikeModel($query) && mb_strlen($norm) >= 4) {
                $match[] = 'model_norm LIKE :ml1 OR sku_norm LIKE :ml2';
                $matchParams += ['ml1' => '%' . $norm . '%', 'ml2' => '%' . $norm . '%'];
            }
            $score[] = '(model_norm = :se1 OR sku_norm = :se2) * 100 + (model_norm LIKE :sp1 OR sku_norm LIKE :sp2) * 50';
            $scoreParams += ['se1' => $norm, 'se2' => $norm, 'sp1' => $norm . '%', 'sp2' => $norm . '%'];
        }

        $ftWords = array_filter(SearchText::words($query), fn ($w) => mb_strlen($w) >= self::MIN_FT_WORD);
        if ($ftWords) {
            $boolean = implode(' ', array_map(fn ($w) => '+' . $w . '*', $ftWords));
            $match[] = 'MATCH(search_text) AGAINST (:ft1 IN BOOLEAN MODE)';
            $matchParams['ft1'] = $boolean;
            $score[] = 'MATCH(search_text) AGAINST (:ft2 IN BOOLEAN MODE)';
            $scoreParams['ft2'] = $boolean;
        } else {
            // Solo palabras cortas: búsqueda por subcadena (todas deben aparecer)
            $like = [];
            foreach (SearchText::words($query) as $i => $w) {
                $like[] = "search_text LIKE :w{$i}";
                $matchParams["w{$i}"] = '%' . $w . '%';
            }
            if ($like) {
                $match[] = implode(' AND ', $like);
            }
        }
        if (!$match) {
            return new SearchResult([], 0, 1, 1, $this->lastSyncedAt($accountId));
        }
        $where[] = '(' . implode(' OR ', array_map(fn ($m) => "({$m})", $match)) . ')';
        $params += $matchParams;

        if (!empty($filters['in_stock'])) {
            $where[] = 'stock > 0';
        }
        if (!empty($filters['brand'])) {
            $where[] = 'brand = :brand';
            $params['brand'] = (string) $filters['brand'];
        }
        if (!empty($filters['category'])) {
            $where[] = 'category = :category';
            $params['category'] = (string) $filters['category'];
        }

        $whereSql = implode(' AND ', $where);
        // Sin puntaje (solo palabras cortas) no se ordena por relevancia: ORDER BY (0) sería "columna 0"
        $orderSql = ($score ? '(' . implode(' + ', $score) . ') DESC, ' : '') . 'stock > 0 DESC, model';
        $total = (int) Database::value("SELECT COUNT(*) FROM provider_catalog WHERE {$whereSql}", $params);
        $offset = ($page - 1) * $limit;
        $rows = Database::fetchAll(
            "SELECT data FROM provider_catalog WHERE {$whereSql} ORDER BY {$orderSql} LIMIT {$limit} OFFSET {$offset}",
            $params + $scoreParams
        );
        return new SearchResult(
            array_map(fn ($r) => Product::fromArray(json_decode($r['data'], true)), $rows),
            $total, $page, max(1, (int) ceil($total / $limit)), $this->lastSyncedAt($accountId)
        );
    }

    public function prune(int $accountId, int $before): int
    {
        return Database::query('DELETE FROM provider_catalog WHERE provider_account_id = ? AND synced_at < ?',
            [$accountId, date('Y-m-d H:i:s', $before)])->rowCount();
    }

    public function count(int $accountId): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM provider_catalog WHERE provider_account_id = ?', [$accountId]);
    }

    public function lastSyncedAt(int $accountId): ?int
    {
        $v = Database::value('SELECT MAX(synced_at) FROM provider_catalog WHERE provider_account_id = ?', [$accountId]);
        return $v ? strtotime((string) $v) : null;
    }
}
