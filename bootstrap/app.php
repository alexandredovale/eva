<?php

declare(strict_types=1);

use Eva\Support\Env;

$root = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'Eva\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $root . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

Env::load($root . '/.env');
Env::load($root . '/api_key.md');

return [
    'root' => $root,
    'app' => require $root . '/config/app.php',
    'database' => require $root . '/config/database.php',
    'ingestion' => require $root . '/config/ingestion.php',
    'logging' => require $root . '/config/logging.php',
    'ai' => require $root . '/config/ai.php',
    'branding' => require $root . '/config/branding.php',
    'security' => require $root . '/config/security.php',
    'queue' => require $root . '/config/queue.php',
];

