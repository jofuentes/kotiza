<?php
declare(strict_types=1);

namespace App\Providers;

/** Producto normalizado: misma forma sin importar el mayorista. */
final class Product
{
    /**
     * @param string[] $images URLs
     * @param array<string,int> $stockByWarehouse almacén => existencia
     * @param string[] $features
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $providerId,
        public readonly string $model,
        public readonly string $brand,
        public readonly string $title,
        public readonly ?string $description = null,
        public readonly ?string $category = null,
        public readonly array $images = [],
        public readonly ?float $cost = null,
        public readonly string $currency = 'USD',
        public readonly int $stock = 0,
        public readonly array $stockByWarehouse = [],
        public readonly ?string $satKey = null,
        public readonly ?string $satUnit = null,
        public readonly ?string $unit = null,
        public readonly ?string $url = null,
        public readonly array $features = [],
        public readonly ?string $warranty = null,
        public readonly ?string $note = null,
    ) {
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $a): self
    {
        return new self(
            provider: (string) $a['provider'],
            providerId: (string) $a['providerId'],
            model: (string) $a['model'],
            brand: (string) $a['brand'],
            title: (string) $a['title'],
            description: $a['description'] ?? null,
            category: $a['category'] ?? null,
            images: $a['images'] ?? [],
            cost: isset($a['cost']) ? (float) $a['cost'] : null,
            currency: (string) ($a['currency'] ?? 'USD'),
            stock: (int) ($a['stock'] ?? 0),
            stockByWarehouse: $a['stockByWarehouse'] ?? [],
            satKey: $a['satKey'] ?? null,
            satUnit: $a['satUnit'] ?? null,
            unit: $a['unit'] ?? null,
            url: $a['url'] ?? null,
            features: $a['features'] ?? [],
            warranty: $a['warranty'] ?? null,
            note: $a['note'] ?? null,
        );
    }

    /** Modelo normalizado para comparar entre proveedores */
    public static function normalizeModel(string $model): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($model));
    }
}
