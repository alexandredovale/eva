<?php

declare(strict_types=1);

namespace Eva\Application\Query;

use Eva\Infrastructure\Storage\FigureStorage;
use PDO;

final readonly class FigureReferenceResolver
{
    public function __construct(
        private PDO $database,
        private FigureStorage $storage,
        private FigureContractParser $parser = new FigureContractParser()
    ) {
    }

    /** @param list<RetrievedEvidence> $evidences @return list<array<string, mixed>> */
    public function resolve(array $evidences): array
    {
        if ($evidences === []) {
            return [];
        }

        $evidenceIds = [];

        foreach ($evidences as $evidence) {
            if ($evidence instanceof RetrievedEvidence) {
                $evidenceIds[$evidence->id] = $evidence->id;
            }
        }

        if ($evidenceIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($evidenceIds), '?'));
        $statement = $this->database->prepare(
            "SELECT e.id, d.public_id AS document_public_id,
                    node.id AS node_id, node.node_type, node.title AS node_title,
                    parent.id AS parent_id, parent.node_type AS parent_node_type,
                    parent.title AS parent_title, parent.metadata AS parent_metadata,
                    grandparent.id AS grandparent_id, grandparent.node_type AS grandparent_node_type,
                    grandparent.title AS grandparent_title
               FROM evidences e
               JOIN documents d ON d.id = e.document_id
               JOIN document_nodes node ON node.id = e.node_id
          LEFT JOIN document_nodes parent ON parent.id = node.parent_id
          LEFT JOIN document_nodes grandparent ON grandparent.id = parent.parent_id
              WHERE e.id IN ({$placeholders})"
        );
        $statement->execute(array_values($evidenceIds));
        $recordsByEvidence = [];
        $structuredNodeIds = [];

        foreach ($statement->fetchAll() as $record) {
            $evidenceId = (int) $record['id'];
            $nodeTitle = (string) ($record['node_title'] ?? '');

            if (($record['node_type'] ?? null) === 'section'
                && $this->parser->isFigureTitle($nodeTitle)
                && $this->isThematicParent(
                    $record['parent_node_type'] ?? null,
                    $record['parent_title'] ?? null,
                    ['section']
                )) {
                $recordsByEvidence[$evidenceId] = [
                    'document_public_id' => (string) $record['document_public_id'],
                    'title' => $nodeTitle,
                    'fields' => null,
                ];
                continue;
            }

            $parentType = $record['parent_node_type'] ?? null;
            $expectedParentType = $parentType === 'object' ? 'object' : 'element';

            if (!in_array($parentType, ['object', 'element'], true)
                || !$this->parser->isFigureTitle((string) ($record['parent_title'] ?? ''))
                || !$this->isThematicParent(
                    $record['grandparent_node_type'] ?? null,
                    $record['grandparent_title'] ?? null,
                    [$expectedParentType]
                )) {
                continue;
            }

            $parentId = (int) $record['parent_id'];
            $recordsByEvidence[$evidenceId] = [
                'document_public_id' => (string) $record['document_public_id'],
                'title' => $this->structuredTitle(
                    (string) $record['parent_title'],
                    (string) ($record['parent_metadata'] ?? '')
                ),
                'fields' => $parentId,
            ];
            $structuredNodeIds[$parentId] = $parentId;
        }

        $structuredFields = $this->structuredFields(array_values($structuredNodeIds));

        foreach ($recordsByEvidence as &$record) {
            if (!is_int($record['fields'])) {
                continue;
            }

            $nodeId = $record['fields'];
            $record['fields'] = $structuredFields[$nodeId] ?? [];
            $record['title'] = $this->titleFromFields($record['title'], $record['fields']);
        }
        unset($record);

        $figures = [];
        $seen = [];

        foreach ($evidences as $evidence) {
            if (!$evidence instanceof RetrievedEvidence || !isset($recordsByEvidence[$evidence->id])) {
                continue;
            }

            $record = $recordsByEvidence[$evidence->id];
            $contract = is_array($record['fields'])
                ? $this->parser->parseFields($record['title'], $record['fields'])
                : $this->parser->parse($record['title'], $evidence->content);

            if ($contract === null) {
                continue;
            }

            $documentPublicId = $record['document_public_id'];
            $asset = $this->storage->locate($documentPublicId, $contract->declaredPath);

            if ($asset === null) {
                continue;
            }

            $key = $documentPublicId . '|' . $asset['relative_path'];

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $encodedPath = implode('/', array_map('rawurlencode', explode('/', $asset['relative_path'])));
            $figures[] = [
                'id' => 'EVA-F' . strtoupper(substr(hash('sha256', $key), 0, 16)),
                'evidence_id' => $evidence->publicId,
                'document_id' => $documentPublicId,
                'title' => $contract->title,
                'file' => 'figuras/' . $asset['relative_path'],
                'type' => $contract->type,
                'description' => $contract->description,
                'visible_text' => $contract->visibleText,
                'represented_relationships' => $contract->representedRelationships,
                'url' => 'documents/' . $documentPublicId . '/figures/' . $encodedPath,
            ];
        }

        return $figures;
    }

    /** @param list<string> $allowedTypes */
    private function isThematicParent(mixed $type, mixed $title, array $allowedTypes): bool
    {
        return is_string($type)
            && in_array($type, $allowedTypes, true)
            && is_string($title)
            && !$this->parser->isFigureTitle($title);
    }

    /** @return array<int, array<string, string>> */
    private function structuredFields(array $nodeIds): array
    {
        if ($nodeIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($nodeIds), '?'));
        $statement = $this->database->prepare(
            "SELECT parent_id, title, content
               FROM document_nodes
              WHERE parent_id IN ({$placeholders})
           ORDER BY parent_id, sort_order"
        );
        $statement->execute($nodeIds);
        $fields = [];

        foreach ($statement->fetchAll() as $record) {
            $value = trim((string) ($record['content'] ?? ''));

            if ($value !== '') {
                $fields[(int) $record['parent_id']][(string) $record['title']] = $value;
            }
        }

        return $fields;
    }

    private function structuredTitle(string $fallback, string $metadata): string
    {
        $decoded = json_decode($metadata, true);
        $attributes = is_array($decoded) && is_array($decoded['attributes'] ?? null)
            ? $decoded['attributes']
            : [];

        foreach (['title', 'titulo'] as $key) {
            $value = $attributes[$key] ?? null;

            if (is_string($value) && $this->parser->isFigureTitle($value)) {
                return trim($value);
            }
        }

        return $fallback;
    }

    /** @param array<string, string> $fields */
    private function titleFromFields(string $fallback, array $fields): string
    {
        foreach ($fields as $key => $value) {
            if (in_array(mb_strtolower(trim($key), 'UTF-8'), ['title', 'título', 'titulo'], true)
                && $this->parser->isFigureTitle($value)) {
                return trim($value);
            }
        }

        return $fallback;
    }
}
