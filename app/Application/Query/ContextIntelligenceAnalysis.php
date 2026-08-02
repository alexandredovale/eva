<?php

declare(strict_types=1);

namespace Eva\Application\Query;

final readonly class ContextIntelligenceAnalysis
{
    /**
     * @param list<ContextCandidate> $convergenceCandidates
     * @param list<ContextCandidate> $coreCandidates
     * @param list<ContextCandidate> $discardedCandidates
     * @param list<ContextCandidate> $selectedCandidates
     */
    public function __construct(
        public float $mean,
        public float $standardDeviation,
        public ?float $coefficientOfVariation,
        public array $convergenceCandidates,
        public array $coreCandidates,
        public array $discardedCandidates,
        public array $selectedCandidates,
        public string $selectedRegion,
        public ?string $documentId = null,
        public ?string $documentTitle = null
    ) {
        if (!is_finite($this->mean)
            || !is_finite($this->standardDeviation)
            || ($this->coefficientOfVariation !== null && !is_finite($this->coefficientOfVariation))
            || !in_array($this->selectedRegion, ['core', 'convergence', 'empty'], true)) {
            throw new QueryException('A análise estatística do contexto é inválida.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'candidate_count' => count($this->convergenceCandidates)
                + count($this->coreCandidates)
                + count($this->discardedCandidates),
            'document_id' => $this->documentId,
            'document' => $this->documentTitle,
            'mean' => $this->mean,
            'standard_deviation' => $this->standardDeviation,
            'coefficient_of_variation' => $this->coefficientOfVariation,
            'convergence_range' => [
                'lower_bound' => $this->mean,
                'upper_bound' => $this->mean + $this->standardDeviation,
            ],
            'selected_region' => $this->selectedRegion,
            'selected_count' => count($this->selectedCandidates),
            'core' => array_map(
                static fn (ContextCandidate $candidate): array => $candidate->toArray(),
                $this->coreCandidates
            ),
            'convergence' => array_map(
                static fn (ContextCandidate $candidate): array => $candidate->toArray(),
                $this->convergenceCandidates
            ),
            'discarded' => array_map(
                static fn (ContextCandidate $candidate): array => $candidate->toArray(),
                $this->discardedCandidates
            ),
        ];
    }

    public function forDocument(string $documentId, string $documentTitle): self
    {
        if (trim($documentId) === '' || trim($documentTitle) === '') {
            throw new QueryException('O documento da análise estatística é inválido.');
        }

        return new self(
            $this->mean,
            $this->standardDeviation,
            $this->coefficientOfVariation,
            $this->convergenceCandidates,
            $this->coreCandidates,
            $this->discardedCandidates,
            $this->selectedCandidates,
            $this->selectedRegion,
            $documentId,
            $documentTitle
        );
    }
}
