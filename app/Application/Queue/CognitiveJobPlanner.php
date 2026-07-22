<?php

declare(strict_types=1);

namespace Eva\Application\Queue;

final readonly class CognitiveJobPlanner
{
    /** @param array<string, mixed> $aiConfig */
    public function __construct(
        private ProcessingQueueService $queue,
        private array $aiConfig
    ) {
    }

    /** @return list<ProcessingJob> */
    public function enqueueDocument(int $documentId): array
    {
        $providers = is_array($this->aiConfig['providers'] ?? null) ? $this->aiConfig['providers'] : [];
        $embeddings = is_array($providers['embeddings'] ?? null) ? $providers['embeddings'] : [];
        $summaries = is_array($providers['summaries'] ?? null) ? $providers['summaries'] : [];
        $embeddingVersion = (string) ($embeddings['provider'] ?? '') . ':' . (string) ($embeddings['model'] ?? '');
        $summaryVersion = (string) ($summaries['provider'] ?? '') . ':' . (string) ($summaries['model'] ?? '');

        return [
            $this->queue->enqueue($documentId, 'summaries', 'summary:' . $summaryVersion),
            $this->queue->enqueue($documentId, 'embeddings', 'embedding:' . $embeddingVersion),
        ];
    }
}
