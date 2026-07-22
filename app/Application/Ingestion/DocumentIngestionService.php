<?php

declare(strict_types=1);

namespace Eva\Application\Ingestion;

use Eva\Application\Ingestion\Parser\ParserFactory;
use Eva\Domain\Document\NormalizedDocument;
use Eva\Domain\Document\NormalizedNode;
use Eva\Infrastructure\Storage\DocumentStorage;
use JsonException;
use PDO;
use PDOStatement;
use Throwable;

final class DocumentIngestionService
{
    public function __construct(
        private readonly PDO $database,
        private readonly DocumentStorage $storage,
        private readonly int $maxDocumentBytes = 10_485_760
    ) {
        if ($this->maxDocumentBytes < 1) {
            throw new IngestionException('O limite máximo do documento deve ser maior que zero.');
        }
    }

    public function ingest(string $originalName, string $content, string $documentTitle): IngestionResult
    {
        if (strlen($content) > $this->maxDocumentBytes) {
            throw new IngestionException('O documento excede o limite de tamanho configurado.');
        }

        $originalName = $this->sanitizeOriginalName($originalName);
        $parser = ParserFactory::forFilename($originalName);
        $document = $parser->parse($content, $documentTitle);
        $documentId = $this->registerDocument($document, $originalName, $parser::class);
        $documentPublicId = $this->documentPublicId($documentId);
        $storagePath = '';

        try {
            $storagePath = $this->storage->store($documentPublicId, $document->format, $content);
            $statement = $this->database->prepare(
                'UPDATE documents SET storage_path = :storage_path WHERE id = :id'
            );
            $statement->execute(['storage_path' => $storagePath, 'id' => $documentId]);
        } catch (Throwable $exception) {
            if ($storagePath !== '') {
                try {
                    $this->storage->remove($storagePath);
                } catch (Throwable) {
                }
            }

            $this->markDocumentFailed($documentId);
            throw new IngestionException('Não foi possível armazenar a fonte do documento.', 0, $exception);
        }

        $nodeCount = 0;
        $evidenceCount = 0;

        try {
            $this->database->beginTransaction();
            $this->updateDocumentStatus($documentId, 'processing');

            $nodeStatement = $this->database->prepare(
                'INSERT INTO document_nodes
                    (document_id, parent_id, node_type, title, structural_path, depth, sort_order,
                     content, source_reference, source_hash, metadata)
                 VALUES
                    (:document_id, :parent_id, :node_type, :title, :structural_path, :depth, :sort_order,
                     :content, :source_reference, :source_hash, :metadata)'
            );
            $evidenceStatement = $this->database->prepare(
                'INSERT INTO evidences
                    (public_id, document_id, node_id, evidence_class, evidence_type, content,
                     summary, source_hash, status)
                 VALUES
                    (:public_id, :document_id, :node_id, :evidence_class, :evidence_type, :content,
                     NULL, :source_hash, :status)'
            );
            $evidenceIdStatement = $this->database->prepare(
                'UPDATE evidences SET public_id = :public_id WHERE id = :id'
            );

            $this->persistNode(
                documentId: $documentId,
                parentId: null,
                node: $document->root,
                nodeStatement: $nodeStatement,
                evidenceStatement: $evidenceStatement,
                evidenceIdStatement: $evidenceIdStatement,
                nodeCount: $nodeCount,
                evidenceCount: $evidenceCount
            );

            $this->updateDocumentStatus($documentId, 'ready');
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            $this->markDocumentFailed($documentId);
            throw new IngestionException('Não foi possível persistir a árvore documental.', 0, $exception);
        }

        return new IngestionResult(
            documentId: $documentId,
            documentPublicId: $documentPublicId,
            format: $document->format,
            storagePath: $storagePath,
            nodeCount: $nodeCount,
            primaryEvidenceCount: $evidenceCount
        );
    }

