<?php
declare(strict_types=1);

namespace App\Domain\Quotes;

/** Errores de validación por campo (ej. "items.3.qty") para devolver al editor */
final class ValidationException extends \RuntimeException
{
    /** @param array<string,string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Hay datos inválidos en la cotización.');
    }
}
