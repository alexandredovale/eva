<?php

declare(strict_types=1);

namespace Eva\Application\Queue;

use Eva\Application\Cognitive\EvidenceEmbeddingService;
use Eva\Application\Cognitive\HierarchicalSummaryService;
use Eva\Infrastructure\Ai\CognitiveProviderFactory;
use Eva\Infrastructure\Audit\AuditRecorder;
use Eva\Infrastructure\Logging\FileLogger;
use Eva\Infrastructure\Logging\SafeFailureDiagnostics;
use PDO;
use Throwable;

final readonly class CognitiveQueueWorker
{
    /** @param array<string, mixed> $aiConfig */
    public function __construct(
        private PDO $database,
        private ProcessingQueueService $queue,
        private CognitiveProviderFactory $providers,
        private array $aiConfig,
        private AuditRecorder $audit,
        private FileLogger $logger
    ) {
    }

    /** @return array<string, mixed> */
    public function runOnce(string $workerId): array
    {
        $job = $this->queue->claimNext($workerId);

        if ($job === null) {
            return ['status' => 'idle'];
        }

        try {
            $result = $this->execute($job);
            $stoppedByLimit = ($result['stopped_by_limit'] ?? false) === true;
            $updated = $stoppedByLimit
                ? $this->queue->release($job->id, $result)
                : $this->queue->complete($job->id, $result);
            $event = $stoppedByLimit ? 'processing_job_progressed' : 'processing_job_completed';
            $this->audit->record($event, 'processing_job', $job->publicId, null, null, [
                'document_id' => $job->documentId,
                'stage' => $job->stage,
                'status' => $updated->status,
            ]);
            $this->logger->info($event, [
                'job_id' => $job->publicId,
                'document_id' => $job->documentId,
                'stage' => $job->stage,
                'status' => $updated->status,
            ]);

            return ['status' => $updated->status, 'job' => $updated->toArray(), 'result' => $result];
        } catch (Throwable $exception) {
            $failed = $this->queue->fail($job->id, $exception->getMessage());
            $this->audit->record('processing_job_failed', 'processing_job', $job->publicId, null, null, [
                'document_id' => $job->documentId,
                'stage' => $job->stage,
                'failure_count' => $failed->failureCount,
                'exception' => $exception::class,
            ]);
            $this->logger->error('processing_job_failed', SafeFailureDiagnostics::context($exception, [
                'job_id' => $job->publicId,
                'document_id' => $job->documentId,
                'stage' => $job->stage,
            ]));

            return ['status' => 'failed', 'job' => $failed->toArray()];
        }
    }

    /** @return array<string, int|bool> */
    private function execute(ProcessingJob $job): array
    {
        if ($job->stage === 'summaries') {
            return (new HierarchicalSummaryService($this->database, $this->providers->summaries()))
                ->buildForDocument(
                    $job->documentId,
                    (int) ($this->aiConfig['max_new_summaries_per_run'] ?? 5)
                )
                ->toArray();
        }

        if ($job->stage === 'embeddings') {
            $embeddingConfig = $this->aiConfig['providers']['embeddings'] ?? [];

            return (new EvidenceEmbeddingService(
                database: $this->database,
                provider: $this->providers->embeddings(),
                maxUnitsPerBatch: (int) ($embeddingConfig['max_units_per_request'] ?? 64),
                maxInputTokens: (int) ($embeddingConfig['max_input_tokens'] ?? 8_192)
            ))
                ->buildForDocument($job->documentId)
                ->toArray();
        }

        throw new QueueException('A etapa cognitiva informada não é suportada pelo Evidence Algorithm.');
    }
}
