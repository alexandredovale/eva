<?php

declare(strict_types=1);

use Eva\Application\Cognitive\CognitiveBuildException;
use Eva\Application\Cognitive\EmbeddingBatchResult;
use Eva\Application\Cognitive\EmbeddingInputGuard;
use Eva\Application\Cognitive\EmbeddingProviderInterface;
use Eva\Application\Cognitive\EmbeddingVector;
use Eva\Application\Cognitive\EvidenceEmbeddingService;
use Eva\Application\Cognitive\StructuredEmbeddingTextBuilder;
use Eva\Application\Cognitive\StructuredEmbeddingUnit;
use Eva\Application\Ingestion\DocumentIngestionService;
use Eva\Application\Query\DocumentContextRetriever;
use Eva\Infrastructure\Database\Connection;
use Eva\Infrastructure\Storage\DocumentStorage;

$container = require __DIR__ . '/bootstrap.php';
$database = Connection::create($container['database']);
$storage = new DocumentStorage($container['ingestion']['document_storage']);
$ingestion = new DocumentIngestionService($database, $storage, $container['ingestion']['max_document_bytes']);
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
    $tables = ['documents', 'document_nodes', 'evidences', 'evidence_embeddings'];
    $counts = [];

    foreach ($tables as $table) {
        $counts[$table] = (int) $database->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }

    return $counts;
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

    $embeddingProvider = new FakeEmbeddingProvider();
    $embeddingService = new EvidenceEmbeddingService(
        database: $database,
        provider: $embeddingProvider,
        maxUnitsPerBatch: 64
    );
    $firstEmbeddingBuild = $embeddingService->buildForDocument($documentId);

    assertCognitiveBuild($firstEmbeddingBuild->eligibleUnits === 77, 'Somente evidências primárias devem ser elegíveis.');
    assertCognitiveBuild($firstEmbeddingBuild->createdEmbeddings === 77, 'Quantidade de embeddings primários criada é inválida.');
    assertCognitiveBuild(count($embeddingProvider->batches) === 2, 'Embeddings devem ser persistidos em lotes observáveis.');
    assertCognitiveBuild(array_sum(array_map('count', $embeddingProvider->batches)) === 77, 'Os lotes perderam evidências primárias.');
    assertCognitiveBuild(max(array_map('count', $embeddingProvider->batches)) === 64, 'O limite de unidades por lote não foi respeitado.');
    assertCognitiveBuild(
        max(array_map(
            static fn (StructuredEmbeddingUnit $unit): int => strlen($unit->text),
            array_merge(...$embeddingProvider->batches)
        )) > 5_000,
        'A evidência primária extensa foi cortada antes do embedding.'
    );
    assertCognitiveBuild($firstEmbeddingBuild->inputTokens === 1_554, 'Uso de tokens dos lotes não foi acumulado.');

    $statement = $database->prepare(
        "SELECT COUNT(*) AS total, MIN(ee.dimensions) AS min_dimensions, MAX(ee.dimensions) AS max_dimensions
           FROM evidence_embeddings ee
           JOIN evidences e ON e.id = ee.evidence_id
          WHERE e.document_id = :document_id
            AND e.evidence_class = 'primary'
            AND ee.model = 'fake-embedding-v1'"
    );
    $statement->execute(['document_id' => $documentId]);
    $embeddingStats = $statement->fetch();
    assertCognitiveBuild((int) $embeddingStats['total'] === 77, 'Embeddings primários versionados não foram persistidos.');
    assertCognitiveBuild(
        (int) $embeddingStats['min_dimensions'] === 3 && (int) $embeddingStats['max_dimensions'] === 3,
        'Dimensões persistidas inválidas.'
    );

    $secondEmbeddingBuild = $embeddingService->buildForDocument($documentId);
    assertCognitiveBuild($secondEmbeddingBuild->createdEmbeddings === 0, 'Embeddings idênticos não devem ser regenerados.');
    assertCognitiveBuild($secondEmbeddingBuild->reusedEmbeddings === 77, 'Embeddings existentes devem ser reutilizados.');
    assertCognitiveBuild(count($embeddingProvider->batches) === 2, 'A segunda execução não deve consumir o provedor.');

    $setPrimaryVector = $database->prepare(
        "UPDATE evidence_embeddings ee
            JOIN evidences e ON e.id = ee.evidence_id
            SET ee.vector_data = '[1,0,0]'
          WHERE e.document_id = :document_id
            AND e.evidence_class = 'primary'
            AND ee.model = 'fake-embedding-v1'"
    );
    $setPrimaryVector->execute(['document_id' => $documentId]);
    $semanticContext = (new DocumentContextRetriever($database, $embeddingProvider))
        ->retrieve($documentId, 'tema semanticamente organizado', 8, 0);
    assertCognitiveBuild(
        array_filter(
            $semanticContext->routingPoints,
            static fn (string $point): bool => str_contains($point, ':primary:node_content')
        ) !== [],
        'A recuperação conceitual deve usar evidências primárias como pontos semânticos.'
    );
    assertCognitiveBuild($semanticContext->evidences !== [], 'A recuperação orientada às fontes deve retornar evidências primárias.');

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

    assertCognitiveBuild($semanticUnits === ['primary:node_content' => 77], 'A construção não deve criar evidências derivadas.');

    $oversizedStatement = $database->prepare(
        "SELECT e.id, e.public_id, e.evidence_class, e.evidence_type, e.content,
                d.title AS document_title, n.node_type, n.title AS node_title,
                n.structural_path, n.source_reference
           FROM evidences e
           JOIN documents d ON d.id = e.document_id
           JOIN document_nodes n ON n.id = e.node_id
          WHERE e.document_id = :document_id
          ORDER BY CHAR_LENGTH(e.content) DESC
          LIMIT 1"
    );
    $oversizedStatement->execute(['document_id' => $documentId]);
    $oversized = $oversizedStatement->fetch();
    assertCognitiveBuild(is_array($oversized), 'O cenário de limite exige uma evidência primária.');
    $oversizedUnit = (new StructuredEmbeddingTextBuilder())->build($oversized);
    $policyTokenLimit = 256;
    $guard = new EmbeddingInputGuard($policyTokenLimit);
    assertCognitiveBuild(!$guard->isCompatible($oversizedUnit), 'A evidência extensa deve exceder o limite reduzido do teste.');

    $deleteOversizedEmbedding = $database->prepare(
        'DELETE FROM evidence_embeddings WHERE evidence_id = :evidence_id AND model = :model'
    );
    $deleteOversizedEmbedding->execute([
        'evidence_id' => (int) $oversized['id'],
        'model' => $embeddingProvider->model(),
    ]);
    $limitedProvider = new FakeEmbeddingProvider();
    $limitFailure = null;

    try {
        (new EvidenceEmbeddingService(
            database: $database,
            provider: $limitedProvider,
            maxUnitsPerBatch: 64,
            maxInputTokens: $policyTokenLimit
        ))->buildForDocument($documentId);
    } catch (CognitiveBuildException $exception) {
        $limitFailure = $exception->getMessage();
    }

    assertCognitiveBuild(
        is_string($limitFailure)
        && str_contains($limitFailure, (string) $oversized['public_id'])
        && str_contains($limitFailure, 'subdivisão estrutural real'),
        'A evidência extensa não produziu o diagnóstico estrutural esperado.'
    );
    assertCognitiveBuild($limitedProvider->batches === [], 'A incompatibilidade deve falhar antes da primeira chamada ao provedor.');
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

echo sprintf("Construção cognitiva primária validada com %d asserções e zero chamadas pagas.\n", $assertions);
