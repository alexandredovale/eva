<?php

declare(strict_types=1);

use Eva\Application\Query\ContextCandidate;
use Eva\Application\Query\QueryLocalKappaDetector;

require __DIR__ . '/bootstrap.php';

$assertions = 0;

function assertKappa(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @param list<float> $scores @return list<ContextCandidate> */
function kappaCandidates(array $scores): array
{
    return array_map(
        static fn (float $score, int $index): ContextCandidate => new ContextCandidate(
            $index + 1,
            sprintf('EVA-E%06d', $index + 1),
            'derived',
            'node_summary',
            $score
        ),
        $scores,
        array_keys($scores)
    );
}

$detector = new QueryLocalKappaDetector();

$clearBreak = $detector->analyze(kappaCandidates([.95, .93, .91, .89, .87, .65, .64, .63, .62]));
assertKappa($clearBreak->status === 'identified', 'A ruptura clara não foi identificada.');
assertKappa($clearBreak->kappa === 5, 'A fronteira clara deveria terminar antes da queda para 0,65.');
assertKappa(count($clearBreak->selectedCandidates) === 5, 'A população legitimada pela ruptura está incorreta.');
assertKappa(count($clearBreak->scores) === 9, 'A análise não examinou a população hierárquica completa.');

$smooth = $detector->analyze(kappaCandidates([.90, .88, .86, .84, .82, .80, .78, .76]));
assertKappa($smooth->status === 'no_structural_break', 'Uma queda linear não deveria fabricar ruptura.');
assertKappa($smooth->kappa === null, 'Uma queda linear não deve produzir κq.');
assertKappa(count($smooth->selectedCandidates) === 8, 'Sem ruptura, a população completa deve seguir para o CIE.');

$nearUniform = $detector->analyze(kappaCandidates([.801, .800, .799, .798, .797, .796]));
assertKappa($nearUniform->status === 'no_structural_break', 'A população quase uniforme ganhou uma ruptura artificial.');

$twoRegimes = $detector->analyze(kappaCandidates([.96, .94, .92, .83, .81, .79, .55, .54, .53]));
assertKappa($twoRegimes->status === 'identified' && $twoRegimes->kappa === 6, 'Os dois regimes não preservaram a ruptura estrutural dominante.');

$initialOutlier = $detector->analyze(kappaCandidates([.99, .75, .74, .73, .72, .71]));
assertKappa($initialOutlier->status === 'identified' && $initialOutlier->kappa === 1, 'O outlier inicial deveria constituir sozinho a região anterior à ruptura.');

$homogeneous = $detector->analyze(kappaCandidates([.5, .5, .5, .5]));
assertKappa($homogeneous->status === 'no_dispersion', 'Scores idênticos devem declarar ausência de dispersão.');
assertKappa(count($homogeneous->selectedCandidates) === 4, 'A ausência de dispersão não deve truncar a população.');

$empty = $detector->analyze([]);
assertKappa($empty->status === 'empty' && $empty->selectedCandidates === [], 'N=0 deve produzir população vazia.');

foreach ([[.9], [.9, .4]] as $smallPopulation) {
    $small = $detector->analyze(kappaCandidates($smallPopulation));
    assertKappa($small->status === 'insufficient_population', 'N=1 ou N=2 deve declarar informação insuficiente.');
    assertKappa(count($small->selectedCandidates) === count($smallPopulation), 'N=1 ou N=2 não pode sofrer corte arbitrário.');
}

$repeat = $detector->analyze(kappaCandidates([.95, .93, .91, .89, .87, .65, .64, .63, .62]));
assertKappa($repeat->toArray() === $clearBreak->toArray(), 'κq deve ser determinístico para a mesma distribuição.');

echo sprintf("Fronteira query-local κq validada com %d asserções.\n", $assertions);
