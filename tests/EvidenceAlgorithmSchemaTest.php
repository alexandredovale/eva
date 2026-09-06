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

foreach (['cnodes', 'cnode_evidences', 'cnode_embeddings', 'interaction_analyses', 'evidence_derivations'] as $obsoleteTable) {
    assertEvidenceSchema(
        !in_array($obsoleteTable, $tables, true),
        'A tabela cognitiva obsoleta ainda existe: ' . $obsoleteTable
    );
}

assertEvidenceSchema(
    in_array('module_events', $tables, true),
    'O schema consolidado deve incluir a caixa postal neutra module_events.'
);

$columnQuery = $database->prepare(
    'SELECT COLUMN_NAME, COLUMN_TYPE
       FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table'
);
$columnQuery->execute(['schema' => $schema, 'table' => 'evidences']);
$evidenceColumns = array_column($columnQuery->fetchAll(), 'COLUMN_TYPE', 'COLUMN_NAME');

assertEvidenceSchema(
    ($evidenceColumns['evidence_class'] ?? '') === "enum('primary')",
    'Evidence class deve aceitar somente unidades persistentes primárias.'
);
assertEvidenceSchema(isset($evidenceColumns['evidence_type']), 'Evidence type é obrigatório no Evidence Algorithm.');

foreach (['summary', 'generation_model', 'generation_input_hash'] as $removedColumn) {
    assertEvidenceSchema(
        !isset($evidenceColumns[$removedColumn]),
        'Campo de síntese removido ainda existe: ' . $removedColumn
    );
}

foreach (['confidence', 'similarity', 'score', 'weight', 'intensity', 'importance'] as $forbiddenColumn) {
    assertEvidenceSchema(
        !isset($evidenceColumns[$forbiddenColumn]),
        'Evidências não devem persistir campo cognitivo proibido: ' . $forbiddenColumn
    );
}

$columnQuery->execute(['schema' => $schema, 'table' => 'processing_jobs']);
$jobColumns = array_column($columnQuery->fetchAll(), 'COLUMN_TYPE', 'COLUMN_NAME');
assertEvidenceSchema(
    ($jobColumns['stage'] ?? '') === "enum('embeddings')",
    'A fila persistente deve aceitar somente embeddings.'
);

echo sprintf("Evidence Algorithm validado no esquema com %d asserções.\n", $assertions);
