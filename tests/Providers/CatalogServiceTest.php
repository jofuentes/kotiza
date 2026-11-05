<?php
declare(strict_types=1);

namespace Tests\Providers;

use App\Http\Controllers\CatalogController;
use App\Providers\CatalogService;
use PHPUnit\Framework\TestCase;

final class CatalogServiceTest extends TestCase
{
    private static function p(string $provider, string $model, ?float $cost, string $cur = 'USD'): array
    {
        return ['provider' => $provider, 'model' => $model, 'cost' => $cost, 'currency' => $cur];
    }

    public function testMarksCheapestAcrossProvidersOnly(): void
    {
        $r = CatalogService::markCheapest([
            self::p('syscom', 'DS-2CD2143G2-I', 80.0),
            self::p('tvc', 'ds2cd2143g2i', 75.5),
            self::p('syscom', 'ABC-1', 10.0),
            self::p('syscom', 'ABC1', 9.0),       // mismo proveedor: no se marca
            self::p('tvc', 'XYZ', null),
            self::p('syscom', 'XYZ', 5.0),        // el otro no tiene costo: no se marca
            self::p('syscom', 'MON', 100.0, 'MXN'),
            self::p('tvc', 'MON', 6.0, 'USD'),    // monedas distintas: no se comparan
        ]);
        $this->assertSame([false, true, false, false, false, false, false, false], array_column($r, 'cheapest'));
    }

    public function testSanitizesProviderHtml(): void
    {
        $html = CatalogController::sanitizeHtml('<div onclick="x()"><p style="color:red">Hola <b>mundo</b></p><script>alert(1)</script><img src=x onerror=alert(1)><a href="javascript:x">l</a></div>');
        $this->assertSame('<div><p>Hola <b>mundo</b></p>l</div>', $html);
    }
}
