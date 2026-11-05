<?php
declare(strict_types=1);

namespace App\Providers\Tvc;

use App\Providers\AbstractProvider;
use App\Providers\Catalog\CatalogPage;
use App\Providers\Catalog\SyncableProvider;
use App\Providers\ConnectionResult;
use App\Providers\FieldDefinition;
use App\Providers\Product;
use App\Providers\ProviderException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;

/**
 * Conector TVC (api.tvc.mx). Su API no tiene búsqueda por texto, así que el catálogo
 * se sincroniza a una tabla local y las búsquedas se hacen ahí; precios y existencias
 * al momento se consultan por ID al ver la ficha o actualizar una cotización.
 */
final class TvcProvider extends AbstractProvider implements SyncableProvider
{
    public static function code(): string
    {
        return 'tvc';
    }

    public static function name(): string
    {
        return 'TVC';
    }

    public static function description(): string
    {
        return 'Mayorista de videovigilancia, control de acceso y redes. El catálogo se sincroniza en segundo plano porque su API no permite buscar por texto.';
    }

    public static function credentialFields(): array
    {
        return [
            new FieldDefinition('token', 'Token de API', 'secret',
                'En tvc.mx → Mi Perfil → API Clientes. TVC lo aprueba y se muestra una sola vez; dura 1 año.', true),
        ];
    }

    public static function settingFields(): array
    {
        return [
            new FieldDefinition('cost_price', 'Precio que se toma como costo', 'select', '', false, [
                'distributor_price' => 'Precio distribuidor (recomendado)',
                'list_price' => 'Precio de lista',
            ], 'distributor_price'),
            // TODO(TVC): confirmar con un token real en qué moneda vienen los precios (la documentación muestra USD).
            new FieldDefinition('currency', 'Moneda de los precios de TVC', 'select',
                'Según la documentación los precios vienen en USD.', false, ['USD' => 'Dólares (USD)', 'MXN' => 'Pesos (MXN)'], 'USD'),
            // TODO(TVC): confirmar si distributor_price incluye IVA. Se asume que NO.
            new FieldDefinition('add_vat', 'Sumar IVA al costo', 'bool',
                'Los precios de TVC vienen sin IVA; actívalo para que el costo lo incluya (igual que Syscom).', false, [], true),
            new FieldDefinition('vat_rate', 'IVA a sumar (%)', 'number', '', false, [], 16),
            new FieldDefinition('sync_hours', 'Sincronizar el catálogo cada (horas)', 'number',
                'Precios y existencias del buscador se actualizan con cada sincronización; al ver la ficha se consultan en vivo.', false, [], 12),
        ];
    }

    /** El token es fijo (lo genera el distribuidor); no hay endpoint para pedirlo */
    protected function accessToken(bool $forceRefresh = false): ?string
    {
        $token = $this->credential('token');
        if ($token === '') {
            throw new ProviderException('Falta el token de API de TVC.');
        }
        return $token;
    }

    // ---------- Conexión ----------

    public function testConnection(): ConnectionResult
    {
        $cfg = self::config();
        try {
            $data = $this->getJson($cfg['base_url'] . $cfg['endpoints']['products'], ['perPage' => 1, 'withPrice' => 'true']);
            $details = ['Productos en el catálogo de TVC' => number_format((int) ($data['meta']['total'] ?? 0))];
            try {
                if ($rate = $this->getExchangeRate()) {
                    $details['Tipo de cambio TVC'] = '$' . number_format($rate, 2) . ' MXN';
                }
            } catch (ProviderException) {
                // no indispensable
            }
            if ($this->catalog) {
                $count = $this->catalog->count($this->account->id);
                $at = $this->catalog->lastSyncedAt($this->account->id);
                $details['Catálogo local'] = $count
                    ? number_format($count) . ' productos (' . date('d/m/Y H:i', (int) $at) . ')'
                    : 'Aún no sincronizado';
            }
            return new ConnectionResult(true, 'Conexión correcta con TVC.', $details);
        } catch (ProviderException $e) {
            return new ConnectionResult(false, $e->getMessage());
        }
    }

    // ---------- Sincronización ----------

