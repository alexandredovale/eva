<?php

declare(strict_types=1);

namespace Eva\Application\Query;

final readonly class ContextIntelligenceEngine
{
    /** @param list<ContextCandidate> $candidates */
    public function analyze(array $candidates): ContextIntelligenceAnalysis
    {
        if ($candidates === []) {
            return new ContextIntelligenceAnalysis(0.0, 0.0, null, [], [], [], [], 'empty');
        }

        foreach ($candidates as $candidate) {
            if (!$candidate instanceof ContextCandidate) {
                throw new QueryException('O CIE recebeu um candidato incompatível.');
            }
        }

        $count = count($candidates);
        $mean = array_sum(array_map(
            static fn (ContextCandidate $candidate): float => $candidate->similarity,
            $candidates
        )) / $count;
        $squaredDeviationSum = 0.0;

        foreach ($candidates as $candidate) {
            $deviation = $candidate->similarity - $mean;
            $squaredDeviationSum += $deviation * $deviation;
        }

        $standardDeviation = sqrt($squaredDeviationSum / $count);
        $coefficientOfVariation = $mean == 0.0 ? null : $standardDeviation / $mean;
        $upperBound = $mean + $standardDeviation;
        $comparisonTolerance = max(1.0, abs($mean), abs($upperBound)) * 1e-12;
        $convergence = [];
        $core = [];
        $discarded = [];

        foreach ($candidates as $candidate) {
            if ($candidate->similarity < $mean - $comparisonTolerance) {
                $discarded[] = $candidate;
            } elseif ($candidate->similarity < $upperBound - $comparisonTolerance) {
                $convergence[] = $candidate;
            } else {
                $core[] = $candidate;
            }
        }

        $selected = $core !== [] ? [...$core, ...$convergence] : $convergence;

        return new ContextIntelligenceAnalysis(
            $mean,
            $standardDeviation,
            $coefficientOfVariation,
            $convergence,
            $core,
            $discarded,
            $selected,
            $core !== [] ? 'core' : ($convergence !== [] ? 'convergence' : 'empty')
        );
    }
}
