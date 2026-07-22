<?php

declare(strict_types=1);

use Eva\Application\Queue\CognitiveQueueWorker;
use Eva\Application\Queue\ProcessingQueueService;
use Eva\Infrastructure\Ai\CognitiveProviderFactory;
use Eva\Infrastructure\Audit\AuditRecorder;
use Eva\Infrastructure\Database\Connection;
use Eva\Infrastructure\Logging\FileLogger;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$container = require dirname(__DIR__) . '/bootstrap/app.php';
$arguments = array_slice($argv, 1);
$liveConfirmed = in_array('--live', $arguments, true);
$drainQueue = in_array('--drain', $arguments, true);

if (!$liveConfirmed || ($container['ai']['live_enabled'] ?? false) !== true) {
    fwrite(STDERR, "Worker bloqueado. Exige AI_LIVE_ENABLED=true e a opção --live.\n");
    exit(3);
}

try {
    $database = Connection::create($container['database']);
    $queue = new ProcessingQueueService($database, (int) $container['queue']['max_failures']);
    $worker = new CognitiveQueueWorker(
        $database,
        $queue,
        new CognitiveProviderFactory($container['ai']),
        $container['ai'],
        new AuditRecorder($database),
        new FileLogger($container['logging']['path'])
    );
    $processedRuns = 0;

    do {
        $result = $worker->runOnce((string) $container['queue']['worker_id']);

        if (($result['status'] ?? 'idle') !== 'idle') {
            $processedRuns++;
        }
    } while ($drainQueue && ($result['status'] ?? 'idle') !== 'idle');

    if ($drainQueue) {
        $result = ['status' => 'drained', 'processed_runs' => $processedRuns];
    }

    echo json_encode(
        $result,
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    ) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Falha no worker cognitivo: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
