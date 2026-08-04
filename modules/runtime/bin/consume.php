<?php

declare(strict_types=1);

use Eva\Infrastructure\Database\Connection;
use Eva\ModuleRuntime\RuntimeFactory;

$root = dirname(__DIR__, 3);
$container = require $root . '/bootstrap/app.php';
require $root . '/modules/runtime/bootstrap.php';

$limit = 50;
$drain = in_array('--drain', $argv, true);

foreach ($argv as $argument) {
    if (preg_match('/^--limit=(\d+)$/', $argument, $matches) === 1) {
        $limit = max(1, min(500, (int) $matches[1]));
    }
}

$factory = new RuntimeFactory($root, Connection::create($container['database']), $container['ai']);
$summary = ['processed' => 0, 'failed' => 0, 'passes' => 0, 'modules' => []];

do {
    $result = $factory->dispatcher()->consumeOnce($limit);
    $summary['processed'] += $result['processed'];
    $summary['failed'] += $result['failed'];
    $summary['passes']++;
    $summary['modules'] = $result['modules'];
} while ($drain && $result['processed'] > 0 && $result['failed'] === 0);

echo json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($summary['failed'] > 0 ? 1 : 0);
