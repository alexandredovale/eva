<?php

declare(strict_types=1);

namespace Eva\Application\Query;

final readonly class QueryContext
{
    /**
     * @param list<RetrievedEvidence> $evidences
     * @param list<string> $routingPoints
     * @param list<string> $limitations
     * @param list<array{project_id: int, project_name: string, response_profile: string, documents: list<string>}> $responseProfiles
     * @param list<ContextIntelligenceAnalysis> $contextIntelligenceAnalyses
     * @param array<string, 'core'|'convergence'> $evidenceSelection
     */
    public function __construct(
        public InputUnderstanding $understanding,
        public array $evidences,
        public int $interactionLimit,
        public array $routingPoints,
        public array $limitations,
        public array $responseProfiles = [],
        public array $contextIntelligenceAnalyses = [],
        public array $evidenceSelection = []
    ) {
        if ($this->interactionLimit < 0 || $this->interactionLimit > 100) {
            throw new QueryException('O limite de interações transitórias é inválido.');
        }

        foreach ($this->evidences as $evidence) {
            if (!$evidence instanceof RetrievedEvidence) {
                throw new QueryException('O contexto contém uma evidência incompatível.');
            }

            $region = $this->evidenceSelection[$evidence->publicId] ?? 'core';

            if (!in_array($region, ['core', 'convergence'], true)) {
                throw new QueryException('A eleição determinística da evidência está incompleta.');
            }
        }
    }
}
