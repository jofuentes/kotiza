<?php
declare(strict_types=1);

namespace Tests\Providers;

use App\Providers\Http\HttpClientFactory;
use App\Providers\Http\MemoryCallLogger;
use App\Providers\Http\MemoryTokenStore;
use App\Providers\ProviderAccount;
use App\Providers\ProviderException;
use App\Providers\ProviderRegistry;
use App\Providers\Syscom\SyscomProvider;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class SyscomProviderTest extends TestCase
{
    private MockHandler $mock;
    private MemoryTokenStore $tokens;
    private MemoryCallLogger $logger;
    /** @var Request[] */
    private array $history = [];

    private function provider(array $settings = []): SyscomProvider
    {
        $this->mock = new MockHandler();
        $this->tokens = new MemoryTokenStore();
        $this->logger = new MemoryCallLogger();
        $this->history = [];
        $history = &$this->history;
        $handler = function ($request, $options) use (&$history) {
            $history[] = $request;
            return ($this->mock)($request, $options);
        };
        $http = HttpClientFactory::make(7, $this->logger, $handler);
        $account = new ProviderAccount(7, 1, 'syscom', ['client_id' => 'cid', 'client_secret' => 'secreto'], $settings);
        return new SyscomProvider($account, $http, $this->tokens);
    }

    private static function json(mixed $data, int $status = 200, array $headers = []): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'] + $headers, is_string($data) ? $data : json_encode($data));
    }

    private static function fixture(string $name): string
    {
        return file_get_contents(__DIR__ . "/../Fixtures/syscom/{$name}.json");
    }

    private static function token(string $t = 'TOKEN-1'): Response
    {
        return self::json(['token_type' => 'Bearer', 'expires_in' => 31536000, 'access_token' => $t]);
    }

    public function testRegistryDiscoversSyscom(): void
    {
        $this->assertSame(SyscomProvider::class, ProviderRegistry::get('syscom'));
    }

    public function testSearchRequestsTokenOnceAndNormalizes(): void
    {
        $p = $this->provider();
        $this->mock->append(self::token(), self::json(self::fixture('search')), self::json(self::fixture('search')));

        $r = $p->search('fuente  12v', ['in_stock' => true]);
        $p->search('otra');

        // 1 token + 2 búsquedas: el token se reutiliza
        $this->assertCount(3, $this->history);
        $this->assertSame('POST', $this->history[0]->getMethod());
        $this->assertStringContainsString('grant_type=client_credentials', (string) $this->history[0]->getBody());
        $this->assertSame('Bearer TOKEN-1', $this->history[1]->getHeaderLine('Authorization'));
        parse_str($this->history[1]->getUri()->getQuery(), $q);
        $this->assertSame('fuente 12v', $q['busqueda']);
        $this->assertSame('true', $q['stock']);
        $this->assertSame('usd', $q['moneda']);
        $this->assertSame('true', $q['financiero'], 'descuento financiero activo por defecto');
        $this->assertSame('true', $q['iva'], 'costo con IVA por defecto');
        $this->assertSame('true', $q['inventarios']);

        $this->assertSame(2, $r->total);
        $first = $r->products[0];
        $this->assertSame('215182', $first->providerId);
        $this->assertSame('DS-2FA1205-C8/K', $first->model);
        $this->assertSame('Fuente de Poder Regulada 12 Vcc & 5 A', $first->title);
        $this->assertSame('Fuentes de poder', $first->category, 'categoría más específica');
        $this->assertSame(9.80, $first->cost, 'precio_descuento por defecto');
        $this->assertSame('USD', $first->currency);
        $this->assertSame(37, $first->stock);
        $this->assertSame(['guadalajara' => 12, 'mexico' => 25], $first->stockByWarehouse);
        $this->assertSame('39121004', $first->satKey);
        $this->assertSame('H87', $first->satUnit);
        $this->assertSame('Pieza', $first->unit);
        $this->assertSame('https://www.syscom.mx/producto/DS-2FA1205-C8-K-HIKVISION-215182.html', $first->url);
        $this->assertSame(['https://ftp3.syscom.mx/usuarios/fotos/BancoFotografiasSyscom/DS2FA1205C8K.JPG'], $first->images);

        $second = $r->products[1];
        $this->assertNull($second->cost, 'sin distribución no hay costo');
        $this->assertSame('SOLICITAR DISTRIBUCIÓN', $second->note);
        $this->assertTrue($first->costIncludesVat);
        $this->assertEqualsWithDelta(15.00 / 1.16, $first->listPrice, 0.0001, 'lista sin IVA cuando se pidió con IVA');
        $this->assertNull($second->listPrice, 'sin distribución no hay lista');
    }

    public function testCostSettingAndOpenBox(): void
    {
        $p = $this->provider(['cost_price' => 'precio_especial', 'include_open_box' => true, 'currency' => 'mxn']);
        $this->mock->append(self::token(), self::json(self::fixture('search')));
        $first = $p->search('fuente')->products[0];
        $this->assertSame(10.20, $first->cost);
        $this->assertSame(39, $first->stock);
        $this->assertSame('MXN', $first->currency);
    }

    public function testFinancialDiscountCanBeDisabled(): void
    {
        $p = $this->provider(['financial_discount' => false, 'include_vat' => false]);
        $this->mock->append(self::token(), self::json(self::fixture('product')));
        $prod = $p->getProduct('215182');
        parse_str($this->history[1]->getUri()->getQuery(), $q);
        $this->assertArrayNotHasKey('financiero', $q);
        $this->assertArrayNotHasKey('iva', $q);
        $this->assertFalse($prod->costIncludesVat);
        $this->assertSame(15.0, $prod->listPrice, 'sin IVA solicitado, la lista se usa tal cual');
        $this->assertSame('usd', $q['moneda']);
    }

    public function testCostFallsBackWhenConfiguredPriceMissing(): void
    {
        $p = $this->provider(['cost_price' => 'precio_descuento']);
        $this->mock->append(self::token(), self::json(self::fixture('product')));
        $prod = $p->getProduct('215182');
        $this->assertSame(10.20, $prod->cost, 'cae a precio_especial');
        $this->assertSame(['https://ftp3.syscom.mx/a.jpg', 'https://ftp3.syscom.mx/b.jpg'], $prod->images);
        $this->assertStringContainsString('/productos/215182', (string) $this->history[1]->getUri());
    }

    public function testGetPricesBatchesIds(): void
    {
        $p = $this->provider();
        $list = json_decode(self::fixture('search'), true)['productos'];
        $this->mock->append(self::token(), self::json($list));
        $prices = $p->getPrices(['215182', '210627', 'no-numérico']);
        $this->assertSame(['215182', '210627'], array_map('strval', array_keys($prices)));
        $this->assertStringContainsString('/productos/215182,210627', rawurldecode((string) $this->history[1]->getUri()));
    }

    public function testExpiredTokenIsRefreshedOnce(): void
    {
        $p = $this->provider();
        $this->tokens->put(7, 'VIEJO', time() + 86400 * 30);
        $this->mock->append(
            self::json(['error' => 'Token inválido', 'code' => 'unauthorized'], 401),
            self::token('NUEVO'),
            self::json(self::fixture('search')),
        );
        $r = $p->search('fuente');
        $this->assertSame(2, $r->total);
        $this->assertSame('Bearer VIEJO', $this->history[0]->getHeaderLine('Authorization'));
        $this->assertSame('Bearer NUEVO', $this->history[2]->getHeaderLine('Authorization'));
        $this->assertSame('NUEVO', $this->tokens->get(7)['token']);
    }

    public function testRetriesOn429AndServerErrorsAndLogsEachAttempt(): void
    {
        $p = $this->provider();
        $this->mock->append(
            self::token(),
            self::json(['error' => 'Demasiadas'], 429, ['Retry-After' => '0']),
            self::json(['error' => 'x'], 503),
            self::json(self::fixture('search')),
        );
        $this->assertSame(2, $p->search('fuente')->total);
        $codes = array_column($this->logger->calls, 'status');
        $this->assertSame([200, 429, 503, 200], $codes);
        $this->assertStringNotContainsString('secreto', json_encode($this->logger->calls), 'el log no expone secretos');
    }

    public function testGivesUpAfterMaxRetriesWithClearMessage(): void
    {
        $p = $this->provider();
        $this->mock->append(self::token());
        for ($i = 0; $i <= HttpClientFactory::MAX_RETRIES; $i++) {
            $this->mock->append(self::json(['error' => 'x'], 500));
        }
        try {
            $p->search('fuente');
            $this->fail('Debió lanzar ProviderException');
        } catch (ProviderException $e) {
            $this->assertSame(500, $e->httpStatus);
            $this->assertStringContainsString('Syscom tiene problemas', $e->getMessage());
        }
    }

    public function testLongRetryAfterIsNotWaitedFor(): void
    {
        $p = $this->provider();
        $this->mock->append(self::token(), self::json(['error' => 'límite'], 429, ['Retry-After' => '120']));
        $this->expectExceptionMessage('Intenta de nuevo en 120 s');
        $p->search('fuente');
    }

    public function testConnectionErrorIsTranslated(): void
    {
        $p = $this->provider();
        $this->tokens->put(7, 'T', time() + 86400 * 30);
        for ($i = 0; $i <= HttpClientFactory::MAX_RETRIES; $i++) {
            $this->mock->append(new ConnectException('timeout', new Request('GET', 'https://x')));
        }
        $this->expectExceptionMessage('No se pudo conectar con Syscom');
        $p->search('fuente');
    }

    public function testTestConnectionReportsBadCredentials(): void
    {
        $p = $this->provider();
        $this->mock->append(self::json(['error' => 'Credenciales inválidas'], 401));
        $r = $p->testConnection();
        $this->assertFalse($r->ok);
        $this->assertStringContainsString('rechazó las credenciales', $r->message);
    }

    public function testTestConnectionSuccessIncludesExchangeRate(): void
    {
        $p = $this->provider(['exchange_type' => 'preferencial']);
        $this->mock->append(
            self::token(),
            self::json([['id' => '21', 'nombre' => 'Videovigilancia', 'nivel' => 1], ['id' => '22', 'nombre' => 'Redes', 'nivel' => 1]]),
            self::json(['normal' => '18.76', 'preferencial' => '18.70']),
        );
        $r = $p->testConnection();
        $this->assertTrue($r->ok, $r->message);
        $this->assertSame('2', $r->details['Categorías disponibles']);
        $this->assertSame('$18.70 MXN', $r->details['Tipo de cambio (preferencial)']);
    }

    public function testAiSearchUsesNaturalLanguageEndpoint(): void
    {
        $p = $this->provider(['ai_search' => true]);
        $this->mock->append(self::token(), self::json(self::fixture('search')));
        $p->search('cámara domo exterior con visión nocturna');
        $uri = $this->history[1]->getUri();
        $this->assertStringEndsWith('/productos/busqueda-ia', $uri->getPath());
        parse_str($uri->getQuery(), $q);
        $this->assertSame('cámara domo exterior con visión nocturna', $q['q']);
    }

    public function testMissingCredentialsFailFast(): void
    {
        $this->mock = new MockHandler();
        $http = HttpClientFactory::make(1, new MemoryCallLogger(), $this->mock);
        $p = new SyscomProvider(new ProviderAccount(1, 1, 'syscom', []), $http, new MemoryTokenStore());
        $this->expectExceptionMessage('Faltan el Client ID');
        $p->search('x');
    }
}
