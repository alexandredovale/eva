<?php

declare(strict_types=1);

namespace Eva\Application\Query;

final readonly class QueryLocalKappaAnalysis
{
    /**
     * @param list<ContextCandidate> $selectedCandidates
     * @param list<float> $scores
     * @param list<float> $normalizedRanks
     * @param list<float> $normalizedSimilarities
     * @param list<float> $gaps
     * @param list<?float> $gapZScores
     */
    public function __construct(
        public string $status,
        public array $selectedCandidates,
        public array $scores,
        public array $normalizedRanks,
        public array $normalizedSimilarities,
        public array $gaps,
        public float $gapMean,
        public float $gapStandardDeviation,
        public array $gapZScores,
        public ?int $candidateKappa,
        public ?int $kappa
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'hierarchical_candidate_count' => count($this->scores),
            'scores_sorted' => $this->scores,
            'normalized_x' => $this->normalizedRanks,
            'normalized_y' => $this->normalizedSimilarities,
            'gaps' => $this->gaps,
            'gap_mean' => $this->gapMean,
            'gap_standard_deviation' => $this->gapStandardDeviation,
            'gap_z_scores' => $this->gapZScores,
            'candidate_kappa' => $this->candidateKappa,
            'kappa' => $this->kappa,
            'selected_population_size' => count($this->selectedCandidates),
        ];
    }
}
