<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** Parámetros de ruta (ej. {id}) */
    public array $params = [];

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private array $query,
        private array $body,
        private array $files,
        private array $server,
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = '/' . trim(rawurldecode($path), '/');

        $body = $_POST;
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $json = json_decode((string) file_get_contents('php://input'), true);
            $body = is_array($json) ? $json : [];
        }
        return new self($method, $path, $_GET, $body, $_FILES, $_SERVER);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /** Valor de texto recortado */
    public function str(string $key, string $default = ''): string
    {
        $v = $this->input($key, $default);
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    /** Campos de formulario como texto recortado; los ausentes o no escalares llegan como '' */
    public function only(array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $v = $this->input($k);
            $out[$k] = is_scalar($v) ? trim((string) $v) : '';
        }
        return $out;
    }

    public function file(string $key): ?array
    {
        $f = $this->files[$key] ?? null;
        return is_array($f) && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? $f : null;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server[$key] ?? null;
    }

    /**
     * IP del visitante. Detrás de un proxy inverso (ej. Nginx Proxy Manager) la conexión llega
     * desde una IP privada: solo en ese caso se confía en X-Real-IP / X-Forwarded-For.
     */
    public function ip(): string
    {
        $remote = (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
        if (!self::isPrivateIp($remote)) {
            return $remote;
        }
        $candidates = array_merge(
            [(string) $this->header('X-Real-IP')],
            array_map('trim', explode(',', (string) $this->header('X-Forwarded-For')))
        );
        foreach ($candidates as $ip) {
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return $remote;
    }

    public static function isPrivateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    public function wantsJson(): bool
    {
        return str_contains((string) $this->header('Accept'), 'application/json')
            || $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    public function isMutating(): bool
    {
        return in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }
}
