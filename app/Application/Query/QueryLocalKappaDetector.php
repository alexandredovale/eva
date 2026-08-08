<?php

declare(strict_types=1);

namespace Eva\Application\Query;

final readonly class QueryLocalKappaDetector
{
    /** @param list<ContextCandidate> $candidates */
    public function analyze(array $candidates): QueryLocalKappaAnalysis
    {
        foreach ($candidates as $index => $candidate) {
            if (!$candidate instanceof ContextCandidate) {
                throw new QueryException('A fronteira query-local recebeu um candidato incompatível.');
            }

            if ($index > 0 && $candidate->similarity > $candidates[$index - 1]->similarity) {
                throw new QueryException('A população da fronteira query-local não está ordenada.');
            }
        }

        $scores = array_map(
            static fn (ContextCandidate $candidate): float => $candidate->similarity,
            $candidates
        );
        $count = count($scores);

        if ($count === 0) {
            return $this->result('empty', [], [], [], [], [], 0.0, 0.0, [], null, null);
        }

        if ($count < 3) {
            return $this->result(
                'insufficient_population',
                $candidates,
                $scores,
                $count === 1 ? [0.0] : [0.0, 1.0],
                $count === 1 ? [1.0] : ($scores[0] === $scores[1] ? [1.0, 1.0] : [1.0, 0.0]),
                $count === 1 ? [] : [$scores[0] - $scores[1]],
                $count === 1 ? 0.0 : $scores[0] - $scores[1],
                0.0,
                $count === 1 ? [] : [null],
                null,
                null
            );
        }

        $range = $scores[0] - $scores[$count - 1];
        $numericTolerance = max(1.0, abs($scores[0]), abs($scores[$count - 1]))
            * PHP_FLOAT_EPSILON * $count;
        $normalizedRanks = [];
        $normalizedSimilarities = [];
        $gaps = [];

        for ($index = 0; $index < $count; $index++) {
            $normalizedRanks[] = $index / ($count - 1);
            $normalizedSimilarities[] = $range <= $numericTolerance
                ? 1.0
                : ($scores[$index] - $scores[$count - 1]) / $range;

            if ($index < $count - 1) {
                $gaps[] = $scores[$index] - $scores[$index + 1];
            }
        }

        [$gapMean, $gapStandardDeviation, $gapZScores] = $this->gapStatistics($gaps);

        if ($range <= $numericTolerance) {
            return $this->result(
                'no_dispersion',
                $candidates,
                $scores,
                $normalizedRanks,
                $normalizedSimilarities,
                $gaps,
                $gapMean,
                $gapStandardDeviation,
                $gapZScores,
                null,
                null
            );
        }

        $distances = [];

        foreach ($normalizedRanks as $index => $rank) {
            // Distância perpendicular à reta y = 1 - x entre os extremos normalizados.
            $distances[] = abs($rank + $normalizedSimilarities[$index] - 1.0) / sqrt(2.0);
        }

        $maximumDistance = max($distances);
        $distanceTolerance = PHP_FLOAT_EPSILON * $count;

        if ($maximumDistance <= $distanceTolerance) {
            return $this->result(
                'no_structural_break',
                $candidates,
                $scores,
                $normalizedRanks,
                $normalizedSimilarities,
                $gaps,
                $gapMean,
                $gapStandardDeviation,
                $gapZScores,
                null,
                null
            );
        }

        $maximumDistanceIndexes = array_keys(array_filter(
            $distances,
            static fn (float $distance): bool => abs($distance - $maximumDistance) <= $distanceTolerance
        ));

        if (count($maximumDistanceIndexes) !== 1) {
            return $this->result(
                'ambiguous_break',
                $candidates,
                $scores,
                $normalizedRanks,
                $normalizedSimilarities,
                $gaps,
                $gapMean,
                $gapStandardDeviation,
                $gapZScores,
                null,
                null
            );
        }

        $kneeIndex = $maximumDistanceIndexes[0];
        $candidateKappa = $kneeIndex + 1;
        $adjacentGapIndexes = array_values(array_filter(
            [$kneeIndex - 1, $kneeIndex],
            static fn (int $index): bool => isset($gaps[$index])
        ));
        $maximumAdjacentGap = max(array_map(
            static fn (int $index): float => $gaps[$index],
            $adjacentGapIndexes
        ));
        $boundaryGapIndexes = array_values(array_filter(
            $adjacentGapIndexes,
            static fn (int $index): bool => abs($gaps[$index] - $maximumAdjacentGap) <= $numericTolerance
        ));

        if (count($boundaryGapIndexes) !== 1
            || $maximumAdjacentGap <= $gapMean + $gapStandardDeviation + $numericTolerance) {
            return $this->result(
                count($boundaryGapIndexes) === 1 ? 'no_structural_break' : 'ambiguous_break',
                $candidates,
                $scores,
                $normalizedRanks,
                $normalizedSimilarities,
                $gaps,
                $gapMean,
                $gapStandardDeviation,
                $gapZScores,
                $candidateKappa,
                null
            );
        }

        $kappa = $boundaryGapIndexes[0] + 1;

        return $this->result(
            'identified',
            array_slice($candidates, 0, $kappa),
            $scores,
            $normalizedRanks,
            $normalizedSimilarities,
            $gaps,
            $gapMean,
            $gapStandardDeviation,
            $gapZScores,
            $candidateKappa,
            $kappa
        );
    }

    /** @param list<float> $gaps @return array{float, float, list<?float>} */
    private function gapStatistics(array $gaps): array
    {
        if ($gaps === []) {
            return [0.0, 0.0, []];
        }

        $mean = array_sum($gaps) / count($gaps);
        $squaredDeviationSum = 0.0;

        foreach ($gaps as $gap) {
            $deviation = $gap - $mean;
            $squaredDeviationSum += $deviation * $deviation;
        }

        $standardDeviation = sqrt($squaredDeviationSum / count($gaps));
        $zScores = array_map(
            static fn (float $gap): ?float => $standardDeviation === 0.0
                ? null
                : ($gap - $mean) / $standardDeviation,
            $gaps
        );

        return [$mean, $standardDeviation, $zScores];
    }

    /**
     * @param list<ContextCandidate> $selectedCandidates
     * @param list<float> $scores
     * @param list<float> $normalizedRanks
     * @param list<float> $normalizedSimilarities
     * @param list<float> $gaps
     * @param list<?float> $gapZScores
     */
    private function result(
        string $status,
        array $selectedCandidates,
        array $scores,
        array $normalizedRanks,
        array $normalizedSimilarities,
        array $gaps,
        float $gapMean,
        float $gapStandardDeviation,
        array $gapZScores,
        ?int $candidateKappa,
        ?int $kappa
    ): QueryLocalKappaAnalysis {
        return new QueryLocalKappaAnalysis(
            $status,
            $selectedCandidates,
            $scores,
            $normalizedRanks,
            $normalizedSimilarities,
            $gaps,
            $gapMean,
            $gapStandardDeviation,
            $gapZScores,
            $candidateKappa,
            $kappa
        );
    }
}
