<?php
declare(strict_types=1);

namespace App\Providers\Http;

/** Logger en memoria (pruebas) */
final class MemoryCallLogger implements CallLogger
{
    public array $calls = [];

    public function log(int $accountId, string $method, string $endpoint, ?int $status, int $ms, ?string $error): void
    {
        $this->calls[] = compact('accountId', 'method', 'endpoint', 'status', 'ms', 'error');
    }
}
