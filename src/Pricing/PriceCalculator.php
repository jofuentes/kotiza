<?php
declare(strict_types=1);

namespace App\Pricing;

/**
 * Precio de venta sugerido a partir del costo (ya en la moneda de la cotización).
 * Versión base: margen del proveedor o margen global como sobreprecio sobre el costo.
 * En la Fase 6 se reemplaza por el motor de reglas (producto/marca/categoría/proveedor/global).
 */
final class PriceCalculator
{
    /** @return array{price: float, rule: string} */
    public static function suggest(float $cost, ?float $providerMargin, float $globalMargin): array
    {
        [$pct, $label] = $providerMargin !== null
            ? [$providerMargin, 'Margen del proveedor']
            : [$globalMargin, 'Margen global'];
        return [
            'price' => round($cost * (1 + $pct / 100), 2),
            'rule' => sprintf('%s %s %%', $label, rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.')),
        ];
    }
}
