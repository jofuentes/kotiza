<?php
declare(strict_types=1);

namespace App\Providers\Catalog;

use App\Providers\Product;

final class CatalogPage
{
    /** @param Product[] $products */
    public function __construct(
        public readonly array $products,
        public readonly int $page,
        public readonly int $lastPage,
        public readonly int $total,
    ) {
    }
}
