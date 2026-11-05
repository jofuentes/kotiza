<?php
declare(strict_types=1);

namespace App\Providers\Syscom;

use App\Providers\AbstractProvider;
use App\Providers\ConnectionResult;
use App\Providers\FieldDefinition;
use App\Providers\Product;
use App\Providers\ProviderException;
use App\Providers\SearchResult;
use GuzzleHttp\Promise\PromiseInterface;

/** Conector Syscom (API REST v1). Documentación: https://developers.syscom.mx/docs */
final class SyscomProvider extends AbstractProvider
{
    /** Orden en que se busca el costo si el precio elegido no viene en la respuesta */
    private const PRICE_FALLBACK = ['precio_descuento', 'precio_especial', 'precio_1', 'precio_lista'];

    public static function code(): string
    {
        return 'syscom';
    }

    public static function name(): string
    {
        return 'Syscom';
    }

    public static function description(): string
    {
        return 'Mayorista de seguridad electrónica, redes y telecomunicaciones. Precios y existencias en tiempo real según tu cuenta de distribuidor.';
    }

    public static function credentialFields(): array
    {
        return [
            new FieldDefinition('client_id', 'Client ID', 'secret', 'Panel de desarrolladores de Syscom → Mis credenciales.', true),
            new FieldDefinition('client_secret', 'Client Secret', 'secret', 'Se muestra una sola vez al generarlo en Syscom.', true),
        ];
    }

    public static function settingFields(): array
    {
        return [
            new FieldDefinition('cost_price', 'Precio que se toma como costo', 'select',
                'En syscom.mx: tu precio es el número grande; el especial aparece en verde y el de lista tachado. Si el precio elegido no viene en un producto se usa el siguiente disponible.', false, [
                    'precio_descuento' => 'Tu precio de distribuidor (el grande en syscom.mx) — recomendado',
                    'precio_especial' => 'Precio especial (el verde en syscom.mx)',
                    'precio_lista' => 'Precio de lista (el tachado en syscom.mx)',
                    'precio_1' => 'Precio 1',
                ], 'precio_descuento'),
            new FieldDefinition('financial_discount', 'Aplicar descuento financiero', 'bool',
                'Descuento que Syscom aplica a tu precio en su sitio (pago de contado). Actívalo para que el costo coincida con el precio grande de syscom.mx.', false, [], true),
            new FieldDefinition('currency', 'Moneda en que se consultan los precios', 'select',
                'Syscom cotiza en USD; el tipo de cambio se aplica en la cotización.', false,
                ['usd' => 'Dólares (USD)', 'mxn' => 'Pesos (MXN, convertidos por Syscom)'], 'usd'),
            new FieldDefinition('exchange_type', 'Tipo de cambio de Syscom', 'select', '', false,
                ['normal' => 'Normal', 'preferencial' => 'Preferencial'], 'normal'),
            new FieldDefinition('include_open_box', 'Contar existencias de caja abierta', 'bool',
                'Suma a la existencia los productos de caja abierta.', false, [], false),
            new FieldDefinition('ai_search', 'Usar búsqueda inteligente (IA)', 'bool',
                'Entiende frases como "cámara domo exterior visión nocturna". Más lenta y con límite de peticiones.', false, [], false),
        ];
    }

    // ---------- Autenticación ----------

    protected function requestToken(): ?array
    {
        $id = $this->credential('client_id');
        $secret = $this->credential('client_secret');
        if ($id === '' || $secret === '') {
            throw new ProviderException('Faltan el Client ID o el Client Secret de Syscom.');
        }
        try {
            $res = $this->http->request('POST', self::config()['token_url'], [
                'form_params' => ['grant_type' => 'client_credentials', 'client_id' => $id, 'client_secret' => $secret],
            ]);
        } catch (\Throwable $e) {
            throw $this->translate($e);
        }
        if ($res->getStatusCode() >= 400) {
            throw $this->translate($res);
        }
        $data = $this->decode($res);
        if (empty($data['access_token'])) {
            throw new ProviderException('Syscom no devolvió un token de acceso.');
        }
        return ['token' => (string) $data['access_token'], 'expires_in' => (int) ($data['expires_in'] ?? 3600)];
    }

