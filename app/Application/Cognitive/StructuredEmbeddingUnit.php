<?php

declare(strict_types=1);

namespace Eva\Application\Cognitive;

final readonly class StructuredEmbeddingUnit
{
    public string $contentHash;

    public function __construct(
        public string $evidencePublicId,
        public string $text
    ) {
        if (trim($this->evidencePublicId) === '' || trim($this->text) === '') {
            throw new CognitiveBuildException('A unidade estrutural de embedding está incompleta.');
        }

        $this->contentHash = hash('sha256', $this->text);
    }
}