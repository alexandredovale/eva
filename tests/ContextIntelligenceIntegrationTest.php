<?php

declare(strict_types=1);

use Eva\Application\Cognitive\EmbeddingBatchResult;
use Eva\Application\Cognitive\EmbeddingProviderInterface;
use Eva\Application\Cognitive\EmbeddingVector;
use Eva\Application\Query\DocumentContextRetriever;
use Eva\Application\Query\DocumentQueryService;
use Eva\Application\Query\GeneratedAnswer;
use Eva\Application\Query\QueryAnswerProviderInterface;
use Eva\Application\Query\QueryContext;
use Eva\Infrastructure\Database\Connection;

$container = require __DIR__ . '/bootstrap.php';
$database = Connection::create($container['database']);
$assertions = 0;

function assertContextIntegration(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class ContextIntegrationEmbeddingProvider implements EmbeddingProviderInterface
{
    public int $calls = 0;

    public function model(): string
    {
        return 'fake-cie-integration-v1';
    }

    public function embed(array $units): EmbeddingBatchResult
    {
        $this->calls++;

        if (count($units) !== 1 || !str_starts_with($units[0]->evidencePublicId, 'EVA-Q')) {
            throw new RuntimeException('A integração esperava somente o embedding transitório da consulta.');
        }

        return new EmbeddingBatchResult([
            new EmbeddingVector(
                $units[0]->evidencePublicId,
                $this->model(),
                [1.0, 0.0],
                $units[0]->contentHash
            ),
        ], 1);
    }
}

final class ContextIntegrationAnswerProvider implements QueryAnswerProviderInterface
{
    public int $calls = 0;

    public function model(): string
    {
        return 'fake-cie-answer-v1';
    }

    public function answer(string $input, QueryContext $context): GeneratedAnswer
    {
        $this->calls++;
        $ids = array_map(static fn ($evidence): string => $evidence->publicId, $context->evidences);
        $citations = implode(' ', array_map(static fn (string $id): string => '[' . $id . ']', $ids));

        return new GeneratedAnswer(
            'Resposta baseada no núcleo e na convergência complementar ' . $citations . '.',
            $ids
        );
    }
}

$database->beginTransaction();

try {
    $hash = hash('sha256', 'cie-integration-document');
    $statement = $database->prepare(
        "INSERT INTO documents
            (public_id, title, original_name, format, source_hash, status)
         VALUES (:public_id, :title, :original_name, 'markdown', :source_hash, 'ready')"
    );
    $statement->execute([
        'public_id' => 'pending-' . bin2hex(random_bytes(6)),
        'title' => 'Documento simples do CIE',
        'original_name' => 'cie-distribution.md',
        'source_hash' => $hash,
    ]);
    $documentId = (int) $database->lastInsertId();
    $documentPublicId = sprintf('EVA-D%06d', $documentId);
    $statement = $database->prepare('UPDATE documents SET public_id = :public_id WHERE id = :id');
    $statement->execute(['public_id' => $documentPublicId, 'id' => $documentId]);

    $nodeStatement = $database->prepare(
        'INSERT INTO document_nodes
            (document_id, node_type, title, structural_path, depth, sort_order, content, source_reference, source_hash)
         VALUES
            (:document_id, :node_type, :title, :structural_path, 1, :sort_order, :content, :source_reference, :source_hash)'
    );
    $evidenceStatement = $database->prepare(
        "INSERT INTO evidences
            (public_id, document_id, node_id, evidence_class, evidence_type, content, source_hash, status)
         VALUES
            (:public_id, :document_id, :node_id, 'primary', 'node_content', :content, :source_hash, 'validated')"
    );
    $embeddingStatement = $database->prepare(
        'INSERT INTO evidence_embeddings
            (evidence_id, model, dimensions, vector_data, content_hash)
         VALUES
            (:evidence_id, :model, 2, :vector_data, :content_hash)'
    );
    $similarities = [1.0, 0.6, 0.6, 0.6, 0.2];
    $evidenceIds = [];

    foreach ($similarities as $index => $similarity) {
        $content = sprintf('Unidade simples %d para validar a distribuição do contexto.', $index + 1);
        $nodeHash = hash('sha256', $content);
        $nodeStatement->execute([
            'document_id' => $documentId,
            'node_type' => 'paragraph',
            'title' => 'Unidade ' . ($index + 1),
            'structural_path' => '/unidade-' . ($index + 1),
            'sort_order' => $index + 1,
            'content' => $content,
            'source_reference' => 'linha ' . ($index + 1),
            'source_hash' => $nodeHash,
        ]);
        $nodeId = (int) $database->lastInsertId();
        $evidenceStatement->execute([
            'public_id' => 'pending-' . bin2hex(random_bytes(6)),
            'document_id' => $documentId,
            'node_id' => $nodeId,
            'content' => $content,
            'source_hash' => $nodeHash,
        ]);
        $evidenceId = (int) $database->lastInsertId();
        $evidencePublicId = sprintf('EVA-E%06d', $evidenceId);
        $database->prepare('UPDATE evidences SET public_id = :public_id WHERE id = :id')
            ->execute(['public_id' => $evidencePublicId, 'id' => $evidenceId]);
        $vector = [$similarity, sqrt(1 - ($similarity * $similarity))];
        $embeddingStatement->execute([
            'evidence_id' => $evidenceId,
            'model' => 'fake-cie-integration-v1',
            'vector_data' => json_encode($vector, JSON_THROW_ON_ERROR),
            'content_hash' => $nodeHash,
        ]);
        $evidenceIds[] = $evidenceId;
    }

    $embeddingProvider = new ContextIntegrationEmbeddingProvider();
    $retriever = new DocumentContextRetriever($database, $embeddingProvider);
    $context = $retriever->retrieve($documentId, 'Explique a distribuição estatística.', 8, 0);
    $analysis = $context->contextIntelligenceAnalyses[0] ?? null;

    assertContextIntegration($analysis !== null, 'A recuperação semântica não produziu análise do CIE.');
    assertContextIntegration(abs($analysis->mean - 0.6) < 1e-12, 'A integração alterou a média esperada.');
    assertContextIntegration($analysis->selectedRegion === 'core', 'A integração não selecionou o núcleo.');
    assertContextIntegration(
        array_column($analysis->selectedCandidates, 'evidenceId') === array_slice($evidenceIds, 0, 4),
        'A integração não preservou núcleo e convergência complementar.'
    );
    assertContextIntegration(
        array_column($context->evidences, 'id') === array_slice($evidenceIds, 0, 4)
            && ($context->evidenceSelection[$context->evidences[0]->publicId] ?? null) === 'core'
            && ($context->evidenceSelection[$context->evidences[1]->publicId] ?? null) === 'convergence',
        'O contexto primário final não preservou os papéis do CIE.'
    );
    assertContextIntegration(
        $analysis->documentId === $documentPublicId && $analysis->documentTitle === 'Documento simples do CIE',
        'A análise auditável perdeu a identidade do documento.'
    );

    $answerProvider = new ContextIntegrationAnswerProvider();
    $result = (new DocumentQueryService($retriever, $answerProvider))
        ->query($documentId, 'Explique a distribuição estatística.', 8, 0);
    $payload = $result->toArray();

    assertContextIntegration($answerProvider->calls === 1, 'A resposta integrada deveria executar uma vez.');
    assertContextIntegration(count($result->usedEvidences) === 4, 'A resposta não aceitou todo o contexto eleito.');
    assertContextIntegration(
        ($payload['context_intelligence'][0]['selected_region'] ?? null) === 'core'
            && ($payload['context_intelligence'][0]['candidate_count'] ?? null) === 5
            && ($payload['evidence_selection']['core_evidence_ids'] ?? []) === [$context->evidences[0]->publicId]
            && ($payload['evidences_used'][1]['selection_region'] ?? null) === 'convergence',
        'A API não expôs a análise transitória e os papéis eleitos esperados.'
    );
    assertContextIntegration($embeddingProvider->calls === 1, 'Consultas idênticas devem reutilizar o embedding transitório.');
} finally {
    $database->rollBack();
}

echo sprintf("Integração do CIE validada com %d asserções e zero chamadas externas.\n", $assertions);
