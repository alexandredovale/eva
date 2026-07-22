<?php

declare(strict_types=1);

namespace Eva\Domain\Document;

final readonly class NormalizedDocument
{
    public function __construct(
        public string $format,
        public string $title,
        public string $sourceHash,
        public NormalizedNode $root
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'format' => $this->format,
            'title' => $this->title,
            'source_hash' => $this->sourceHash,
            'root' => $this->root->toArray(),
        ];
    }
}

