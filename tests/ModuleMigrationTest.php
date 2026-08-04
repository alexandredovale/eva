<?php

declare(strict_types=1);

$container = require __DIR__ . '/bootstrap.php';
$configuration = $container['database'];
$databaseName = 'eva_module_migration_' . bin2hex(random_bytes(5));

if (preg_match('/^[a-z0-9_]+$/', $databaseName) !== 1) {
    throw new RuntimeException('O nome do banco temporário é inválido.');
}

$server = new PDO(
    sprintf('mysql:host=%s;port=%d;charset=%s', $configuration['host'], $configuration['port'], $configuration['charset']),
    $configuration['username'],
    $configuration['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$migration = (string) file_get_contents(dirname(__DIR__) . '/database/migrations/20260803_010_module_events.sql');
$assertions = 0;

function assertModuleMigration(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

assertModuleMigration(substr_count(strtoupper($migration), 'CREATE TABLE') === 1, 'A migration deve criar exatamente uma tabela.');
assertModuleMigration(preg_match('/\bALTER\s+TABLE\b/i', $migration) !== 1, 'A migration não pode alterar tabelas existentes.');

try {
    $server->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $databaseName));
    $temporary = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $configuration['host'], $configuration['port'], $databaseName, $configuration['charset']),
        $configuration['username'],
        $configuration['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $temporary->exec($migration);
    $tables = $temporary->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    assertModuleMigration($tables === ['module_events'], 'A migration criou estrutura além de module_events.');
    $columns = $temporary->query('SHOW COLUMNS FROM module_events')->fetchAll();
    assertModuleMigration(count($columns) === 6, 'A tabela module_events não possui exatamente seis colunas.');
    $temporary->exec('DROP TABLE module_events');
    assertModuleMigration($temporary->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) === [], 'O rollback isolado não removeu a tabela modular.');
    unset($temporary);

    echo sprintf("Migration modular validada com %d asserções e rollback isolado.\n", $assertions);
} finally {
    $server->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
}
