<?php

declare(strict_types=1);

namespace Eva\Http\Product;

final readonly class HttpResponse
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public function __construct(
        public int $status,
        public array $payload,
        public array $headers = []
    ) {
    }
}
