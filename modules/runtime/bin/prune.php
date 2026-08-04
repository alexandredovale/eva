<?php

declare(strict_types=1);

use Eva\Infrastructure\Database\Connection;
use Eva\ModuleRuntime\RuntimeFactory;

$root = dirname(__DIR__, 3);
$container = require $root . '/bootstrap/app.php';
$days = null;

foreach ($argv as $argument) {
    if (preg_match('/^--days=(\d+)$/', $argument, $matches) === 1) {
        $days = (int) $matches[1];
    }
}

if (!in_array('--confirm', $argv, true) || $days === null || $days < 1 || $days > 3650) {
    fwrite(STDERR, "Uso: php prune.php --days=N --confirm (N entre 1 e 3650).\n");
    exit(2);
}

$factory = new RuntimeFactory($root, Connection::create($container['database']), $container['ai']);
$threshold = (new DateTimeImmutable())->modify(sprintf('-%d days', $days));
$removed = $factory->events()->pruneBefore($threshold);
echo json_encode([
    'removed' => $removed,
    'threshold' => $threshold->format(DateTimeImmutable::ATOM),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
