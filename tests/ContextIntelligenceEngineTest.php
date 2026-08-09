<?php

declare(strict_types=1);

use Eva\Application\Query\ContextCandidate;
use Eva\Application\Query\ContextIntelligenceEngine;

require __DIR__ . '/bootstrap.php';

$assertions = 0;

function assertContextIntelligence(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function candidate(int $id, float $similarity): ContextCandidate
{
    return new ContextCandidate($id, sprintf('EVA-E%06d', $id), 'primary', 'node_content', $similarity);
}

$engine = new ContextIntelligenceEngine();
$analysis = $engine->analyze([
    candidate(1, 1.0),
    candidate(2, 0.6),
    candidate(3, 0.6),
    candidate(4, 0.6),
    candidate(5, 0.2),
]);

assertContextIntelligence(abs($analysis->mean - 0.6) < 1e-12, 'A média populacional do CIE está incorreta.');
assertContextIntelligence(
    abs($analysis->standardDeviation - sqrt(0.32 / 5)) < 1e-12,
    'O desvio padrão populacional do CIE está incorreto.'
);
assertContextIntelligence(
    $analysis->coefficientOfVariation !== null
        && abs($analysis->coefficientOfVariation - ($analysis->standardDeviation / 0.6)) < 1e-12,
    'O coeficiente de variação do CIE está incorreto.'
);
assertContextIntelligence(
    array_column($analysis->coreCandidates, 'publicId') === ['EVA-E000001'],
    'O núcleo deve conter somente candidatos em s >= média + desvio padrão.'
);
assertContextIntelligence(
    array_column($analysis->convergenceCandidates, 'publicId') === [
        'EVA-E000002',
        'EVA-E000003',
        'EVA-E000004',
    ],
    'A faixa de convergência deve conter candidatos em média <= s < média + desvio padrão.'
);
assertContextIntelligence(
    array_column($analysis->discardedCandidates, 'publicId') === ['EVA-E000005'],
    'A região abaixo da média deve ser descartada.'
);
assertContextIntelligence(
    $analysis->selectedRegion === 'core'
        && array_column($analysis->selectedCandidates, 'publicId') === [
            'EVA-E000001',
            'EVA-E000002',
            'EVA-E000003',
            'EVA-E000004',
        ],
    'O núcleo deve preceder a convergência complementar no contexto final.'
);

$fallback = $engine->analyze([
    candidate(6, -1.0),
    candidate(7, 1.0),
    candidate(8, 1.0),
]);
assertContextIntelligence($fallback->coreCandidates === [], 'O cenário de fallback não deveria produzir núcleo.');
assertContextIntelligence(
    $fallback->selectedRegion === 'convergence'
        && array_column($fallback->selectedCandidates, 'publicId') === ['EVA-E000007', 'EVA-E000008'],
    'Na ausência de núcleo, o CIE deve selecionar a faixa de convergência.'
);

$zeroMean = $engine->analyze([
    candidate(9, -1.0),
    candidate(10, 0.0),
    candidate(11, 1.0),
]);
assertContextIntelligence(
    $zeroMean->mean === 0.0 && $zeroMean->coefficientOfVariation === null,
    'O CV deve ser indefinido quando a média for zero.'
);

$homogeneous = $engine->analyze([
    candidate(12, 0.5),
    candidate(13, 0.5),
]);
assertContextIntelligence(
    $homogeneous->standardDeviation === 0.0
        && array_column($homogeneous->coreCandidates, 'publicId') === ['EVA-E000012', 'EVA-E000013'],
    'Uma distribuição homogênea deve permanecer integralmente no núcleo.'
);

$empty = $engine->analyze([]);
assertContextIntelligence(
    $empty->selectedRegion === 'empty' && $empty->selectedCandidates === [],
    'Uma distribuição vazia deve produzir uma análise vazia e determinística.'
);

$serialized = $analysis->toArray();
assertContextIntelligence(
    $serialized['candidate_count'] === 5
        && $serialized['selected_count'] === 4
        && $serialized['selected_region'] === 'core',
    'A saída auditável do CIE perdeu suas contagens ou região selecionada.'
);

mt_srand(20260809);
$propertyChecksPassed = true;

for ($round = 0; $round < 300; $round++) {
    $count = 1 + ($round % 97);
    $population = [];

    for ($index = 0; $index < $count; $index++) {
        $similarity = (mt_rand(-1_000_000, 1_000_000) / 1_000_000);
        $population[] = candidate(20_000 + ($round * 100) + $index, $similarity);
    }

    usort($population, static fn (ContextCandidate $left, ContextCandidate $right): int =>
        ($right->similarity <=> $left->similarity) ?: ($left->evidenceId <=> $right->evidenceId)
    );
    $propertyAnalysis = $engine->analyze($population);
    $partitionIds = [
        ...array_column($propertyAnalysis->coreCandidates, 'evidenceId'),
        ...array_column($propertyAnalysis->convergenceCandidates, 'evidenceId'),
        ...array_column($propertyAnalysis->discardedCandidates, 'evidenceId'),
    ];
    $inputIds = array_column($population, 'evidenceId');
    sort($partitionIds);
    sort($inputIds);
    $expectedSelection = $propertyAnalysis->coreCandidates !== []
        ? [...$propertyAnalysis->coreCandidates, ...$propertyAnalysis->convergenceCandidates]
        : $propertyAnalysis->convergenceCandidates;

    if ($partitionIds !== $inputIds
        || count($partitionIds) !== count(array_unique($partitionIds))
        || array_column($propertyAnalysis->selectedCandidates, 'evidenceId')
            !== array_column($expectedSelection, 'evidenceId')
        || !is_finite($propertyAnalysis->mean)
        || !is_finite($propertyAnalysis->standardDeviation)
        || ($propertyAnalysis->coefficientOfVariation !== null
            && !is_finite($propertyAnalysis->coefficientOfVariation))) {
        $propertyChecksPassed = false;
        break;
    }

    if ($propertyAnalysis->standardDeviation > 1e-12
        && count($propertyAnalysis->coreCandidates) > (count($population) / 2)) {
        $propertyChecksPassed = false;
        break;
    }
}

assertContextIntelligence(
    $propertyChecksPassed,
    'As propriedades de partição, seleção, finitude e limite de Cantelli falharam.'
);

echo sprintf("Context Intelligence Engine validado com %d asserções.\n", $assertions);
