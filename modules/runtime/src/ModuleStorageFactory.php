<?php

declare(strict_types=1);

namespace Eva\ModuleRuntime;

use PDO;

final readonly class ModuleStorageFactory
{
    public function __construct(private string $dataRoot)
    {
    }

    public function open(ModuleManifest $manifest): PDO
    {
        if (!extension_loaded('pdo_sqlite')) {
            throw new ModuleException('A extensão pdo_sqlite é obrigatória para os módulos.');
        }

        if (!is_dir($this->dataRoot) && !mkdir($this->dataRoot, 0700, true) && !is_dir($this->dataRoot)) {
            throw new ModuleException('O diretório de dados dos módulos não pode ser criado.');
        }

        $resolvedRoot = realpath($this->dataRoot);

        if ($resolvedRoot === false) {
            throw new ModuleException('O diretório de dados dos módulos não pode ser resolvido.');
        }

        $moduleDirectory = $resolvedRoot . DIRECTORY_SEPARATOR . $manifest->id;

        if (!is_dir($moduleDirectory) && !mkdir($moduleDirectory, 0700, true) && !is_dir($moduleDirectory)) {
            throw new ModuleException('O diretório de dados do módulo não pode ser criado.');
        }

        $resolvedModuleDirectory = realpath($moduleDirectory);

        if ($resolvedModuleDirectory === false
            || !str_starts_with($resolvedModuleDirectory . DIRECTORY_SEPARATOR, $resolvedRoot . DIRECTORY_SEPARATOR)) {
            throw new ModuleException('O caminho de dados do módulo é inválido.');
        }

        $databasePath = $resolvedModuleDirectory . DIRECTORY_SEPARATOR . 'module.sqlite';
        $database = new PDO('sqlite:' . $databasePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $database->exec('PRAGMA journal_mode = WAL');
        $database->exec('PRAGMA foreign_keys = ON');
        $database->exec('PRAGMA busy_timeout = 5000');
        $database->exec(
            'CREATE TABLE IF NOT EXISTS runtime_event_cursor (
                singleton_id INTEGER PRIMARY KEY CHECK (singleton_id = 1),
                last_event_row_id INTEGER NOT NULL DEFAULT 0,
                updated_at TEXT NOT NULL
            )'
        );
        $database->exec(
            "INSERT OR IGNORE INTO runtime_event_cursor (singleton_id, last_event_row_id, updated_at)
             VALUES (1, 0, datetime('now'))"
        );
        $database->exec(
            'CREATE TABLE IF NOT EXISTS runtime_processed_events (
                event_id TEXT PRIMARY KEY,
                event_type TEXT NOT NULL,
                processed_at TEXT NOT NULL
            )'
        );
        $database->exec(
            'CREATE TABLE IF NOT EXISTS runtime_event_gaps (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                previous_cursor INTEGER NOT NULL,
                resumed_at_row_id INTEGER NOT NULL,
                recorded_at TEXT NOT NULL
            )'
        );

        return $database;
    }

    public function deleteData(ModuleManifest $manifest): int
    {
        $resolvedRoot = realpath($this->dataRoot);

        if ($resolvedRoot === false) {
            throw new ModuleException('O diretório de dados dos módulos não pode ser resolvido.');
        }

        $moduleDirectory = $resolvedRoot . DIRECTORY_SEPARATOR . $manifest->id;

        if (!is_dir($moduleDirectory)) {
            return 0;
        }

        return SafeDirectoryRemover::removeExactChild($resolvedRoot, $moduleDirectory, $manifest->id);
    }
}
