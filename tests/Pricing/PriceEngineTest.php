<?php
declare(strict_types=1);

namespace Tests\Pricing;

use App\Pricing\PriceContext;
use App\Pricing\PriceEngine;
use App\Pricing\PricingRule;
use App\Pricing\Rounding;
use PHPUnit\Framework\TestCase;

final class PriceEngineTest extends TestCase
{
    private function ctx(array $o = []): PriceContext
    {
        return new PriceContext(
            provider: $o['provider'] ?? 'syscom',
            model: $o['model'] ?? 'DS-2CD2143G2-I',
            brand: $o['brand'] ?? 'HIKVISION',
            category: $o['category'] ?? 'Cámaras IP',
            cost: $o['cost'] ?? 100.0,
            listPrice: array_key_exists('list', $o) ? $o['list'] : 150.0,
            fromBaseCurrency: $o['conv'] ?? null,
        );
    }

    private function global(array $o = []): PricingRule
    {
        return PricingRule::global($o + ['price_method' => 'markup', 'global_margin' => 25, 'list_discount' => 0, 'price_rounding' => 'none']);
    }

    public function testGlobalMarkupAndMargin(): void
    {
        $e = new PriceEngine([], $this->global());
        $r = $e->price($this->ctx());
        $this->assertSame(125.0, $r->price);
        $this->assertSame('Costo + 25 % (global)', $r->label);

        $e = new PriceEngine([], $this->global(['price_method' => 'margin']));
        $this->assertSame(133.33, $e->price($this->ctx())->price);
    }

    public function testGlobalListPriceWithSuggestedDiscount(): void
    {
        $e = new PriceEngine([], $this->global(['price_method' => 'list', 'list_discount' => 10]));
        $r = $e->price($this->ctx(['list' => 7091.2759]));
        $this->assertSame(7091.28, $r->price);
        $this->assertSame(10.0, $r->suggestedDiscount);
        $this->assertSame('Precio de lista (global) · desc. sugerido 10 %', $r->label);
    }

    public function testListWithoutListPriceFallsBackToMarkup(): void
    {
        $e = new PriceEngine([], $this->global(['price_method' => 'list', 'global_margin' => 30]));
        $r = $e->price($this->ctx(['list' => null]));
        $this->assertSame(130.0, $r->price);
        $this->assertSame(0.0, $r->suggestedDiscount);
        $this->assertStringStartsWith('Sin precio de lista', $r->label);
    }

    public function testSpecificityOrder(): void
    {
        $rules = [
            new PricingRule('provider', 'syscom', 'markup', 10),
            new PricingRule('category', 'cámaras ip', 'markup', 20),
            new PricingRule('brand', 'hikvision', 'markup', 30),
            new PricingRule('product', 'DS2CD2143G2I', 'markup', 40),
        ];
        $e = new PriceEngine($rules, $this->global());
        $this->assertSame(140.0, $e->price($this->ctx())->price, 'producto (modelo normalizado) gana');
        $this->assertSame(130.0, $e->price($this->ctx(['model' => 'OTRO']))->price, 'luego marca');
        $this->assertSame(120.0, $e->price($this->ctx(['model' => 'OTRO', 'brand' => 'Dahua']))->price, 'luego categoría');
        $this->assertSame(110.0, $e->price($this->ctx(['model' => 'OTRO', 'brand' => 'Dahua', 'category' => 'Redes']))->price, 'luego proveedor');
        $this->assertSame(125.0, $e->price($this->ctx(['model' => 'OTRO', 'brand' => 'Dahua', 'category' => 'Redes', 'provider' => 'tvc']))->price, 'global');
    }

    public function testRuleRestrictedToProviderWinsTie(): void
    {
        $rules = [new PricingRule('brand', 'HIKVISION', 'markup', 30), new PricingRule('brand', 'HIKVISION', 'markup', 15, providerCode: 'tvc')];
        $e = new PriceEngine($rules, $this->global());
        $this->assertSame(115.0, $e->price($this->ctx(['provider' => 'tvc']))->price);
        $this->assertSame(130.0, $e->price($this->ctx(['provider' => 'syscom']))->price);
    }

    public function testMinPriceUsesConversion(): void
    {
        $rules = [new PricingRule('brand', 'HIKVISION', 'markup', 10, minPrice: 1876)];
        $e = new PriceEngine($rules, $this->global());
        // cotización en USD: el mínimo (MXN) se convierte a 100 USD
        $r = $e->price($this->ctx(['cost' => 50, 'conv' => fn ($v) => $v / 18.76]));
        $this->assertSame(100.0, $r->price);
        $this->assertStringEndsWith('precio mínimo', $r->label);
    }

    public function testRoundingAppliesAfterMethod(): void
    {
        $rules = [new PricingRule('brand', 'HIKVISION', 'markup', 23.4, rounding: 'multiple', roundingValue: 5)];
        $e = new PriceEngine($rules, $this->global());
        $this->assertSame(125.0, $e->price($this->ctx())->price); // 123.40 → 125
    }

    public function testRounding(): void
    {
        $this->assertSame(124.0, Rounding::apply(123.01, 'integer'));
        $this->assertSame(123.0, Rounding::apply(123.0, 'integer'));
        $this->assertSame(123.90, Rounding::apply(123.45, 'ending90'));
        $this->assertSame(124.90, Rounding::apply(123.95, 'ending90'));
        $this->assertSame(123.90, Rounding::apply(123.90, 'ending90'));
        $this->assertSame(130.0, Rounding::apply(121, 'multiple', 10));
        $this->assertSame(120.0, Rounding::apply(120, 'multiple', 10));
        $this->assertSame(99.5, Rounding::apply(99.5, 'multiple', 0));
        $this->assertSame(99.57, Rounding::apply(99.567, 'none'));
    }
}
