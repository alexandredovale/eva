<?php

declare(strict_types=1);

use Eva\Application\Access\AccessManagementService;
use Eva\Application\Access\AuthService;
use Eva\Application\Ingestion\DocumentIngestionService;
use Eva\Application\Product\ContentDeletionService;
use Eva\Application\Queue\ProcessingQueueService;
use Eva\Infrastructure\Database\Connection;
use Eva\Infrastructure\Storage\DocumentStorage;
use Eva\Infrastructure\Storage\FigureStorage;

$container = require __DIR__ . '/bootstrap.php';
$database = Connection::create($container['database']);
$storage = new DocumentStorage($container['ingestion']['document_storage']);
$figureStorage = new FigureStorage(
    $container['ingestion']['figure_storage'],
    $container['ingestion']['max_figure_bytes']
);
$ingestion = new DocumentIngestionService($database, $storage, $container['ingestion']['max_document_bytes']);
$deletion = new ContentDeletionService($database, $storage, $figureStorage);
$management = new AccessManagementService(
    $database,
    new AuthService($database, $container['security'])
);
$assertions = 0;
$documentResults = [];
$projectIds = [];
$userId = null;

function assertDeletion(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @param list<int> $documentIds */
function countForDocuments(PDO $database, string $table, array $documentIds): int
{
    if ($documentIds === []) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($documentIds), '?'));
    $column = $table === 'documents' ? 'id' : 'document_id';
    $statement = $database->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} IN ({$placeholders})");
    $statement->execute($documentIds);

    return (int) $statement->fetchColumn();
}

