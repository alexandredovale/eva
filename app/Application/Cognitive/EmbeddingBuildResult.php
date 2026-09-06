<?php

declare(strict_types=1);

namespace Eva\Application\Cognitive;

final readonly class EmbeddingBuildResult
{
    public function __construct(
        public int $eligibleUnits,
        public int $createdEmbeddings,
        public int $reusedEmbeddings,
        public int $inputTokens
    ) {
    }

    /** @return array<string, int> */
    public function toArray(): array
    {
        return [
            'eligible_units' => $this->eligibleUnits,
            'created_embeddings' => $this->createdEmbeddings,
            'reused_embeddings' => $this->reusedEmbeddings,
            'input_tokens' => $this->inputTokens,
        ];
    }
}