    public function fetchCatalogPage(int $page): CatalogPage
    {
        $cfg = self::config();
        $data = $this->getJson($cfg['base_url'] . $cfg['endpoints']['products'], [
            'page' => $page,
            'perPage' => $cfg['per_page'],
            'withPrice' => 'true',
            'withInventory' => 'simple',
            'withMedia' => 'true',
            'withCategoryBreadcrumb' => 'true',
        ]);
        $items = is_array($data['data'] ?? null) ? $data['data'] : [];
        return new CatalogPage(
            array_values(array_map(fn ($p) => $this->normalize($p), array_filter($items, 'is_array'))),
            (int) ($data['meta']['current_page'] ?? $page),
            max(1, (int) ($data['meta']['last_page'] ?? $page)),
            (int) ($data['meta']['total'] ?? count($items)),
        );
    }

    public function syncDelayMs(): int
    {
        return (int) self::config()['sync_delay_ms'];
    }

    public function syncIntervalHours(): int
    {
        return max(1, (int) $this->setting('sync_hours'));
    }

    // ---------- Catálogo ----------

    public function searchAsync(string $query, array $filters = []): PromiseInterface
    {
        if (!$this->catalog || $this->catalog->count($this->account->id) === 0) {
            return Create::rejectionFor(new ProviderException(
                'El catálogo de TVC aún no se ha sincronizado. Un administrador puede iniciarlo en Proveedores → TVC.'));
        }
        return Create::promiseFor($this->catalog->search($this->account->id, $query, $filters));
    }

    public function getProduct(string $id): ?Product
    {
        $cfg = self::config();
        try {
            $data = $this->getJson($cfg['base_url'] . str_replace('{id}', rawurlencode($id), $cfg['endpoints']['product']), [
                'withPrice' => 'true', 'withInventory' => 'detailed', 'withMedia' => 'true',
                'withOverviews' => 'true', 'withCategoryBreadcrumb' => 'true',
            ]);
        } catch (ProviderException $e) {
            if ($e->httpStatus === 404) {
                return null;
            }
            throw $e;
        }
        return is_array($data['data'] ?? null) ? $this->normalize($data['data']) : null;
    }

