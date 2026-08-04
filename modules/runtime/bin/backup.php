<?php

declare(strict_types=1);

use Eva\Infrastructure\Database\Connection;
use Eva\ModuleRuntime\RuntimeFactory;

$root = dirname(__DIR__, 3);
$container = require $root . '/bootstrap/app.php';
$moduleId = '';

foreach ($argv as $argument) {
    if (preg_match('/^--module=([a-z0-9]+(?:[.-][a-z0-9]+)+)$/', $argument, $matches) === 1) {
        $moduleId = $matches[1];
    }
}

if ($moduleId === '') {
    fwrite(STDERR, "Uso: php backup.php --module=com.fornecedor.modulo\n");
    exit(2);
}

$factory = new RuntimeFactory($root, Connection::create($container['database']), $container['ai']);
$manifest = $factory->registry()->find($moduleId);

if ($factory->state()->isActive($moduleId)) {
    fwrite(STDERR, "Desconecte o módulo antes de gerar o backup.\n");
    exit(2);
}

$database = $factory->storage()->open($manifest);
$database->exec('PRAGMA wal_checkpoint(TRUNCATE)');
$dataDirectory = $root . '/modules/.runtime/data/' . $moduleId;
$source = $dataDirectory . '/module.sqlite';
$backupDirectory = $dataDirectory . '/backups';

if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0700, true) && !is_dir($backupDirectory)) {
    throw new RuntimeException('O diretório de backup não pôde ser criado.');
}

$target = $backupDirectory . '/module-' . gmdate('Ymd-His') . '.sqlite';

if (!copy($source, $target)) {
    throw new RuntimeException('O arquivo SQLite não pôde ser copiado.');
}

$verification = new PDO('sqlite:' . $target, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

if ((string) $verification->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') {
    unlink($target);
    throw new RuntimeException('O backup produzido não passou na verificação de integridade.');
}

echo json_encode([
    'module_id' => $moduleId,
    'backup' => str_replace('\\', '/', substr($target, strlen($root) + 1)),
    'bytes' => filesize($target),
    'sha256' => hash_file('sha256', $target),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
