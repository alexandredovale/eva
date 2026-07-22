<?php

declare(strict_types=1);

use Eva\Application\Ingestion\DocumentIngestionService;
use Eva\Application\Ingestion\Parser\MarkdownParser;
use Eva\Domain\Document\NormalizedNode;
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
$sourcePath = __DIR__ . '/fixtures/synthetic_systems_manual.md';
$source = file_get_contents($sourcePath);

if ($source === false) {
    throw new RuntimeException('Unable to read the public synthetic Markdown fixture.');
}

$assertions = 0;
$token = bin2hex(random_bytes(6));
$originalName = 'synthetic-systems-manual-' . $token . '.md';
$title = 'Synthetic Systems Operations Manual';
$documentId = null;
$storagePath = null;
$baseline = realDocumentCounts($database);

function assertRealDocument(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array{documents: int, nodes: int, evidences: int, derivations: int} */
function realDocumentCounts(PDO $database): array
{
    return [
        'documents' => (int) $database->query('SELECT COUNT(*) FROM documents')->fetchColumn(),
        'nodes' => (int) $database->query('SELECT COUNT(*) FROM document_nodes')->fetchColumn(),
        'evidences' => (int) $database->query('SELECT COUNT(*) FROM evidences')->fetchColumn(),
        'derivations' => (int) $database->query('SELECT COUNT(*) FROM evidence_derivations')->fetchColumn(),
    ];
}

/** @return array<string, NormalizedNode> */
function flattenRealNodes(NormalizedNode $node): array
{
    $nodes = [$node->structuralPath => $node];

    foreach ($node->children as $child) {
        $nodes += flattenRealNodes($child);
    }

    return $nodes;
}

try {
    $parsed = (new MarkdownParser())->parse($source, $title);
    $expectedNodes = flattenRealNodes($parsed->root);
    $expectedEvidenceCount = count(array_filter(
        $expectedNodes,
        static function (NormalizedNode $node): bool {
            $content = trim($node->content);
            return $content !== '' && $content !== '{}' && $content !== '[]';
        }
    ));

    $result = $service->ingest($originalName, $source, $title);
    $documentId = $result->documentId;
    $storagePath = $result->storagePath;

    assertRealDocument(
        hash('sha256', $source) === '9776a4edf271512051d618603a7a7979b9b29af5b17a77c58599dc4033c6b340',
        'O arquivo real de teste foi alterado sem atualização consciente do teste.'
    );
    assertRealDocument($result->nodeCount === count($expectedNodes), 'A árvore real perdeu nós estruturais.');
    assertRealDocument(
        $result->primaryEvidenceCount === $expectedEvidenceCount,
        'A quantidade de evidências do documento real é inconsistente.'
    );
    assertRealDocument(
        file_get_contents($storage->absolutePath($result->storagePath)) === $source,
        'A fonte real armazenada não corresponde byte a byte ao arquivo de teste.'
    );

    $statement = $database->prepare(
        'SELECT structural_path, content, source_reference, source_hash
           FROM document_nodes
          WHERE document_id = :document_id'
    );
    $statement->execute(['document_id' => $documentId]);
    $persistedNodes = array_column($statement->fetchAll(), null, 'structural_path');

    foreach ($expectedNodes as $path => $expectedNode) {
        assertRealDocument(isset($persistedNodes[$path]), 'Nó estrutural ausente: ' . $path);
        assertRealDocument(
            $persistedNodes[$path]['content'] === $expectedNode->content,
            'Conteúdo estrutural alterado: ' . $path
        );
        assertRealDocument(
            $persistedNodes[$path]['source_reference'] === $expectedNode->sourceReference,
            'Referência de origem alterada: ' . $path
        );
        assertRealDocument(
            $persistedNodes[$path]['source_hash'] === hash('sha256', $expectedNode->content),
            'Hash estrutural alterado: ' . $path
        );
    }

    foreach ([
        '/part-one',
        '/part-one/chapter-i-system-foundations/definitions',
        '/part-one/chapter-ii-system-components/data-and-control',
        '/part-one/chapter-iv-lifecycle/intelligence-and-instinct',
    ] as $requiredPath) {
        assertRealDocument(isset($persistedNodes[$requiredPath]), 'Caminho real não localizado: ' . $requiredPath);
    }

    $statement = $database->prepare(
        'SELECT COUNT(*)
           FROM evidences e
           JOIN document_nodes n ON n.id = e.node_id
          WHERE e.document_id = :document_id
            AND (e.content <> n.content OR e.source_hash <> n.source_hash OR e.summary IS NOT NULL)'
    );
    $statement->execute(['document_id' => $documentId]);
    assertRealDocument((int) $statement->fetchColumn() === 0, 'Evidências reais foram cortadas ou modificadas.');

    $statement = $database->prepare(
        'SELECT MAX(CHAR_LENGTH(content)) FROM evidences WHERE document_id = :document_id'
    );
    $statement->execute(['document_id' => $documentId]);
    assertRealDocument(
        (int) $statement->fetchColumn() > 5_000,
        'O conteúdo estrutural extenso não deve ser cortado por limite de caracteres.'
    );

    assertRealDocument(
        realDocumentCounts($database)['derivations'] === $baseline['derivations'],
        'A ingestão real não deve antecipar derivações.'
    );
} finally {
    if ($documentId !== null) {
        $statement = $database->prepare('DELETE FROM documents WHERE id = :id');
        $statement->execute(['id' => $documentId]);
    } else {
        $statement = $database->prepare(
            'SELECT id, storage_path FROM documents WHERE original_name = :original_name'
        );
        $statement->execute(['original_name' => $originalName]);

        foreach ($statement->fetchAll() as $document) {
            $delete = $database->prepare('DELETE FROM documents WHERE id = :id');
            $delete->execute(['id' => $document['id']]);
            $storagePath = $document['storage_path'];
        }
    }

    if (is_string($storagePath) && $storagePath !== '') {
        $storage->remove($storagePath);
    }
}

assertRealDocument(realDocumentCounts($database) === $baseline, 'O teste real não restaurou o banco.');

echo sprintf(
    "Documento real validado com %d asserções, %d nós e %d evidências primárias.\n",
    $assertions,
    count($expectedNodes),
    $expectedEvidenceCount
);