    public function getPrices(array $ids): array
    {
        $cfg = self::config();
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids), 'ctype_digit')));
        $out = [];
        foreach (array_chunk($ids, $cfg['max_ids_per_request']) as $chunk) {
            // TVC espera tvcIds[]=1&tvcIds[]=2 (sin índices)
            $query = implode('&', array_map(fn ($id) => 'tvcIds%5B%5D=' . $id, $chunk))
                . '&perPage=' . count($chunk) . '&withPrice=true&withInventory=detailed&withMedia=true';
            $data = $this->getJson($cfg['base_url'] . $cfg['endpoints']['products'], $query);
            foreach ((array) ($data['data'] ?? []) as $p) {
                if (is_array($p) && isset($p['tvc_id'])) {
                    $product = $this->normalize($p);
                    $out[$product->providerId] = $product;
                }
            }
        }
        return $out;
    }

    public function getExchangeRate(): ?float
    {
        $cfg = self::config();
        $res = $this->http->request('GET', $cfg['base_url'] . $cfg['endpoints']['exchange_rate'], [
            'headers' => ['Authorization' => 'Bearer ' . $this->accessToken()],
        ]);
        if ($res->getStatusCode() >= 400) {
            throw $this->translate($res);
        }
        // TODO(TVC): la documentación muestra "{ 18.55 }" (no es JSON válido); se acepta un número
        // suelto, un objeto con data/rate, o el primer número que aparezca en la respuesta.
        $body = trim((string) $res->getBody());
        $json = json_decode($body, true);
        $candidates = is_array($json)
            ? array_merge([$json['data'] ?? null, $json['rate'] ?? null], array_values($json))
            : [$json];
        foreach ($candidates as $c) {
            if (is_numeric($c) && (float) $c > 0) {
                return (float) $c;
            }
        }
        return preg_match('/\d+(\.\d+)?/', $body, $m) && (float) $m[0] > 0 ? (float) $m[0] : null;
    }

    public function getCategories(): array
    {
        $cfg = self::config();
        $data = $this->getJson($cfg['base_url'] . $cfg['endpoints']['categories'], ['per_page' => 1000]);
        return array_map(fn ($c) => [
            'id' => (string) $c['id'],
            'name' => (string) ($c['breadcrumb'] ?? $c['nombre']),
            'level' => ($c['parent_id'] ?? null) === null ? 1 : 2,
        ], (array) ($data['data'] ?? []));
    }

    public function getBrands(): array
    {
        $cfg = self::config();
        $data = $this->getJson($cfg['base_url'] . $cfg['endpoints']['brands'], ['per_page' => 1000]);
        return array_map(fn ($b) => ['id' => (string) $b['id'], 'name' => (string) $b['nombre']], (array) ($data['data'] ?? []));
    }

    // ---------- Normalización ----------

    /** Convierte un producto de la API de TVC al modelo común */
    public function normalize(array $p): Product
    {
        $cfg = self::config();
        $media = is_array($p['media'] ?? null) ? $p['media'] : [];
        $images = [];
        foreach (array_merge([$media['main_image'] ?? null], (array) ($media['gallery'] ?? [])) as $path) {
            if (is_string($path) && $path !== '') {
                $url = preg_match('#^https?://#', $path) ? $path : rtrim($cfg['media_base_url'], '/') . '/' . ltrim($path, '/');
                if (!in_array($url, $images, true)) {
                    $images[] = $url;
                }
            }
        }

        $warehouses = [];
        foreach ((array) ($p['inventory_detailed'] ?? []) as $w) {
            if (is_array($w) && (int) ($w['quantity'] ?? 0) > 0) {
                $warehouses[(string) ($w['name'] ?? $w['warehouse_id'] ?? '?')] = (int) $w['quantity'];
            }
        }
        $stock = isset($p['total_inventories']) ? (int) $p['total_inventories'] : array_sum($warehouses);

        $description = null;
        if (!empty($p['overviews']) && is_array($p['overviews'])) {
            $ov = array_filter($p['overviews'], 'is_array');
            usort($ov, fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
            $description = implode('', array_map(
                fn ($o) => '<h4>' . htmlspecialchars(html_entity_decode((string) ($o['title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')) . '</h4>'
                    . (string) ($o['description'] ?? ''),
                $ov
            ));
        }

        $features = [];
        foreach ((array) ($p['volume_product_discount_options'] ?? []) as $v) {
            if (is_array($v) && isset($v['quantity'], $v['price'])) {
                $features[] = sprintf('Precio por volumen desde %d pzas: $%s %s', (int) $v['quantity'],
                    number_format((float) $v['price'], 2), $v['currency'] ?? '');
            }
        }

        $note = null;
        if (isset($p['stage_name']) && mb_strtolower((string) $p['stage_name']) !== 'activo') {
            $note = 'Estatus en TVC: ' . $p['stage_name'];
        }

        $tvcModel = trim((string) ($p['tvc_model'] ?? ''));
        $model = trim((string) ($p['provider_model'] ?? '')) ?: $tvcModel;
        $url = $cfg['product_url_template']
            ? strtr($cfg['product_url_template'], ['{tvc_model}' => rawurlencode($tvcModel), '{tvc_id}' => (string) $p['tvc_id']])
            : null;

        return new Product(
            provider: self::code(),
            providerId: (string) $p['tvc_id'],
            model: $model,
            brand: trim((string) ($p['brand'] ?? '')),
            title: trim(preg_replace('/\s+/', ' ', html_entity_decode((string) ($p['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
            description: $description,
            category: isset($p['category']) ? (string) $p['category'] : null,
            images: $images,
            cost: $this->cost($p),
            currency: (string) $this->setting('currency') === 'MXN' ? 'MXN' : 'USD',
            stock: max(0, $stock),
            stockByWarehouse: $warehouses,
            satKey: isset($p['sat_key']) ? (string) $p['sat_key'] : null,
            url: $url,
            features: $features,
            note: $note,
            costIncludesVat: (bool) $this->setting('add_vat'),
            sku: $tvcModel !== '' && $tvcModel !== $model ? $tvcModel : null,
        );
    }

    private function cost(array $p): ?float
    {
        $order = array_unique([(string) $this->setting('cost_price'), 'distributor_price', 'list_price']);
        foreach ($order as $key) {
            if (isset($p[$key]) && is_numeric($p[$key]) && (float) $p[$key] > 0) {
                $cost = (float) $p[$key];
                if ($this->setting('add_vat')) {
                    $cost *= 1 + ((float) $this->setting('vat_rate')) / 100;
                }
                return round($cost, 4);
            }
        }
        return null;
    }
}
