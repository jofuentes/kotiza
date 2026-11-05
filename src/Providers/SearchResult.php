<?php
declare(strict_types=1);

namespace App\Providers;

final class SearchResult
{
    /** @param Product[] $products */
    public function __construct(
        public readonly array $products,
        public readonly int $total,
        public readonly int $page = 1,
        public readonly int $pages = 1,
    ) {
    }

    public function toArray(): array
    {
        return [
            'products' => array_map(fn (Product $p) => $p->toArray(), $this->products),
            'total' => $this->total, 'page' => $this->page, 'pages' => $this->pages,
        ];
    }

    public static function fromArray(array $a): self
    {
        return new self(array_map([Product::class, 'fromArray'], $a['products'] ?? []), (int) $a['total'], (int) $a['page'], (int) $a['pages']);
    }
}
