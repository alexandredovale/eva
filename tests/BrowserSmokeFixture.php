<?php

declare(strict_types=1);

use Eva\Infrastructure\Database\Connection;
use Eva\Support\Env;

$container = require __DIR__ . '/bootstrap.php';
$database = Connection::create($container['database']);
$arguments = array_slice($argv, 1);

if (in_array('--baseline', $arguments, true)) {
    echo json_encode([
        'users' => (int) $database->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'sessions' => (int) $database->query('SELECT COUNT(*) FROM user_sessions')->fetchColumn(),
        'audit_max_id' => (int) $database->query('SELECT COALESCE(MAX(id), 0) FROM audit_events')->fetchColumn(),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

$summaryArgument = array_values(array_filter(
    $arguments,
    static fn (string $argument): bool => str_starts_with($argument, '--audit-summary-after=')
));

if (isset($summaryArgument[0])) {
    $auditId = (int) substr($summaryArgument[0], strlen('--audit-summary-after='));
    $statement = $database->prepare(
        'SELECT id, event_type, entity_type, entity_id FROM audit_events WHERE id > :id ORDER BY id ASC'
    );
    $statement->execute(['id' => max(0, $auditId)]);
    echo json_encode($statement->fetchAll(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

$purgeAdminArgument = array_values(array_filter(
    $arguments,
    static fn (string $argument): bool => str_starts_with($argument, '--purge-admin-logout-after=')
));

if (isset($purgeAdminArgument[0])) {
    $auditId = (int) substr($purgeAdminArgument[0], strlen('--purge-admin-logout-after='));
    $adminToken = (string) Env::get('ADMIN_API_TOKEN', '');

    if ($auditId < 0 || strlen($adminToken) < 24) {
        fwrite(STDERR, "Não foi possível validar o intervalo ou a credencial administrativa.\n");
        exit(1);
    }

    $statement = $database->prepare(
        "DELETE FROM audit_events
          WHERE id > :audit_id
            AND event_type = 'user_logout'
            AND entity_type = 'user'
            AND entity_id IS NULL
            AND actor_fingerprint = :actor_fingerprint"
    );
    $statement->execute([
        'audit_id' => $auditId,
        'actor_fingerprint' => hash('sha256', $adminToken),
    ]);
    echo json_encode(['audit_events_deleted' => $statement->rowCount()], JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(0);
}

$usernameArgument = array_values(array_filter(
    $arguments,
    static fn (string $argument): bool => str_starts_with($argument, '--cleanup=')
));
$auditArgument = array_values(array_filter(
    $arguments,
    static fn (string $argument): bool => str_starts_with($argument, '--after-audit-id=')
));

$username = isset($usernameArgument[0]) ? substr($usernameArgument[0], strlen('--cleanup=')) : '';
$auditId = isset($auditArgument[0]) ? (int) substr($auditArgument[0], strlen('--after-audit-id=')) : -1;

if (preg_match('/^smoke_[a-f0-9]{12}$/', $username) !== 1 || $auditId < 0) {
    fwrite(STDERR, "Use --baseline ou --cleanup=smoke_<12 hex> --after-audit-id=<id>.\n");
    exit(1);
}

$statement = $database->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
$statement->execute(['username' => $username]);
$userId = $statement->fetchColumn();

if ($userId === false) {
    fwrite(STDERR, "O usuário temporário do smoke test não foi localizado.\n");
    exit(1);
}

$userId = (int) $userId;
$actorFingerprint = hash('sha256', 'user:' . $userId);
$database->beginTransaction();

try {
    $deleteAudit = $database->prepare(
        'DELETE FROM audit_events
          WHERE id > :audit_id
            AND ((entity_type = :entity_type AND entity_id = :entity_id)
                 OR actor_fingerprint = :actor_fingerprint)'
    );
    $deleteAudit->execute([
        'audit_id' => $auditId,
        'entity_type' => 'user',
        'entity_id' => (string) $userId,
        'actor_fingerprint' => $actorFingerprint,
    ]);
    $auditDeleted = $deleteAudit->rowCount();

    $deleteUser = $database->prepare('DELETE FROM users WHERE id = :id AND username = :username');
    $deleteUser->execute(['id' => $userId, 'username' => $username]);

    if ($deleteUser->rowCount() !== 1) {
        throw new RuntimeException('A limpeza não removeu exatamente um usuário temporário.');
    }

    $database->commit();
} catch (Throwable $exception) {
    $database->rollBack();
    throw $exception;
}

echo json_encode([
    'user_deleted' => true,
    'audit_events_deleted' => $auditDeleted,
    'users' => (int) $database->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'sessions' => (int) $database->query('SELECT COUNT(*) FROM user_sessions')->fetchColumn(),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
