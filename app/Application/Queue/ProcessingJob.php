<?php

declare(strict_types=1);

namespace Eva\Application\Queue;

final readonly class ProcessingJob
{
    public function __construct(
        public int $id,
        public string $publicId,
        public int $documentId,
        public string $stage,
        public string $versionKey,
        public string $status,
        public int $runCount,
        public int $failureCount,
        public int $maxFailures
    ) {
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'id' => $this->publicId,
            'document_id' => $this->documentId,
            'stage' => $this->stage,
            'status' => $this->status,
            'run_count' => $this->runCount,
            'failure_count' => $this->failureCount,
            'max_failures' => $this->maxFailures,
        ];
    }
}