    public function testConnection(): ConnectionResult
    {
        try {
            $this->accessToken(true);
            $categories = $this->getCategories();
            $details = ['Categorías disponibles' => (string) count($categories)];
            try {
                if ($rate = $this->getExchangeRate()) {
                    $details['Tipo de cambio (' . $this->setting('exchange_type') . ')'] = '$' . number_format($rate, 2) . ' MXN';
                }
            } catch (ProviderException) {
                // El tipo de cambio no es indispensable para validar la conexión
            }
            $t = $this->tokens->get($this->account->id);
            if ($t) {
                $details['Token vigente hasta'] = date('d/m/Y', $t['expires_at']);
            }
            return new ConnectionResult(true, 'Conexión correcta con Syscom.', $details);
        } catch (ProviderException $e) {
            return new ConnectionResult(false, $e->getMessage());
        }
    }

    // ---------- Catálogo ----------

    public function searchAsync(string $query, array $filters = []): PromiseInterface
    {
        $cfg = self::config();
        $query = trim($query);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $common = ['pagina' => $page] + $this->priceParams();
        if (!empty($filters['in_stock'])) {
            $common['stock'] = 'true';
        }

        if ($this->setting('ai_search') && mb_strlen($query) >= 3) {
            $url = $cfg['base_url'] . $cfg['endpoints']['search_ai'];
            $params = $common + ['q' => mb_substr($query, 0, 256), 'limit' => min(60, (int) ($filters['limit'] ?? 60))];
        } else {
            $url = $cfg['base_url'] . $cfg['endpoints']['search'];
            // Syscom admite máx. 120 caracteres / 10 palabras
            $words = array_slice(preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 10);
            $params = $common + [
                'busqueda' => mb_substr(implode(' ', $words), 0, 120),
                'limit' => max(10, min(1000, (int) ($filters['limit'] ?? $cfg['page_size']))),
                'inventarios' => 'true',
            ];
            if (!empty($filters['brand'])) {
                $params['marca'] = preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $filters['brand']));
            }
            if (!empty($filters['category']) && preg_match('/^[0-9,]+$/', (string) $filters['category'])) {
                $params['categoria'] = $filters['category'];
            }
        }

        return $this->getJsonAsync($url, $params)->then(function ($data) use ($page) {
            $items = is_array($data['productos'] ?? null) ? $data['productos'] : [];
            return new SearchResult(
                array_map(fn (array $p) => $this->normalize($p), $items),
                (int) ($data['cantidad'] ?? count($items)),
                (int) ($data['pagina'] ?? $page),
                max(1, (int) ($data['paginas'] ?? 1)),
            );
        });
    }

    public function getProduct(string $id): ?Product
    {
        return $this->getPrices([$id])[$id] ?? null;
    }

    public function getPrices(array $ids): array
    {
        $cfg = self::config();
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids), fn ($i) => ctype_digit($i))));
        $out = [];
        foreach (array_chunk($ids, $cfg['max_ids_per_request']) as $chunk) {
            $url = $cfg['base_url'] . str_replace('{id}', implode(',', $chunk), $cfg['endpoints']['product']);
            try {
                $data = $this->getJson($url, $this->priceParams() + ['inventarios' => 'true']);
            } catch (ProviderException $e) {
                if ($e->httpStatus === 404) {
                    continue; // product_not_available
                }
                throw $e;
            }
            // Un ID → objeto; varios → arreglo
            $list = isset($data['producto_id']) ? [$data] : (is_array($data) ? $data : []);
            foreach ($list as $p) {
                if (is_array($p) && isset($p['producto_id'])) {
                    $product = $this->normalize($p);
                    $out[$product->providerId] = $product;
                }
            }
        }
        return $out;
    }

    /** Parámetros que determinan el precio devuelto por Syscom */
    private function priceParams(): array
    {
        $params = ['moneda' => $this->setting('currency')];
        if ($this->setting('financial_discount')) {
            $params['financiero'] = 'true';
        }
        return $params;
    }

    public function getExchangeRate(): ?float
    {
        $cfg = self::config();
        $data = $this->http->request('GET', $cfg['base_url'] . $cfg['endpoints']['exchange_rate']);
        if ($data->getStatusCode() >= 400) {
            throw $this->translate($data);
        }
        $json = $this->decode($data);
        $rate = $json[$this->setting('exchange_type')] ?? $json['normal'] ?? null;
        return is_numeric($rate) && (float) $rate > 0 ? (float) $rate : null;
    }

    public function getCategories(): array
    {
        $cfg = self::config();
        $data = $this->getJson($cfg['base_url'] . $cfg['endpoints']['categories']);
        return array_map(fn ($c) => [
            'id' => (string) $c['id'], 'name' => (string) $c['nombre'], 'level' => (int) ($c['nivel'] ?? 1),
        ], is_array($data) ? $data : []);
    }

    public function getBrands(): array
    {
        $cfg = self::config();
        $data = $this->getJson($cfg['base_url'] . $cfg['endpoints']['brands']);
        return array_map(fn ($b) => ['id' => (string) $b['id'], 'name' => (string) $b['nombre']], is_array($data) ? $data : []);
    }

    // ---------- Normalización ----------

    /** Convierte un producto de la API de Syscom al modelo común */
    public function normalize(array $p): Product
    {
        $cfg = self::config();

        $images = [];
        foreach ((array) ($p['imagenes'] ?? []) as $img) {
            $url = is_array($img) ? ($img['imagen'] ?? null) : $img;
            if (is_string($url) && $url !== '') {
                $images[] = $url;
            }
        }
        if (!empty($p['img_portada']) && !in_array($p['img_portada'], $images, true)) {
            array_unshift($images, (string) $p['img_portada']);
        }

        // Categoría más específica (mayor nivel)
        $category = null;
        $level = -1;
        foreach ((array) ($p['categorias'] ?? []) as $c) {
            if (is_array($c) && (int) ($c['nivel'] ?? 0) > $level) {
                $level = (int) ($c['nivel'] ?? 0);
                $category = (string) ($c['nombre'] ?? '');
            }
        }

        // Existencias por almacén (solo condición "nuevo")
        $warehouses = [];
        $detail = $p['existencia']['detalle'] ?? [];
        if (is_array($detail) && is_array($detail['nuevo'] ?? null)) {
            foreach ($detail['nuevo'] as $wh => $qty) {
                if ((int) $qty > 0) {
                    $warehouses[(string) $wh] = (int) $qty;
                }
            }
        }
        $stock = (int) ($p['total_existencia'] ?? $p['existencia']['nuevo'] ?? 0);
        if ($this->setting('include_open_box')) {
            $stock += (int) ($p['total_existencias_caja_abierta'] ?? 0);
        }

        $description = isset($p['descripcion']) && is_string($p['descripcion']) ? $p['descripcion'] : null;
        $unit = is_array($p['unidad_de_medida'] ?? null) ? $p['unidad_de_medida'] : [];

        return new Product(
            provider: self::code(),
            providerId: (string) $p['producto_id'],
            model: trim((string) ($p['modelo'] ?? '')),
            brand: trim((string) ($p['marca'] ?? '')),
            title: trim(html_entity_decode(strip_tags((string) ($p['titulo'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            description: $description,
            category: $category,
            images: $images,
            cost: $this->cost($p['precios'] ?? []),
            currency: strtoupper((string) $this->setting('currency')) === 'MXN' ? 'MXN' : 'USD',
            stock: $stock,
            stockByWarehouse: $warehouses,
            satKey: isset($p['sat_key']) ? (string) $p['sat_key'] : null,
            satUnit: isset($unit['clave_unidad_sat']) ? (string) $unit['clave_unidad_sat'] : null,
            unit: isset($unit['nombre']) ? (string) $unit['nombre'] : null,
            url: !empty($p['link']) ? $cfg['web_url'] . $p['link'] : null,
            features: array_values(array_filter((array) ($p['caracteristicas'] ?? []), 'is_string')),
            warranty: isset($p['garantia']) ? (string) $p['garantia'] : null,
            note: isset($p['nota']) ? (string) $p['nota'] : null,
        );
    }

    /** Costo según el precio configurado; null si no hay distribución del producto */
    private function cost(mixed $prices): ?float
    {
        if (!is_array($prices) || $prices === []) {
            return null;
        }
        $order = array_unique(array_merge([(string) $this->setting('cost_price')], self::PRICE_FALLBACK));
        foreach ($order as $key) {
            if (isset($prices[$key]) && is_numeric($prices[$key]) && (float) $prices[$key] > 0) {
                return round((float) $prices[$key], 4);
            }
        }
        return null;
    }
}
