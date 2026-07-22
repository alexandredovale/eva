<?php

declare(strict_types=1);

namespace Eva\Domain\Document;

final readonly class NormalizedNode
{
    /**
     * @param array<string, mixed> $metadata
     * @param list<NormalizedNode> $children
     */
    public function __construct(
        public string $type,
        public string $title,
        public string $structuralPath,
        public int $depth,
        public int $order,
        public string $content,
        public string $sourceReference,
        public array $metadata = [],
        public array $children = []
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'structural_path' => $this->structuralPath,
            'depth' => $this->depth,
            'order' => $this->order,
            'content' => $this->content,
            'source_reference' => $this->sourceReference,
            'source_hash' => hash('sha256', $this->content),
            'metadata' => $this->metadata,
            'children' => array_map(
                static fn (NormalizedNode $child): array => $child->toArray(),
                $this->children
            ),
        ];
    }
}

