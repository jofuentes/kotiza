<?php
declare(strict_types=1);

namespace App\Pricing;

/** Regla de precio. scope "global" representa la configuración general de la empresa. */
final class PricingRule
{
    public const SCOPES = ['product' => 'Producto (modelo)', 'brand' => 'Marca', 'category' => 'Categoría', 'provider' => 'Proveedor'];
    public const METHODS = [
        'markup' => 'Costo + porcentaje',
        'margin' => 'Margen sobre precio de venta',
        'list' => 'Precio de lista del proveedor',
    ];
    public const ROUNDINGS = [
        'none' => 'Sin redondeo',
        'integer' => 'Al peso siguiente (.00)',
        'ending90' => 'Terminación .90',
        'multiple' => 'Al múltiplo siguiente de…',
    ];
    /** Prioridad: el número menor gana */
    public const PRIORITY = ['product' => 1, 'brand' => 2, 'category' => 3, 'provider' => 4, 'global' => 5];

    public function __construct(
        public readonly string $scope,
        public readonly string $scopeValue,
        public readonly string $method = 'markup',
        public readonly float $percent = 0,
        public readonly float $listDiscount = 0,
        public readonly ?float $minPrice = null,
        public readonly string $rounding = 'none',
        public readonly ?float $roundingValue = null,
        public readonly ?string $providerCode = null,
        public readonly ?int $id = null,
    ) {
    }

    public static function fromRow(array $r): self
    {
        return new self(
            scope: (string) $r['scope'],
            scopeValue: (string) $r['scope_value'],
            method: (string) $r['method'],
            percent: (float) $r['percent'],
            listDiscount: (float) $r['list_discount'],
            minPrice: $r['min_price'] !== null ? (float) $r['min_price'] : null,
            rounding: (string) $r['rounding'],
            roundingValue: $r['rounding_value'] !== null ? (float) $r['rounding_value'] : null,
            providerCode: $r['provider_code'] ?: null,
            id: isset($r['id']) ? (int) $r['id'] : null,
        );
    }

    /** Regla global a partir de la configuración de la empresa */
    public static function global(array $company): self
    {
        return new self(
            scope: 'global',
            scopeValue: '',
            method: (string) ($company['price_method'] ?? 'markup'),
            percent: (float) ($company['global_margin'] ?? 0),
            listDiscount: (float) ($company['list_discount'] ?? 0),
            rounding: (string) ($company['price_rounding'] ?? 'none'),
            roundingValue: isset($company['price_rounding_value']) && $company['price_rounding_value'] !== null ? (float) $company['price_rounding_value'] : null,
        );
    }

    public function matches(PriceContext $c): bool
    {
        if ($this->providerCode !== null && $this->providerCode !== $c->provider) {
            return false;
        }
        $norm = fn (?string $s) => mb_strtolower(trim((string) $s));
        return match ($this->scope) {
            'global' => true,
            'provider' => $this->scopeValue === $c->provider,
            'brand' => $norm($this->scopeValue) !== '' && $norm($this->scopeValue) === $norm($c->brand),
            'category' => $norm($this->scopeValue) !== '' && $norm($this->scopeValue) === $norm($c->category),
            'product' => \App\Providers\Product::normalizeModel($this->scopeValue) !== ''
                && \App\Providers\Product::normalizeModel($this->scopeValue) === \App\Providers\Product::normalizeModel((string) $c->model),
            default => false,
        };
    }

    public function scopeLabel(): string
    {
        return match ($this->scope) {
            'global' => 'global',
            'provider' => 'proveedor',
            'brand' => 'marca ' . $this->scopeValue,
            'category' => 'categoría ' . $this->scopeValue,
            'product' => 'producto ' . $this->scopeValue,
            default => $this->scope,
        };
    }
}
