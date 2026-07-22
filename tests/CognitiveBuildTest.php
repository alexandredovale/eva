<?php

declare(strict_types=1);

use Eva\Application\Cognitive\EmbeddingBatchResult;
use Eva\Application\Cognitive\CognitiveBuildException;
use Eva\Application\Cognitive\EmbeddingInputGuard;
use Eva\Application\Cognitive\EmbeddingProviderInterface;
use Eva\Application\Cognitive\EmbeddingVector;
use Eva\Application\Cognitive\EvidenceEmbeddingService;
use Eva\Application\Cognitive\HierarchicalSummaryService;
use Eva\Application\Cognitive\StructuredEmbeddingUnit;
use Eva\Application\Cognitive\StructuredEmbeddingTextBuilder;
use Eva\Application\Cognitive\StructuredSummaryUnit;
use Eva\Application\Cognitive\SummaryProviderInterface;
use Eva\Application\Cognitive\SummaryResult;
use Eva\Application\Ingestion\DocumentIngestionService;
use Eva\Application\Query\DocumentContextRetriever;
use Eva\Infrastructure\Database\Connection;
use Eva\Infrastructure\Storage\DocumentStorage;

$container = require __DIR__ . '/bootstrap.php';
$database = Connection::create($container['database']);
$storage = new DocumentStorage($container['ingestion']['document_storage']);
$ingestion = new DocumentIngestionService(
    $database,
    $storage,
    $container['ingestion']['max_document_bytes']
);
$source = file_get_contents(__DIR__ . '/fixtures/synthetic_systems_manual.md');

if ($source === false) {
    throw new RuntimeException('Unable to read the public synthetic Markdown fixture.');
}

$assertions = 0;
$documentId = null;
$storagePath = null;
$originalName = 'cognitive-synthetic-' . bin2hex(random_bytes(6)) . '.md';
$baseline = cognitiveCounts($database);

