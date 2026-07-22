<?php

declare(strict_types=1);

namespace Eva\Http\Security;

use RuntimeException;

final class AccessException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus)
    {
        parent::__construct($message);
    }
}
