<?php

declare(strict_types=1);

namespace Eva\Infrastructure\Ai;

interface JsonHttpClientInterface
{
    /**
     * @param list<string> $headers
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function post(string $url, array $headers, array $payload, int $timeoutSeconds): array;
}