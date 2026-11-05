<?php
declare(strict_types=1);

namespace Tests\Pricing;

use App\Pricing\CurrencyConverter;
use App\Pricing\ExchangeRateService;
use App\Pricing\PriceCalculator;
use PHPUnit\Framework\TestCase;

final class PricingBasicsTest extends TestCase
{
    public function testConvert(): void
    {
        $this->assertSame(100.0, CurrencyConverter::convert(100, 'MXN', 'MXN', null));
        $this->assertEqualsWithDelta(1876.0, CurrencyConverter::convert(100, 'USD', 'MXN', 18.76), 1e-9);
        $this->assertEqualsWithDelta(100.0, CurrencyConverter::convert(1876, 'MXN', 'USD', 18.76), 1e-9);
    }

    public function testConvertWithoutRateFails(): void
    {
        $this->expectException(\DomainException::class);
        CurrencyConverter::convert(1, 'USD', 'MXN', null);
    }

    public function testSuggestUsesProviderMarginFirst(): void
    {
        $this->assertSame(['price' => 1300.0, 'rule' => 'Margen del proveedor 30 %'], PriceCalculator::suggest(1000, 30, 25));
        $this->assertSame(['price' => 1255.0, 'rule' => 'Margen global 25.5 %'], PriceCalculator::suggest(1000, null, 25.5));
    }

    public function testExchangeBuffer(): void
    {
        $this->assertSame(['rate' => 19.26, 'source' => 'Syscom + 0.50'], ExchangeRateService::applyBuffer(18.76, 'Syscom', 0.5));
        $this->assertSame(['rate' => 18.76, 'source' => 'Syscom'], ExchangeRateService::applyBuffer(18.76, 'Syscom', 0));
    }
}
