<?php
declare(strict_types=1);

namespace App\Providers\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\Create;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Cliente HTTP por cuenta de proveedor con:
 *  - reintentos con backoff exponencial ante errores de red, 429 y 5xx (respeta Retry-After hasta 5 s)
 *  - registro de cada llamada (endpoint, código, tiempo) en el CallLogger
 */
final class HttpClientFactory
{
    public const MAX_RETRIES = 3;
    public const MAX_RETRY_AFTER = 5;

    /** @param callable|null $handler handler base (MockHandler en pruebas) */
    public static function make(int $accountId, CallLogger $logger, ?callable $handler = null, array $options = []): Client
    {
        $stack = HandlerStack::create($handler);
        // En HandlerStack lo primero que se agrega queda por fuera: retry → log → handler,
        // así cada intento queda registrado por separado
        $stack->push(Middleware::retry(self::decider(), self::delay()), 'retry');
        $stack->push(self::logMiddleware($accountId, $logger), 'log');

        return new Client($options + [
            'handler' => $stack,
            'connect_timeout' => 5,
            'timeout' => 20,
            // Los códigos 4xx/5xx los interpreta el conector (necesita leer Retry-After y el cuerpo)
            'http_errors' => false,
            'headers' => ['Accept' => 'application/json', 'User-Agent' => 'Kotiza/1.0'],
        ]);
    }

    private static function decider(): callable
    {
        return function (int $retries, RequestInterface $req, ?ResponseInterface $res = null, ?\Throwable $e = null): bool {
            if ($retries >= self::MAX_RETRIES) {
                return false;
            }
            if ($e instanceof ConnectException) {
                return true;
            }
            $status = $res?->getStatusCode() ?? ($e instanceof RequestException ? $e->getResponse()?->getStatusCode() : null);
            if ($status === 429) {
                $after = self::retryAfter($res ?? ($e instanceof RequestException ? $e->getResponse() : null));
                return $after === null || $after <= self::MAX_RETRY_AFTER;
            }
            return in_array($status, [500, 502, 503, 504], true);
        };
    }

    private static function delay(): callable
    {
        return function (int $retries, ?ResponseInterface $res = null): int {
            $after = self::retryAfter($res);
            if ($after !== null) {
                return $after * 1000;
            }
            return (int) (250 * (2 ** ($retries - 1)));
        };
    }

    public static function retryAfter(?ResponseInterface $res): ?int
    {
        $h = $res?->getHeaderLine('Retry-After');
        return $h !== null && $h !== '' && ctype_digit($h) ? (int) $h : null;
    }

    private static function logMiddleware(int $accountId, CallLogger $logger): callable
    {
        return function (callable $next) use ($accountId, $logger) {
            return function (RequestInterface $request, array $options) use ($next, $accountId, $logger) {
                $start = microtime(true);
                $endpoint = self::safeEndpoint($request);
                return $next($request, $options)->then(
                    function (ResponseInterface $res) use ($request, $start, $endpoint, $accountId, $logger) {
                        $logger->log($accountId, $request->getMethod(), $endpoint, $res->getStatusCode(), self::ms($start), null);
                        return $res;
                    },
                    function ($reason) use ($request, $start, $endpoint, $accountId, $logger) {
                        $status = $reason instanceof RequestException ? $reason->getResponse()?->getStatusCode() : null;
                        $msg = $reason instanceof \Throwable ? $reason->getMessage() : 'Error desconocido';
                        $logger->log($accountId, $request->getMethod(), $endpoint, $status, self::ms($start), mb_substr($msg, 0, 300));
                        return Create::rejectionFor($reason);
                    }
                );
            };
        };
    }

    /** Ruta y query sin datos sensibles */
    private static function safeEndpoint(RequestInterface $request): string
    {
        $uri = $request->getUri();
        parse_str($uri->getQuery(), $q);
        foreach (['client_secret', 'token', 'access_token', 'api_key', 'password'] as $k) {
            if (isset($q[$k])) {
                $q[$k] = '***';
            }
        }
        $query = http_build_query($q);
        return $uri->getHost() . $uri->getPath() . ($query !== '' ? '?' . urldecode($query) : '');
    }

    private static function ms(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}
