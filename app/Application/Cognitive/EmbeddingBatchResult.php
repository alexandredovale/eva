<?php

declare(strict_types=1);

namespace Eva\Application\Cognitive;

final readonly class EmbeddingBatchResult
{
    /** @param list<EmbeddingVector> $vectors */
    public function __construct(
        public array $vectors,
        public int $inputTokens = 0
    ) {
        if ($this->inputTokens < 0) {
            throw new CognitiveBuildException('O uso de tokens do embedding é inválido.');
        }
    }
}