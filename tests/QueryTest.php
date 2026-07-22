<?php

declare(strict_types=1);

use Eva\Application\Cognitive\EmbeddingBatchResult;
use Eva\Application\Cognitive\EmbeddingProviderInterface;
use Eva\Application\Cognitive\EmbeddingVector;
use Eva\Application\Cognitive\EvidenceEmbeddingService;
use Eva\Application\Cognitive\StructuredEmbeddingUnit;
use Eva\Application\Ingestion\DocumentIngestionService;
use Eva\Application\Query\DocumentContextRetriever;
use Eva\Application\Query\DocumentQueryService;
use Eva\Application\Query\GeneratedAnswer;
use Eva\Application\Query\InputType;
use Eva\Application\Query\InputTypeDetector;
use Eva\Application\Query\QueryAnswerProviderInterface;
use Eva\Application\Query\QueryContext;
use Eva\Application\Query\QueryException;
use Eva\Application\Query\RetrievedInteraction;
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
$originalName = 'query-synthetic-' . bin2hex(random_bytes(6)) . '.md';
$baseline = queryTableCounts($database);

function assertQuery(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array<string, int> */
function queryTableCounts(PDO $database): array
{
    $tables = ['documents', 'document_nodes', 'evidences', 'evidence_derivations', 'evidence_embeddings'];
    $counts = [];

    foreach ($tables as $table) {
        $counts[$table] = (int) $database->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }

    return $counts;
}

final class SemanticFakeEmbeddingProvider implements EmbeddingProviderInterface
{
    /** @var list<list<StructuredEmbeddingUnit>> */
    public array $batches = [];

    public function model(): string
    {
        return 'fake-query-embedding-v1';
    }

    public function embed(array $units): EmbeddingBatchResult
    {
        $this->batches[] = $units;
        $vectors = [];

        foreach ($units as $unit) {
            $normalized = mb_strtolower($unit->text, 'UTF-8');

            if (str_starts_with($unit->evidencePublicId, 'EVA-Q')
                && (str_contains($normalized, 'intelligence') || str_contains($normalized, 'instinct'))) {
                $vector = [1.0, 0.0, 0.0];
            } elseif (str_contains($normalized, '/intelligence-and-instinct')) {
                $vector = [1.0, 0.0, 0.0];
            } elseif (str_contains($normalized, 'intelligence') || str_contains($normalized, 'instinct')) {
                $vector = [0.7, 0.3, 0.0];
            } elseif (str_contains($normalized, 'data') || str_contains($normalized, 'control')) {
                $vector = [0.0, 1.0, 0.0];
            } else {
                $vector = [0.0, 0.0, 1.0];
            }

            $vectors[] = new EmbeddingVector(
                $unit->evidencePublicId,
                $this->model(),
                $vector,
                $unit->contentHash
            );
        }

        return new EmbeddingBatchResult($vectors, count($units));
    }
}

final class CitingFakeAnswerProvider implements QueryAnswerProviderInterface
{
    public int $calls = 0;

    public function model(): string
    {
        return 'fake-query-answer-v1';
    }

    public function answer(string $input, QueryContext $context): GeneratedAnswer
    {
        $this->calls++;
        $used = [$context->evidences[0]->publicId];
        $interactions = [];

        if ($context->understanding->has(InputType::Relational) && isset($context->evidences[2])) {
            $first = $context->evidences[0];
            $second = $context->evidences[1];
            $third = $context->evidences[2];
            $used = [$first->publicId, $second->publicId, $third->publicId];
            $interactions = [
                new RetrievedInteraction('simetry', 'Interação recíproca explícita de teste.', [
                    ['evidence_id' => $first->publicId, 'role' => 'participant', 'excerpt_reference' => $first->sourceReference, 'excerpt' => $first->content],
                    ['evidence_id' => $second->publicId, 'role' => 'participant', 'excerpt_reference' => $second->sourceReference, 'excerpt' => $second->content],
                ]),
                new RetrievedInteraction('assimetry', 'Interação orientada explícita de teste.', [
                    ['evidence_id' => $first->publicId, 'role' => 'origin', 'excerpt_reference' => $first->sourceReference, 'excerpt' => $first->content],
                    ['evidence_id' => $third->publicId, 'role' => 'destination', 'excerpt_reference' => $third->sourceReference, 'excerpt' => $third->content],
                ]),
            ];
        }

        $citations = implode(' ', array_map(static fn (string $id): string => '[' . $id . ']', $used));

        return new GeneratedAnswer(
            'A resposta está limitada ao conteúdo documental recuperado ' . $citations . '.',
            $used,
            $interactions
        );
    }
}

final class InvalidCitationAnswerProvider implements QueryAnswerProviderInterface
{
    public function model(): string
    {
        return 'invalid-query-answer-v1';
    }

    public function answer(string $input, QueryContext $context): GeneratedAnswer
    {
        return new GeneratedAnswer('Citação inexistente [EVA-E999999].', ['EVA-E999999']);
    }
}

final class MissingVisibleCitationAnswerProvider implements QueryAnswerProviderInterface
{
    public function model(): string
    {
        return 'missing-visible-citation-v1';
    }

    public function answer(string $input, QueryContext $context): GeneratedAnswer
    {
        return new GeneratedAnswer(
            'Resposta documental com fonte estruturada, mas sem marcador produzido pelo provedor.',
            [$context->evidences[0]->publicId]
        );
    }
}

final class CandidateLimitationAnswerProvider implements QueryAnswerProviderInterface
{
    public int $calls = 0;

    public function model(): string
    {
        return 'candidate-limitation-v1';
    }

    public function answer(string $input, QueryContext $context): GeneratedAnswer
    {
        $this->calls++;

        return new GeneratedAnswer(
            'Os textos candidatos foram analisados, mas nenhum sustenta o aspecto solicitado.',
            [],
            [],
            ['Não foi localizada evidência suficiente no contexto recuperado para: aspecto lexical solicitado.']
        );
    }
}

final class EmptyCandidateAnswerProvider implements QueryAnswerProviderInterface
{
    public function model(): string
    {
        return 'empty-candidate-answer-v1';
    }

    public function answer(string $input, QueryContext $context): GeneratedAnswer
    {
        return new GeneratedAnswer('Nenhuma fonte candidata foi utilizada.', []);
    }
}

try {
    $ingested = $ingestion->ingest($originalName, $source, 'Synthetic Systems Operations Manual');
    $documentId = $ingested->documentId;
    $storagePath = $ingested->storagePath;
    assertQuery($ingested->nodeCount === 99 && $ingested->primaryEvidenceCount === 77, 'O documento real perdeu sua estrutura.');

    $embeddingProvider = new SemanticFakeEmbeddingProvider();
    $embeddingBuild = (new EvidenceEmbeddingService($database, $embeddingProvider))->buildForDocument($documentId);
    assertQuery($embeddingBuild->createdEmbeddings === 77, 'As evidências primárias não foram vetorizadas para consulta.');
    assertQuery(count($embeddingProvider->batches) === 1, 'A vetorização documental deve usar um lote.');
    assertQuery(
        max(array_map(static fn (StructuredEmbeddingUnit $unit): int => strlen($unit->text), $embeddingProvider->batches[0])) > 5_000,
        'Uma unidade documental extensa foi cortada antes do embedding.'
    );

    $targetStatement = $database->prepare(
        "SELECT e.id, e.public_id, e.content, n.structural_path,
                CASE
                    WHEN n.structural_path LIKE :intelligence_path THEN 'intelligence'
                    WHEN n.structural_path LIKE :matter_path THEN 'matter'
                    WHEN n.structural_path LIKE :vital_path THEN 'vital'
                END AS target
           FROM evidences e
           JOIN document_nodes n ON n.id = e.node_id
          WHERE e.document_id = :document_id
            AND e.evidence_class = 'primary'
            AND (n.structural_path LIKE :intelligence_filter
                 OR n.structural_path LIKE :matter_filter
                 OR n.structural_path LIKE :vital_filter)
          ORDER BY e.id ASC"
    );
    $targetStatement->execute([
        'document_id' => $documentId,
        'intelligence_path' => '/part-one/chapter-iv-lifecycle/intelligence-and-instinct/item-%',
        'matter_path' => '/part-one/chapter-ii-system-components/data-and-control/item-%',
        'vital_path' => '/part-one/chapter-iv-lifecycle/operation-and-shutdown/item-%',
        'intelligence_filter' => '/part-one/chapter-iv-lifecycle/intelligence-and-instinct/item-%',
        'matter_filter' => '/part-one/chapter-ii-system-components/data-and-control/item-%',
        'vital_filter' => '/part-one/chapter-iv-lifecycle/operation-and-shutdown/item-%',
    ]);
    $targets = [];

    foreach ($targetStatement->fetchAll() as $target) {
        $targets[$target['target']] ??= $target;
    }

    assertQuery(count($targets) === 3, 'As evidências-alvo do documento real não foram localizadas.');

    $intelligence = $targets['intelligence'];
    $matter = $targets['matter'];
    $vital = $targets['vital'];
    $detector = new InputTypeDetector();
    $directType = $detector->detect('Explique ' . $intelligence['public_id']);
    assertQuery($directType->has(InputType::Direct), 'O identificador explícito deve produzir input direto.');
    $relationalType = $detector->detect('Como inteligência e instinto interagem?');
    assertQuery($relationalType->has(InputType::Relational), 'A pergunta de interação deve ser relacional.');
    assertQuery($relationalType->has(InputType::Conceptual), 'Um input relacional sem localização também deve ser conceitual.');
    foreach ([
        'Como inteligência e instinto se relacionam?',
        'De que modo inteligência e instinto estão relacionados?',
        'How do intelligence and instinct interact?',
        'inteligência ↔ instinto',
    ] as $relationalInput) {
        assertQuery(
            $detector->detect($relationalInput)->has(InputType::Relational),
            'Uma formulação relacional válida não foi reconhecida: ' . $relationalInput
        );
    }
    foreach ([
        'Explique inteligência e instinto.',
        'Apresente um relatório sobre inteligência.',
        'Descreva o conteúdo interativo da obra.',
    ] as $nonRelationalInput) {
        assertQuery(
            !$detector->detect($nonRelationalInput)->has(InputType::Relational),
            'Um input não relacional foi classificado incorretamente: ' . $nonRelationalInput
        );
    }
    $broadType = $detector->detect('Apresente uma visão geral da obra.');
    assertQuery($broadType->has(InputType::Broad), 'A visão geral deve produzir input amplo.');

    $retriever = new DocumentContextRetriever($database, $embeddingProvider, $detector);
    $answerProvider = new CitingFakeAnswerProvider();
    $queryService = new DocumentQueryService($retriever, $answerProvider);

    $directResult = $queryService->query($documentId, 'Explique ' . $intelligence['public_id']);
    assertQuery(count($directResult->usedEvidences) === 1, 'A consulta direta deve usar a evidência citada.');
    assertQuery($directResult->usedEvidences[0]->publicId === $intelligence['public_id'], 'A consulta direta recuperou outra evidência.');
    assertQuery(count($embeddingProvider->batches) === 1, 'A consulta direta não deve consumir embedding de query.');

    $structuralContext = $retriever->retrieve($documentId, 'O que consta no capítulo IV sobre princípio vital?', 8, 20);
    assertQuery($structuralContext->understanding->has(InputType::Structural), 'A consulta de capítulo deve ser estrutural.');
    assertQuery(
        array_filter(
            $structuralContext->evidences,
            static fn ($evidence): bool => str_contains($evidence->structuralPath, '/capítulo-iv-do-princípio-vital')
        ) !== [],
        'O roteamento estrutural não desceu ao capítulo indicado.'
    );
    assertQuery(count($embeddingProvider->batches) === 1, 'A consulta estrutural não deve consumir embedding de query.');

    $broadContext = $retriever->retrieve($documentId, 'Apresente uma visão geral da obra.', 6, 20);
    assertQuery($broadContext->understanding->has(InputType::Broad), 'A consulta ampla perdeu seu tipo.');
    assertQuery(count($broadContext->evidences) === 6, 'A consulta ampla deve respeitar o limite de unidades primárias.');
    assertQuery(str_starts_with($broadContext->routingPoints[0], 'node:') || str_starts_with($broadContext->routingPoints[0], 'root:'), 'A consulta ampla não iniciou na hierarquia.');

    $conceptualContext = $retriever->retrieve($documentId, 'inteligência e instinto', 5, 20);
    assertQuery($conceptualContext->understanding->has(InputType::Conceptual), 'A consulta temática deve ser conceitual.');
    assertQuery(
        array_filter(
            $conceptualContext->evidences,
            static fn ($evidence): bool => $evidence->id === (int) $intelligence['id']
        ) !== [],
        'A recuperação conceitual não incluiu a unidade adequada.'
    );
    assertQuery(count($embeddingProvider->batches) === 2, 'A consulta conceitual deve usar um embedding transitório.');

    $retriever->retrieve(
        $documentId,
        'Qual é a relação entre inteligência e instinto em simetry ou assimetry?',
        5,
        20
    );
    $internalVocabularyBatch = $embeddingProvider->batches[array_key_last($embeddingProvider->batches)];
    $semanticQueryText = mb_strtolower($internalVocabularyBatch[0]->text, 'UTF-8');
    assertQuery(
        str_contains($semanticQueryText, 'simetry') && str_contains($semanticQueryText, 'assimetry'),
        'Os operadores cognitivos devem permanecer no contexto integral da consulta relacional.'
    );

    $relationalResult = $queryService->query($documentId, 'Como inteligência e instinto interagem?', 8, 20);
    assertQuery($relationalResult->understanding->has(InputType::Relational), 'A consulta relacional perdeu seu tipo.');
    assertQuery(count($relationalResult->simetryInteractions) >= 1, 'A expansão simetry não foi recuperada.');
    assertQuery(count($relationalResult->assimetryInteractions) >= 1, 'A expansão assimetry não foi recuperada.');
    $assimetryRoles = array_column($relationalResult->assimetryInteractions[0]->evidences, 'role');
    assertQuery($assimetryRoles[0] === 'origin' && $assimetryRoles[1] === 'destination', 'A orientação assimétrica foi perdida.');
    assertQuery(str_contains($relationalResult->answer, '[EVA-E'), 'A resposta validada deve mostrar citação primária.');
    assertQuery(count($relationalResult->usedEvidences) === 3, 'A resposta relacional deve validar todas as fontes primárias das interações.');

    $citationFallback = (new DocumentQueryService($retriever, new MissingVisibleCitationAnswerProvider()))
        ->query($documentId, 'Explique ' . $intelligence['public_id']);
    assertQuery(
        str_contains($citationFallback->answer, '[' . $intelligence['public_id'] . ']'),
        'A aplicação deve renderizar a citação estruturada ausente no texto do provedor.'
    );
    assertQuery(
        str_contains($citationFallback->answer, 'Evidências:'),
        'A citação acrescentada deve possuir rótulo documental explícito.'
    );

    $candidateLimitationProvider = new CandidateLimitationAnswerProvider();
    $candidateLimitationResult = (new DocumentQueryService($retriever, $candidateLimitationProvider))
        ->query($documentId, 'Explique ' . $intelligence['public_id']);
    assertQuery($candidateLimitationProvider->calls === 1, 'Candidatos recuperados devem ser analisados pelo provedor.');
    assertQuery($candidateLimitationResult->usedEvidences === [], 'Um candidato intruso não deve ser citado como evidência usada.');
    assertQuery(
        $candidateLimitationResult->limitations !== [],
        'A ausência de candidato utilizável deve retornar limitação sem descartar a análise.'
    );

    $emptyCandidateRejected = false;

    try {
        (new DocumentQueryService($retriever, new EmptyCandidateAnswerProvider()))
            ->query($documentId, 'Explique ' . $intelligence['public_id']);
    } catch (QueryException $exception) {
        $emptyCandidateRejected = str_contains($exception->getMessage(), 'nem limitação');
    }

    assertQuery(
        $emptyCandidateRejected,
        'Uma análise sem evidência utilizada deve justificar explicitamente a limitação documental.'
    );

    $invalidService = new DocumentQueryService($retriever, new InvalidCitationAnswerProvider());
    $invalidRejected = false;

    try {
        $invalidService->query($documentId, 'inteligência e instinto');
    } catch (QueryException $exception) {
        $invalidRejected = str_contains($exception->getMessage(), 'fora do contexto');
    }

    assertQuery($invalidRejected, 'Uma citação fora do contexto deve invalidar a resposta.');

    $callsBeforeMissing = $answerProvider->calls;
    $missingResult = $queryService->query($documentId, 'Explique EVA-E999999');
    assertQuery($missingResult->usedEvidences === [], 'Uma referência inexistente não deve produzir evidência.');
    assertQuery($missingResult->limitations !== [], 'A ausência documental deve ser informada.');
    assertQuery($answerProvider->calls === $callsBeforeMissing, 'A ausência de evidência não deve consumir o provedor de resposta.');
} finally {
    if ($documentId !== null) {
        $statement = $database->prepare('DELETE FROM documents WHERE id = :id');
        $statement->execute(['id' => $documentId]);
    }

    if (is_string($storagePath) && $storagePath !== '') {
        $storage->remove($storagePath);
    }
}

assertQuery(queryTableCounts($database) === $baseline, 'O teste de consulta deve restaurar o banco.');

echo sprintf("Consulta real validada com %d asserções e zero chamadas pagas.\n", $assertions);
