<?php
declare(strict_types=1);

namespace App\Domain\Quotes;

/**
 * Totales de una cotización. Mismo algoritmo que el editor (assets/quote-editor.js):
 *   importe = cantidad × precio unitario − descuento de la partida
 *   subtotal = Σ importes;  descuento global sobre el subtotal
 *   IVA sobre (subtotal − descuento global);  total = base + IVA
 * Todo se redondea a 2 decimales en cada paso.
 */
final class QuoteCalculator
{
    /**
     * @param array<int, array{qty: float|string, unit_price: float|string, discount_type?: string, discount_value?: float|string, unit_cost?: float|null}> $items
     *        unit_cost: costo unitario ya convertido a la moneda de la cotización (null si se desconoce)
     * @return array{lines: array<int, array{gross: float, discount: float, total: float, cost: ?float}>,
     *               subtotal: float, discount_total: float, taxable: float, tax_total: float, total: float,
     *               cost_total: ?float, profit: ?float, margin_pct: ?float}
     */
    public static function calculate(array $items, string $globalType, float $globalValue, float $vatRate): array
    {
        $lines = [];
        $subtotal = 0.0;
        $costTotal = 0.0;
        $costKnown = true;
        $revenueWithCost = 0.0;

        foreach ($items as $k => $it) {
            $qty = max(0.0, (float) $it['qty']);
            $gross = self::r($qty * max(0.0, (float) $it['unit_price']));
            $discount = self::discount($gross, (string) ($it['discount_type'] ?? 'percent'), (float) ($it['discount_value'] ?? 0));
            $total = self::r($gross - $discount);
            $cost = isset($it['unit_cost']) && $it['unit_cost'] !== null ? self::r($qty * (float) $it['unit_cost']) : null;
            $lines[$k] = ['gross' => $gross, 'discount' => $discount, 'total' => $total, 'cost' => $cost];
            $subtotal += $total;
            if ($cost === null) {
                $costKnown = false;
            } else {
                $costTotal += $cost;
                $revenueWithCost += $total;
            }
        }

        $subtotal = self::r($subtotal);
        $globalDiscount = self::discount($subtotal, $globalType, $globalValue);
        $taxable = self::r($subtotal - $globalDiscount);
        $tax = self::r($taxable * max(0.0, $vatRate) / 100);

        // Utilidad sobre lo que se cobra antes de IVA (el descuento global se reparte proporcionalmente)
        $profit = null;
        $marginPct = null;
        if ($items && $revenueWithCost > 0) {
            $factor = $subtotal > 0 ? $taxable / $subtotal : 1.0;
            $revenue = $revenueWithCost * $factor;
            $profit = self::r($revenue - $costTotal);
            $marginPct = $revenue > 0 ? round($profit / $revenue * 100, 2) : null;
        }

        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'discount_total' => $globalDiscount,
            'taxable' => $taxable,
            'tax_total' => $tax,
            'total' => self::r($taxable + $tax),
            'cost_total' => $items && $costKnown ? self::r($costTotal) : ($costTotal > 0 ? self::r($costTotal) : null),
            'cost_complete' => $costKnown,
            'profit' => $profit,
            'margin_pct' => $marginPct,
        ];
    }

    /** Descuento en monto: porcentaje (0–100) o monto fijo, nunca mayor que la base */
    public static function discount(float $base, string $type, float $value): float
    {
        $value = max(0.0, $value);
        $d = $type === 'amount' ? $value : $base * min(100.0, $value) / 100;
        return self::r(min($base, $d));
    }

    public static function r(float $v): float
    {
        return round($v + 0.0, 2);
    }
}
