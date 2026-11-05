<?php
declare(strict_types=1);

namespace App\Providers\Http;

/** Guarda los tokens de acceso de cada cuenta de proveedor */
interface TokenStore
{
    /** @return ?array{token:string, expires_at:int} */
    public function get(int $accountId): ?array;

    public function put(int $accountId, string $token, int $expiresAt): void;

    public function forget(int $accountId): void;
}
