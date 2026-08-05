<?php

declare(strict_types=1);

use Eva\Http\Security\ActorContext;
use Eva\ModuleRuntime\ModuleAccessDeniedException;
use Eva\ModuleRuntime\ModuleException;
use Eva\ModuleRuntime\RuntimeFactory;

require __DIR__ . '/bootstrap.php';

$temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'eva-module-connector-' . bin2hex(random_bytes(6));
$source = __DIR__ . '/fixtures/modules/com.example.interactive';
$target = $temporaryRoot . '/modules/com.example.interactive';
$assertions = 0;

function assertModuleConnector(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function copyModuleConnectorFixture(string $source, string $target): void
{
    if (!mkdir($target, 0700, true) && !is_dir($target)) {
        throw new RuntimeException('O fixture do conector não pôde ser criado.');
    }

    foreach (new DirectoryIterator($source) as $item) {
        if ($item->isDot()) continue;
        $destination = $target . DIRECTORY_SEPARATOR . $item->getFilename();

        if ($item->isDir()) {
            copyModuleConnectorFixture($item->getPathname(), $destination);
        } elseif (!copy($item->getPathname(), $destination)) {
            throw new RuntimeException('Um arquivo do fixture do conector não pôde ser copiado.');
        }
    }
}

function removeModuleConnectorRoot(string $path): void
{
    $resolved = realpath($path);
    $temporary = rtrim(realpath(sys_get_temp_dir()) ?: sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if ($resolved === false || !str_starts_with($resolved . DIRECTORY_SEPARATOR, $temporary)
        || !str_starts_with(basename($resolved), 'eva-module-connector-')) {
        throw new RuntimeException('A limpeza do conector recusou um caminho inesperado.');
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

copyModuleConnectorFixture($source, $target);
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
$factory = new RuntimeFactory($temporaryRoot, $database, ['live_enabled' => false]);
$manager = $factory->manager();
$allowed = new ActorContext('allowed', 'user', 1, 'allowed');
$denied = new ActorContext('denied', 'user', 2, 'denied');
$admin = new ActorContext('admin');

try {
    $manager->setActive('com.example.interactive', true);
    assertModuleConnector(count($manager->dashboards($allowed)) === 1, 'O usuário habilitado não recebeu o módulo.');
    assertModuleConnector($manager->dashboards($denied) === [], 'O usuário sem habilitação recebeu o módulo.');
    assertModuleConnector(count($manager->dashboards($admin)) === 1, 'O superadmin perdeu o acesso administrativo.');
    assertModuleConnector(
        ($manager->dashboard('com.example.interactive', $allowed, [])['contract'] ?? null) === 'eva.module.dashboard/1',
        'O dashboard autorizado não respeitou o contrato.'
    );

    $deniedDashboard = false;

    try {
        $manager->dashboard('com.example.interactive', $denied, []);
    } catch (ModuleAccessDeniedException) {
        $deniedDashboard = true;
    }

    assertModuleConnector($deniedDashboard, 'O acesso direto ao dashboard não foi bloqueado.');
    $result = $manager->action(
        'com.example.interactive',
        $allowed,
        'save_value',
        ['value' => 'persistido'],
        'request-0001'
    );
    assertModuleConnector(
        ($result['contract'] ?? null) === 'eva.module.action/1'
        && ($result['dashboard']['contract'] ?? null) === 'eva.module.dashboard/1',
        'A ação não respeitou os contratos do conector.'
    );
    $storage = $factory->storage()->open($factory->registry()->find('com.example.interactive'));
    assertModuleConnector(
        (int) $storage->query('SELECT COUNT(*) FROM action_requests')->fetchColumn() === 1,
        'A ação não foi persistida no SQLite privado.'
    );
    $manager->action('com.example.interactive', $allowed, 'save_value', ['value' => 'repetido'], 'request-0001');
    assertModuleConnector(
        (int) $storage->query('SELECT COUNT(*) FROM action_requests')->fetchColumn() === 1,
        'A chave de requisição não permitiu idempotência no módulo.'
    );

    $deniedAction = false;

    try {
        $manager->action('com.example.interactive', $denied, 'save_value', ['value' => 'x'], 'request-0002');
    } catch (ModuleAccessDeniedException) {
        $deniedAction = true;
    }

    assertModuleConnector($deniedAction, 'A ação direta não respeitou a autorização modular.');
    $sensitiveRejected = false;

    try {
        $manager->action('com.example.interactive', $allowed, 'save_value', ['api_token' => 'x'], 'request-0003');
    } catch (ModuleException) {
        $sensitiveRejected = true;
    }

    assertModuleConnector($sensitiveRejected, 'O conector aceitou um campo sensível.');
    $queryCapabilityRejected = false;

    try {
        $factory->context($factory->registry()->find('com.example.interactive'), $allowed)
            ->scopedQuery()
            ->answer([['type' => 'project', 'id' => 1]], 'Consulta');
    } catch (ModuleException $exception) {
        $queryCapabilityRejected = str_contains($exception->getMessage(), 'core.query.scoped');
    }

    assertModuleConnector($queryCapabilityRejected, 'A consulta escopada ignorou a capacidade declarada.');

    echo sprintf("Conector modular validado com %d asserções.\n", $assertions);
} finally {
    if (isset($storage) && $storage instanceof PDO) {
        $storage->exec('PRAGMA wal_checkpoint(TRUNCATE)');
    }

    unset($storage, $manager, $factory, $database);
    gc_collect_cycles();
    usleep(100_000);
    removeModuleConnectorRoot($temporaryRoot);
}
