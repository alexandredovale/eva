<?php

declare(strict_types=1);

namespace Eva\Application\Cognitive;

final readonly class HierarchicalSummaryResult
{
    public function __construct(
        public int $eligibleNodes,
        public int $createdSummaries,
        public int $reusedSummaries,
        public int $inputTokens,
        public int $outputTokens,
        public bool $stoppedByLimit = false
    ) {
    }

    /** @return array<string, int|bool> */
    public function toArray(): array
    {
        return [
            'eligible_nodes' => $this->eligibleNodes,
            'created_summaries' => $this->createdSummaries,
            'reused_summaries' => $this->reusedSummaries,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'stopped_by_limit' => $this->stoppedByLimit,
        ];
    }
}