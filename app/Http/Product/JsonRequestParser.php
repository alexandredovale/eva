<?php

declare(strict_types=1);

namespace Eva\Http\Product;

use JsonException;

final class JsonRequestParser
{
    /** @return array<string, mixed> */
    public function parse(string $body, int $maxBytes = 65_536): array
    {
        if ($body === '' || strlen($body) > $maxBytes) {
            throw new ProductHttpException('O corpo JSON está vazio ou excede o limite permitido.', 400);
        }

        try {
            $decoded = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ProductHttpException('O corpo JSON é inválido.', 400, $exception);
        }

        if (!is_array($decoded)) {
            throw new ProductHttpException('O corpo JSON deve ser um objeto.', 400);
        }

        return $decoded;
    }
}
