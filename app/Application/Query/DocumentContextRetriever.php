<?php

declare(strict_types=1);

namespace Eva\Application\Query;

use Eva\Application\Cognitive\EmbeddingProviderInterface;
use Eva\Application\Cognitive\StructuredEmbeddingUnit;
use JsonException;
use PDO;

final readonly class DocumentContextRetriever
{
    public function __construct(
        private PDO $database,
        private ?EmbeddingProviderInterface $embeddingProvider = null,
        private InputTypeDetector $detector = new InputTypeDetector()
    ) {
    }

    public function retrieve(
        int $documentId,
        string $input,
        int $maxEvidence = 8,
        int $maxInteractions = 20
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

        if ($understanding->has(InputType::Direct)) {
            foreach ($this->loadDirectEvidence($documentId, $understanding, $input) as $evidence) {
                $evidenceById[$evidence->id] = $evidence;
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
            }
        }

        if ($understanding->has(InputType::Broad)) {
            $routingPoints[] = 'root:' . $document['title'];

            foreach ($this->loadBroadPrimaryEvidence($documentId, $maxEvidence) as $evidence) {
                $evidenceById[$evidence->id] = $evidence;
            }
        }

        if ($understanding->has(InputType::Conceptual) || $understanding->has(InputType::Relational)) {
            if ($this->embeddingProvider === null) {
                $limitations[] = 'A recuperação conceitual exige um provedor de embedding configurado.';
            } else {
                [$semanticEvidences, $semanticRoutes] = $this->loadSemanticEvidence(
                    $documentId,
                    $input,
                    $maxEvidence
                );

                foreach ($semanticEvidences as $evidence) {
                    $evidenceById[$evidence->id] = $evidence;
                }

                $routingPoints = [...$routingPoints, ...$semanticRoutes];
            }
        }

        $evidences = array_slice(array_values($evidenceById), 0, $maxEvidence);

        if ($evidences === []) {
            $limitations[] = 'Nenhuma evidência primária foi localizada para o input.';
        }

        return new QueryContext(
            $understanding,
            $evidences,
            $maxInteractions,
            array_values(array_unique($routingPoints)),
            array_values(array_unique($limitations))
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

    /** @return array{list<RetrievedEvidence>, list<string>} */
    private function loadSemanticEvidence(int $documentId, string $input, int $limit): array
    {
        $unit = new StructuredEmbeddingUnit('EVA-Q' . substr(hash('sha256', $input), 0, 16), $input);
        $batch = $this->embeddingProvider?->embed([$unit]);
        $queryVector = $batch?->vectors[0] ?? null;

        if ($queryVector === null || $queryVector->evidencePublicId !== $unit->evidencePublicId) {
            throw new QueryException('O provedor não retornou o embedding transitório da consulta.');
        }

        $statement = $this->database->prepare(
            "SELECT e.id, e.public_id, e.evidence_class, e.evidence_type, ee.vector_data
               FROM evidences e
               JOIN evidence_embeddings ee ON ee.evidence_id = e.id
              WHERE e.document_id = :document_id
                AND e.evidence_class IN ('primary', 'derived')
                AND e.status IN ('generated', 'validated')
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
            $similarity = $this->cosine($queryVector->vector, $vector);

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

        $matches = array_slice($ranked, 0, $limit);
        $primaryIds = $this->resolvePrimaryEvidenceIds($documentId, $matches, $limit);
        $routes = array_map(
            static fn (array $match): string => sprintf(
                'evidence:%s:%s:%s',
                $match['public_id'],
                $match['evidence_class'],
                $match['evidence_type']
            ),
            $matches
        );

        return [$this->loadEvidenceByIds($documentId, $primaryIds), $routes];
    }

    /**
     * @param list<array{id: int, evidence_class: string}> $matches
     * @return list<int>
     */
    private function resolvePrimaryEvidenceIds(int $documentId, array $matches, int $limit): array
    {
        $primaryIds = [];

        foreach ($matches as $match) {
            $sources = $match['evidence_class'] === 'primary'
                ? [$match['id']]
                : $this->primarySourcesForEvidence($documentId, $match['id']);

            foreach ($sources as $sourceId) {
                $primaryIds[$sourceId] = $sourceId;

                if (count($primaryIds) >= $limit) {
                    break 2;
                }
            }
        }

        return array_values($primaryIds);
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