    private function registerDocument(
        NormalizedDocument $document,
        string $originalName,
        string $parserClass
    ): int {
        $temporaryPublicId = 'TMP-' . bin2hex(random_bytes(12));
        $metadata = $this->encodeMetadata([
            'parser' => $parserClass,
            'normalized_contract_version' => 1,
            'primary_evidence_rule' => 'direct_node_content',
        ]);
        $statement = $this->database->prepare(
            'INSERT INTO documents
                (public_id, title, original_name, format, source_hash, status, metadata)
             VALUES
                (:public_id, :title, :original_name, :format, :source_hash, :status, :metadata)'
        );
        $statement->execute([
            'public_id' => $temporaryPublicId,
            'title' => $document->title,
            'original_name' => $originalName,
            'format' => $document->format,
            'source_hash' => $document->sourceHash,
            'status' => 'received',
            'metadata' => $metadata,
        ]);

        $documentId = (int) $this->database->lastInsertId();
        $statement = $this->database->prepare(
            'UPDATE documents SET public_id = :public_id WHERE id = :id'
        );
        $statement->execute([
            'public_id' => $this->documentPublicId($documentId),
            'id' => $documentId,
        ]);

        return $documentId;
    }

    private function persistNode(
        int $documentId,
        ?int $parentId,
        NormalizedNode $node,
        PDOStatement $nodeStatement,
        PDOStatement $evidenceStatement,
        PDOStatement $evidenceIdStatement,
        int &$nodeCount,
        int &$evidenceCount
    ): void {
        $sourceHash = hash('sha256', $node->content);
        $nodeStatement->execute([
            'document_id' => $documentId,
            'parent_id' => $parentId,
            'node_type' => $node->type,
            'title' => $node->title,
            'structural_path' => $node->structuralPath,
            'depth' => $node->depth,
            'sort_order' => $node->order,
            'content' => $node->content,
            'source_reference' => $node->sourceReference,
            'source_hash' => $sourceHash,
            'metadata' => $this->encodeMetadata($node->metadata),
        ]);

        $nodeId = (int) $this->database->lastInsertId();
        $nodeCount++;

        if ($this->hasPrimaryEvidenceContent($node->content)) {
            $temporaryPublicId = 'TMP-' . bin2hex(random_bytes(12));
            $evidenceStatement->execute([
                'public_id' => $temporaryPublicId,
                'document_id' => $documentId,
                'node_id' => $nodeId,
                'evidence_class' => 'primary',
                'evidence_type' => 'node_content',
                'content' => $node->content,
                'source_hash' => $sourceHash,
                'status' => 'validated',
            ]);

            $evidenceId = (int) $this->database->lastInsertId();
            $evidenceIdStatement->execute([
                'public_id' => $this->evidencePublicId($evidenceId),
                'id' => $evidenceId,
            ]);
            $evidenceCount++;
        }

        foreach ($node->children as $child) {
            $this->persistNode(
                documentId: $documentId,
                parentId: $nodeId,
                node: $child,
                nodeStatement: $nodeStatement,
                evidenceStatement: $evidenceStatement,
                evidenceIdStatement: $evidenceIdStatement,
                nodeCount: $nodeCount,
                evidenceCount: $evidenceCount
            );
        }
    }

    private function hasPrimaryEvidenceContent(string $content): bool
    {
        $content = trim($content);

        return $content !== '' && $content !== '{}' && $content !== '[]';
    }

    /** @param array<string, mixed> $metadata */
    private function encodeMetadata(array $metadata): string
    {
        try {
            return json_encode(
                $metadata === [] ? (object) [] : $metadata,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException $exception) {
            throw new IngestionException('Os metadados normalizados são inválidos.', 0, $exception);
        }
    }

    private function sanitizeOriginalName(string $originalName): string
    {
        $originalName = str_replace('\\', '/', $originalName);
        $originalName = basename($originalName);
        $originalName = str_replace(["\0", "\r", "\n"], '', $originalName);
        $originalName = trim($originalName);

        if ($originalName === '') {
            throw new IngestionException('O nome original do documento é obrigatório.');
        }

        if (preg_match('//u', $originalName) !== 1) {
            throw new IngestionException('O nome original do documento não possui codificação UTF-8 válida.');
        }

        return mb_substr($originalName, 0, 255, 'UTF-8');
    }

    private function updateDocumentStatus(int $documentId, string $status): void
    {
        $statement = $this->database->prepare(
            'UPDATE documents SET status = :status WHERE id = :id'
        );
        $statement->execute(['status' => $status, 'id' => $documentId]);
    }

    private function markDocumentFailed(int $documentId): void
    {
        try {
            $this->updateDocumentStatus($documentId, 'failed');
        } catch (Throwable) {
        }
    }

    private function documentPublicId(int $documentId): string
    {
        return sprintf('EVA-D%06d', $documentId);
    }

    private function evidencePublicId(int $evidenceId): string
    {
        return sprintf('EVA-E%06d', $evidenceId);
    }
}

