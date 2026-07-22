<?php

declare(strict_types=1);

namespace Eva\Application\Cognitive;

use PDO;
use Throwable;

final readonly class HierarchicalSummaryService
{
    public function __construct(
        private PDO $database,
        private SummaryProviderInterface $provider
    ) {
    }

    public function buildForDocument(int $documentId, ?int $maxNewSummaries = null): HierarchicalSummaryResult
    {
        if ($documentId < 1) {
            throw new CognitiveBuildException('O documento informado é inválido.');
        }

        if ($maxNewSummaries !== null && $maxNewSummaries < 1) {
            throw new CognitiveBuildException('O limite de novos resumos deve ser maior que zero.');
        }

        $document = $this->loadDocument($documentId);
        $nodes = $this->loadNodesBottomUp($documentId);
        $primaryEvidenceByNode = $this->loadPrimaryEvidenceIds($documentId);
        /** @var array<int, list<array{id: int, title: string, structural_path: string, summary: string}>> $childrenByParent */
        $childrenByParent = [];
        $eligible = 0;
        $created = 0;
        $reused = 0;
        $inputTokens = 0;
        $outputTokens = 0;
        $stoppedByLimit = false;

        foreach ($nodes as $node) {
            $nodeId = (int) $node['id'];
            $parentId = $node['parent_id'] === null ? null : (int) $node['parent_id'];
            $ownContent = $this->meaningfulContent((string) $node['content']);
            $children = $childrenByParent[$nodeId] ?? [];

            if ($ownContent === '' && $children === []) {
                continue;
            }

            $eligible++;
            $unit = new StructuredSummaryUnit(
                documentTitle: (string) $document['title'],
                nodeType: (string) $node['node_type'],
                nodeTitle: (string) $node['title'],
                structuralPath: (string) $node['structural_path'],
                ownContent: $ownContent,
                childSummaries: array_map(
                    static fn (array $child): array => [
                        'title' => $child['title'],
                        'structural_path' => $child['structural_path'],
                        'summary' => $child['summary'],
                    ],
                    $children
                )
            );
            $inputHash = $unit->inputHash();
            $summaryEvidence = $this->findExistingSummary($nodeId, $inputHash);

            if ($summaryEvidence === null) {
                if ($maxNewSummaries !== null && $created >= $maxNewSummaries) {
                    $stoppedByLimit = true;
                    break;
                }

                // A unidade completa é enviada ao provedor; não há cortes por caracteres ou tokens.
                $result = $this->provider->summarize($unit);
                $sourceEvidenceIds = [];

                if (isset($primaryEvidenceByNode[$nodeId])) {
                    $sourceEvidenceIds[] = $primaryEvidenceByNode[$nodeId];
                }

                foreach ($children as $child) {
                    $sourceEvidenceIds[] = $child['id'];
                }

                $summaryEvidence = $this->persistSummary(
                    documentId: $documentId,
                    nodeId: $nodeId,
                    summary: $result->summary,
                    inputHash: $inputHash,
                    sourceEvidenceIds: array_values(array_unique($sourceEvidenceIds))
                );
                $created++;
                $inputTokens += $result->inputTokens;
                $outputTokens += $result->outputTokens;
            } else {
                $reused++;
            }

            if ($parentId !== null) {
                $childrenByParent[$parentId][] = [
                    'id' => (int) $summaryEvidence['id'],
                    'title' => (string) $node['title'],
                    'structural_path' => (string) $node['structural_path'],
                    'summary' => (string) $summaryEvidence['summary'],
                ];
            }
        }

        return new HierarchicalSummaryResult($eligible, $created, $reused, $inputTokens, $outputTokens, $stoppedByLimit);
    }

    /** @return array<string, mixed> */
    private function loadDocument(int $documentId): array
    {
        $statement = $this->database->prepare('SELECT id, title FROM documents WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $documentId]);
        $document = $statement->fetch();

        if (!is_array($document)) {
            throw new CognitiveBuildException('O documento não foi localizado.');
        }

        return $document;
    }

    /** @return list<array<string, mixed>> */
    private function loadNodesBottomUp(int $documentId): array
    {
        $statement = $this->database->prepare(
            'SELECT id, parent_id, node_type, title, structural_path, depth, sort_order, content
               FROM document_nodes
              WHERE document_id = :document_id
              ORDER BY depth DESC, parent_id ASC, sort_order ASC, id ASC'
        );
        $statement->execute(['document_id' => $documentId]);

        return $statement->fetchAll();
    }

    /** @return array<int, int> */
    private function loadPrimaryEvidenceIds(int $documentId): array
    {
        $statement = $this->database->prepare(
            "SELECT id, node_id
               FROM evidences
              WHERE document_id = :document_id
                AND evidence_class = 'primary'
                AND evidence_type = 'node_content'
                AND status = 'validated'
              ORDER BY id ASC"
        );
        $statement->execute(['document_id' => $documentId]);
        $map = [];

        foreach ($statement->fetchAll() as $evidence) {
            $map[(int) $evidence['node_id']] = (int) $evidence['id'];
        }

        return $map;
    }

    /** @return array{id: int, summary: string}|null */
    private function findExistingSummary(int $nodeId, string $inputHash): ?array
    {
        $statement = $this->database->prepare(
            "SELECT id, summary
               FROM evidences
              WHERE node_id = :node_id
                AND evidence_class = 'derived'
                AND evidence_type = 'node_summary'
                AND generation_model = :generation_model
                AND generation_input_hash = :generation_input_hash
                AND status IN ('generated', 'validated')
              LIMIT 1"
        );
        $statement->execute([
            'node_id' => $nodeId,
            'generation_model' => $this->provider->model(),
            'generation_input_hash' => $inputHash,
        ]);
        $evidence = $statement->fetch();

        if (!is_array($evidence) || !is_string($evidence['summary']) || trim($evidence['summary']) === '') {
            return null;
        }

        return ['id' => (int) $evidence['id'], 'summary' => $evidence['summary']];
    }

    /**
     * @param list<int> $sourceEvidenceIds
     * @return array{id: int, summary: string}
     */
    private function persistSummary(
        int $documentId,
        int $nodeId,
        string $summary,
        string $inputHash,
        array $sourceEvidenceIds
    ): array {
        $summary = trim($summary);

        if ($summary === '') {
            throw new CognitiveBuildException('O resumo hierárquico está vazio.');
        }

        try {
            $this->database->beginTransaction();
            $temporaryPublicId = 'TMP-' . bin2hex(random_bytes(12));
            $statement = $this->database->prepare(
                "INSERT INTO evidences
                    (public_id, document_id, node_id, evidence_class, evidence_type, content, summary,
                     source_hash, generation_model, generation_input_hash, status)
                 VALUES
                    (:public_id, :document_id, :node_id, 'derived', 'node_summary', :content, :summary,
                     :source_hash, :generation_model, :generation_input_hash, 'generated')"
            );
            $statement->execute([
                'public_id' => $temporaryPublicId,
                'document_id' => $documentId,
                'node_id' => $nodeId,
                'content' => $summary,
                'summary' => $summary,
                'source_hash' => hash('sha256', $summary),
                'generation_model' => $this->provider->model(),
                'generation_input_hash' => $inputHash,
            ]);
            $evidenceId = (int) $this->database->lastInsertId();
            $publicId = sprintf('EVA-E%06d', $evidenceId);
            $statement = $this->database->prepare('UPDATE evidences SET public_id = :public_id WHERE id = :id');
            $statement->execute(['public_id' => $publicId, 'id' => $evidenceId]);
            $derivation = $this->database->prepare(
                'INSERT IGNORE INTO evidence_derivations (evidence_id, source_evidence_id)
                 VALUES (:evidence_id, :source_evidence_id)'
            );

            foreach ($sourceEvidenceIds as $sourceEvidenceId) {
                $derivation->execute([
                    'evidence_id' => $evidenceId,
                    'source_evidence_id' => $sourceEvidenceId,
                ]);
            }

            $this->database->commit();

            return ['id' => $evidenceId, 'summary' => $summary];
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw new CognitiveBuildException('Não foi possível persistir o resumo hierárquico.', 0, $exception);
        }
    }

    private function meaningfulContent(string $content): string
    {
        $content = trim($content);

        return in_array($content, ['', '{}', '[]'], true) ? '' : $content;
    }
}