<?php

declare(strict_types=1);

use Eva\Application\Query\DocumentContextRetriever;
use Eva\Application\Query\DocumentQueryService;
use Eva\Application\Query\InputType;
use Eva\Application\Query\InputTypeDetector;
use Eva\Infrastructure\Ai\CognitiveProviderFactory;
use Eva\Infrastructure\Database\Connection;
use Eva\Infrastructure\Logging\FileLogger;
use Eva\Infrastructure\Logging\SafeFailureDiagnostics;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$container = require dirname(__DIR__) . '/bootstrap/app.php';
$logger = new FileLogger($container['logging']['path']);
$arguments = array_slice($argv, 1);
$documentId = isset($arguments[0]) && ctype_digit($arguments[0]) ? (int) $arguments[0] : 0;
$liveConfirmed = in_array('--live', $arguments, true);
$maxEvidence = (int) $container['ai']['query']['max_evidence'];
$maxInteractions = (int) $container['ai']['query']['max_interactions'];
$inputParts = [];

foreach (array_slice($arguments, 1) as $argument) {
    if ($argument === '--live') {
        continue;
    }

    if (str_starts_with($argument, '--evidence-limit=')) {
        $maxEvidence = (int) substr($argument, strlen('--evidence-limit='));
        continue;
    }

    if (str_starts_with($argument, '--interaction-limit=')) {
        $maxInteractions = (int) substr($argument, strlen('--interaction-limit='));
        continue;
    }

    $inputParts[] = $argument;
}

$input = trim(implode(' ', $inputParts));

if ($documentId < 1 || $input === '' || $maxEvidence < 1 || $maxEvidence > 50
    || $maxInteractions < 0 || $maxInteractions > 100) {
    fwrite(
        STDERR,
        "Uso: php bin/query-document.php <document-id> --live [--evidence-limit=N] [--interaction-limit=N] \"pergunta\"\n"
    );
    exit(2);
}

if (!$liveConfirmed || ($container['ai']['live_enabled'] ?? false) !== true) {
    fwrite(STDERR, "Chamadas reais bloqueadas. Exigem AI_LIVE_ENABLED=true e a opção --live.\n");
    exit(3);
}

try {
    $database = Connection::create($container['database']);
    $factory = new CognitiveProviderFactory($container['ai']);
    $detector = new InputTypeDetector();
    $understanding = $detector->detect($input);
    $needsSemanticRetrieval = $understanding->has(InputType::Conceptual)
        || $understanding->has(InputType::Relational);
    $retriever = new DocumentContextRetriever(
        $database,
        $needsSemanticRetrieval ? $factory->embeddings() : null,
        $detector
    );
    $service = new DocumentQueryService($retriever, $factory->queryAnswers());
    $result = $service->query($documentId, $input, $maxEvidence, $maxInteractions);

    echo json_encode(
        $result->toArray(),
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    ) . PHP_EOL;
    $logger->info('document_query_completed', [
        'document_id' => $documentId,
        'input_types' => $result->understanding->toArray()['types'],
        'evidence_count' => count($result->usedEvidences),
        'simetry_count' => count($result->simetryInteractions),
        'assimetry_count' => count($result->assimetryInteractions),
    ]);
} catch (Throwable $exception) {
    $logger->error('document_query_failed', SafeFailureDiagnostics::context($exception, [
        'document_id' => $documentId,
    ]));
    fwrite(STDERR, 'Falha na consulta documental: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
