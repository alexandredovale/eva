<?php

declare(strict_types=1);

namespace Eva\Http\Upload;

use RuntimeException;

final class UploadException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 422)
    {
        parent::__construct($message);
    }
}

