<?php
declare(strict_types=1);

namespace App\Providers\Http;

/** Almacén en memoria (pruebas) */
final class MemoryTokenStore implements TokenStore
{
    public array $tokens = [];

    public function get(int $accountId): ?array
    {
        return $this->tokens[$accountId] ?? null;
    }

    public function put(int $accountId, string $token, int $expiresAt): void
    {
        $this->tokens[$accountId] = ['token' => $token, 'expires_at' => $expiresAt];
    }

    public function forget(int $accountId): void
    {
        unset($this->tokens[$accountId]);
    }
}
