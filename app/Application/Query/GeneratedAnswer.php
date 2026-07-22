<?php

declare(strict_types=1);

namespace Eva\Application\Query;

final readonly class GeneratedAnswer
{
    /**
     * @param list<string> $usedEvidenceIds
     * @param list<RetrievedInteraction> $interactions
     * @param list<string> $limitations
     */
    public function __construct(
        public string $answer,
        public array $usedEvidenceIds,
        public array $interactions = [],
        public array $limitations = []
    ) {
        if (trim($this->answer) === '') {
            throw new QueryException('O provedor retornou uma resposta vazia.');
        }

        foreach ($this->interactions as $interaction) {
            if (!$interaction instanceof RetrievedInteraction) {
                throw new QueryException('O provedor retornou uma interação transitória inválida.');
            }
        }
    }
}
