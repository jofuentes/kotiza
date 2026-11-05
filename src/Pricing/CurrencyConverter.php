<?php
declare(strict_types=1);

namespace App\Pricing;

/** Conversión MXN ↔ USD con el tipo de cambio de la cotización (MXN por 1 USD) */
final class CurrencyConverter
{
    public static function convert(float $amount, string $from, string $to, ?float $rate): float
    {
        if ($from === $to) {
            return $amount;
        }
        if ($rate === null || $rate <= 0) {
            throw new \DomainException('Captura el tipo de cambio de la cotización para convertir entre pesos y dólares.');
        }
        return match ("{$from}>{$to}") {
            'USD>MXN' => $amount * $rate,
            'MXN>USD' => $amount / $rate,
            default => throw new \DomainException("Conversión no soportada: {$from} → {$to}"),
        };
    }
}
