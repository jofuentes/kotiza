<?php
declare(strict_types=1);

namespace Tests\Providers;

use App\Providers\Catalog\CatalogSynchronizer;
use App\Providers\Catalog\MemoryLocalCatalog;
use App\Providers\Http\HttpClientFactory;
use App\Providers\Http\MemoryCallLogger;
use App\Providers\Http\MemoryTokenStore;
use App\Providers\ProviderAccount;
use App\Providers\ProviderException;
use App\Providers\ProviderRegistry;
use App\Providers\Tvc\TvcProvider;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class TvcProviderTest extends TestCase
{
    private MockHandler $mock;
    private MemoryLocalCatalog $catalog;
    private array $history = [];

    private function provider(array $settings = [], array $credentials = ['token' => 'TVC-TOKEN']): TvcProvider
    {
        $this->mock = new MockHandler();
        $this->catalog = new MemoryLocalCatalog();
        $this->history = [];
        $history = &$this->history;
        $handler = function ($request, $options) use (&$history) {
            $history[] = $request;
            return ($this->mock)($request, $options);
        };
        $http = HttpClientFactory::make(9, new MemoryCallLogger(), $handler);
        return new TvcProvider(new ProviderAccount(9, 1, 'tvc', $credentials, $settings), $http, new MemoryTokenStore(), $this->catalog);
    }

    private static function json(string|array $data, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], is_string($data) ? $data : json_encode($data));
    }

    private static function fixture(string $name): string
    {
        return file_get_contents(__DIR__ . "/../Fixtures/tvc/{$name}.json");
    }

    public function testRegistryDiscoversTvc(): void
    {
        $this->assertSame(TvcProvider::class, ProviderRegistry::get('tvc'));
    }

    public function testFetchCatalogPageUsesBearerAndNormalizes(): void
    {
        $p = $this->provider();
        $this->mock->append(self::json(self::fixture('products-page1')));
        $page = $p->fetchCatalogPage(1);

        $req = $this->history[0];
        $this->assertSame('Bearer TVC-TOKEN', $req->getHeaderLine('Authorization'));
        parse_str($req->getUri()->getQuery(), $q);
        $this->assertSame(['page' => '1', 'perPage' => '100', 'withPrice' => 'true', 'withInventory' => 'simple',
            'withMedia' => 'true', 'withCategoryBreadcrumb' => 'true'], $q);

        $this->assertSame(2, $page->lastPage);
        $this->assertSame(3, $page->total);
        $a = $page->products[0];
        $this->assertSame('5464', $a->providerId);
        $this->assertSame('APC-AMC2-4WCF', $a->model, 'modelo del fabricante para comparar entre proveedores');
        $this->assertSame('RBM065001', $a->sku, 'clave TVC');
        $this->assertSame('BOSCH A_APCAMC24WCF- AMC2 MODULO DE CONTROL DE ACCESO DE 1 A 4 PUERTAS / INTERFAZ WIEGAND / 8 ENTRADAS / 8 SALIDAS', $a->title);
        $this->assertEqualsWithDelta(24.67 * 1.16, $a->cost, 0.0001, 'precio distribuidor + IVA por defecto');
        $this->assertTrue($a->costIncludesVat);
        $this->assertSame('USD', $a->currency);
        $this->assertSame(500, $a->stock);
        $this->assertSame(['https://cdn.tvc.mx/media/3168/AMC2.jpg', 'https://cdn.tvc.mx/media/3172/AMC2-diagram.jpg'], $a->images);
        $this->assertSame('Precio por volumen desde 4 pzas: $24.42 USD', $a->features[0]);
        $this->assertNull($a->note);

        $b = $page->products[1];
        $this->assertSame('Estatus en TVC: Descontinuado', $b->note);
        $this->assertSame(['https://cdn.tvc.mx/media/1/domo.jpg'], $b->images);
    }

    public function testCostSettings(): void
    {
        $p = $this->provider(['cost_price' => 'list_price', 'add_vat' => false, 'currency' => 'MXN']);
        $this->mock->append(self::json(self::fixture('products-page1')));
        $a = $p->fetchCatalogPage(1)->products[0];
        $this->assertSame(47.65, $a->cost);
        $this->assertFalse($a->costIncludesVat);
        $this->assertSame('MXN', $a->currency);
    }

    public function testMissingProviderModelFallsBackToTvcModel(): void
    {
        $p = $this->provider();
        $this->mock->append(self::json(self::fixture('products-page2')));
        $c = $p->fetchCatalogPage(2)->products[0];
        $this->assertSame('SXI0410013', $c->model);
        $this->assertNull($c->sku);
        $this->assertEqualsWithDelta(60 * 1.16, $c->cost, 0.0001, 'sin precio distribuidor usa el de lista');
    }

    public function testSyncThenSearchLocally(): void
    {
        $p = $this->provider();
        $this->mock->append(self::json(self::fixture('products-page1')), self::json(self::fixture('products-page2')));
        $progress = [];
        $sleeps = [];
        $sync = new CatalogSynchronizer($this->catalog,
            function (int $page, int $last, int $items) use (&$progress) { $progress[] = [$page, $last, $items]; },
            function (int $ms) use (&$sleeps) { $sleeps[] = $ms; });
        $r = $sync->run($p, 9);

        $this->assertSame(['items' => 3, 'pages' => 2, 'removed' => 0], $r);
        $this->assertSame([[1, 2, 2], [2, 2, 3]], $progress);
        $this->assertSame([450], $sleeps, 'pausa entre páginas para respetar 200 req/min');

        $res = $p->search('camara domo');
        $this->assertSame(['148'], array_map(fn ($x) => $x->providerId, $res->products));
        $this->assertSame(['5464'], array_map(fn ($x) => $x->providerId, $p->search('rbm065001')->products), 'por clave TVC');
        $this->assertSame(['5464'], array_map(fn ($x) => $x->providerId, $p->search('APCAMC2')->products), 'por modelo sin guiones');
        $this->assertSame(['31071'], array_map(fn ($x) => $x->providerId, $p->search('switch', ['in_stock' => true])->products));
        $this->assertNotNull($res->syncedAt);
        $this->assertCount(2, $this->history, 'solo las 2 páginas de la sincronización: buscar no llama a la API');
    }

    public function testFullSyncPrunesRemovedProducts(): void
    {
        $p = $this->provider();
        $old = $p->normalize(['tvc_id' => 1, 'tvc_model' => 'VIEJO', 'name' => 'Ya no existe', 'brand' => 'X']);
        $this->catalog->upsert(9, [$old], time() - 86400);
        $this->mock->append(self::json(self::fixture('products-page1')), self::json(self::fixture('products-page2')));
        $r = (new CatalogSynchronizer($this->catalog, null, fn () => null))->run($p, 9);
        $this->assertSame(1, $r['removed']);
        $this->assertSame(3, $this->catalog->count(9));
    }

    public function testFailedSyncKeepsPreviousCatalog(): void
    {
        $p = $this->provider();
        $this->catalog->upsert(9, [$p->normalize(['tvc_id' => 1, 'tvc_model' => 'A', 'name' => 'A'])], time() - 86400);
        $this->mock->append(self::json(self::fixture('products-page1')));
        for ($i = 0; $i <= HttpClientFactory::MAX_RETRIES; $i++) {
            $this->mock->append(self::json(['message' => 'error'], 500));
        }
        try {
            (new CatalogSynchronizer($this->catalog, null, fn () => null))->run($p, 9);
            $this->fail('Debió fallar');
        } catch (ProviderException $e) {
            $this->assertStringContainsString('TVC tiene problemas', $e->getMessage());
        }
        $this->assertSame(3, $this->catalog->count(9), 'no se borró nada');
    }

    public function testSearchBeforeSyncExplainsWhatToDo(): void
    {
        $p = $this->provider();
        $this->expectExceptionMessage('aún no se ha sincronizado');
        $p->search('camara');
    }

    public function testGetPricesUsesArrayParamsWithoutIndexes(): void
    {
        $p = $this->provider();
        $this->mock->append(self::json(self::fixture('products-page1')));
        $prices = $p->getPrices(['5464', '148', 'x']);
        $this->assertSame(['5464', '148'], array_map('strval', array_keys($prices)));
        $query = rawurldecode($this->history[0]->getUri()->getQuery());
        $this->assertStringContainsString('tvcIds[]=5464&tvcIds[]=148', $query);
        $this->assertStringContainsString('withInventory=detailed', $query);
    }

    public function testGetProductDetailWithWarehousesAndOverviews(): void
    {
        $p = $this->provider();
        $this->mock->append(self::json(self::fixture('product-detail')));
        $prod = $p->getProduct('5464');
        $this->assertStringEndsWith('/products/5464', $this->history[0]->getUri()->getPath());
        $this->assertSame(['Matriz' => 250, 'Guadalajara' => 100], $prod->stockByWarehouse);
        $this->assertSame('<h4>Descripción</h4><p>Desc. del Producto.</p><h4>Función</h4><ul><li>Apertura de puerta</li></ul>', $prod->description);
        $this->assertEqualsWithDelta(29.0, $prod->cost, 0.0001);
    }

    public function testProductNotFoundReturnsNull(): void
    {
        $p = $this->provider();
        $this->mock->append(self::json(['message' => 'No encontrado'], 404));
        $this->assertNull($p->getProduct('999'));
    }

    /** La documentación muestra "{ 18.55 }"; aceptamos varias formas */
    public function testExchangeRateParsing(): void
    {
        foreach (['{ 18.55 }', '18.55', '{"data": 18.55}', '{"rate": "18.55"}'] as $body) {
            $p = $this->provider();
            $this->mock->append(new Response(200, [], $body));
            $this->assertSame(18.55, $p->getExchangeRate(), $body);
        }
    }

    public function testConnectionWithBadToken(): void
    {
        $p = $this->provider();
        $this->mock->append(self::json(['message' => 'Unauthenticated.'], 401), self::json(['message' => 'Unauthenticated.'], 401));
        $r = $p->testConnection();
        $this->assertFalse($r->ok);
        $this->assertStringContainsString('TVC rechazó las credenciales', $r->message);
    }

    public function testConnectionOk(): void
    {
        $p = $this->provider();
        $this->mock->append(self::json(['data' => [], 'meta' => ['total' => 15234]]), new Response(200, [], '18.55'));
        $r = $p->testConnection();
        $this->assertTrue($r->ok, $r->message);
        $this->assertSame('15,234', $r->details['Productos en el catálogo de TVC']);
        $this->assertSame('$18.55 MXN', $r->details['Tipo de cambio TVC']);
        $this->assertSame('Aún no sincronizado', $r->details['Catálogo local']);
    }

    public function testMissingToken(): void
    {
        $p = $this->provider([], []);
        $r = $p->testConnection();
        $this->assertFalse($r->ok);
        $this->assertSame('Falta el token de API de TVC.', $r->message);
    }
}
