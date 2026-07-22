<?php

declare(strict_types=1);

namespace Eva\Application\Queue;

use JsonException;
use PDO;
use Throwable;

final readonly class ProcessingQueueService
{
    public function __construct(private PDO $database, private int $defaultMaxFailures = 3)
    {
        if ($this->defaultMaxFailures < 1) {
            throw new QueueException('O limite de falhas da fila é inválido.');
        }
    }

    public function enqueue(int $documentId, string $stage, string $versionKey): ProcessingJob
    {
        if ($documentId < 1 || !in_array($stage, ['summaries', 'embeddings'], true)
            || trim($versionKey) === '') {
            throw new QueueException('Os dados do trabalho cognitivo são inválidos.');
        }

        $statement = $this->database->prepare("SELECT 1 FROM documents WHERE id = :id AND status = 'ready'");
        $statement->execute(['id' => $documentId]);

        if ($statement->fetchColumn() === false) {
            throw new QueueException('O documento pronto para processamento não foi localizado.');
        }

        $versionKey = mb_substr(trim($versionKey), 0, 255, 'UTF-8');
        $jobKey = hash('sha256', $documentId . '|' . $stage . '|' . $versionKey);
        $temporaryPublicId = 'TMP-' . bin2hex(random_bytes(12));
        $statement = $this->database->prepare(
            "INSERT IGNORE INTO processing_jobs
                (public_id, document_id, stage, version_key, job_key, max_failures)
             VALUES
                (:public_id, :document_id, :stage, :version_key, :job_key, :max_failures)"
        );
        $statement->execute([
            'public_id' => $temporaryPublicId,
            'document_id' => $documentId,
            'stage' => $stage,
            'version_key' => $versionKey,
            'job_key' => $jobKey,
            'max_failures' => $this->defaultMaxFailures,
        ]);

        if ($statement->rowCount() === 1) {
            $jobId = (int) $this->database->lastInsertId();
            $publicId = sprintf('EVA-J%06d', $jobId);
            $update = $this->database->prepare('UPDATE processing_jobs SET public_id = :public_id WHERE id = :id');
            $update->execute(['public_id' => $publicId, 'id' => $jobId]);

            return $this->getById($jobId);
        }

        $statement = $this->database->prepare('SELECT id FROM processing_jobs WHERE job_key = :job_key LIMIT 1');
        $statement->execute(['job_key' => $jobKey]);
        $jobId = (int) $statement->fetchColumn();

        return $this->getById($jobId);
    }

    public function claimNext(string $workerId): ?ProcessingJob
    {
        $workerId = mb_substr(trim($workerId), 0, 120, 'UTF-8');

        if ($workerId === '') {
            throw new QueueException('O identificador do worker é obrigatório.');
        }

        try {
            $this->database->beginTransaction();
            $statement = $this->database->query(
                "SELECT candidate.id
                   FROM processing_jobs candidate
                  WHERE candidate.status = 'queued'
                    AND candidate.available_at <= NOW()
                    AND (
                        candidate.stage <> 'embeddings'
                        OR (
                            SELECT summary_job.status
                              FROM processing_jobs summary_job
                             WHERE summary_job.document_id = candidate.document_id
                               AND summary_job.stage = 'summaries'
                             ORDER BY summary_job.id DESC
                             LIMIT 1
                        ) = 'completed'
                    )
                  ORDER BY candidate.id ASC
                  LIMIT 1
                  FOR UPDATE"
            );
            $jobId = $statement->fetchColumn();

            if ($jobId === false) {
                $this->database->commit();
                return null;
            }

            $update = $this->database->prepare(
                "UPDATE processing_jobs
                    SET status = 'running', run_count = run_count + 1, locked_by = :worker_id,
                        started_at = NOW(), finished_at = NULL, last_error = NULL
                  WHERE id = :id AND status = 'queued'"
            );
            $update->execute(['worker_id' => $workerId, 'id' => $jobId]);

            if ($update->rowCount() !== 1) {
                throw new QueueException('O trabalho não pôde ser bloqueado pelo worker.');
            }

            $this->database->commit();

            return $this->getById((int) $jobId);
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception instanceof QueueException
                ? $exception
                : new QueueException('Não foi possível obter o próximo trabalho.', 0, $exception);
        }
    }

    /** @param array<string, mixed> $result */
    public function complete(int $jobId, array $result): ProcessingJob
    {
        return $this->finish($jobId, 'completed', $result, null);
    }

    /** @param array<string, mixed> $result */
    public function release(int $jobId, array $result): ProcessingJob
    {
        return $this->finish($jobId, 'queued', $result, null);
    }

    public function fail(int $jobId, string $error): ProcessingJob
    {
        $job = $this->getById($jobId);
        $failureCount = $job->failureCount + 1;
        $statement = $this->database->prepare(
            "UPDATE processing_jobs
                SET status = 'failed', failure_count = :failure_count, locked_by = NULL,
                    last_error = :last_error, finished_at = NOW()
              WHERE id = :id AND status = 'running'"
        );
        $statement->execute([
            'failure_count' => $failureCount,
            'last_error' => mb_substr($error, 0, 500, 'UTF-8'),
            'id' => $jobId,
        ]);

        return $this->getById($jobId);
    }

    public function retry(string $publicId): ProcessingJob
    {
        $job = $this->getByPublicId($publicId);

        if ($job->status !== 'failed') {
            throw new QueueException('Somente trabalhos com falha podem ser retomados.');
        }

        if ($job->failureCount >= $job->maxFailures) {
            throw new QueueException('O trabalho atingiu o limite de falhas permitido.');
        }

        try {
            $this->database->beginTransaction();
            $statement = $this->database->prepare(
                "UPDATE processing_jobs
                    SET status = 'queued', locked_by = NULL, last_error = NULL,
                        available_at = NOW(), started_at = NULL, finished_at = NULL
                  WHERE id = :id AND status = 'failed'"
            );
            $statement->execute(['id' => $job->id]);

            if ($statement->rowCount() !== 1) {
                throw new QueueException('O trabalho não pôde ser retomado.');
            }

            if ($job->stage === 'summaries') {
                $pairedStatement = $this->database->prepare(
                    "SELECT id, stage, status
                       FROM processing_jobs
                      WHERE document_id = :document_id AND id > :job_id
                      ORDER BY id ASC
                      LIMIT 1
                      FOR UPDATE"
                );
                $pairedStatement->execute([
                    'document_id' => $job->documentId,
                    'job_id' => $job->id,
                ]);
                $pairedJob = $pairedStatement->fetch();

                if (is_array($pairedJob)
                    && ($pairedJob['stage'] ?? null) === 'embeddings'
                    && ($pairedJob['status'] ?? null) === 'completed') {
                    $resetEmbedding = $this->database->prepare(
                        "UPDATE processing_jobs
                            SET status = 'queued', result = NULL, locked_by = NULL, last_error = NULL,
                                available_at = NOW(), started_at = NULL, finished_at = NULL
                          WHERE id = :id AND status = 'completed'"
                    );
                    $resetEmbedding->execute(['id' => (int) $pairedJob['id']]);
                }
            }

            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception instanceof QueueException
                ? $exception
                : new QueueException('Não foi possível retomar o trabalho.', 0, $exception);
        }

        return $this->getById($job->id);
    }

    public function getByPublicId(string $publicId): ProcessingJob
    {
        $statement = $this->database->prepare('SELECT * FROM processing_jobs WHERE public_id = :public_id LIMIT 1');
        $statement->execute(['public_id' => $publicId]);
        $record = $statement->fetch();

        if (!is_array($record)) {
            throw new QueueException('O trabalho cognitivo não foi localizado.');
        }

        return $this->hydrate($record);
    }

    /** @param array<string, mixed> $result */
    private function finish(int $jobId, string $status, array $result, ?string $error): ProcessingJob
    {
        try {
            $encoded = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new QueueException('O resultado do trabalho não pôde ser serializado.', 0, $exception);
        }

        $finishedAt = $status === 'completed' ? 'NOW()' : 'NULL';
        $statement = $this->database->prepare(
            "UPDATE processing_jobs
                SET status = :status, result = :result, locked_by = NULL, last_error = :last_error,
                    available_at = NOW(), finished_at = {$finishedAt}
              WHERE id = :id AND status = 'running'"
        );
        $statement->execute([
            'status' => $status,
            'result' => $encoded,
            'last_error' => $error,
            'id' => $jobId,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new QueueException('O trabalho não estava em execução.');
        }

        return $this->getById($jobId);
    }

    private function getById(int $jobId): ProcessingJob
    {
        $statement = $this->database->prepare('SELECT * FROM processing_jobs WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $jobId]);
        $record = $statement->fetch();

        if (!is_array($record)) {
            throw new QueueException('O trabalho cognitivo não foi localizado.');
        }

        return $this->hydrate($record);
    }

    /** @param array<string, mixed> $record */
    private function hydrate(array $record): ProcessingJob
    {
        return new ProcessingJob(
            (int) $record['id'],
            (string) $record['public_id'],
            (int) $record['document_id'],
            (string) $record['stage'],
            (string) $record['version_key'],
            (string) $record['status'],
            (int) $record['run_count'],
            (int) $record['failure_count'],
            (int) $record['max_failures']
        );
    }
}
