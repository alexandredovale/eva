<?php

declare(strict_types=1);

namespace Eva\Http\Product;

use RuntimeException;
use Throwable;

final class ProductHttpException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
