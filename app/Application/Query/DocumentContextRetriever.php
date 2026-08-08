<?php

declare(strict_types=1);

namespace Eva\Application\Query;

use Eva\Application\Cognitive\EmbeddingProviderInterface;
use Eva\Application\Cognitive\StructuredEmbeddingUnit;
use JsonException;
use PDO;

final class DocumentContextRetriever
{
    /** @var array<string, list<float>> */
    private array $queryVectorCache = [];

    public function __construct(
        private readonly PDO $database,
        private readonly ?EmbeddingProviderInterface $embeddingProvider = null,
        private readonly InputTypeDetector $detector = new InputTypeDetector(),
        private readonly QueryLocalKappaDetector $kappaDetector = new QueryLocalKappaDetector(),
        private readonly ContextIntelligenceEngine $contextIntelligenceEngine = new ContextIntelligenceEngine()
    ) {
    }

    public function retrieve(
        int $documentId,
        string $input,
        int $maxEvidence = 8,
        int $maxInteractions = 20,
        bool $includeSemantic = true
    ): QueryContext {
        if ($documentId < 1 || $maxEvidence < 1 || $maxEvidence > 50
            || $maxInteractions < 0 || $maxInteractions > 100) {
            throw new QueryException('Os parâmetros da consulta são inválidos.');
        }

        $document = $this->loadDocument($documentId);
        $understanding = $this->detector->detect($input);
        /** @var array<int, RetrievedEvidence> $evidenceById */
        $evidenceById = [];
        $routingPoints = [];
        $limitations = [];
        $contextIntelligenceAnalyses = [];
        $evidenceSelection = [];
        $hasSemanticContext = false;

        if ($understanding->has(InputType::Conceptual) || $understanding->has(InputType::Relational)) {
            $literalEvidences = $this->loadLiteralEvidence($documentId, $input);

            foreach ($literalEvidences as $evidence) {
                $evidenceById[$evidence->id] = $evidence;
                $evidenceSelection[$evidence->publicId] = 'core';
            }

            if ($literalEvidences !== []) {
                $routingPoints[] = 'literal_phrase';
            }
        }

        if ($understanding->has(InputType::Direct)) {
            foreach ($this->loadDirectEvidence($documentId, $understanding, $input) as $evidence) {
                $evidenceById[$evidence->id] = $evidence;
                $evidenceSelection[$evidence->publicId] = 'core';
            }
            $routingPoints[] = 'direct_reference';
        }

        if ($understanding->has(InputType::Structural)) {
            $paths = $this->selectStructuralPaths($documentId, $input, 3);

            foreach ($paths as $path) {
                $routingPoints[] = 'node:' . $path;
            }

            foreach ($this->loadPrimaryEvidenceByPaths($documentId, $paths, $maxEvidence) as $evidence) {
                $evidenceById[$evidence->id] = $evidence;
                $evidenceSelection[$evidence->publicId] = 'core';
            }
        }

        if ($understanding->has(InputType::Broad)) {
            $routingPoints[] = 'root:' . $document['title'];

            foreach ($this->loadBroadPrimaryEvidence($documentId, $maxEvidence) as $evidence) {
                $evidenceById[$evidence->id] = $evidence;
                $evidenceSelection[$evidence->publicId] = 'core';
            }
        }

        if ($includeSemantic
            && ($understanding->has(InputType::Conceptual) || $understanding->has(InputType::Relational))) {
            if ($this->embeddingProvider === null) {
                $limitations[] = 'A recuperação conceitual exige um provedor de embedding configurado.';
            } else {
                [$semanticEvidences, $semanticRoutes, $analyses, $semanticSelection] = $this->loadSemanticEvidence(
                    $documentId,
                    $input
                );
                $hasSemanticContext = true;

                foreach ($semanticEvidences as $evidence) {
                    $evidenceById[$evidence->id] = $evidence;
                    $region = $semanticSelection[$evidence->publicId] ?? 'convergence';

                    if (($evidenceSelection[$evidence->publicId] ?? null) !== 'core') {
                        $evidenceSelection[$evidence->publicId] = $region;
                    }
                }

                $routingPoints = [...$routingPoints, ...$semanticRoutes];
                foreach ($analyses as $analysis) {
                    $contextIntelligenceAnalyses[] = $analysis->forDocument(
                        $document['public_id'],
                        $document['title']
                    );
                }
            }
        }

        $evidences = array_values($evidenceById);

        if (!$hasSemanticContext) {
            $evidences = array_slice($evidences, 0, $maxEvidence);
        }
        $selectedPublicIds = array_fill_keys(array_map(
            static fn (RetrievedEvidence $evidence): string => $evidence->publicId,
            $evidences
        ), true);
        $evidenceSelection = array_intersect_key($evidenceSelection, $selectedPublicIds);

        if ($evidences === []) {
            $limitations[] = 'Nenhuma evidência primária foi localizada para o input.';
        }

        return new QueryContext(
            $understanding,
            $evidences,
            $maxInteractions,
            array_values(array_unique($routingPoints)),
            array_values(array_unique($limitations)),
            [],
            $contextIntelligenceAnalyses,
            $evidenceSelection
        );
    }

