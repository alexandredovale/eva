<?php

declare(strict_types=1);

use Eva\Application\Ingestion\DocumentIngestionService;
use Eva\Application\Product\ProductReadService;
use Eva\Http\Product\ProductApi;
use Eva\Infrastructure\Audit\AuditRecorder;
use Eva\Infrastructure\Database\Connection;
use Eva\Infrastructure\Logging\FileLogger;
use Eva\Infrastructure\Storage\DocumentStorage;

$container = require __DIR__ . '/bootstrap.php';
$container['ai']['live_enabled'] = false;
$database = Connection::create($container['database']);
$storage = new DocumentStorage($container['ingestion']['document_storage']);
$logger = new FileLogger($container['logging']['path']);
$api = new ProductApi($database, $container, $logger);
$assertions = 0;
$originalToken = getenv('ADMIN_API_TOKEN');
$testToken = str_repeat('T', 32);
$auditBaseline = (int) $database->query('SELECT COALESCE(MAX(id), 0) FROM audit_events')->fetchColumn();
$result = null;

putenv('ADMIN_API_TOKEN=' . $testToken);
$_ENV['ADMIN_API_TOKEN'] = $testToken;

function assertProduct(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array<string, mixed> */
function productServer(?string $token = null): array
{
    $server = ['REMOTE_ADDR' => '198.51.100.24'];

    if ($token !== null) {
        $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
    }

    return $server;
}

try {
    $ingestion = new DocumentIngestionService(
        $database,
        $storage,
        $container['ingestion']['max_document_bytes']
    );
    $token = bin2hex(random_bytes(6));
    $result = $ingestion->ingest(
        'product-' . $token . '.md',
        "# Produto\n\nConteúdo primário para validar a camada administrativa.",
        'Documento de produto ' . $token
    );

    $branding = $api->handle('GET', '/api/branding', [], [], [], '');
    assertProduct($branding->status === 200, 'Branding deve ser público.');
    assertProduct(isset($branding->payload['branding']['name']), 'Branding não retornou nome.');
    assertProduct(!isset($branding->payload['branding']['provider']), 'Branding não deve expor provedores.');

    $denied = $api->handle('GET', '/api/documents', productServer(), [], [], '');
    assertProduct($denied->status === 401, 'A listagem deve exigir autenticação.');
    assertProduct(($denied->headers['WWW-Authenticate'] ?? null) === 'Bearer', 'Desafio Bearer ausente.');

    $authorized = productServer($testToken);
    $documents = $api->handle('GET', '/api/documents', $authorized, [], [], '');
    assertProduct($documents->status === 200, 'A listagem autenticada falhou.');
    $documentRows = array_filter(
        $documents->payload['documents'],
        static fn (array $document): bool => (int) $document['id'] === $result->documentId
    );
    assertProduct(count($documentRows) === 1, 'O documento de teste não apareceu no produto.');
    assertProduct(
        in_array((string) array_values($documentRows)[0]['processing_status'], ['pending', 'completed'], true),
        'A listagem não retornou o estado de processamento do documento.'
    );

    $processPath = '/api/documents/' . $result->documentId . '/process';
    $firstPlan = $api->handle('POST', $processPath, $authorized, [], [], '');
    $secondPlan = $api->handle('POST', $processPath, $authorized, [], [], '');
    assertProduct($firstPlan->status === 202 && count($firstPlan->payload['jobs']) === 2, 'O plano cognitivo deve criar sínteses e embeddings.');
    assertProduct(
        array_column($firstPlan->payload['jobs'], 'id') === array_column($secondPlan->payload['jobs'], 'id'),
        'O agendamento deve ser idempotente para a mesma configuração.'
    );
    assertProduct(
        !array_key_exists('version_key', $firstPlan->payload['jobs'][0]),
        'A API white label não deve expor vínculos internos de modelo ou fornecedor.'
    );

    $metrics = $api->handle('GET', '/api/metrics', $authorized, [], [], '');
    assertProduct($metrics->status === 200, 'As métricas não foram retornadas.');
    assertProduct(($metrics->payload['metrics']['jobs']['queued'] ?? 0) >= 2, 'As métricas não contabilizaram a fila.');

    $jobs = $api->handle('GET', '/api/jobs', $authorized, [], [], '');
    assertProduct($jobs->status === 200, 'A listagem de trabalhos falhou.');
    $documentJobs = array_values(array_filter(
        $jobs->payload['jobs'],
        static fn (array $job): bool => (int) $job['document_id'] === $result->documentId
    ));
    assertProduct(
        count($documentJobs) === 2
        && array_key_exists('progress_current', $documentJobs[0])
        && array_key_exists('progress_total', $documentJobs[0])
        && array_key_exists('progress_percent', $documentJobs[0]),
        'A fila não retornou o progresso observável das etapas.'
    );
    assertProduct(
        min(array_column($documentJobs, 'progress_total')) > 0,
        'O total de unidades cognitivas não foi calculado.'
    );
    $jobId = $firstPlan->payload['jobs'][0]['id'];
    $failJob = $database->prepare(
        "UPDATE processing_jobs SET status = 'failed', failure_count = 1, last_error = 'falha simulada' WHERE public_id = :id"
    );
    $failJob->execute(['id' => $jobId]);
    $retried = $api->handle('POST', '/api/jobs/' . $jobId . '/retry', $authorized, [], [], '');
    assertProduct($retried->status === 202, 'A retomada explícita falhou.');
    assertProduct($retried->payload['job']['status'] === 'queued', 'O trabalho não voltou à fila.');

    $invalidJson = $api->handle('POST', '/api/query', $authorized, [], [], '{');
    assertProduct($invalidJson->status === 400, 'JSON inválido deve ser rejeitado.');

    $liveBlocked = $api->handle(
        'POST',
        '/api/query',
        $authorized,
        [],
        [],
        json_encode(['document_id' => $result->documentId, 'input' => 'Explique o documento.'], JSON_THROW_ON_ERROR)
    );
    assertProduct($liveBlocked->status === 503, 'Consulta real deve permanecer bloqueada sem opt-in.');

    (new AuditRecorder($database))->record(
        'sanitization_test',
        'document',
        (string) $result->documentId,
        hash('sha256', 'actor'),
        '203.0.113.10',
        ['token' => 'segredo', 'input' => 'conteúdo sensível', 'safe_count' => 2]
    );
    $auditRecord = $database->query(
        "SELECT metadata, network_fingerprint FROM audit_events WHERE event_type = 'sanitization_test' ORDER BY id DESC LIMIT 1"
    )->fetch();
    $metadata = json_decode((string) $auditRecord['metadata'], true, 32, JSON_THROW_ON_ERROR);
    assertProduct($metadata['token'] === '[REDACTED]' && $metadata['input'] === '[REDACTED]', 'A auditoria não removeu conteúdo sensível.');
    assertProduct($metadata['safe_count'] === 2, 'A auditoria removeu metadado descritivo permitido.');
    assertProduct($auditRecord['network_fingerprint'] === hash('sha256', '203.0.113.10'), 'O endereço de rede não foi anonimizado.');

    $audit = $api->handle('GET', '/api/audit', $authorized, [], [], '');
    assertProduct($audit->status === 200, 'A consulta de auditoria falhou.');
    assertProduct(
        in_array('document_processing_enqueued', array_column($audit->payload['events'], 'event_type'), true),
        'O agendamento não deixou trilha de auditoria.'
    );

    $read = new ProductReadService($database);
    $readMetrics = $read->metrics();
    assertProduct(isset($readMetrics['evidence_types']), 'As métricas perderam os tipos de evidência.');
    assertProduct(isset($readMetrics['embeddings'], $readMetrics['derivations']), 'As métricas perderam a arquitetura semântica persistente.');
    assertProduct(!isset($readMetrics['cnodes']), 'Cnodes não devem permanecer como métrica persistente.');
} finally {
    if ($result !== null) {
        $delete = $database->prepare('DELETE FROM documents WHERE id = :id');
        $delete->execute(['id' => $result->documentId]);
        $storage->remove($result->storagePath);
    }

    $deleteAudit = $database->prepare('DELETE FROM audit_events WHERE id > :baseline');
    $deleteAudit->execute(['baseline' => $auditBaseline]);

    if ($originalToken === false) {
        putenv('ADMIN_API_TOKEN');
        unset($_ENV['ADMIN_API_TOKEN']);
    } else {
        putenv('ADMIN_API_TOKEN=' . $originalToken);
        $_ENV['ADMIN_API_TOKEN'] = $originalToken;
    }
}

echo sprintf("Camada de produto validada com %d asserções e zero chamadas pagas.\n", $assertions);
