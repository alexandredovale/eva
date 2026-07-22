<?php

declare(strict_types=1);

use Eva\Application\Ingestion\DocumentIngestionService;
use Eva\Application\Ingestion\IngestionResult;
use Eva\Infrastructure\Database\Connection;
use Eva\Infrastructure\Storage\DocumentStorage;

$container = require __DIR__ . '/bootstrap.php';
$database = Connection::create($container['database']);
$storage = new DocumentStorage($container['ingestion']['document_storage']);
$service = new DocumentIngestionService(
    $database,
    $storage,
    $container['ingestion']['max_document_bytes']
);
$assertions = 0;
$token = bin2hex(random_bytes(6));
$fixtures = [
    [
        'name' => 'ingestion-' . $token . '.md',
        'title' => 'Teste Markdown',
        'content' => "Apresentação.\n\n# Parte 1\nConteúdo da parte.\n\n## Capítulo 1\nConteúdo do capítulo.",
        'format' => 'markdown',
        'nodes' => 3,
        'evidences' => 3,
    ],
    [
        'name' => 'ingestion-' . $token . '.json',
        'title' => 'Teste JSON',
        'content' => '{"parte":{"título":"Capítulo JSON","vazio":{}}}',
        'format' => 'json',
        'nodes' => 4,
        'evidences' => 1,
    ],
    [
        'name' => 'ingestion-' . $token . '.xml',
        'title' => 'Teste XML',
        'content' => '<obra><capitulo>Capítulo XML</capitulo><vazio/></obra>',
        'format' => 'xml',
        'nodes' => 4,
        'evidences' => 1,
    ],
];

/** @var list<IngestionResult> $results */
$results = [];
$baseline = tableCounts($database);

function assertIngestion(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array{documents: int, nodes: int, evidences: int, derivations: int} */
function tableCounts(PDO $database): array
{
    return [
        'documents' => (int) $database->query('SELECT COUNT(*) FROM documents')->fetchColumn(),
        'nodes' => (int) $database->query('SELECT COUNT(*) FROM document_nodes')->fetchColumn(),
        'evidences' => (int) $database->query('SELECT COUNT(*) FROM evidences')->fetchColumn(),
        'derivations' => (int) $database->query('SELECT COUNT(*) FROM evidence_derivations')->fetchColumn(),
    ];
}

try {
    foreach ($fixtures as $fixture) {
        $result = $service->ingest($fixture['name'], $fixture['content'], $fixture['title']);
        $results[] = $result;

        assertIngestion($result->format === $fixture['format'], 'Formato persistido incorretamente.');
        assertIngestion($result->nodeCount === $fixture['nodes'], 'Quantidade de nós incorreta.');
        assertIngestion(
            $result->primaryEvidenceCount === $fixture['evidences'],
            'Quantidade de evidências primárias incorreta.'
        );
        assertIngestion(
            preg_match('/^EVA-D\d{6,}$/', $result->documentPublicId) === 1,
            'Identificador público do documento inválido.'
        );
        assertIngestion(
            file_get_contents($storage->absolutePath($result->storagePath)) === $fixture['content'],
            'A fonte original armazenada foi alterada.'
        );
    }

    $documentIds = array_map(
        static fn (IngestionResult $result): int => $result->documentId,
        $results
    );
    $placeholders = implode(',', array_fill(0, count($documentIds), '?'));

    $statement = $database->prepare(
        'SELECT COUNT(*) FROM documents WHERE id IN (' . $placeholders . ') AND status = ?'
    );
    $statement->execute([...$documentIds, 'ready']);
    assertIngestion((int) $statement->fetchColumn() === 3, 'Todos os documentos devem terminar como ready.');

    $statement = $database->prepare(
        'SELECT COUNT(*) FROM document_nodes WHERE document_id IN (' . $placeholders . ') AND parent_id IS NULL'
    );
    $statement->execute($documentIds);
    assertIngestion((int) $statement->fetchColumn() === 3, 'Cada documento deve possuir um único nó raiz.');

    $statement = $database->prepare(
        'SELECT COUNT(*)
           FROM evidences
          WHERE document_id IN (' . $placeholders . ')
            AND evidence_class = ?
            AND evidence_type = ?
            AND status = ?
            AND summary IS NULL'
    );
    $statement->execute([...$documentIds, 'primary', 'node_content', 'validated']);
    assertIngestion((int) $statement->fetchColumn() === 5, 'Evidências primárias devem ser literais e validadas.');

    $statement = $database->prepare(
        'SELECT COUNT(*) FROM evidences WHERE document_id IN (' . $placeholders . ') AND public_id NOT REGEXP ?'
    );
    $statement->execute([...$documentIds, '^EVA-E[0-9]{6,}$']);
    assertIngestion((int) $statement->fetchColumn() === 0, 'Identificador público de evidência inválido.');

    $statement = $database->prepare(
        'SELECT COUNT(*)
           FROM evidences e
           JOIN document_nodes n ON n.id = e.node_id
          WHERE e.document_id IN (' . $placeholders . ')
            AND (e.content <> n.content OR e.source_hash <> n.source_hash)'
    );
    $statement->execute($documentIds);
    assertIngestion((int) $statement->fetchColumn() === 0, 'A evidência primária deve reproduzir seu nó de origem.');

    assertIngestion(
        tableCounts($database)['derivations'] === $baseline['derivations'],
        'A ingestão primária não deve antecipar derivações.'
    );
} finally {
    $findDocuments = $database->prepare(
        'SELECT id, storage_path FROM documents WHERE original_name = :original_name'
    );
    $deleteDocument = $database->prepare('DELETE FROM documents WHERE id = :id');

    foreach ($fixtures as $fixture) {
        $findDocuments->execute(['original_name' => $fixture['name']]);

        foreach ($findDocuments->fetchAll() as $document) {
            $deleteDocument->execute(['id' => $document['id']]);

            if (is_string($document['storage_path']) && $document['storage_path'] !== '') {
                $storage->remove($document['storage_path']);
            }
        }
    }
}

$finalCounts = tableCounts($database);
assertIngestion($finalCounts === $baseline, 'O teste deve restaurar o estado anterior do banco.');

echo sprintf("Persistência documental validada com %d asserções.\n", $assertions);
