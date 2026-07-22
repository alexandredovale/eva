<?php

declare(strict_types=1);

namespace Eva\Application\Cognitive;

final readonly class SummaryResult
{
    public function __construct(
        public string $summary,
        public string $model,
        public int $inputTokens = 0,
        public int $outputTokens = 0
    ) {
        if (trim($this->summary) === '' || trim($this->model) === '') {
            throw new CognitiveBuildException('O resumo retornado pelo provedor está incompleto.');
        }

        if ($this->inputTokens < 0 || $this->outputTokens < 0) {
            throw new CognitiveBuildException('O uso de tokens do resumo é inválido.');
        }
    }
}