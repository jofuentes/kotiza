<?php
declare(strict_types=1);

namespace App\Pricing;

/**
 * Motor de precios. Prioridad (la más específica gana):
 *   precio manual de la partida (lo resuelve el editor) → producto → marca → categoría → proveedor → global.
 * Métodos:
 *   markup  precio = costo × (1 + %)
 *   margin  precio = costo ÷ (1 − %)            (margen sobre el precio de venta)
 *   list    precio = precio de lista del proveedor (sin IVA); descuento sugerido opcional
 *           si el producto no tiene lista, se usa costo + % de la misma regla (o el global)
 * Después: precio mínimo y redondeo.
 */
final class PriceEngine
{
    /** @param PricingRule[] $rules reglas activas de la empresa (sin la global) */
    public function __construct(private array $rules, private PricingRule $global)
    {
    }

    public function rule(PriceContext $c): PricingRule
    {
        $best = null;
        foreach ($this->rules as $r) {
            if (!$r->matches($c)) {
                continue;
            }
            $better = $best === null
                || PricingRule::PRIORITY[$r->scope] < PricingRule::PRIORITY[$best->scope]
                // misma prioridad: gana la que además restringe proveedor
                || (PricingRule::PRIORITY[$r->scope] === PricingRule::PRIORITY[$best->scope] && $r->providerCode !== null && $best->providerCode === null);
            if ($better) {
                $best = $r;
            }
        }
        return $best ?? $this->global;
    }

    public function price(PriceContext $c): PriceResult
    {
        $rule = $this->rule($c);
        $where = $rule->scopeLabel();
        $suggested = 0.0;

        if ($rule->method === 'list' && $c->listPrice !== null && $c->listPrice > 0) {
            $price = $c->listPrice;
            $suggested = max(0.0, min(100.0, $rule->listDiscount));
            $label = 'Precio de lista (' . $where . ')' . ($suggested > 0 ? ' · desc. sugerido ' . self::pct($suggested) : '');
        } elseif ($rule->method === 'list') {
            // Sin precio de lista: costo + % de la regla, o el global si la regla no define %
            $pct = $rule->percent > 0 ? $rule->percent : $this->global->percent;
            $price = $c->cost * (1 + $pct / 100);
            $label = 'Sin precio de lista: costo + ' . self::pct($pct) . ' (' . $where . ')';
        } elseif ($rule->method === 'margin' && $rule->percent < 100) {
            $price = $c->cost / (1 - $rule->percent / 100);
            $label = 'Margen ' . self::pct($rule->percent) . ' (' . $where . ')';
        } else {
            $price = $c->cost * (1 + $rule->percent / 100);
            $label = 'Costo + ' . self::pct($rule->percent) . ' (' . $where . ')';
        }

        if ($rule->minPrice !== null && $rule->minPrice > 0) {
            $min = $c->fromBaseCurrency ? ($c->fromBaseCurrency)($rule->minPrice) : $rule->minPrice;
            if ($price < $min) {
                $price = $min;
                $label .= ' · precio mínimo';
            }
        }

        $price = Rounding::apply(round($price, 4), $rule->rounding, $rule->roundingValue);
        return new PriceResult(round($price, 2), $label, $suggested, $rule);
    }

    public static function pct(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . ' %';
    }
}
