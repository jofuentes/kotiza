<?php
declare(strict_types=1);

namespace App\Pricing;

/** Datos de un producto para calcular su precio (montos ya en la moneda de la cotización) */
final class PriceContext
{
    public function __construct(
        public readonly string $provider,
        public readonly string $model,
        public readonly string $brand,
        public readonly ?string $category,
        public readonly float $cost,
        public readonly ?float $listPrice = null,
        /** Convierte un monto de la moneda base de la empresa a la de la cotización (para precio mínimo) */
        public readonly ?\Closure $fromBaseCurrency = null,
    ) {
    }
}
