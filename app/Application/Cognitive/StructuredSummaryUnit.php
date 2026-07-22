<?php

declare(strict_types=1);

namespace Eva\Application\Cognitive;

final readonly class StructuredSummaryUnit
{
    /**
     * @param list<array{title: string, structural_path: string, summary: string}> $childSummaries
     */
    public function __construct(
        public string $documentTitle,
        public string $nodeType,
        public string $nodeTitle,
        public string $structuralPath,
        public string $ownContent,
        public array $childSummaries
    ) {
        if (trim($this->documentTitle) === '' || trim($this->nodeTitle) === '' || trim($this->structuralPath) === '') {
            throw new CognitiveBuildException('A unidade estrutural de resumo está incompleta.');
        }

        if (trim($this->ownContent) === '' && $this->childSummaries === []) {
            throw new CognitiveBuildException('Não há conteúdo organizado para resumir.');
        }

        foreach ($this->childSummaries as $child) {
            if (trim($child['title'] ?? '') === '' || trim($child['structural_path'] ?? '') === '' || trim($child['summary'] ?? '') === '') {
                throw new CognitiveBuildException('Um resumo filho está incompleto.');
            }
        }
    }

    public function inputHash(): string
    {
        return hash('sha256', serialize($this->toArray()));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'document_title' => $this->documentTitle,
            'node_type' => $this->nodeType,
            'node_title' => $this->nodeTitle,
            'structural_path' => $this->structuralPath,
            'own_content' => $this->ownContent,
            'child_summaries' => $this->childSummaries,
        ];
    }
}