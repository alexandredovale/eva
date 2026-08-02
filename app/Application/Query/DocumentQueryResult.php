<?php

declare(strict_types=1);

namespace Eva\Application\Query;

final readonly class DocumentQueryResult
{
    /**
     * @param list<RetrievedEvidence> $usedEvidences
     * @param list<RetrievedInteraction> $simetryInteractions
     * @param list<RetrievedInteraction> $assimetryInteractions
     * @param list<string> $routingPoints
     * @param list<string> $limitations
     * @param list<ContextIntelligenceAnalysis> $contextIntelligenceAnalyses
     * @param array<string, 'core'|'convergence'> $evidenceSelection
     */
    public function __construct(
        public InputUnderstanding $understanding,
        public string $answer,
        public array $usedEvidences,
        public array $simetryInteractions,
        public array $assimetryInteractions,
        public array $routingPoints,
        public array $limitations,
        public array $contextIntelligenceAnalyses = [],
        public array $evidenceSelection = []
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'input' => $this->understanding->toArray(),
            'answer' => $this->answer,
            'evidences_used' => array_map(
                fn (RetrievedEvidence $evidence): array => [
                    ...$evidence->toArray(),
                    'selection_region' => $this->evidenceSelection[$evidence->publicId] ?? 'core',
                ],
                $this->usedEvidences
            ),
            'evidence_selection' => [
                'core_evidence_ids' => array_values(array_keys(array_filter(
                    $this->evidenceSelection,
                    static fn (string $region): bool => $region === 'core'
                ))),
                'convergence_evidence_ids' => array_values(array_keys(array_filter(
                    $this->evidenceSelection,
                    static fn (string $region): bool => $region === 'convergence'
                ))),
            ],
            'simetry_interactions' => array_map(
                static fn (RetrievedInteraction $interaction): array => $interaction->toArray(),
                $this->simetryInteractions
            ),
            'assimetry_interactions' => array_map(
                static fn (RetrievedInteraction $interaction): array => $interaction->toArray(),
                $this->assimetryInteractions
            ),
            'routing_points' => $this->routingPoints,
            'context_intelligence' => array_map(
                static fn (ContextIntelligenceAnalysis $analysis): array => $analysis->toArray(),
                $this->contextIntelligenceAnalyses
            ),
            'limitations' => $this->limitations,
        ];
    }
}
