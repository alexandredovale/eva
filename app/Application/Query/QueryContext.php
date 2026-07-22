<?php

declare(strict_types=1);

namespace Eva\Application\Query;

final readonly class QueryContext
{
    /**
     * @param list<RetrievedEvidence> $evidences
     * @param list<string> $routingPoints
     * @param list<string> $limitations
     */
    public function __construct(
        public InputUnderstanding $understanding,
        public array $evidences,
        public int $interactionLimit,
        public array $routingPoints,
        public array $limitations
    ) {
        if ($this->interactionLimit < 0 || $this->interactionLimit > 100) {
            throw new QueryException('O limite de interações transitórias é inválido.');
        }
    }
}
