<?php
declare(strict_types=1);

namespace App\Providers;

final class ConnectionResult
{
    /** @param array<string,string> $details datos extra para mostrar (ej. tipo de cambio) */
    public function __construct(
        public readonly bool $ok,
        public readonly string $message,
        public readonly array $details = [],
    ) {
    }
}
