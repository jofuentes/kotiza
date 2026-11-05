<?php
declare(strict_types=1);

namespace App\Pricing;

/** Redondeo de precios hacia arriba (nunca se baja el precio) */
final class Rounding
{
    private const EPS = 1e-6;

    public static function apply(float $price, string $mode, ?float $value = null): float
    {
        $price = round($price, 2);
        return match ($mode) {
            'integer' => ceil($price - self::EPS),
            'ending90' => (function () use ($price) {
                $x = floor($price) + 0.90;
                return round($x + self::EPS < $price ? $x + 1 : $x, 2);
            })(),
            'multiple' => $value !== null && $value > 0 ? round(ceil($price / $value - self::EPS) * $value, 2) : $price,
            default => $price,
        };
    }
}
