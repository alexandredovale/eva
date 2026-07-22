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
     */
    public function __construct(
        public InputUnderstanding $understanding,
        public string $answer,
        public array $usedEvidences,
        public array $simetryInteractions,
        public array $assimetryInteractions,
        public array $routingPoints,
        public array $limitations
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'input' => $this->understanding->toArray(),
            'answer' => $this->answer,
            'evidences_used' => array_map(
                static fn (RetrievedEvidence $evidence): array => $evidence->toArray(),
                $this->usedEvidences
            ),
            'simetry_interactions' => array_map(
                static fn (RetrievedInteraction $interaction): array => $interaction->toArray(),
                $this->simetryInteractions
            ),
            'assimetry_interactions' => array_map(
                static fn (RetrievedInteraction $interaction): array => $interaction->toArray(),
                $this->assimetryInteractions
            ),
            'routing_points' => $this->routingPoints,
            'limitations' => $this->limitations,
        ];
    }
}