function assertCognitiveBuild(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array<string, int> */
function cognitiveCounts(PDO $database): array
{
    $tables = ['documents', 'document_nodes', 'evidences', 'evidence_derivations', 'evidence_embeddings'];
    $counts = [];

    foreach ($tables as $table) {
        $counts[$table] = (int) $database->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }

    return $counts;
}

final class FakeSummaryProvider implements SummaryProviderInterface
{
    /** @var list<StructuredSummaryUnit> */
    public array $units = [];

    public function model(): string
    {
        return 'fake-summary-v1';
    }

    public function summarize(StructuredSummaryUnit $unit): SummaryResult
    {
        $this->units[] = $unit;
        $summary = sprintf(
            'Unidade %s; conteúdo próprio=%d; filhos=%d.',
            $unit->structuralPath,
            mb_strlen($unit->ownContent, 'UTF-8'),
            count($unit->childSummaries)
        );

        return new SummaryResult($summary, $this->model(), 10, 5);
    }
}

final class FakeEmbeddingProvider implements EmbeddingProviderInterface
{
    /** @var list<list<StructuredEmbeddingUnit>> */
    public array $batches = [];

    public function model(): string
    {
        return 'fake-embedding-v1';
    }

    public function embed(array $units): EmbeddingBatchResult
    {
        $this->batches[] = $units;
        $vectors = [];

        foreach ($units as $index => $unit) {
            $vector = str_starts_with($unit->evidencePublicId, 'EVA-Q')
                ? [1.0, 0.0, 0.0]
                : [(float) $index, (float) strlen($unit->text), 1.0];
            $vectors[] = new EmbeddingVector(
                $unit->evidencePublicId,
                $this->model(),
                $vector,
                $unit->contentHash
            );
        }

        return new EmbeddingBatchResult($vectors, 777);
    }
}

try {
    $ingested = $ingestion->ingest($originalName, $source, 'Synthetic Systems Operations Manual');
    $documentId = $ingested->documentId;
    $storagePath = $ingested->storagePath;
    assertCognitiveBuild($ingested->nodeCount === 99, 'O documento cognitivo deve manter os 99 nós reais.');
    assertCognitiveBuild($ingested->primaryEvidenceCount === 77, 'O documento cognitivo deve manter as 77 evidências primárias.');

    $summaryProvider = new FakeSummaryProvider();
    $summaryService = new HierarchicalSummaryService($database, $summaryProvider);
    $limitedSummaryBuild = $summaryService->buildForDocument($documentId, 5);
    assertCognitiveBuild($limitedSummaryBuild->createdSummaries === 5, 'O limite responsável de resumos não foi respeitado.');
    assertCognitiveBuild($limitedSummaryBuild->stoppedByLimit, 'A interrupção por limite deve ser informada.');
    assertCognitiveBuild(count($summaryProvider->units) === 5, 'O limite deve impedir chamadas adicionais ao provedor.');

    $firstSummaryBuild = $summaryService->buildForDocument($documentId);

    assertCognitiveBuild($firstSummaryBuild->eligibleNodes === 98, 'Os 98 nós com conteúdo próprio ou descendente devem produzir síntese; a folha vazia não deve ser inventada.');
    assertCognitiveBuild($firstSummaryBuild->createdSummaries === 93, 'A retomada deve criar apenas os 93 resumos restantes.');
    assertCognitiveBuild($firstSummaryBuild->reusedSummaries === 5, 'A retomada deve reutilizar os cinco resumos anteriores.');
    assertCognitiveBuild(count($summaryProvider->units) === 98, 'O provedor simulado deve receber cada nó uma vez.');
    assertCognitiveBuild($summaryProvider->units[97]->structuralPath === '/', 'O resumo raiz deve ser produzido por último.');
    assertCognitiveBuild($summaryProvider->units[97]->childSummaries !== [], 'A raiz deve receber sínteses de seus filhos estruturais.');
    assertCognitiveBuild(
        max(array_map(static fn (StructuredSummaryUnit $unit): int => strlen($unit->ownContent), $summaryProvider->units)) > 5_000,
        'A unidade extensa foi cortada antes do resumo.'
    );

    $statement = $database->prepare(
        "SELECT COUNT(*) FROM evidences
          WHERE document_id = :document_id
            AND evidence_class = 'derived'
            AND evidence_type = 'node_summary'
            AND generation_model = 'fake-summary-v1'
            AND status = 'generated'"
    );
    $statement->execute(['document_id' => $documentId]);
    assertCognitiveBuild((int) $statement->fetchColumn() === 98, 'Resumos derivados não foram persistidos.');

    $statement = $database->prepare(
        'SELECT COUNT(*)
           FROM evidence_derivations ed
           JOIN evidences e ON e.id = ed.evidence_id
          WHERE e.document_id = :document_id'
    );
    $statement->execute(['document_id' => $documentId]);
    assertCognitiveBuild((int) $statement->fetchColumn() === 174, 'A rastreabilidade ascendente está incompleta.');

    $secondSummaryBuild = $summaryService->buildForDocument($documentId);
    assertCognitiveBuild($secondSummaryBuild->createdSummaries === 0, 'Resumos idênticos não devem ser regenerados.');
    assertCognitiveBuild($secondSummaryBuild->reusedSummaries === 98, 'Resumos existentes devem ser reutilizados.');
    assertCognitiveBuild(count($summaryProvider->units) === 98, 'A segunda execução não deve consumir o provedor.');

    $embeddingProvider = new FakeEmbeddingProvider();
    $embeddingService = new EvidenceEmbeddingService(
        database: $database,
        provider: $embeddingProvider,
        maxUnitsPerBatch: 64
    );
    $firstEmbeddingBuild = $embeddingService->buildForDocument($documentId);

    assertCognitiveBuild($firstEmbeddingBuild->eligibleUnits === 175, 'Embeddings devem cobrir evidências primárias e resumos estruturais.');
    assertCognitiveBuild($firstEmbeddingBuild->createdEmbeddings === 175, 'Quantidade de embeddings criados inválida.');
    assertCognitiveBuild(count($embeddingProvider->batches) === 3, 'Embeddings devem ser persistidos em lotes observáveis.');
    assertCognitiveBuild(
        array_sum(array_map('count', $embeddingProvider->batches)) === 175,
        'Os lotes perderam unidades estruturais.'
    );
    assertCognitiveBuild(
        max(array_map('count', $embeddingProvider->batches)) === 64,
        'O limite de unidades por lote não foi respeitado.'
    );
    assertCognitiveBuild(
        max(array_map(
            static fn (StructuredEmbeddingUnit $unit): int => strlen($unit->text),
            array_merge(...$embeddingProvider->batches)
        )) > 5_000,
        'O embedding contextual extenso foi cortado.'
    );
    assertCognitiveBuild($firstEmbeddingBuild->inputTokens === 2_331, 'Uso de tokens dos lotes não foi acumulado.');

    $statement = $database->prepare(
        "SELECT COUNT(*), MIN(dimensions), MAX(dimensions)
           FROM evidence_embeddings ee
           JOIN evidences e ON e.id = ee.evidence_id
          WHERE e.document_id = :document_id AND ee.model = 'fake-embedding-v1'"
    );
    $statement->execute(['document_id' => $documentId]);
    $embeddingStats = $statement->fetch();
    assertCognitiveBuild((int) $embeddingStats['COUNT(*)'] === 175, 'Embeddings versionados não foram persistidos.');
    assertCognitiveBuild((int) $embeddingStats['MIN(dimensions)'] === 3 && (int) $embeddingStats['MAX(dimensions)'] === 3, 'Dimensões persistidas inválidas.');

    $secondEmbeddingBuild = $embeddingService->buildForDocument($documentId);
    assertCognitiveBuild($secondEmbeddingBuild->createdEmbeddings === 0, 'Embeddings idênticos não devem ser regenerados.');
    assertCognitiveBuild($secondEmbeddingBuild->reusedEmbeddings === 175, 'Embeddings existentes devem ser reutilizados.');
    assertCognitiveBuild(count($embeddingProvider->batches) === 3, 'A segunda execução não deve consumir embeddings.');

    $setPrimaryVector = $database->prepare(
        "UPDATE evidence_embeddings ee
            JOIN evidences e ON e.id = ee.evidence_id
            SET ee.vector_data = '[0,1,0]'
          WHERE e.document_id = :document_id
            AND e.evidence_class = 'primary'
            AND ee.model = 'fake-embedding-v1'"
    );
    $setPrimaryVector->execute(['document_id' => $documentId]);
    $setDerivedVector = $database->prepare(
        "UPDATE evidence_embeddings ee
            JOIN evidences e ON e.id = ee.evidence_id
            SET ee.vector_data = '[1,0,0]'
          WHERE e.document_id = :document_id
            AND e.evidence_class = 'derived'
            AND ee.model = 'fake-embedding-v1'"
    );
    $setDerivedVector->execute(['document_id' => $documentId]);
    $semanticContext = (new DocumentContextRetriever($database, $embeddingProvider))
        ->retrieve($documentId, 'tema semanticamente organizado', 8, 0);
    assertCognitiveBuild(
        array_filter(
            $semanticContext->routingPoints,
            static fn (string $point): bool => str_contains($point, ':derived:node_summary')
        ) !== [],
        'A recuperação conceitual deve usar evidências derivadas como pontos semânticos.'
    );
    assertCognitiveBuild($semanticContext->evidences !== [], 'A síntese recuperada deve resolver fontes primárias.');
    assertCognitiveBuild(
        array_filter(
            $semanticContext->evidences,
            static fn ($evidence): bool => !str_starts_with($evidence->publicId, 'EVA-E')
        ) === [],
        'A resposta deve receber somente as fontes primárias resolvidas.'
    );

    $classStatement = $database->prepare(
        'SELECT evidence_class, evidence_type, COUNT(*) AS total
           FROM evidences
          WHERE document_id = :document_id
          GROUP BY evidence_class, evidence_type'
    );
    $classStatement->execute(['document_id' => $documentId]);
    $semanticUnits = [];

    foreach ($classStatement->fetchAll() as $unit) {
        $semanticUnits[$unit['evidence_class'] . ':' . $unit['evidence_type']] = (int) $unit['total'];
    }

    assertCognitiveBuild(($semanticUnits['primary:node_content'] ?? 0) === 77, 'A classificação primária foi perdida.');
    assertCognitiveBuild(($semanticUnits['derived:node_summary'] ?? 0) === 98, 'A classificação derivada foi perdida.');

    $oversizedStatement = $database->prepare(
        "SELECT primary_evidence.id AS primary_id, primary_evidence.public_id AS primary_public_id,
                derived.id AS derived_id, derived.public_id AS derived_public_id
           FROM evidences primary_evidence
           JOIN evidence_derivations ed ON ed.source_evidence_id = primary_evidence.id
           JOIN evidences derived ON derived.id = ed.evidence_id
          WHERE primary_evidence.document_id = :document_id
            AND primary_evidence.evidence_class = 'primary'
            AND derived.evidence_class = 'derived'
            AND derived.evidence_type = 'node_summary'
          ORDER BY CHAR_LENGTH(primary_evidence.content) DESC
          LIMIT 1"
    );
    $oversizedStatement->execute(['document_id' => $documentId]);
    $oversized = $oversizedStatement->fetch();
    assertCognitiveBuild(is_array($oversized), 'O cenário de fallback exige uma evidência primária com síntese derivada.');

    $embeddingRecord = $database->prepare(
        "SELECT e.id, e.public_id, e.evidence_class, e.evidence_type, e.content, e.summary,
                d.title AS document_title, n.node_type, n.title AS node_title,
                n.structural_path, n.source_reference
           FROM evidences e
           JOIN documents d ON d.id = e.document_id
           JOIN document_nodes n ON n.id = e.node_id
          WHERE e.id = :id"
    );
    $embeddingRecord->execute(['id' => (int) $oversized['primary_id']]);
    $primaryUnit = (new StructuredEmbeddingTextBuilder())->build($embeddingRecord->fetch());
    $embeddingRecord->execute(['id' => (int) $oversized['derived_id']]);
    $derivedUnit = (new StructuredEmbeddingTextBuilder())->build($embeddingRecord->fetch());
    $policyTokenLimit = 256;

    while ($policyTokenLimit < 8_192) {
        $guard = new EmbeddingInputGuard($policyTokenLimit);

        if (!$guard->isCompatible($primaryUnit) && $guard->isCompatible($derivedUnit)) {
            break;
        }

        $policyTokenLimit++;
    }

    assertCognitiveBuild(
        $policyTokenLimit < 8_192,
        'Não foi possível estabelecer um limite que diferencie a fonte extensa de sua síntese.'
    );
    $deleteFallbackEmbeddings = $database->prepare(
        'DELETE FROM evidence_embeddings WHERE evidence_id IN (:primary_id, :derived_id)'
    );
    $deleteFallbackEmbeddings->execute([
        'primary_id' => (int) $oversized['primary_id'],
        'derived_id' => (int) $oversized['derived_id'],
    ]);
    $fallbackProvider = new FakeEmbeddingProvider();
    $fallbackResult = (new EvidenceEmbeddingService(
        database: $database,
        provider: $fallbackProvider,
        maxUnitsPerBatch: 64,
        maxInputTokens: $policyTokenLimit
    ))->buildForDocument($documentId);
    $fallbackUnits = array_merge(...$fallbackProvider->batches);

    assertCognitiveBuild($fallbackResult->representedByDerived === 1, 'A fonte extensa não foi representada pela síntese derivada.');
    assertCognitiveBuild($fallbackResult->createdEmbeddings === 1, 'Somente o embedding derivado pendente deveria ser criado.');
    assertCognitiveBuild(
        count($fallbackUnits) === 1 && $fallbackUnits[0]->evidencePublicId === $oversized['derived_public_id'],
        'O provedor deve receber somente a síntese derivada completa.'
    );
    assertCognitiveBuild(
        $fallbackUnits[0]->text === $derivedUnit->text,
        'A síntese derivada foi cortada ou alterada antes do embedding.'
    );
    $fallbackCount = $database->prepare(
        'SELECT COUNT(*) FROM evidence_embeddings WHERE evidence_id = :evidence_id AND model = :model'
    );
    $fallbackCount->execute(['evidence_id' => (int) $oversized['primary_id'], 'model' => $fallbackProvider->model()]);
    assertCognitiveBuild((int) $fallbackCount->fetchColumn() === 0, 'A fonte incompatível não deve possuir vetor truncado.');
    $fallbackCount->execute(['evidence_id' => (int) $oversized['derived_id'], 'model' => $fallbackProvider->model()]);
    assertCognitiveBuild((int) $fallbackCount->fetchColumn() === 1, 'O embedding da síntese derivada não foi persistido.');

    $removeFallbackLineage = $database->prepare(
        'DELETE FROM evidence_derivations WHERE evidence_id = :derived_id AND source_evidence_id = :primary_id'
    );
    $removeFallbackLineage->execute([
        'derived_id' => (int) $oversized['derived_id'],
        'primary_id' => (int) $oversized['primary_id'],
    ]);
    $unrepresentedProvider = new FakeEmbeddingProvider();
    $unrepresentedFailure = null;

    try {
        (new EvidenceEmbeddingService(
            database: $database,
            provider: $unrepresentedProvider,
            maxUnitsPerBatch: 64,
            maxInputTokens: $policyTokenLimit
        ))->buildForDocument($documentId);
    } catch (CognitiveBuildException $exception) {
        $unrepresentedFailure = $exception->getMessage();
    }

    assertCognitiveBuild(
        is_string($unrepresentedFailure)
        && str_contains($unrepresentedFailure, (string) $oversized['primary_public_id'])
        && str_contains($unrepresentedFailure, 'subdivisão estrutural real'),
        'A ausência de síntese derivada não produziu o diagnóstico estrutural esperado.'
    );
    assertCognitiveBuild(
        $unrepresentedProvider->batches === [],
        'A incompatibilidade sem representação derivada deve falhar antes da primeira chamada ao provedor.'
    );
} finally {
    if ($documentId !== null) {
        $statement = $database->prepare('DELETE FROM documents WHERE id = :id');
        $statement->execute(['id' => $documentId]);
    }

    if (is_string($storagePath) && $storagePath !== '') {
        $storage->remove($storagePath);
    }
}

assertCognitiveBuild(cognitiveCounts($database) === $baseline, 'O teste cognitivo deve restaurar o estado do banco.');

echo sprintf("Construção cognitiva real validada com %d asserções e zero chamadas pagas.\n", $assertions);
