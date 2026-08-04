<?php

declare(strict_types=1);

use Eva\ModuleRuntime\ModuleBridge;
use Eva\ModuleRuntime\ModuleDispatcher;
use Eva\ModuleRuntime\ModuleEvent;
use Eva\ModuleRuntime\ModuleEventRepository;
use Eva\ModuleRuntime\ModuleException;
use Eva\ModuleRuntime\ModuleManifest;
use Eva\ModuleRuntime\ModuleRegistry;
use Eva\ModuleRuntime\ModuleStateStore;
use Eva\ModuleRuntime\ModuleStorageFactory;

require dirname(__DIR__) . '/modules/runtime/bootstrap.php';

$assertions = 0;

function assertModuleRuntime(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function removeModuleRuntimeDirectory(string $path): void
{
    $resolved = realpath($path);
    $temporaryRoot = rtrim(realpath(sys_get_temp_dir()) ?: sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if ($resolved === false
        || !str_starts_with($resolved . DIRECTORY_SEPARATOR, $temporaryRoot)
        || !str_starts_with(basename($resolved), 'eva-module-runtime-')) {
        throw new RuntimeException('A limpeza do teste recusou um caminho inesperado.');
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($resolved);
}

$temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'eva-module-runtime-' . bin2hex(random_bytes(6));
mkdir($temporaryRoot, 0700, true);
$state = new ModuleStateStore($temporaryRoot . '/state.json');
$modulesRoot = __DIR__ . '/fixtures/modules';
$registry = new ModuleRegistry($modulesRoot, $state);
$database = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$database->exec(
    'CREATE TABLE module_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_id TEXT NOT NULL UNIQUE,
        event_type TEXT NOT NULL,
        contract_version INTEGER NOT NULL,
        payload_json TEXT NOT NULL,
        created_at TEXT NOT NULL
    )'
);
$events = new ModuleEventRepository($database);
$storageFactory = new ModuleStorageFactory($temporaryRoot . '/data');
$dispatcher = new ModuleDispatcher($registry, $state, $storageFactory, $events, $database);
$bridge = new ModuleBridge($events);

try {
    foreach (glob(dirname(__DIR__) . '/modules/runtime/contracts/*.json') ?: [] as $schemaPath) {
        assertModuleRuntime(json_decode((string) file_get_contents($schemaPath), true, 64, JSON_THROW_ON_ERROR) !== null, 'Contrato JSON inválido.');
    }

    $manifests = $registry->manifests();
    assertModuleRuntime(count($manifests) === 4, 'O Runtime não descobriu os quatro fixtures.');
    assertModuleRuntime($registry->activeManifests() === [], 'Módulos devem iniciar inativos.');

    $invalidEventRejected = false;

    try {
        ModuleEvent::fromArray(['event_id' => 'invalid']);
    } catch (ModuleException) {
        $invalidEventRejected = true;
    }

    assertModuleRuntime($invalidEventRejected, 'Evento inválido não foi rejeitado.');

    $sensitiveEventRejected = false;

    try {
        ModuleEvent::fromArray([
            'event_id' => 'EVA-EVT-AAAAAAAAAAAAAAAAAAAAAAAA',
            'event_type' => 'interaction.completed',
            'contract_version' => 1,
            'occurred_at' => '2026-08-03T12:00:00-03:00',
            'actor' => ['user_id' => 1, 'role' => 'user'],
            'scope' => ['projects' => [], 'documents' => []],
            'interaction' => ['current_input' => 'a', 'contextual_input' => 'a', 'answer' => 'b'],
            'evidences' => [['api_key' => 'proibida']],
            'limitations' => [],
        ]);
    } catch (ModuleException) {
        $sensitiveEventRejected = true;
    }

    assertModuleRuntime($sensitiveEventRejected, 'Evento com campo sensível não foi rejeitado.');

    $traversalDirectory = $temporaryRoot . '/com.example.traversal';
    mkdir($traversalDirectory, 0700, true);
    file_put_contents($traversalDirectory . '/module.json', json_encode([
        'id' => 'com.example.traversal',
        'name' => 'Traversal',
        'vendor' => 'Test',
        'version' => '1.0.0',
        'eva_contract' => '1',
        'entrypoint' => '../outside.php',
        'subscribed_events' => [],
        'capabilities' => [],
        'storage' => ['driver' => 'sqlite', 'schema_version' => 1],
    ], JSON_THROW_ON_ERROR));
    $traversalRejected = false;

    try {
        ModuleManifest::fromDirectory($traversalDirectory);
    } catch (ModuleException) {
        $traversalRejected = true;
    }

    assertModuleRuntime($traversalRejected, 'Entry point com path traversal não foi rejeitado.');

    $state->setActive('com.example.capture', true);
    $state->setActive('com.example.second', true);
    $state->setActive('com.example.unrelated', true);
    $first = $bridge->emit(
        'interaction.completed',
        ['user_id' => 1, 'role' => 'user'],
        ['projects' => [], 'documents' => []],
        ['current_input' => 'Pergunta', 'contextual_input' => 'Pergunta', 'answer' => 'Resposta']
    );
    assertModuleRuntime($events->append($first) === 1, 'A inserção idempotente não retornou a linha existente.');
    $result = $dispatcher->consumeOnce();
    assertModuleRuntime($result['processed'] === 2, 'O fan-out não processou os dois módulos.');
    assertModuleRuntime($result['failed'] === 0, 'O fan-out apresentou falha inesperada.');
    $unrelatedStorage = $storageFactory->open($registry->find('com.example.unrelated'));
    assertModuleRuntime((int) $unrelatedStorage->query('SELECT COUNT(*) FROM unrelated_events')->fetchColumn() === 0, 'Módulo não assinante recebeu o evento.');

    foreach (['com.example.capture', 'com.example.second'] as $moduleId) {
        $manifest = $registry->find($moduleId);
        $storage = $storageFactory->open($manifest);
        assertModuleRuntime((int) $storage->query('SELECT COUNT(*) FROM captured_events')->fetchColumn() === 1, 'O fixture não persistiu o evento.');
        assertModuleRuntime((int) $storage->query('SELECT last_event_row_id FROM runtime_event_cursor')->fetchColumn() === 1, 'O cursor do fixture não avançou.');
        assertModuleRuntime(strtolower((string) $storage->query('PRAGMA journal_mode')->fetchColumn()) === 'wal', 'O SQLite não está em WAL.');
        assertModuleRuntime((int) $storage->query('PRAGMA foreign_keys')->fetchColumn() === 1, 'Foreign keys não foram habilitadas.');
        assertModuleRuntime((int) $storage->query('PRAGMA busy_timeout')->fetchColumn() === 5000, 'Busy timeout não foi aplicado.');
    }

    $second = $bridge->emit(
        'interaction.completed',
        ['user_id' => 2, 'role' => 'user'],
        ['projects' => [], 'documents' => []],
        ['current_input' => 'Outra', 'contextual_input' => 'Outra', 'answer' => 'Resposta']
    );
    assertModuleRuntime($second->eventId !== $first->eventId, 'Eventos distintos receberam o mesmo identificador.');
    $state->setActive('com.example.fail', true);
    $failureResult = $dispatcher->consumeOnce();
    assertModuleRuntime($failureResult['failed'] === 1, 'A falha isolada não foi registrada.');
    assertModuleRuntime($failureResult['processed'] === 2, 'A falha de um módulo interrompeu os demais.');

    $captureStorage = $storageFactory->open($registry->find('com.example.capture'));
    assertModuleRuntime((int) $captureStorage->query('SELECT COUNT(*) FROM captured_events')->fetchColumn() === 2, 'O módulo saudável não processou o segundo evento.');
    $failingStorage = $storageFactory->open($registry->find('com.example.fail'));
    assertModuleRuntime((int) $failingStorage->query('SELECT last_event_row_id FROM runtime_event_cursor')->fetchColumn() === 0, 'O cursor do módulo com falha avançou indevidamente.');

    $state->setActive('com.example.capture', false);
    $state->setActive('com.example.second', false);
    $state->setActive('com.example.fail', false);
    $state->setActive('com.example.unrelated', false);
    $bridge->emit(
        'interaction.completed',
        ['user_id' => 3, 'role' => 'user'],
        ['projects' => [], 'documents' => []],
        ['current_input' => 'Inativa', 'contextual_input' => 'Inativa', 'answer' => 'Resposta']
    );
    $inactiveResult = $dispatcher->consumeOnce();
    assertModuleRuntime($inactiveResult['processed'] === 0 && $inactiveResult['failed'] === 0, 'Módulos inativos receberam eventos.');
    assertModuleRuntime((int) $captureStorage->query('SELECT COUNT(*) FROM captured_events')->fetchColumn() === 2, 'O módulo inativo persistiu evento.');

    echo sprintf("Runtime modular validado com %d asserções.\n", $assertions);
} finally {
    foreach ([$storage ?? null, $captureStorage ?? null, $failingStorage ?? null, $unrelatedStorage ?? null] as $openStorage) {
        if ($openStorage instanceof PDO) {
            $openStorage->exec('PRAGMA wal_checkpoint(TRUNCATE)');
        }
    }

    unset($openStorage, $storage, $captureStorage, $failingStorage, $unrelatedStorage, $dispatcher, $storageFactory, $registry, $events, $bridge, $database);
    gc_collect_cycles();
    usleep(100_000);
    removeModuleRuntimeDirectory($temporaryRoot);
}
