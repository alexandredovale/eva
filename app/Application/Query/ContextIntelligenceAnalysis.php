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
        public ?string $documentTitle = null,
        public ?QueryLocalKappaAnalysis $retrievalBoundary = null,
        public string $stage = 'hierarchical',
        public ?string $sourceRegion = null
    ) {
        if (!is_finite($this->mean)
            || !is_finite($this->standardDeviation)
            || ($this->coefficientOfVariation !== null && !is_finite($this->coefficientOfVariation))
            || !in_array($this->selectedRegion, ['core', 'convergence', 'empty'], true)
            || !in_array($this->stage, ['hierarchical', 'primary', 'global'], true)
            || ($this->sourceRegion !== null && !in_array($this->sourceRegion, ['core', 'convergence'], true))
            || ($this->stage !== 'primary' && $this->sourceRegion !== null)) {
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
            'stage' => $this->stage,
            'source_region' => $this->sourceRegion,
            'retrieval_boundary' => $this->retrievalBoundary?->toArray(),
            'mean' => $this->mean,
            'standard_deviation' => $this->standardDeviation,
            'coefficient_of_variation' => $this->coefficientOfVariation,
            'convergence_range' => [
                'lower_bound' => $this->mean,
                'upper_bound' => $this->mean + $this->standardDeviation,
            ],
            'selected_region' => $this->selectedRegion,
            'selected_count' => count($this->selectedCandidates),
            'core_count' => count($this->coreCandidates),
            'convergence_count' => count($this->convergenceCandidates),
            'discard_count' => count($this->discardedCandidates),
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
            $documentTitle,
            $this->retrievalBoundary,
            $this->stage,
            $this->sourceRegion
        );
    }

    public function withRetrievalBoundary(QueryLocalKappaAnalysis $retrievalBoundary): self
    {
        return new self(
            $this->mean,
            $this->standardDeviation,
            $this->coefficientOfVariation,
            $this->convergenceCandidates,
            $this->coreCandidates,
            $this->discardedCandidates,
            $this->selectedCandidates,
            $this->selectedRegion,
            $this->documentId,
            $this->documentTitle,
            $retrievalBoundary,
            $this->stage,
            $this->sourceRegion
        );
    }

    public function forPrimaryStage(string $sourceRegion): self
    {
        if (!in_array($sourceRegion, ['core', 'convergence'], true)) {
            throw new QueryException('A região de origem do CIE primário é inválida.');
        }

        return $this->forStage('primary', $sourceRegion);
    }

    public function forGlobalStage(): self
    {
        return $this->forStage('global', null);
    }

    private function forStage(string $stage, ?string $sourceRegion): self
    {
        return new self(
            $this->mean,
            $this->standardDeviation,
            $this->coefficientOfVariation,
            $this->convergenceCandidates,
            $this->coreCandidates,
            $this->discardedCandidates,
            $this->selectedCandidates,
            $this->selectedRegion,
            $this->documentId,
            $this->documentTitle,
            $this->retrievalBoundary,
            $stage,
            $sourceRegion
        );
    }
}
