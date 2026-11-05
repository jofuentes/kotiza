<?php
declare(strict_types=1);

namespace App\Providers\Http;

/** Registro de llamadas HTTP a proveedores (para depurar desde la UI) */
interface CallLogger
{
    public function log(int $accountId, string $method, string $endpoint, ?int $status, int $ms, ?string $error): void;
}
