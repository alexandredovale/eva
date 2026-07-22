<?php

declare(strict_types=1);

namespace Eva\Application\Query;

final readonly class RetrievedInteraction
{
    /**
     * @param list<array{evidence_id: string, role: string, excerpt_reference: ?string, excerpt: ?string}> $evidences
     */
    public function __construct(
        public string $interactionType,
        public string $summary,
        public array $evidences
    ) {
        if (!in_array($this->interactionType, ['simetry', 'assimetry'], true)
            || trim($this->summary) === '' || count($this->evidences) !== 2) {
            throw new QueryException('Uma interação recuperada está incompleta.');
        }

        $evidenceIds = [];

        foreach ($this->evidences as $evidence) {
            if (!is_array($evidence)
                || !is_string($evidence['evidence_id'] ?? null)
                || preg_match('/^EVA-E\d{6,}$/', $evidence['evidence_id']) !== 1
                || !is_string($evidence['role'] ?? null)
                || !in_array($evidence['role'], ['participant', 'origin', 'destination'], true)
                || !is_string($evidence['excerpt'] ?? null)
                || trim($evidence['excerpt']) === ''
                || (!is_string($evidence['excerpt_reference'] ?? null)
                    && ($evidence['excerpt_reference'] ?? null) !== null)) {
                throw new QueryException('Uma interação recuperada possui associação inválida.');
            }

            $evidenceIds[] = $evidence['evidence_id'];
        }

        if (count(array_unique($evidenceIds)) !== 2) {
            throw new QueryException('Uma interação deve conectar duas evidências distintas.');
        }

        $roles = array_column($this->evidences, 'role');

        if ($this->interactionType === 'simetry'
            && (count(array_filter($roles, static fn (string $role): bool => $role === 'participant')) < 2
                || array_filter($roles, static fn (string $role): bool => in_array($role, ['origin', 'destination'], true)) !== [])) {
            throw new QueryException('Uma interação simetry recuperada possui papéis inválidos.');
        }

        if ($this->interactionType === 'assimetry'
            && (count(array_filter($roles, static fn (string $role): bool => $role === 'origin')) !== 1
                || count(array_filter($roles, static fn (string $role): bool => $role === 'destination')) !== 1)) {
            throw new QueryException('Uma interação assimetry recuperada perdeu sua orientação.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'interaction_type' => $this->interactionType,
            'summary' => $this->summary,
            'evidences' => $this->evidences,
        ];
    }
}
