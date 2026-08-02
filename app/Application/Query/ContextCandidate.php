<?php

declare(strict_types=1);

namespace Eva\Application\Query;

final readonly class ContextCandidate
{
    public function __construct(
        public int $evidenceId,
        public string $publicId,
        public string $evidenceClass,
        public string $evidenceType,
        public float $similarity
    ) {
        if ($this->evidenceId < 1
            || trim($this->publicId) === ''
            || !in_array($this->evidenceClass, ['primary', 'derived'], true)
            || trim($this->evidenceType) === ''
            || !is_finite($this->similarity)) {
            throw new QueryException('Um candidato do contexto estatístico é inválido.');
        }
    }

    /** @return array{id: string, evidence_class: string, evidence_type: string, similarity: float} */
    public function toArray(): array
    {
        return [
            'id' => $this->publicId,
            'evidence_class' => $this->evidenceClass,
            'evidence_type' => $this->evidenceType,
            'similarity' => $this->similarity,
        ];
    }
}
