<?php
declare(strict_types=1);

namespace App\Providers;

use App\Providers\Http\TokenStore;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Base de los conectores: credenciales/ajustes, token Bearer con caché y renovación,
 * peticiones JSON asíncronas y traducción de errores HTTP a mensajes en español.
 */
abstract class AbstractProvider implements ProviderInterface
{
    public function __construct(
        protected ProviderAccount $account,
        protected ClientInterface $http,
        protected TokenStore $tokens,
        /** Catálogo local (solo lo usan proveedores sincronizables) */
        protected ?\App\Providers\Catalog\LocalCatalog $catalog = null,
    ) {
    }

    /** Configuración del conector desde config/providers/{code}.php */
    public static function config(): array
    {
        static $cache = [];
        $code = static::code();
        if (!isset($cache[$code])) {
            $file = dirname(__DIR__, 2) . "/config/providers/{$code}.php";
            $cache[$code] = is_file($file) ? require $file : [];
        }
        return $cache[$code];
    }

    public static function settingFields(): array
    {
        return [];
    }

    protected function credential(string $key): string
    {
        return trim((string) ($this->account->credentials[$key] ?? ''));
    }

    protected function setting(string $key): mixed
    {
        if (array_key_exists($key, $this->account->settings)) {
            return $this->account->settings[$key];
        }
        foreach (static::settingFields() as $f) {
            if ($f->key === $key) {
                return $f->default;
            }
        }
        return null;
    }

    // ---------- Token ----------

    /**
     * Obtiene un token nuevo del proveedor.
     * @return array{token:string, expires_in:int} o null si el proveedor no usa token
     */
    protected function requestToken(): ?array
    {
        return null;
    }

    /** Token vigente (de caché o nuevo). Se renueva 1 hora antes de expirar. */
    protected function accessToken(bool $forceRefresh = false): ?string
    {
        if (!$forceRefresh && ($t = $this->tokens->get($this->account->id)) && $t['expires_at'] > time() + 3600) {
            return $t['token'];
        }
        $new = $this->requestToken();
        if ($new === null) {
            return null;
        }
        $this->tokens->put($this->account->id, $new['token'], time() + max(60, $new['expires_in']));
        return $new['token'];
    }

    // ---------- Peticiones ----------

    /** GET JSON asíncrono con Bearer; ante 401 renueva el token y reintenta una vez. */
    /** @param array|string $query arreglo o query string ya armado (para parámetros tipo ids[]=1&ids[]=2) */
    protected function getJsonAsync(string $url, array|string $query = []): PromiseInterface
    {
        $send = function (bool $refresh) use ($url, $query): PromiseInterface {
            try {
                $token = $this->accessToken($refresh);
            } catch (\Throwable $e) {
                return Create::rejectionFor($e);
            }
            $headers = $token !== null ? ['Authorization' => 'Bearer ' . $token] : [];
            return $this->http->requestAsync('GET', $url, ['query' => $query, 'headers' => $headers]);
        };

        return $send(false)
            ->then(function (ResponseInterface $res) use ($send) {
                if ($res->getStatusCode() === 401) {
                    $this->tokens->forget($this->account->id);
                    return $send(true);
                }
                return $res;
            })
            ->then(
                function (ResponseInterface $res) {
                    if ($res->getStatusCode() >= 400) {
                        throw $this->translate($res);
                    }
                    return $this->decode($res);
                },
                fn ($e) => Create::rejectionFor($this->translate($e))
            );
    }

    protected function getJson(string $url, array|string $query = []): mixed
    {
        return $this->getJsonAsync($url, $query)->wait();
    }

    protected function decode(ResponseInterface $res): mixed
    {
        $body = (string) $res->getBody();
        if ($body === '') {
            return null;
        }
        try {
            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ProviderException(static::name() . ' respondió con un formato inesperado.', $res->getStatusCode());
        }
    }

    /** Convierte cualquier error en ProviderException con mensaje claro */
    protected function translate(mixed $e): ProviderException
    {
        if ($e instanceof ProviderException) {
            return $e;
        }
        $name = static::name();
        if ($e instanceof ConnectException) {
            return new ProviderException("No se pudo conectar con {$name}. Revisa la conexión a internet o intenta más tarde.", null, $e);
        }
        $res = $e instanceof ResponseInterface ? $e : ($e instanceof RequestException ? $e->getResponse() : null);
        if ($res !== null) {
            $status = $res->getStatusCode();
            $remote = $this->remoteMessage($res);
            $msg = match (true) {
                $status === 400 => "{$name} rechazó la petición" . ($remote ? ": {$remote}" : '.'),
                $status === 401 => "{$name} rechazó las credenciales. Verifica tus datos de acceso.",
                $status === 403 => "Tu cuenta de {$name} no tiene acceso a este recurso" . ($remote ? " ({$remote})" : '') . '.',
                $status === 404 => "{$name} no encontró el recurso solicitado.",
                $status === 429 => "Se alcanzó el límite de peticiones de {$name}"
                    . (($s = \App\Providers\Http\HttpClientFactory::retryAfter($res)) ? ". Intenta de nuevo en {$s} s." : '. Intenta en unos momentos.'),
                $status >= 500 => "{$name} tiene problemas en este momento (error {$status}). Intenta más tarde.",
                default => "{$name} respondió con error {$status}.",
            };
            return new ProviderException($msg, $status, $e instanceof \Throwable ? $e : null);
        }
        $text = $e instanceof \Throwable ? $e->getMessage() : 'error desconocido';
        return new ProviderException("Error al consultar {$name}: {$text}", null, $e instanceof \Throwable ? $e : null);
    }

    protected function remoteMessage(ResponseInterface $res): ?string
    {
        $data = json_decode((string) $res->getBody(), true);
        $msg = is_array($data) ? ($data['error'] ?? $data['message'] ?? null) : null;
        return is_string($msg) ? mb_substr($msg, 0, 200) : null;
    }

    // ---------- Valores por defecto de la interfaz ----------

    public function search(string $query, array $filters = []): SearchResult
    {
        return $this->searchAsync($query, $filters)->wait();
    }

    public function getPrices(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            if ($p = $this->getProduct((string) $id)) {
                $out[$p->providerId] = $p;
            }
        }
        return $out;
    }

    public function getStock(string $id): ?array
    {
        $p = $this->getProduct($id);
        return $p ? ['total' => $p->stock, 'warehouses' => $p->stockByWarehouse] : null;
    }

    public function getExchangeRate(): ?float
    {
        return null;
    }

    public function getCategories(): array
    {
        return [];
    }

    public function getBrands(): array
    {
        return [];
    }
}
