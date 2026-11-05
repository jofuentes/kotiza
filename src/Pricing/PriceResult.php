<?php
declare(strict_types=1);

namespace App\Pricing;

final class PriceResult
{
    public function __construct(
        public readonly float $price,
        public readonly string $label,
        /** Descuento sugerido (%) que se aplica como descuento visible de la partida */
        public readonly float $suggestedDiscount = 0,
        public readonly ?PricingRule $rule = null,
    ) {
    }
}
