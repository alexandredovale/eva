<?php

declare(strict_types=1);

namespace Eva\Application\Cognitive;

interface EmbeddingProviderInterface
{
    public function model(): string;

    /** @param list<StructuredEmbeddingUnit> $units */
    public function embed(array $units): EmbeddingBatchResult;
}