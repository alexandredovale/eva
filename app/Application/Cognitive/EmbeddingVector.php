<?php

declare(strict_types=1);

namespace Eva\Application\Cognitive;

final readonly class EmbeddingVector
{
    /** @param list<float> $vector */
    public function __construct(
        public string $evidencePublicId,
        public string $model,
        public array $vector,
        public string $contentHash
    ) {
        if ($this->evidencePublicId === '' || $this->model === '' || $this->vector === []) {
            throw new CognitiveBuildException('O vetor retornado pelo provedor está incompleto.');
        }

        foreach ($this->vector as $value) {
            if (!is_float($value) || !is_finite($value)) {
                throw new CognitiveBuildException('O provedor retornou um vetor inválido.');
            }
        }
    }

    public function dimensions(): int
    {
        return count($this->vector);
    }
}