<?php

declare(strict_types=1);

namespace Eva\Application\Product;

use JsonException;
use PDO;

final readonly class ProductReadService
{
    public function __construct(private PDO $database)
    {
    }

    /** @return list<array<string, mixed>> */
    public function documents(int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));
        $statement = $this->database->query(
            "SELECT d.id, d.public_id, d.title, d.format, d.status, d.created_at,
                    (SELECT COUNT(*) FROM document_nodes n WHERE n.document_id = d.id) AS node_count,
                    (SELECT COUNT(*) FROM evidences e WHERE e.document_id = d.id AND e.evidence_class = 'primary') AS primary_evidence_count,
                    (SELECT COUNT(*) FROM evidences e WHERE e.document_id = d.id AND e.evidence_class = 'derived') AS derived_evidence_count,
                    (SELECT COUNT(*) FROM evidence_embeddings ee
                       JOIN evidences e ON e.id = ee.evidence_id
                      WHERE e.document_id = d.id) AS embedding_count,
                    CASE
                        WHEN EXISTS (
                            SELECT 1 FROM processing_jobs running_job
                             WHERE running_job.document_id = d.id AND running_job.status = 'running'
                        ) THEN 'running'
                        WHEN EXISTS (
                            SELECT 1 FROM processing_jobs queued_job
                             WHERE queued_job.document_id = d.id AND queued_job.status = 'queued'
                        ) THEN 'queued'
                        WHEN (SELECT COUNT(*) FROM evidences primary_evidence
                               WHERE primary_evidence.document_id = d.id
                                 AND primary_evidence.evidence_class = 'primary') > 0
                         AND (SELECT COUNT(*) FROM evidence_embeddings processed_embedding
                               JOIN evidences processed_evidence ON processed_evidence.id = processed_embedding.evidence_id
                              WHERE processed_evidence.document_id = d.id) >=
                             (SELECT COUNT(*) FROM evidences expected_primary
                               WHERE expected_primary.document_id = d.id
                                 AND expected_primary.evidence_class = 'primary') THEN 'completed'
                        WHEN EXISTS (
                            SELECT 1 FROM processing_jobs failed_job
                             WHERE failed_job.document_id = d.id AND failed_job.status = 'failed'
                        ) THEN 'failed'
                        ELSE 'pending'
                    END AS processing_status
               FROM documents d
              ORDER BY d.id DESC
              LIMIT {$limit}"
        );

        return $statement->fetchAll();
    }

    /** @return array<string, mixed> */
    public function metrics(): array
    {
        return [
            'documents' => $this->groupedCount('documents', 'status'),
            'evidences' => $this->groupedCount('evidences', 'evidence_class'),
            'evidence_types' => $this->groupedCount('evidences', 'evidence_type'),
            'embeddings' => (int) $this->database->query('SELECT COUNT(*) FROM evidence_embeddings')->fetchColumn(),
            'derivations' => (int) $this->database->query('SELECT COUNT(*) FROM evidence_derivations')->fetchColumn(),
            'jobs' => $this->groupedCount('processing_jobs', 'status'),
            'audit_events' => (int) $this->database->query('SELECT COUNT(*) FROM audit_events')->fetchColumn(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function jobs(int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));
        $statement = $this->database->query(
            "SELECT j.public_id, j.document_id, d.public_id AS document_public_id, d.title AS document_title,
                    j.stage, j.version_key, j.status, j.run_count, j.failure_count, j.max_failures,
                    j.last_error, j.started_at, j.finished_at, j.created_at, j.updated_at
               FROM processing_jobs j
               JOIN documents d ON d.id = j.document_id
              ORDER BY j.id DESC
              LIMIT {$limit}"
        );

        $jobs = $statement->fetchAll();
        $progress = [];

        foreach ($jobs as &$job) {
            $cacheKey = implode('|', [
                (string) $job['document_id'],
                (string) $job['stage'],
                (string) $job['version_key'],
            ]);

            if (!isset($progress[$cacheKey])) {
                $progress[$cacheKey] = $this->jobProgress(
                    (int) $job['document_id'],
                    (string) $job['stage'],
                    (string) $job['version_key']
                );
            }

            $current = $progress[$cacheKey]['current'];
            $total = $progress[$cacheKey]['total'];

            if ($job['status'] === 'completed') {
                $current = $total;
            }

            $job['progress_current'] = min($current, $total);
            $job['progress_total'] = $total;
            $job['progress_percent'] = $total > 0
                ? min(100, (int) floor(($job['progress_current'] / $total) * 100))
                : ($job['status'] === 'completed' ? 100 : 0);
            unset($job['version_key']);
        }
        unset($job);

        return $jobs;
    }

    /** @return list<array<string, mixed>> */
    public function auditEvents(int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));
        $statement = $this->database->query(
            "SELECT event_type, entity_type, entity_id, metadata, created_at
               FROM audit_events
              ORDER BY id DESC
              LIMIT {$limit}"
        );
        $events = [];

        foreach ($statement->fetchAll() as $event) {
            try {
                $event['metadata'] = json_decode((string) $event['metadata'], true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $event['metadata'] = [];
            }

            $events[] = $event;
        }

        return $events;
    }

    /** @return array<string, int> */
    private function groupedCount(string $table, string $column, ?string $condition = null): array
    {
        $sql = "SELECT {$column} AS label, COUNT(*) AS total FROM {$table}";

        if ($condition !== null) {
            $sql .= ' WHERE ' . $condition;
        }

        $sql .= " GROUP BY {$column}";
        $counts = [];

        foreach ($this->database->query($sql)->fetchAll() as $row) {
            $counts[(string) $row['label']] = (int) $row['total'];
        }

        return $counts;
    }

    /** @return array{current: int, total: int} */
    private function jobProgress(int $documentId, string $stage, string $versionKey): array
    {
        $versionParts = explode(':', $versionKey, 3);
        $model = $versionParts[2] ?? '';

        if ($stage === 'summaries') {
            return [
                'current' => $this->summaryProgress($documentId, $model),
                'total' => $this->eligibleSummaryNodes($documentId),
            ];
        }

        $totalStatement = $this->database->prepare(
            "SELECT COUNT(*)
               FROM evidences
              WHERE document_id = :document_id
                AND evidence_class IN ('primary', 'derived')
                AND status IN ('generated', 'validated')"
        );
        $totalStatement->execute(['document_id' => $documentId]);
        $currentStatement = $this->database->prepare(
            'SELECT COUNT(*)
               FROM evidence_embeddings ee
               JOIN evidences e ON e.id = ee.evidence_id
              WHERE e.document_id = :document_id
                AND ee.model = :model'
        );
        $currentStatement->execute(['document_id' => $documentId, 'model' => $model]);

        return [
            'current' => (int) $currentStatement->fetchColumn(),
            'total' => (int) $totalStatement->fetchColumn(),
        ];
    }

    private function summaryProgress(int $documentId, string $model): int
    {
        $statement = $this->database->prepare(
            "SELECT COUNT(*)
               FROM evidences
              WHERE document_id = :document_id
                AND evidence_class = 'derived'
                AND evidence_type = 'node_summary'
                AND generation_model = :model
                AND status IN ('generated', 'validated')"
        );
        $statement->execute(['document_id' => $documentId, 'model' => $model]);

        return (int) $statement->fetchColumn();
    }

    private function eligibleSummaryNodes(int $documentId): int
    {
        $statement = $this->database->prepare(
            'SELECT id, parent_id, content
               FROM document_nodes
              WHERE document_id = :document_id'
        );
        $statement->execute(['document_id' => $documentId]);
        $parents = [];
        $contentNodes = [];

        foreach ($statement->fetchAll() as $node) {
            $nodeId = (int) $node['id'];
            $parents[$nodeId] = $node['parent_id'] === null ? null : (int) $node['parent_id'];
            $content = trim((string) $node['content']);

            if (!in_array($content, ['', '{}', '[]'], true)) {
                $contentNodes[] = $nodeId;
            }
        }

        $eligible = [];

        foreach ($contentNodes as $nodeId) {
            while ($nodeId !== null && !isset($eligible[$nodeId])) {
                $eligible[$nodeId] = true;
                $nodeId = $parents[$nodeId] ?? null;
            }
        }

        return count($eligible);
    }
}
