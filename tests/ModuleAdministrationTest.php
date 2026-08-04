<?php

declare(strict_types=1);

use Eva\ModuleRuntime\RuntimeFactory;

$container = require __DIR__ . '/bootstrap.php';
$temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'eva-module-runtime-' . bin2hex(random_bytes(6));
$modulesRoot = $temporaryRoot . DIRECTORY_SEPARATOR . 'modules';
$fixturesRoot = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'modules';
$assertions = 0;

function assertModuleAdministration(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function copyModuleAdministrationFixture(string $source, string $target): void
{
    if (!mkdir($target, 0700, true) && !is_dir($target)) {
        throw new RuntimeException('O fixture modular não pôde ser criado.');
    }

    foreach (new DirectoryIterator($source) as $item) {
        if ($item->isDot()) {
            continue;
        }

        $destination = $target . DIRECTORY_SEPARATOR . $item->getFilename();

        if ($item->isDir()) {
            copyModuleAdministrationFixture($item->getPathname(), $destination);
        } elseif (!copy($item->getPathname(), $destination)) {
            throw new RuntimeException('Um arquivo do fixture modular não pôde ser copiado.');
        }
    }
}

function removeModuleAdministrationTemporaryRoot(string $path): void
{
    $resolved = realpath($path);
    $temporary = rtrim(realpath(sys_get_temp_dir()) ?: sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if ($resolved === false || !str_starts_with($resolved . DIRECTORY_SEPARATOR, $temporary)
        || !str_starts_with(basename($resolved), 'eva-module-runtime-')) {
        throw new RuntimeException('A limpeza administrativa recusou um caminho inesperado.');
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $item->isLink() || !$item->isDir() ? unlink($item->getPathname()) : rmdir($item->getPathname());
    }

    rmdir($resolved);
}

mkdir($modulesRoot, 0700, true);
copyModuleAdministrationFixture($fixturesRoot . '/com.example.capture', $modulesRoot . '/com.example.capture');
copyModuleAdministrationFixture($fixturesRoot . '/com.example.second', $modulesRoot . '/com.example.second');
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

try {
    $activated = $factory->manager()->setActive('com.example.capture', true);
    assertModuleAdministration($activated['active'] === true, 'O módulo temporário não foi ativado.');
    $dataDirectory = $modulesRoot . '/.runtime/data/com.example.capture';
    assertModuleAdministration(is_file($dataDirectory . '/module.sqlite'), 'A ativação não criou o SQLite do módulo.');
    file_put_contents($dataDirectory . '/history-marker.txt', 'history');
    $factory->manager()->setActive('com.example.capture', false);
    assertModuleAdministration(is_file($dataDirectory . '/module.sqlite'), 'Desativar excluiu indevidamente o histórico.');

    $deletion = $factory->manager()->delete('com.example.capture');
    assertModuleAdministration($deletion['package_entries_deleted'] > 0, 'A exclusão não removeu o pacote.');
    assertModuleAdministration($deletion['data_entries_deleted'] > 0, 'A exclusão não removeu os dados.');
    assertModuleAdministration(!is_dir($modulesRoot . '/com.example.capture'), 'O diretório do pacote permaneceu após a exclusão.');
    assertModuleAdministration(!is_dir($dataDirectory), 'O SQLite ou histórico permaneceu após a exclusão.');
    assertModuleAdministration(is_dir($modulesRoot . '/com.example.second'), 'A exclusão atingiu outro módulo.');
    assertModuleAdministration(is_dir($modulesRoot . '/.runtime'), 'A exclusão atingiu o Runtime privado.');
    assertModuleAdministration(!isset($factory->state()->read()['modules']['com.example.capture']), 'O estado do módulo excluído permaneceu registrado.');

    echo sprintf("Administração modular validada com %d asserções.\n", $assertions);
} finally {
    unset($factory, $database);
    gc_collect_cycles();
    usleep(100_000);
    removeModuleAdministrationTemporaryRoot($temporaryRoot);
}
