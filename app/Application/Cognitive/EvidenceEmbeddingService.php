<?php

declare(strict_types=1);

namespace Eva\Application\Cognitive;

use JsonException;
use PDO;
use Throwable;

final readonly class EvidenceEmbeddingService
{
    public function __construct(
        private PDO $database,
        private EmbeddingProviderInterface $provider,
        private StructuredEmbeddingTextBuilder $textBuilder = new StructuredEmbeddingTextBuilder(),
        private int $maxUnitsPerBatch = PHP_INT_MAX,
        private int $maxInputTokens = 8_192
    ) {
        if ($this->maxUnitsPerBatch < 1) {
            throw new CognitiveBuildException('O tamanho do lote de embeddings é inválido.');
        }

        if ($this->maxInputTokens < 1) {
            throw new CognitiveBuildException('O limite de tokens por unidade de embedding é inválido.');
        }
    }

    public function buildForDocument(int $documentId): EmbeddingBuildResult
    {
        if ($documentId < 1) {
            throw new CognitiveBuildException('O documento informado é inválido.');
        }

        $records = $this->loadEligibleEvidence($documentId);
        $unitsByPublicId = [];
        $databaseIds = [];
        $recordsByPublicId = [];

        foreach ($records as $record) {
            $unit = $this->textBuilder->build($record);
            $unitsByPublicId[$unit->evidencePublicId] = $unit;
            $databaseIds[$unit->evidencePublicId] = (int) $record['id'];
            $recordsByPublicId[$unit->evidencePublicId] = $record;
        }

        $pendingUnits = [];
        $reused = 0;
        $exists = $this->database->prepare(
            'SELECT 1
               FROM evidence_embeddings
              WHERE evidence_id = :evidence_id
                AND model = :model
                AND content_hash = :content_hash
              LIMIT 1'
        );

        foreach ($unitsByPublicId as $unit) {
            $exists->execute([
                'evidence_id' => $databaseIds[$unit->evidencePublicId],
                'model' => $this->provider->model(),
                'content_hash' => $unit->contentHash,
            ]);

            if ($exists->fetchColumn() !== false) {
                $reused++;
                continue;
            }

            $pendingUnits[] = $unit;
        }

        [$pendingUnits, $representedByDerived] = $this->filterIncompatiblePrimaryUnits(
            $documentId,
            $pendingUnits,
            $unitsByPublicId,
            $recordsByPublicId
        );

        if ($pendingUnits === []) {
            return new EmbeddingBuildResult(count($unitsByPublicId), 0, $reused, 0, $representedByDerived);
        }

        $created = 0;
        $inputTokens = 0;

        // Cada lote mantém unidades estruturais completas e é persistido antes do próximo.
        foreach (array_chunk($pendingUnits, $this->maxUnitsPerBatch) as $batchUnits) {
            $batch = $this->provider->embed($batchUnits);

            if (count($batch->vectors) !== count($batchUnits)) {
                throw new CognitiveBuildException('O provedor não retornou todos os embeddings solicitados.');
            }

            $created += $this->persistBatch($batchUnits, $batch->vectors, $unitsByPublicId, $databaseIds);
            $inputTokens += $batch->inputTokens;
        }

        return new EmbeddingBuildResult(
            count($unitsByPublicId),
            $created,
            $reused + count($pendingUnits) - $created,
            $inputTokens,
            $representedByDerived
        );
    }

    /**
     * @param list<StructuredEmbeddingUnit> $pendingUnits
     * @param array<string, StructuredEmbeddingUnit> $unitsByPublicId
     * @param array<string, array<string, mixed>> $recordsByPublicId
     * @return array{list<StructuredEmbeddingUnit>, int}
     */
    private function filterIncompatiblePrimaryUnits(
        int $documentId,
        array $pendingUnits,
        array $unitsByPublicId,
        array $recordsByPublicId
    ): array {
        $guard = new EmbeddingInputGuard($this->maxInputTokens);
        $derivedBySourceId = $this->loadDerivedRepresentations($documentId);
        $compatibleUnits = [];
        $representedByDerived = 0;

        foreach ($pendingUnits as $unit) {
            if ($guard->isCompatible($unit)) {
                $compatibleUnits[] = $unit;
                continue;
            }

            $record = $recordsByPublicId[$unit->evidencePublicId] ?? null;

            if (!is_array($record) || ($record['evidence_class'] ?? null) !== 'primary') {
                throw new CognitiveBuildException(sprintf(
                    'A unidade de embedding %s excede o orçamento seguro estimado (%d tokens; limite operacional %d) e exige reorganização estrutural.',
                    $unit->evidencePublicId,
                    $guard->estimateTokens($unit->text),
                    $guard->safeTokenLimit()
                ));
            }

            $hasCompatibleDerived = false;

            foreach ($derivedBySourceId[(int) $record['id']] ?? [] as $derivedPublicId) {
                $derivedUnit = $unitsByPublicId[$derivedPublicId] ?? null;

                if ($derivedUnit instanceof StructuredEmbeddingUnit && $guard->isCompatible($derivedUnit)) {
                    $hasCompatibleDerived = true;
                    break;
                }
            }

            if (!$hasCompatibleDerived) {
                throw new CognitiveBuildException(sprintf(
                    'A evidência primária %s excede o orçamento seguro de embedding (%d tokens estimados; limite operacional %d), não possui síntese derivada compatível e exige subdivisão estrutural real.',
                    $unit->evidencePublicId,
                    $guard->estimateTokens($unit->text),
                    $guard->safeTokenLimit()
                ));
            }

            $representedByDerived++;
        }

        return [$compatibleUnits, $representedByDerived];
    }

    /** @return array<int, list<string>> */
    private function loadDerivedRepresentations(int $documentId): array
    {
        $statement = $this->database->prepare(
            "SELECT ed.source_evidence_id, derived.public_id
               FROM evidence_derivations ed
               JOIN evidences derived ON derived.id = ed.evidence_id
              WHERE derived.document_id = :document_id
                AND derived.evidence_class = 'derived'
                AND derived.evidence_type = 'node_summary'
                AND derived.status IN ('generated', 'validated')
              ORDER BY derived.id DESC"
        );
        $statement->execute(['document_id' => $documentId]);
        $derivedBySourceId = [];

        foreach ($statement->fetchAll() as $record) {
            $derivedBySourceId[(int) $record['source_evidence_id']][] = (string) $record['public_id'];
        }

        return $derivedBySourceId;
    }

    /**
     * @param list<StructuredEmbeddingUnit> $batchUnits
     * @param list<EmbeddingVector> $vectors
     * @param array<string, StructuredEmbeddingUnit> $unitsByPublicId
     * @param array<string, int> $databaseIds
     */
    private function persistBatch(
        array $batchUnits,
        array $vectors,
        array $unitsByPublicId,
        array $databaseIds
    ): int {
        $expected = array_fill_keys(array_map(
            static fn (StructuredEmbeddingUnit $unit): string => $unit->evidencePublicId,
            $batchUnits
        ), true);
        $created = 0;

        try {
            $this->database->beginTransaction();
            $insert = $this->database->prepare(
                'INSERT IGNORE INTO evidence_embeddings
                    (evidence_id, model, dimensions, vector_data, content_hash)
                 VALUES
                    (:evidence_id, :model, :dimensions, :vector_data, :content_hash)'
            );

            foreach ($vectors as $vector) {
                if (!isset($expected[$vector->evidencePublicId])) {
                    throw new CognitiveBuildException('O provedor retornou um embedding sem evidência correspondente.');
                }

                $unit = $unitsByPublicId[$vector->evidencePublicId];

                if (!hash_equals($unit->contentHash, $vector->contentHash)) {
                    throw new CognitiveBuildException('O embedding retornado não corresponde ao conteúdo enviado.');
                }

                try {
                    $vectorData = json_encode($vector->vector, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
                } catch (JsonException $exception) {
                    throw new CognitiveBuildException('Não foi possível serializar o embedding.', 0, $exception);
                }

                $insert->execute([
                    'evidence_id' => $databaseIds[$vector->evidencePublicId],
                    'model' => $vector->model,
                    'dimensions' => $vector->dimensions(),
                    'vector_data' => $vectorData,
                    'content_hash' => $vector->contentHash,
                ]);
                $created += $insert->rowCount();
                unset($expected[$vector->evidencePublicId]);
            }

            if ($expected !== []) {
                throw new CognitiveBuildException('Há evidências sem embedding correspondente.');
            }

            $this->database->commit();

            return $created;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            if ($exception instanceof CognitiveBuildException) {
                throw $exception;
            }

            throw new CognitiveBuildException('Não foi possível persistir os embeddings.', 0, $exception);
        }
    }

    /** @return list<array<string, mixed>> */
    private function loadEligibleEvidence(int $documentId): array
    {
        $statement = $this->database->prepare(
            "SELECT e.id, e.public_id, e.evidence_class, e.evidence_type, e.content, e.summary,
                    d.title AS document_title, n.node_type, n.title AS node_title,
                    n.structural_path, n.source_reference
               FROM evidences e
               JOIN documents d ON d.id = e.document_id
               JOIN document_nodes n ON n.id = e.node_id
              WHERE e.document_id = :document_id
                AND e.evidence_class IN ('primary', 'derived')
                AND e.status IN ('generated', 'validated')
              ORDER BY n.depth ASC, n.sort_order ASC, e.id ASC"
        );
        $statement->execute(['document_id' => $documentId]);

        return $statement->fetchAll();
    }
}
