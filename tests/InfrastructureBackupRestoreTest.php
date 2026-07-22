<?php

declare(strict_types=1);

use Eva\Infrastructure\Database\Connection;

$container = require __DIR__ . '/bootstrap.php';
$config = $container['database'];
$databaseName = (string) ($config['database'] ?? '');

if (preg_match('/^[A-Za-z0-9_]+$/', $databaseName) !== 1) {
    throw new RuntimeException('O nome do banco não pode ser usado no teste seguro de restauração.');
}

$mysqlDirectory = 'C:\\xampp\\mysql\\bin';
$mysql = $mysqlDirectory . '\\mysql.exe';
$mysqldump = $mysqlDirectory . '\\mysqldump.exe';

if (!is_file($mysql) || !is_file($mysqldump)) {
    throw new RuntimeException('Os utilitários de backup do MySQL não foram localizados.');
}

$temporaryRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
    . DIRECTORY_SEPARATOR . 'eva-infra-' . bin2hex(random_bytes(8));
$temporaryDatabase = 'eva_restore_smoke_' . bin2hex(random_bytes(6));
$dumpPath = $temporaryRoot . DIRECTORY_SEPARATOR . 'database.sql';
$archivePath = $temporaryRoot . DIRECTORY_SEPARATOR . 'documents.zip';
$restorePath = $temporaryRoot . DIRECTORY_SEPARATOR . 'restore';
$createdDatabase = false;

if (!mkdir($temporaryRoot, 0700, true) && !is_dir($temporaryRoot)) {
    throw new RuntimeException('Não foi possível criar a área temporária do teste.');
}

/** @param list<string> $command @param array<string, string> $environment */
function runInfrastructureProcess(array $command, array $environment): void
{
    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptor, $pipes, null, $environment, ['bypass_shell' => true]);

    if (!is_resource($process)) {
        throw new RuntimeException('Não foi possível iniciar um utilitário de infraestrutura.');
    }

    fclose($pipes[0]);
    stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    if (proc_close($process) !== 0) {
        throw new RuntimeException('Um utilitário de infraestrutura encerrou com falha.');
    }
}

/** @return array<string, int> */
function infrastructureCounts(PDO $database): array
{
    $tables = ['users', 'projects', 'documents', 'evidences', 'evidence_embeddings', 'audit_events'];
    $counts = [
        'tables' => (int) $database->query(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()'
        )->fetchColumn(),
    ];

    foreach ($tables as $table) {
        $counts[$table] = (int) $database->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }

    return $counts;
}

/** @return array<string, string> */
function fileHashes(string $root): array
{
    $hashes = [];

    if (!is_dir($root)) {
        return $hashes;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getFilename() === '.gitkeep') {
            continue;
        }

        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        $hashes[$relative] = hash_file('sha256', $file->getPathname());
    }

    ksort($hashes);

    return $hashes;
}

function removeInfrastructureTemporaryDirectory(string $path): void
{
    $temporaryBase = rtrim(realpath(sys_get_temp_dir()) ?: sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR;
    $resolved = realpath($path);

    if ($resolved === false
        || !str_starts_with($resolved . DIRECTORY_SEPARATOR, $temporaryBase)
        || !str_starts_with(basename($resolved), 'eva-infra-')) {
        throw new RuntimeException('A limpeza recusou um diretório fora da área temporária esperada.');
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

$processEnvironment = getenv();
$processEnvironment = is_array($processEnvironment) ? $processEnvironment : [];
$processEnvironment['MYSQL_PWD'] = (string) ($config['password'] ?? '');
$connectionArguments = [
    '--host=' . (string) ($config['host'] ?? '127.0.0.1'),
    '--port=' . (int) ($config['port'] ?? 3306),
    '--user=' . (string) ($config['username'] ?? 'root'),
    '--default-character-set=utf8mb4',
];
$adminDsn = sprintf(
    'mysql:host=%s;port=%d;charset=%s',
    (string) ($config['host'] ?? '127.0.0.1'),
    (int) ($config['port'] ?? 3306),
    (string) ($config['charset'] ?? 'utf8mb4')
);
$adminDatabase = new PDO(
    $adminDsn,
    (string) ($config['username'] ?? 'root'),
    (string) ($config['password'] ?? ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

try {
    runInfrastructureProcess([
        $mysqldump,
        ...$connectionArguments,
        '--single-transaction',
        '--routines',
        '--triggers',
        '--events',
        '--result-file=' . $dumpPath,
        $databaseName,
    ], $processEnvironment);

    $adminDatabase->exec(
        'CREATE DATABASE `' . $temporaryDatabase . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );
    $createdDatabase = true;
    runInfrastructureProcess([
        $mysql,
        ...$connectionArguments,
        '--database=' . $temporaryDatabase,
        '--execute=source ' . str_replace('\\', '/', $dumpPath),
    ], $processEnvironment);

    $sourceDatabase = Connection::create($config);
    $restoredConfig = $config;
    $restoredConfig['database'] = $temporaryDatabase;
    $restoredDatabase = Connection::create($restoredConfig);
    $sourceCounts = infrastructureCounts($sourceDatabase);
    $restoredCounts = infrastructureCounts($restoredDatabase);

    if ($sourceCounts !== $restoredCounts) {
        throw new RuntimeException('A restauração do banco apresentou contagens divergentes.');
    }

    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('A extensão ZipArchive não está disponível para validar os documentos.');
    }

    $storagePath = (string) $container['ingestion']['document_storage'];
    $sourceHashes = fileHashes($storagePath);
    $archive = new ZipArchive();

    if ($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Não foi possível criar o backup dos documentos.');
    }

    foreach (array_keys($sourceHashes) as $relative) {
        if (!$archive->addFile($storagePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative), $relative)) {
            $archive->close();
            throw new RuntimeException('Não foi possível adicionar um documento ao backup.');
        }
    }

    $archive->close();
    mkdir($restorePath, 0700, true);
    $restoredArchive = new ZipArchive();

    if ($restoredArchive->open($archivePath) !== true || !$restoredArchive->extractTo($restorePath)) {
        throw new RuntimeException('Não foi possível restaurar o backup dos documentos.');
    }

    $restoredArchive->close();
    $restoredHashes = fileHashes($restorePath);

    if ($sourceHashes !== $restoredHashes) {
        throw new RuntimeException('Os documentos restaurados não correspondem aos originais.');
    }

    echo json_encode([
        'database_restore' => 'passed',
        'database_counts' => $sourceCounts,
        'dump_bytes' => filesize($dumpPath),
        'dump_sha256' => hash_file('sha256', $dumpPath),
        'document_restore' => 'passed',
        'document_files' => count($sourceHashes),
        'archive_bytes' => filesize($archivePath),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    if ($createdDatabase) {
        $adminDatabase->exec('DROP DATABASE IF EXISTS `' . $temporaryDatabase . '`');
    }

    removeInfrastructureTemporaryDirectory($temporaryRoot);
}
