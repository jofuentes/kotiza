<?php
declare(strict_types=1);

namespace App\Providers;

/** Campo configurable de un conector (se dibuja automáticamente en la sección Proveedores) */
final class FieldDefinition
{
    /**
     * @param 'text'|'secret'|'select'|'bool'|'number' $type
     * @param array<string,string> $options para select: valor => texto
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type = 'text',
        public readonly string $help = '',
        public readonly bool $required = false,
        public readonly array $options = [],
        public readonly mixed $default = null,
    ) {
    }
}
