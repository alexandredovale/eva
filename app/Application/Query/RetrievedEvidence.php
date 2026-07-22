<?php

declare(strict_types=1);

namespace Eva\Application\Query;

final readonly class RetrievedEvidence
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $documentTitle,
        public string $nodeTitle,
        public string $structuralPath,
        public ?string $sourceReference,
        public string $content
    ) {
        if ($this->id < 1 || trim($this->publicId) === '' || trim($this->content) === '') {
            throw new QueryException('Uma evidência recuperada está incompleta.');
        }
    }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'id' => $this->publicId,
            'document' => $this->documentTitle,
            'node' => $this->nodeTitle,
            'structural_path' => $this->structuralPath,
            'source_reference' => $this->sourceReference,
            'content' => $this->content,
        ];
    }
}
