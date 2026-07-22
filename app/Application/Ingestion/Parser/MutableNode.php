<?php

declare(strict_types=1);

namespace Eva\Application\Ingestion\Parser;

use Eva\Domain\Document\NormalizedNode;

/** @internal Utilizado somente durante a leitura de Markdown. */
final class MutableNode
{
    /** @var list<MutableNode> */
    public array $children = [];

    /** @var list<string> */
    public array $contentLines = [];

    /** @var array<string, int> */
    private array $segmentCounts = [];

    /** @param array<string, mixed> $metadata */
    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly string $structuralPath,
        public readonly int $depth,
        public readonly int $order,
        public readonly int $startLine,
        public int $endLine,
        public readonly array $metadata = []
    ) {
    }

    public function addContent(string $line, int $lineNumber): void
    {
        $this->contentLines[] = $line;
        $this->endLine = max($this->endLine, $lineNumber);
    }

    public function addChild(MutableNode $child): void
    {
        $this->children[] = $child;
    }

    public function nextChildPath(string $title): string
    {
        $segment = StructuralPath::segment($title);
        $count = ($this->segmentCounts[$segment] ?? 0) + 1;
        $this->segmentCounts[$segment] = $count;

        if ($count > 1) {
            $segment .= '-' . $count;
        }

        return StructuralPath::child($this->structuralPath, $segment);
    }

    public function toNormalizedNode(): NormalizedNode
    {
        return new NormalizedNode(
            type: $this->type,
            title: $this->title,
            structuralPath: $this->structuralPath,
            depth: $this->depth,
            order: $this->order,
            content: rtrim(implode("\n", $this->contentLines), "\n"),
            sourceReference: sprintf('lines:%d-%d', $this->startLine, $this->subtreeEndLine()),
            metadata: $this->metadata,
            children: array_map(
                static fn (MutableNode $child): NormalizedNode => $child->toNormalizedNode(),
                $this->children
            )
        );
    }

    private function subtreeEndLine(): int
    {
        $endLine = $this->endLine;

        foreach ($this->children as $child) {
            $endLine = max($endLine, $child->subtreeEndLine());
        }

        return $endLine;
    }
}

