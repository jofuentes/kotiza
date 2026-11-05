<?php
declare(strict_types=1);

namespace App\Domain\Quotes;

/** Otra persona guardó la cotización después de que se abrió en este editor */
final class ConflictException extends \RuntimeException
{
}
