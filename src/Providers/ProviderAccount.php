<?php
declare(strict_types=1);

namespace App\Providers;

/** Configuración de un proveedor para una empresa (credenciales ya descifradas). */
final class ProviderAccount
{
    public function __construct(
        public readonly int $id,
        public readonly int $companyId,
        public readonly string $providerCode,
        public readonly array $credentials = [],
        public readonly array $settings = [],
        public readonly bool $isActive = true,
        public readonly int $priority = 10,
        public readonly ?float $defaultMargin = null,
    ) {
    }
}