    /** @return list<RetrievedEvidence> */
    private function loadLiteralEvidence(int $documentId, string $input): array
    {
        $phrase = preg_replace('/^[\p{P}\p{Z}]+|[\p{P}\p{Z}]+$/u', '', trim($input));

        if (!is_string($phrase) || mb_strlen($phrase, 'UTF-8') < 3) {
            return [];
        }

        $statement = $this->database->prepare(
            "SELECT e.id
               FROM evidences e
              WHERE e.document_id = :document_id
                AND e.evidence_class = 'primary'
                AND e.status = 'validated'
                AND e.content LIKE :phrase
              ORDER BY CHAR_LENGTH(e.content) ASC, e.id ASC"
        );
        $statement->execute([
            'document_id' => $documentId,
            'phrase' => '%' . $this->escapeLike($phrase) . '%',
        ]);

        return $this->loadEvidenceByIds(
            $documentId,
            array_map('intval', array_column($statement->fetchAll(), 'id'))
        );
    }

    /** @return array{id: int, title: string, public_id: string} */
    private function loadDocument(int $documentId): array
    {
        $statement = $this->database->prepare(
            "SELECT id, title, public_id FROM documents WHERE id = :id AND status = 'ready' LIMIT 1"
        );
        $statement->execute(['id' => $documentId]);
        $document = $statement->fetch();

        if (!is_array($document)) {
            throw new QueryException('O documento pronto para consulta não foi localizado.');
        }

        return [
            'id' => (int) $document['id'],
            'title' => (string) $document['title'],
            'public_id' => (string) $document['public_id'],
        ];
    }

    /** @return list<RetrievedEvidence> */
    private function loadDirectEvidence(
        int $documentId,
        InputUnderstanding $understanding,
        string $input
    ): array {
        $evidencePublicIds = array_values(array_filter(
            $understanding->directReferences,
            static fn (string $reference): bool => str_starts_with($reference, 'EVA-E')
        ));
        $internalIds = [];

        if ($evidencePublicIds !== []) {
            [$sql, $parameters] = $this->inClause('e.public_id', 'evidence_public_id', $evidencePublicIds);
            $statement = $this->database->prepare(
                "SELECT e.id FROM evidences e
                  WHERE e.document_id = :document_id
                    AND e.evidence_class = 'primary'
                    AND e.status = 'validated'
                    AND {$sql}"
            );
            $statement->execute(['document_id' => $documentId, ...$parameters]);
            $internalIds = array_map('intval', array_column($statement->fetchAll(), 'id'));
        }

        preg_match_all('/["“]([^"”]+)["”]/u', $input, $quoteMatches);

        foreach (array_slice($quoteMatches[1] ?? [], 0, 3) as $index => $phrase) {
            $statement = $this->database->prepare(
                "SELECT id FROM evidences
                  WHERE document_id = :document_id
                    AND evidence_class = 'primary'
                    AND status = 'validated'
                    AND content LIKE :phrase
                  ORDER BY id ASC
                  LIMIT 5"
            );
            $statement->execute([
                'document_id' => $documentId,
                'phrase' => '%' . $this->escapeLike((string) $phrase) . '%',
            ]);
            $internalIds = [...$internalIds, ...array_map('intval', array_column($statement->fetchAll(), 'id'))];
        }

        return $this->loadEvidenceByIds($documentId, array_values(array_unique($internalIds)));
    }

