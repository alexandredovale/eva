<?php

declare(strict_types=1);

use Eva\Application\Ingestion\DocumentIngestionService;
use Eva\Http\Upload\DocumentUploadHandler;
use Eva\Http\Upload\DocumentUploadValidator;
use Eva\Http\Upload\UploadException;
use Eva\Infrastructure\Database\Connection;
use Eva\Infrastructure\Logging\FileLogger;
use Eva\Infrastructure\Storage\DocumentStorage;

$container = require __DIR__ . '/bootstrap.php';
$token = bin2hex(random_bytes(6));
$logPath = dirname(__DIR__) . '/storage/logs/upload-security-' . $token . '.log';
$logger = new FileLogger($logPath);
$assertions = 0;

function assertUploadSecurity(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectUploadException(callable $callback, int $status): void
{
    global $assertions;
    $assertions++;

    try {
        $callback();
    } catch (UploadException $exception) {
        if ($exception->httpStatus !== $status) {
            throw new RuntimeException('Status de rejeição de upload incorreto.');
        }

        return;
    }

    throw new RuntimeException('O upload inválido não foi rejeitado.');
}

try {
    $validator = new DocumentUploadValidator($container['ingestion']['max_document_bytes']);
    expectUploadException(static fn () => $validator->validate(null, null), 400);
    expectUploadException(
        static fn () => $validator->validate([
            'name' => ['arquivo.md'],
            'tmp_name' => '',
            'error' => UPLOAD_ERR_OK,
            'size' => 10,
        ], null),
        400
    );
    expectUploadException(
        static fn () => $validator->validate([
            'name' => 'arquivo.md',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_INI_SIZE,
            'size' => 0,
        ], null),
        413
    );

    $database = Connection::create($container['database']);
    $handler = new DocumentUploadHandler(
        $validator,
        new DocumentIngestionService(
            $database,
            new DocumentStorage($container['ingestion']['document_storage']),
            $container['ingestion']['max_document_bytes']
        ),
        $logger
    );
    $response = $handler->handle([], []);
    assertUploadSecurity($response['status'] === 400, 'O endpoint deve rejeitar upload ausente.');

    $logger->error('redaction_test', [
        'api_key' => 'secret-api-key-value',
        'authorization' => 'Bearer secret-token-value',
        'nested' => ['password' => 'secret-password-value'],
        'safe' => 'visible-value',
    ]);
    $log = file_get_contents($logPath);
    assertUploadSecurity(is_string($log), 'O arquivo de log de teste não foi criado.');
    assertUploadSecurity(!str_contains($log, 'secret-api-key-value'), 'A chave de API apareceu no log.');
    assertUploadSecurity(!str_contains($log, 'secret-token-value'), 'O token apareceu no log.');
    assertUploadSecurity(!str_contains($log, 'secret-password-value'), 'A senha apareceu no log.');
    assertUploadSecurity(str_contains($log, '[REDACTED]'), 'Valores sensíveis não foram marcados como removidos.');
    assertUploadSecurity(str_contains($log, 'visible-value'), 'Contexto seguro deveria permanecer no log.');
} finally {
    if (is_file($logPath)) {
        unlink($logPath);
    }
}

echo sprintf("Segurança de upload validada com %d asserções.\n", $assertions);