try {
    $suffix = bin2hex(random_bytes(5));

    foreach (['Primeira obra', 'Segunda obra'] as $index => $title) {
        $documentResults[] = $ingestion->ingest(
            "deletion-{$suffix}-{$index}.md",
            "# {$title}\n\nConteúdo temporário para validar exclusão em cascata {$index}.",
            "{$title} {$suffix}"
        );
    }

    $documentIds = array_map(static fn ($result): int => $result->documentId, $documentResults);
    $mainProject = $management->saveProject(null, 'Projeto exclusao ' . $suffix, $documentIds);
    $sharedProject = $management->saveProject(null, 'Projeto compartilhado ' . $suffix, [$documentIds[0]]);
    $projectIds = [(int) $mainProject['id'], (int) $sharedProject['id']];
    $createdUser = $management->createUser('delete_' . $suffix, 'Senha-exclusao-123');
    $userId = (int) $createdUser['user']['id'];
    $management->setPermissions($userId, [$projectIds[0]], [$documentIds[1]]);

    $primaryStatement = $database->prepare(
        "SELECT id FROM evidences WHERE document_id = :document_id AND evidence_class = 'primary' LIMIT 1"
    );
    $primaryStatement->execute(['document_id' => $documentIds[0]]);
    $primaryId = (int) $primaryStatement->fetchColumn();
    $embedding = $database->prepare(
        'INSERT INTO evidence_embeddings (evidence_id, model, dimensions, vector_data, content_hash)
         VALUES (:evidence_id, :model, 3, :vector_data, :content_hash)'
    );
    $embedding->execute([
        'evidence_id' => $primaryId,
        'model' => 'deletion-test',
        'vector_data' => '[1,0,0]',
        'content_hash' => hash('sha256', 'deletion-vector-' . $suffix),
    ]);
    (new ProcessingQueueService($database))->enqueue($documentIds[0], 'embeddings', 'deletion-test-' . $suffix);

    foreach ($documentResults as $result) {
        assertDeletion(is_file($storage->absolutePath($result->storagePath)), 'A fonte temporária não foi armazenada.');
        $figureDirectory = $container['ingestion']['figure_storage']
            . DIRECTORY_SEPARATOR . $result->documentPublicId;
        if (!mkdir($figureDirectory, 0775, true) && !is_dir($figureDirectory)) {
            throw new RuntimeException('Não foi possível preparar a figura temporária.');
        }
        file_put_contents($figureDirectory . DIRECTORY_SEPARATOR . 'temporaria.png', 'fixture');
    }

    $deleted = $deletion->deleteProject($projectIds[0]);
    assertDeletion($deleted['documents_deleted'] === 2, 'A exclusão do projeto não removeu todas as obras.');
    assertDeletion($deleted['nodes_deleted'] >= 2, 'Os nós não foram contabilizados na exclusão.');
    assertDeletion($deleted['evidences_deleted'] >= 2, 'As evidências não foram contabilizadas na exclusão.');
    assertDeletion($deleted['embeddings_deleted'] === 1, 'O embedding primário não foi contabilizado.');
    assertDeletion($deleted['jobs_deleted'] === 1, 'O trabalho de processamento não foi contabilizado.');
    assertDeletion($deleted['storage_cleanup_failures'] === 0, 'A limpeza das fontes apresentou falha.');
    assertDeletion(countForDocuments($database, 'documents', $documentIds) === 0, 'As obras permaneceram no banco.');
    assertDeletion(countForDocuments($database, 'document_nodes', $documentIds) === 0, 'Os nós permaneceram no banco.');
    assertDeletion(countForDocuments($database, 'evidences', $documentIds) === 0, 'As evidências permaneceram no banco.');
    assertDeletion(countForDocuments($database, 'processing_jobs', $documentIds) === 0, 'Os trabalhos permaneceram no banco.');
    assertDeletion((int) $database->query("SELECT COUNT(*) FROM evidence_embeddings WHERE evidence_id = {$primaryId}")->fetchColumn() === 0, 'O embedding permaneceu no banco.');
    assertDeletion((int) $database->query("SELECT COUNT(*) FROM projects WHERE id = {$projectIds[0]}")->fetchColumn() === 0, 'O projeto principal permaneceu no banco.');
    assertDeletion((int) $database->query("SELECT COUNT(*) FROM projects WHERE id = {$projectIds[1]}")->fetchColumn() === 1, 'O outro projeto foi removido indevidamente.');
    assertDeletion((int) $database->query("SELECT COUNT(*) FROM project_documents WHERE project_id = {$projectIds[1]}")->fetchColumn() === 0, 'O vínculo compartilhado da obra permaneceu no outro projeto.');
    assertDeletion((int) $database->query("SELECT COUNT(*) FROM user_projects WHERE user_id = {$userId}")->fetchColumn() === 0, 'A permissão de projeto permaneceu no banco.');
    assertDeletion((int) $database->query("SELECT COUNT(*) FROM user_documents WHERE user_id = {$userId}")->fetchColumn() === 0, 'A permissão de obra permaneceu no banco.');

    foreach ($documentResults as $result) {
        assertDeletion(!is_file($storage->absolutePath($result->storagePath)), 'A fonte excluída permaneceu no armazenamento.');
        assertDeletion(
            !is_dir($container['ingestion']['figure_storage'] . DIRECTORY_SEPARATOR . $result->documentPublicId),
            'O diretório de figuras permaneceu após a exclusão da obra.'
        );
    }

    $standalone = $ingestion->ingest(
        'standalone-' . $suffix . '.md',
        "# Obra isolada\n\nConteúdo temporário isolado.",
        'Obra isolada ' . $suffix
    );
    $documentResults[] = $standalone;
    $standaloneFigureDirectory = $container['ingestion']['figure_storage']
        . DIRECTORY_SEPARATOR . $standalone->documentPublicId;
    if (!mkdir($standaloneFigureDirectory, 0775, true) && !is_dir($standaloneFigureDirectory)) {
        throw new RuntimeException('Não foi possível preparar a figura isolada temporária.');
    }
    file_put_contents($standaloneFigureDirectory . DIRECTORY_SEPARATOR . 'temporaria.png', 'fixture');
    $standaloneDeleted = $deletion->deleteDocument($standalone->documentId);
    assertDeletion($standaloneDeleted['documents_deleted'] === 1, 'A exclusão individual não removeu a obra.');
    assertDeletion((int) $database->query("SELECT COUNT(*) FROM documents WHERE id = {$standalone->documentId}")->fetchColumn() === 0, 'A obra individual permaneceu no banco.');
    assertDeletion(!is_file($storage->absolutePath($standalone->storagePath)), 'A fonte individual permaneceu no armazenamento.');
    assertDeletion(!is_dir($standaloneFigureDirectory), 'As figuras da obra individual permaneceram no armazenamento.');
} finally {
    foreach ($projectIds as $projectId) {
        $statement = $database->prepare('DELETE FROM projects WHERE id = :id');
        $statement->execute(['id' => $projectId]);
    }

    if ($userId !== null) {
        $statement = $database->prepare('DELETE FROM users WHERE id = :id');
        $statement->execute(['id' => $userId]);
    }

    foreach ($documentResults as $result) {
        $statement = $database->prepare('DELETE FROM documents WHERE id = :id');
        $statement->execute(['id' => $result->documentId]);

        try {
            $storage->remove($result->storagePath);
        } catch (Throwable) {
        }

        try {
            $figureStorage->removeDocument($result->documentPublicId);
        } catch (Throwable) {
        }
    }
}

echo sprintf("Exclusão em cascata validada com %d asserções.\n", $assertions);