    /** @return list<string> */
    private function selectStructuralPaths(int $documentId, string $input, int $limit): array
    {
        $statement = $this->database->prepare(
            'SELECT id, title, structural_path, depth, sort_order
               FROM document_nodes
              WHERE document_id = :document_id
              ORDER BY depth ASC, sort_order ASC, id ASC'
        );
        $statement->execute(['document_id' => $documentId]);
        $tokens = $this->searchTokens($input);
        $normalizedInput = mb_strtolower($input, 'UTF-8');
        $ranked = [];

        foreach ($statement->fetchAll() as $node) {
            $title = mb_strtolower((string) $node['title'], 'UTF-8');
            $path = mb_strtolower((string) $node['structural_path'], 'UTF-8');
            $score = str_contains($normalizedInput, $title) && mb_strlen($title, 'UTF-8') > 3 ? 10 : 0;

            foreach ($tokens as $token) {
                $score += substr_count($title, $token) * 3;
                $score += substr_count($path, $token);
            }

            if ($score > 0) {
                $ranked[] = ['path' => (string) $node['structural_path'], 'score' => $score, 'id' => (int) $node['id']];
            }
        }

        usort($ranked, static fn (array $left, array $right): int =>
            ($right['score'] <=> $left['score']) ?: ($left['id'] <=> $right['id'])
        );

        return array_values(array_unique(array_column(array_slice($ranked, 0, $limit), 'path')));
    }

    /** @param list<string> $paths @return list<RetrievedEvidence> */
    private function loadPrimaryEvidenceByPaths(int $documentId, array $paths, int $limit): array
    {
        if ($paths === []) {
            return [];
        }

        $conditions = [];
        $parameters = ['document_id' => $documentId];

        foreach ($paths as $index => $path) {
            if ($path === '/') {
                $conditions[] = '1 = 1';
                continue;
            }

            $conditions[] = "(n.structural_path = :path_{$index} OR n.structural_path LIKE :descendant_{$index})";
            $parameters["path_{$index}"] = $path;
            $parameters["descendant_{$index}"] = $this->escapeLike($path) . '/%';
        }

        $statement = $this->database->prepare(
            "SELECT e.id
               FROM evidences e
               JOIN document_nodes n ON n.id = e.node_id
              WHERE e.document_id = :document_id
                AND e.evidence_class = 'primary'
                AND e.status = 'validated'
                AND (" . implode(' OR ', $conditions) . ")
              ORDER BY n.depth ASC, n.sort_order ASC, e.id ASC
              LIMIT " . (int) $limit
        );
        $statement->execute($parameters);

        return $this->loadEvidenceByIds($documentId, array_map('intval', array_column($statement->fetchAll(), 'id')));
    }

    /** @return list<RetrievedEvidence> */
    private function loadBroadPrimaryEvidence(int $documentId, int $limit): array
    {
        $statement = $this->database->prepare(
            "SELECT e.id
               FROM evidences e
               JOIN document_nodes n ON n.id = e.node_id
              WHERE e.document_id = :document_id
                AND e.evidence_class = 'primary'
                AND e.status = 'validated'
              ORDER BY n.depth ASC, n.sort_order ASC, e.id ASC
              LIMIT " . (int) $limit
        );
        $statement->execute(['document_id' => $documentId]);

        return $this->loadEvidenceByIds($documentId, array_map('intval', array_column($statement->fetchAll(), 'id')));
    }

