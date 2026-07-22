<?php

declare(strict_types=1);

use Eva\Application\Cognitive\EvidenceEmbeddingService;
use Eva\Application\Cognitive\HierarchicalSummaryService;
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
$stage = null;
$liveConfirmed = in_array('--live', $arguments, true);
$summaryLimit = (int) $container['ai']['max_new_summaries_per_run'];

foreach ($arguments as $argument) {
    if (str_starts_with($argument, '--stage=')) {
        $stage = substr($argument, strlen('--stage='));
    } elseif (str_starts_with($argument, '--summary-limit=')) {
        $summaryLimit = (int) substr($argument, strlen('--summary-limit='));
    }
}

if ($documentId < 1 || !in_array($stage, ['summaries', 'embeddings'], true)) {
    fwrite(STDERR, "Uso: php bin/build-cognitive.php <document-id> --stage=summaries|embeddings --live\n");
    exit(2);
}

if (!$liveConfirmed || ($container['ai']['live_enabled'] ?? false) !== true) {
    fwrite(STDERR, "Chamadas reais bloqueadas. Exigem AI_LIVE_ENABLED=true e a opção --live.\n");
    exit(3);
}

try {
    $database = Connection::create($container['database']);
    $factory = new CognitiveProviderFactory($container['ai']);

    if ($stage === 'summaries') {
        if ($summaryLimit < 1 || $summaryLimit > 100) {
            throw new InvalidArgumentException('O limite de resumos deve estar entre 1 e 100.');
        }

        $result = (new HierarchicalSummaryService($database, $factory->summaries()))
            ->buildForDocument($documentId, $summaryLimit)
            ->toArray();
    } else {
        $embeddingConfig = $container['ai']['providers']['embeddings'] ?? [];
        $result = (new EvidenceEmbeddingService(
            database: $database,
            provider: $factory->embeddings(),
            maxUnitsPerBatch: (int) ($embeddingConfig['max_units_per_request'] ?? 64),
            maxInputTokens: (int) ($embeddingConfig['max_input_tokens'] ?? 8_192)
        ))
            ->buildForDocument($documentId)
            ->toArray();
    }

    echo json_encode([
        'document_id' => $documentId,
        'stage' => $stage,
        'result' => $result,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    $logger->info('cognitive_stage_completed', [
        'document_id' => $documentId,
        'stage' => $stage,
        'result' => $result,
    ]);
} catch (Throwable $exception) {
    $logger->error('cognitive_stage_failed', SafeFailureDiagnostics::context($exception, [
        'document_id' => $documentId,
        'stage' => $stage,
    ]));
    fwrite(STDERR, 'Falha na etapa cognitiva: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
