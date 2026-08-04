<?php

declare(strict_types=1);

namespace Eva\Application\Query;

use RuntimeException;
use Throwable;

final class QueryException extends RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?string $validationCode = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