    /** @return array{list<RetrievedEvidence>, list<string>, list<ContextIntelligenceAnalysis>, array<string, 'core'|'convergence'>} */
    private function loadSemanticEvidence(int $documentId, string $input): array
    {
        $queryVector = $this->queryVector($input);

        $statement = $this->database->prepare(
            "SELECT e.id, e.public_id, e.evidence_class, e.evidence_type, ee.vector_data
               FROM evidences e
               JOIN evidence_embeddings ee ON ee.evidence_id = e.id
              WHERE e.document_id = :document_id
                AND e.evidence_class = 'derived'
                AND e.evidence_type = 'node_summary'
                AND e.status IN ('generated', 'validated')
                AND e.id = (
                    SELECT MAX(latest_summary.id)
                      FROM evidences latest_summary
                     WHERE latest_summary.node_id = e.node_id
                       AND latest_summary.evidence_class = 'derived'
                       AND latest_summary.evidence_type = 'node_summary'
                       AND latest_summary.status IN ('generated', 'validated')
                )
                AND ee.model = :model
                AND ee.id = (
                    SELECT MAX(latest.id)
                      FROM evidence_embeddings latest
                     WHERE latest.evidence_id = e.id AND latest.model = :latest_model
                )"
        );
        $statement->execute([
            'document_id' => $documentId,
            'model' => $this->embeddingProvider->model(),
            'latest_model' => $this->embeddingProvider->model(),
        ]);
        $ranked = [];

        foreach ($statement->fetchAll() as $record) {
            try {
                $decoded = json_decode((string) $record['vector_data'], true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new QueryException('Um embedding documental não contém JSON válido.', 0, $exception);
            }

            if (!is_array($decoded)) {
                throw new QueryException('Um embedding documental é inválido.');
            }

            $vector = array_map(static fn (mixed $value): float => is_numeric($value)
                ? (float) $value
                : throw new QueryException('Um embedding documental contém componente inválido.'), $decoded);
            $similarity = $this->cosine($queryVector, $vector);

            if ($similarity !== null) {
                $ranked[] = [
                    'id' => (int) $record['id'],
                    'public_id' => (string) $record['public_id'],
                    'evidence_class' => (string) $record['evidence_class'],
                    'evidence_type' => (string) $record['evidence_type'],
                    'similarity' => $similarity,
                ];
            }
        }

        usort($ranked, static fn (array $left, array $right): int =>
            ($right['similarity'] <=> $left['similarity']) ?: ($left['id'] <=> $right['id'])
        );
        $hierarchicalCandidates = array_map(
            static fn (array $match): ContextCandidate => new ContextCandidate(
                $match['id'],
                $match['public_id'],
                $match['evidence_class'],
                $match['evidence_type'],
                $match['similarity']
            ),
            $ranked
        );
        $retrievalBoundary = $this->kappaDetector->analyze($hierarchicalCandidates);
        $analysis = $this->contextIntelligenceEngine
            ->analyze($retrievalBoundary->selectedCandidates)
            ->withRetrievalBoundary($retrievalBoundary);
        $candidateRegions = [];

        foreach ($analysis->coreCandidates as $candidate) {
            $candidateRegions[$candidate->evidenceId] = 'core';
        }

        foreach ($analysis->convergenceCandidates as $candidate) {
            $candidateRegions[$candidate->evidenceId] = 'convergence';
        }

        $matches = array_map(
            static fn (ContextCandidate $candidate): array => [
                'id' => $candidate->evidenceId,
                'evidence_class' => $candidate->evidenceClass,
                'region' => $candidateRegions[$candidate->evidenceId] ?? 'convergence',
            ],
            $analysis->selectedCandidates
        );
        $primaryRegions = $this->resolvePrimaryEvidenceRegions(
            $documentId,
            $matches
        );
        [$primaryIds, $selectionByInternalId, $primaryAnalyses] = $this->analyzePrimaryEvidence(
            $documentId,
            $queryVector,
            $primaryRegions
        );
        $routes = ['retrieval:kappa:' . $retrievalBoundary->status, ...array_map(
            static fn (ContextCandidate $candidate): string => sprintf(
                'evidence:%s:%s:%s',
                $candidate->publicId,
                $candidate->evidenceClass,
                $candidate->evidenceType
            ),
            $analysis->selectedCandidates
        )];

        $evidences = $this->loadEvidenceByIds($documentId, $primaryIds);
        $selectionByPublicId = [];

        foreach ($evidences as $evidence) {
            $selectionByPublicId[$evidence->publicId] = $selectionByInternalId[$evidence->id] ?? 'convergence';
        }

        return [$evidences, $routes, [$analysis, ...$primaryAnalyses], $selectionByPublicId];
    }

    /** @return list<float> */
    private function queryVector(string $input): array
    {
        $cacheKey = hash('sha256', $input);

        if (isset($this->queryVectorCache[$cacheKey])) {
            return $this->queryVectorCache[$cacheKey];
        }

        $unit = new StructuredEmbeddingUnit('EVA-Q' . substr($cacheKey, 0, 16), $input);
        $batch = $this->embeddingProvider?->embed([$unit]);
        $queryVector = $batch?->vectors[0] ?? null;

        if ($queryVector === null || $queryVector->evidencePublicId !== $unit->evidencePublicId) {
            throw new QueryException('O provedor não retornou o embedding transitório da consulta.');
        }

        return $this->queryVectorCache[$cacheKey] = $queryVector->vector;
    }

    /**
     * @param list<array{id: int, evidence_class: string, region: 'core'|'convergence'}> $matches
     * @return array<int, 'core'|'convergence'>
     */
    private function resolvePrimaryEvidenceRegions(
        int $documentId,
        array $matches
    ): array {
        $regions = [];

        foreach ($matches as $match) {
            $sources = $match['evidence_class'] === 'primary'
                ? [$match['id']]
                : $this->primarySourcesForEvidence($documentId, $match['id']);

            foreach ($sources as $sourceId) {
                if (($regions[$sourceId] ?? null) !== 'core') {
                    $regions[$sourceId] = $match['region'];
                }
            }
        }

        return $regions;
    }

    /**
     * @param array<int, 'core'|'convergence'> $primaryRegions
     * @return array{list<int>, array<int, 'core'|'convergence'>, list<ContextIntelligenceAnalysis>}
     */
    private function analyzePrimaryEvidence(int $documentId, array $queryVector, array $primaryRegions): array
    {
        if ($primaryRegions === []) {
            return [[], [], []];
        }

        [$sql, $parameters] = $this->inClause('e.id', 'primary_evidence_id', array_keys($primaryRegions));
        $statement = $this->database->prepare(
            "SELECT e.id, e.public_id, e.evidence_class, e.evidence_type, ee.vector_data
               FROM evidences e
               JOIN evidence_embeddings ee ON ee.evidence_id = e.id
              WHERE e.document_id = :document_id
                AND e.evidence_class = 'primary'
                AND e.status = 'validated'
                AND {$sql}
                AND ee.model = :model
                AND ee.id = (
                    SELECT MAX(latest.id)
                      FROM evidence_embeddings latest
                     WHERE latest.evidence_id = e.id AND latest.model = :latest_model
                )"
        );
        $model = $this->embeddingProvider?->model();
        $statement->execute([
            'document_id' => $documentId,
            ...$parameters,
            'model' => $model,
            'latest_model' => $model,
        ]);
        $candidatesByRegion = ['core' => [], 'convergence' => []];

        foreach ($statement->fetchAll() as $record) {
            try {
                $decoded = json_decode((string) $record['vector_data'], true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new QueryException('Um embedding primário não contém JSON válido.', 0, $exception);
            }

            if (!is_array($decoded)) {
                throw new QueryException('Um embedding primário é inválido.');
            }

            $vector = array_map(static fn (mixed $value): float => is_numeric($value)
                ? (float) $value
                : throw new QueryException('Um embedding primário contém componente inválido.'), $decoded);
            $similarity = $this->cosine($queryVector, $vector);

            if ($similarity === null) {
                continue;
            }

            $evidenceId = (int) $record['id'];
            $sourceRegion = $primaryRegions[$evidenceId] ?? null;

            if ($sourceRegion === null) {
                continue;
            }

            $candidatesByRegion[$sourceRegion][] = new ContextCandidate(
                $evidenceId,
                (string) $record['public_id'],
                (string) $record['evidence_class'],
                (string) $record['evidence_type'],
                $similarity
            );
        }

        $primaryIds = [];
        $selection = [];
        $analyses = [];

        foreach (['core', 'convergence'] as $sourceRegion) {
            $candidates = $candidatesByRegion[$sourceRegion];
            usort($candidates, static fn (ContextCandidate $left, ContextCandidate $right): int =>
                ($right->similarity <=> $left->similarity) ?: ($left->evidenceId <=> $right->evidenceId)
            );

            if ($candidates === []) {
                continue;
            }

            $boundary = $this->kappaDetector->analyze($candidates);
            $primaryAnalysis = $this->contextIntelligenceEngine
                ->analyze($boundary->selectedCandidates)
                ->withRetrievalBoundary($boundary)
                ->forPrimaryStage($sourceRegion);
            $localNucleus = $primaryAnalysis->coreCandidates !== []
                ? $primaryAnalysis->coreCandidates
                : $primaryAnalysis->convergenceCandidates;

            foreach ($localNucleus as $candidate) {
                $primaryIds[$candidate->evidenceId] = $candidate->evidenceId;
                $selection[$candidate->evidenceId] = $sourceRegion;
            }

            $analyses[] = $primaryAnalysis;
        }

        return [array_values($primaryIds), $selection, $analyses];
    }

    /** @return list<int> */
    private function primarySourcesForEvidence(int $documentId, int $evidenceId): array
    {
        $statement = $this->database->prepare(
            'SELECT source.id, source.evidence_class
               FROM evidence_derivations derivation
               JOIN evidences source ON source.id = derivation.source_evidence_id
              WHERE derivation.evidence_id = :evidence_id
                AND source.document_id = :document_id
                AND source.status IN (\'generated\', \'validated\')
              ORDER BY source.id ASC'
        );
        $queue = [$evidenceId];
        $visited = [];
        $primaryIds = [];

        while ($queue !== []) {
            $currentId = array_shift($queue);

            if (isset($visited[$currentId])) {
                continue;
            }

            $visited[$currentId] = true;
            $statement->execute(['evidence_id' => $currentId, 'document_id' => $documentId]);

            foreach ($statement->fetchAll() as $source) {
                $sourceId = (int) $source['id'];

                if ($source['evidence_class'] === 'primary') {
                    $primaryIds[$sourceId] = $sourceId;
                } else {
                    $queue[] = $sourceId;
                }
            }
        }

        return array_values($primaryIds);
    }

    /** @param list<int> $ids @return list<RetrievedEvidence> */
    private function loadEvidenceByIds(int $documentId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        [$sql, $parameters] = $this->inClause('e.id', 'evidence_id', $ids);
        $statement = $this->database->prepare(
            "SELECT e.id, e.public_id, e.content, d.title AS document_title,
                    n.title AS node_title, n.structural_path, n.source_reference
               FROM evidences e
               JOIN documents d ON d.id = e.document_id
               JOIN document_nodes n ON n.id = e.node_id
              WHERE e.document_id = :document_id
                AND e.evidence_class = 'primary'
                AND e.status = 'validated'
                AND {$sql}"
        );
        $statement->execute(['document_id' => $documentId, ...$parameters]);
        $recordsById = [];

        foreach ($statement->fetchAll() as $record) {
            $recordsById[(int) $record['id']] = new RetrievedEvidence(
                (int) $record['id'],
                (string) $record['public_id'],
                (string) $record['document_title'],
                (string) $record['node_title'],
                (string) $record['structural_path'],
                is_string($record['source_reference']) ? $record['source_reference'] : null,
                (string) $record['content']
            );
        }

        $ordered = [];

        foreach ($ids as $id) {
            if (isset($recordsById[$id])) {
                $ordered[] = $recordsById[$id];
            }
        }

        return $ordered;
    }

    /** @return list<string> */
    private function searchTokens(string $input): array
    {
        $normalized = mb_strtolower($input, 'UTF-8');
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stopWords = ['sobre', 'qual', 'quais', 'como', 'onde', 'obra', 'parte', 'seção', 'secao', 'capítulo', 'capitulo', 'título', 'titulo'];

        return array_values(array_unique(array_filter(
            $tokens,
            static fn (string $token): bool => mb_strlen($token, 'UTF-8') >= 3 && !in_array($token, $stopWords, true)
        )));
    }

    /** @param list<float> $left @param list<float> $right */
    private function cosine(array $left, array $right): ?float
    {
        if ($left === [] || count($left) !== count($right)) {
            throw new QueryException('Os embeddings da consulta possuem dimensões incompatíveis.');
        }

        $dot = 0.0;
        $leftNorm = 0.0;
        $rightNorm = 0.0;

        foreach ($left as $index => $leftValue) {
            $rightValue = $right[$index];
            $dot += $leftValue * $rightValue;
            $leftNorm += $leftValue * $leftValue;
            $rightNorm += $rightValue * $rightValue;
        }

        return $leftNorm === 0.0 || $rightNorm === 0.0
            ? null
            : $dot / (sqrt($leftNorm) * sqrt($rightNorm));
    }

    /**
     * @param list<int|string> $values
     * @return array{string, array<string, int|string>}
     */
    private function inClause(string $column, string $prefix, array $values): array
    {
        $placeholders = [];
        $parameters = [];

        foreach (array_values($values) as $index => $value) {
            $name = $prefix . '_' . $index;
            $placeholders[] = ':' . $name;
            $parameters[$name] = $value;
        }

        return [sprintf('%s IN (%s)', $column, implode(', ', $placeholders)), $parameters];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
