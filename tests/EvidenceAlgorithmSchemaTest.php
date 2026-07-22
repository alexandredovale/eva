<?php

declare(strict_types=1);

use Eva\Infrastructure\Database\Connection;

$container = require __DIR__ . '/bootstrap.php';
$database = Connection::create($container['database']);
$schema = $container['database']['database'];
$assertions = 0;

function assertEvidenceSchema(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$tableQuery = $database->prepare(
    'SELECT TABLE_NAME
       FROM INFORMATION_SCHEMA.TABLES
      WHERE TABLE_SCHEMA = :schema'
);
$tableQuery->execute(['schema' => $schema]);
$tables = array_column($tableQuery->fetchAll(), 'TABLE_NAME');

foreach (['cnodes', 'cnode_evidences', 'cnode_embeddings', 'interaction_analyses'] as $obsoleteTable) {
    assertEvidenceSchema(
        !in_array($obsoleteTable, $tables, true),
        'A tabela cognitiva obsoleta ainda existe: ' . $obsoleteTable
    );
}

$columnQuery = $database->prepare(
    'SELECT COLUMN_NAME, COLUMN_TYPE
       FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table'
);
$columnQuery->execute(['schema' => $schema, 'table' => 'evidences']);
$evidenceColumns = array_column($columnQuery->fetchAll(), 'COLUMN_TYPE', 'COLUMN_NAME');

assertEvidenceSchema(
    ($evidenceColumns['evidence_class'] ?? '') === "enum('primary','derived')",
    'Evidence class deve distinguir somente unidades persistentes primárias e derivadas.'
);
assertEvidenceSchema(isset($evidenceColumns['evidence_type']), 'Evidence type é obrigatório no Evidence Algorithm.');

foreach (['confidence', 'similarity', 'score', 'weight', 'intensity', 'importance'] as $forbiddenColumn) {
    assertEvidenceSchema(
        !isset($evidenceColumns[$forbiddenColumn]),
        'Evidências não devem persistir campo cognitivo proibido: ' . $forbiddenColumn
    );
}

$columnQuery->execute(['schema' => $schema, 'table' => 'processing_jobs']);
$jobColumns = array_column($columnQuery->fetchAll(), 'COLUMN_TYPE', 'COLUMN_NAME');
assertEvidenceSchema(
    ($jobColumns['stage'] ?? '') === "enum('summaries','embeddings')",
    'A fila persistente deve terminar em sínteses e embeddings.'
);

$foreignKeyQuery = $database->prepare(
    'SELECT COUNT(*)
       FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
      WHERE TABLE_SCHEMA = :schema
        AND TABLE_NAME = :table
        AND REFERENCED_TABLE_NAME = :referenced_table'
);
$foreignKeyQuery->execute([
    'schema' => $schema,
    'table' => 'evidence_derivations',
    'referenced_table' => 'evidences',
]);
assertEvidenceSchema(
    (int) $foreignKeyQuery->fetchColumn() === 2,
    'A linhagem deve manter as duas referências para evidências.'
);

echo sprintf("Evidence Algorithm validado no esquema com %d asserções.\n", $assertions);
