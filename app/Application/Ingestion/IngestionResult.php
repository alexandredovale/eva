<?php

declare(strict_types=1);

namespace Eva\Application\Ingestion;

final readonly class IngestionResult
{
    public function __construct(
        public int $documentId,
        public string $documentPublicId,
        public string $format,
        public string $storagePath,
        public int $nodeCount,
        public int $primaryEvidenceCount
    ) {
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'document_id' => $this->documentId,
            'document_public_id' => $this->documentPublicId,
            'format' => $this->format,
            'storage_path' => $this->storagePath,
            'node_count' => $this->nodeCount,
            'primary_evidence_count' => $this->primaryEvidenceCount,
        ];
    }
}

