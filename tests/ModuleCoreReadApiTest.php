<?php

declare(strict_types=1);

use Eva\Infrastructure\Database\Connection;
use Eva\ModuleRuntime\CoreReadApi;

$container = require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/modules/runtime/bootstrap.php';
$database = Connection::create($container['database']);
$assertions = 0;

function assertModuleCoreRead(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$database->beginTransaction();

try {
    $token = bin2hex(random_bytes(8));
    $statement = $database->prepare(
        "INSERT INTO documents (public_id, title, original_name, format, source_hash, status)
         VALUES (:public_id, 'Runtime modular', :original_name, 'markdown', :source_hash, 'ready')"
    );
    $statement->execute([
        'public_id' => 'MOD-D-' . $token,
        'original_name' => 'module-core-' . $token . '.md',
        'source_hash' => hash('sha256', $token),
    ]);
    $documentId = (int) $database->lastInsertId();
    $statement = $database->prepare(
        "INSERT INTO evidences
            (public_id, document_id, evidence_class, evidence_type, content, source_hash, status)
         VALUES
            (:public_id, :document_id, 'primary', 'node_content', :content, :source_hash, 'validated')"
    );
    $publicId = 'MOD-E-' . $token;
    $statement->execute([
        'public_id' => $publicId,
        'document_id' => $documentId,
        'content' => 'Evidência primária para validar o contrato modular 6.0.1.',
        'source_hash' => hash('sha256', 'evidence-' . $token),
    ]);

    $record = (new CoreReadApi($database, ['core.read.evidences']))->evidenceByPublicId($publicId);
    assertModuleCoreRead(is_array($record), 'O Runtime modular não recuperou a evidência primária.');
    assertModuleCoreRead(($record['evidence_class'] ?? null) === 'primary', 'O Runtime retornou classe incompatível.');
    assertModuleCoreRead(!array_key_exists('summary', $record), 'O contrato modular ainda expõe a coluna removida summary.');
} finally {
    $database->rollBack();
}

echo sprintf("Leitura modular de evidências primárias validada com %d asserções.\n", $assertions);
