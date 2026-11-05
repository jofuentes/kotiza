<?php
declare(strict_types=1);

namespace App\Core;

final class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message = '', public readonly ?string $title = null)
    {
        parent::__construct($message, $status);
    }
}
