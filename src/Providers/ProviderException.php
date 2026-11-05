<?php
declare(strict_types=1);

namespace App\Providers;

/** Error de un proveedor con mensaje apto para el usuario */
final class ProviderException extends \RuntimeException
{
    public function __construct(string $message, public readonly ?int $httpStatus = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
