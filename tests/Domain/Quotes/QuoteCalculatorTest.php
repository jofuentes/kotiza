<?php
declare(strict_types=1);

namespace Tests\Domain\Quotes;

use App\Domain\Quotes\QuoteCalculator;
use PHPUnit\Framework\TestCase;

final class QuoteCalculatorTest extends TestCase
{
    public function testLinesDiscountsAndTotals(): void
    {
        $r = QuoteCalculator::calculate([
            ['qty' => 2, 'unit_price' => 1000, 'discount_type' => 'percent', 'discount_value' => 10, 'unit_cost' => 700],
            ['qty' => 3, 'unit_price' => 150.5, 'discount_type' => 'amount', 'discount_value' => 1.5, 'unit_cost' => 100],
            ['qty' => 1, 'unit_price' => 500, 'unit_cost' => null], // mano de obra sin costo
        ], 'percent', 5, 16);

        $this->assertSame(['gross' => 2000.0, 'discount' => 200.0, 'total' => 1800.0, 'cost' => 1400.0], $r['lines'][0]);
        $this->assertSame(['gross' => 451.5, 'discount' => 1.5, 'total' => 450.0, 'cost' => 300.0], $r['lines'][1]);
        $this->assertSame(2750.0, $r['subtotal']);
        $this->assertSame(137.5, $r['discount_total']);
        $this->assertSame(2612.5, $r['taxable']);
        $this->assertSame(418.0, $r['tax_total']);
        $this->assertSame(3030.5, $r['total']);
        $this->assertSame(1700.0, $r['cost_total']);
        $this->assertFalse($r['cost_complete']);
        // Utilidad solo de las partidas con costo: (1800+450) × 0.95 − 1700
        $this->assertSame(437.5, $r['profit']);
        $this->assertSame(20.47, $r['margin_pct']);
    }

    public function testDiscountsNeverExceedBase(): void
    {
        $this->assertSame(100.0, QuoteCalculator::discount(100, 'amount', 250));
        $this->assertSame(100.0, QuoteCalculator::discount(100, 'percent', 150));
        $this->assertSame(0.0, QuoteCalculator::discount(100, 'percent', -5));
        $r = QuoteCalculator::calculate([['qty' => 1, 'unit_price' => 100]], 'amount', 500, 16);
        $this->assertSame(0.0, $r['total']);
    }

    public function testFractionalQuantitiesAndRounding(): void
    {
        $r = QuoteCalculator::calculate([['qty' => '1.5', 'unit_price' => '33.333']], 'percent', 0, 16);
        $this->assertSame(50.0, $r['subtotal']);
        $this->assertSame(8.0, $r['tax_total']);
        $this->assertSame(58.0, $r['total']);
    }

    public function testEmptyQuote(): void
    {
        $r = QuoteCalculator::calculate([], 'percent', 0, 16);
        $this->assertSame(0.0, $r['total']);
        $this->assertNull($r['profit']);
        $this->assertNull($r['cost_total']);
    }
}
