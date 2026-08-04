<?php

declare(strict_types=1);

use Eva\Http\Product\ProductApi;
use Eva\Infrastructure\Database\Connection;
use Eva\Infrastructure\Logging\FileLogger;

$container = require __DIR__ . '/bootstrap.php';
$database = Connection::create($container['database']);
$api = new ProductApi($database, $container, new FileLogger($container['logging']['path']));
$assertions = 0;
$originalToken = getenv('ADMIN_API_TOKEN');
$adminToken = str_repeat('U', 32);
$auditBaseline = (int) $database->query('SELECT COALESCE(MAX(id), 0) FROM audit_events')->fetchColumn();
$userIds = [];
$projectId = null;

putenv('ADMIN_API_TOKEN=' . $adminToken);
$_ENV['ADMIN_API_TOKEN'] = $adminToken;

function assertUserAdministration(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array<string, mixed> */
function userAdministrationServer(string $token): array
{
    return ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'REMOTE_ADDR' => '198.51.100.91'];
}

/** @param array<string, mixed> $payload */
function userAdministrationRequest(
    ProductApi $api,
    string $method,
    string $path,
    string $token,
    array $payload = []
): Eva\Http\Product\HttpResponse {
    return $api->handle(
        $method,
        $path,
        userAdministrationServer($token),
        [],
        [],
        $payload === [] ? '' : json_encode($payload, JSON_THROW_ON_ERROR)
    );
}

try {
    $suffix = bin2hex(random_bytes(5));
    $originalUsername = 'usuario_original_' . $suffix;
    $renamedUsername = 'usuario_renomeado_' . $suffix;
    $reusedUsername = 'usuario_reaproveitado_' . $suffix;
    $collisionUsername = 'usuario_existente_' . $suffix;
    $password = 'Senha-administrativa-123';

    $created = userAdministrationRequest(
        $api,
        'POST',
        '/api/admin/users',
        $adminToken,
        ['username' => $originalUsername, 'password' => $password]
    );
    assertUserAdministration($created->status === 201, 'O usuário principal não foi criado.');
    $userId = (int) $created->payload['user']['id'];
    $userIds[] = $userId;

    $collision = userAdministrationRequest(
        $api,
        'POST',
        '/api/admin/users',
        $adminToken,
        ['username' => $collisionUsername, 'password' => $password]
    );
    assertUserAdministration($collision->status === 201, 'O usuário usado para validar colisão não foi criado.');
    $collisionUserId = (int) $collision->payload['user']['id'];
    $userIds[] = $collisionUserId;

    $project = userAdministrationRequest(
        $api,
        'POST',
        '/api/admin/projects',
        $adminToken,
        ['name' => 'Projeto de usuário ' . $suffix, 'document_ids' => []]
    );
    assertUserAdministration($project->status === 201, 'O projeto temporário não foi criado.');
    $projectId = (int) $project->payload['project']['id'];

    $permissions = userAdministrationRequest(
        $api,
        'PUT',
        "/api/admin/users/{$userId}/permissions",
        $adminToken,
        ['project_ids' => [$projectId], 'document_ids' => []]
    );
    assertUserAdministration($permissions->status === 200, 'A permissão temporária não foi atribuída.');

    $login = $api->handle(
        'POST',
        '/api/auth/login',
        ['REMOTE_ADDR' => '198.51.100.92'],
        [],
        [],
        json_encode(['username' => $originalUsername, 'password' => $password], JSON_THROW_ON_ERROR)
    );
    assertUserAdministration($login->status === 200, 'O usuário principal não iniciou sessão antes da renomeação.');
    $userToken = (string) $login->payload['token'];

    $renamed = userAdministrationRequest(
        $api,
        'PATCH',
        "/api/admin/users/{$userId}",
        $adminToken,
        ['username' => $renamedUsername]
    );
    assertUserAdministration(
        $renamed->status === 200
            && (int) $renamed->payload['user']['id'] === $userId
            && $renamed->payload['user']['username'] === $renamedUsername,
        'A renomeação não preservou o ID do usuário.'
    );

    $sameSession = userAdministrationRequest($api, 'GET', '/api/me', $userToken);
    assertUserAdministration(
        $sameSession->status === 200 && $sameSession->payload['user']['username'] === $renamedUsername,
        'A sessão vigente não passou a refletir o novo username.'
    );

    $listed = userAdministrationRequest($api, 'GET', '/api/admin/users', $adminToken);
    $listedUser = null;

    foreach ($listed->payload['users'] ?? [] as $candidate) {
        if ((int) ($candidate['id'] ?? 0) === $userId) {
            $listedUser = $candidate;
            break;
        }
    }

    assertUserAdministration(
        is_array($listedUser)
            && $listedUser['username'] === $renamedUsername
            && in_array($projectId, array_map('intval', $listedUser['project_ids']), true),
        'A renomeação não preservou as permissões do usuário.'
    );

    $oldUsernameLogin = $api->handle(
        'POST',
        '/api/auth/login',
        ['REMOTE_ADDR' => '198.51.100.92'],
        [],
        [],
        json_encode(['username' => $originalUsername, 'password' => $password], JSON_THROW_ON_ERROR)
    );
    assertUserAdministration($oldUsernameLogin->status === 401, 'O username anterior continuou autenticando.');

    $duplicate = userAdministrationRequest(
        $api,
        'PATCH',
        "/api/admin/users/{$userId}",
        $adminToken,
        ['username' => $collisionUsername]
    );
    assertUserAdministration($duplicate->status === 409, 'Um username duplicado foi aceito na renomeação.');

    $invalid = userAdministrationRequest(
        $api,
        'PATCH',
        "/api/admin/users/{$userId}",
        $adminToken,
        ['username' => 'nome inválido com espaços']
    );
    assertUserAdministration($invalid->status === 422, 'Um username inválido foi aceito na renomeação.');

    $deactivated = userAdministrationRequest(
        $api,
        'PATCH',
        "/api/admin/users/{$userId}",
        $adminToken,
        ['active' => false]
    );
    assertUserAdministration($deactivated->status === 200, 'O usuário não foi desativado antes do reaproveitamento.');

    $reused = userAdministrationRequest(
        $api,
        'PATCH',
        "/api/admin/users/{$userId}",
        $adminToken,
        ['username' => $reusedUsername]
    );
    assertUserAdministration($reused->status === 200, 'O usuário desativado não pôde ser renomeado para reaproveitamento.');

    $wrongConfirmation = userAdministrationRequest(
        $api,
        'DELETE',
        "/api/admin/users/{$userId}",
        $adminToken,
        ['confirm_username' => $renamedUsername]
    );
    assertUserAdministration($wrongConfirmation->status === 422, 'A exclusão aceitou um username de confirmação incorreto.');

    $reactivated = userAdministrationRequest(
        $api,
        'PATCH',
        "/api/admin/users/{$userId}",
        $adminToken,
        ['active' => true]
    );
    assertUserAdministration($reactivated->status === 200, 'O usuário reaproveitado não foi reativado.');

    $reusedLogin = $api->handle(
        'POST',
        '/api/auth/login',
        ['REMOTE_ADDR' => '198.51.100.92'],
        [],
        [],
        json_encode(['username' => $reusedUsername, 'password' => $password], JSON_THROW_ON_ERROR)
    );
    assertUserAdministration($reusedLogin->status === 200, 'O cadastro reaproveitado não autenticou com a senha preservada.');
    $reusedToken = (string) $reusedLogin->payload['token'];

    $deleted = userAdministrationRequest(
        $api,
        'DELETE',
        "/api/admin/users/{$userId}",
        $adminToken,
        ['confirm_username' => $reusedUsername]
    );
    assertUserAdministration(
        $deleted->status === 200
            && $deleted->payload['deletion']['username'] === $reusedUsername
            && (int) $deleted->payload['deletion']['sessions_deleted'] >= 1
            && (int) $deleted->payload['deletion']['project_permissions_deleted'] === 1,
        'A exclusão não informou corretamente as sessões e permissões removidas.'
    );
    $userIds = array_values(array_filter($userIds, static fn (int $id): bool => $id !== $userId));

    $deletedSession = userAdministrationRequest($api, 'GET', '/api/me', $reusedToken);
    assertUserAdministration($deletedSession->status === 401, 'Uma sessão permaneceu válida após excluir o usuário.');

    $userCount = $database->prepare('SELECT COUNT(*) FROM users WHERE id = :id');
    $userCount->execute(['id' => $userId]);
    assertUserAdministration((int) $userCount->fetchColumn() === 0, 'O cadastro excluído permaneceu no banco.');

    $projectCount = $database->prepare('SELECT COUNT(*) FROM projects WHERE id = :id');
    $projectCount->execute(['id' => $projectId]);
    assertUserAdministration((int) $projectCount->fetchColumn() === 1, 'A exclusão do usuário removeu indevidamente o projeto.');

    $auditTypes = $database->prepare(
        "SELECT event_type FROM audit_events
          WHERE entity_type = 'user' AND entity_id = :entity_id AND id > :baseline"
    );
    $auditTypes->execute(['entity_id' => (string) $userId, 'baseline' => $auditBaseline]);
    $events = array_column($auditTypes->fetchAll(), 'event_type');
    assertUserAdministration(
        in_array('user_renamed', $events, true) && in_array('user_deleted', $events, true),
        'As ações de renomear e excluir não foram registradas na auditoria.'
    );
} finally {
    foreach ($userIds as $remainingUserId) {
        $delete = $database->prepare('DELETE FROM users WHERE id = :id');
        $delete->execute(['id' => $remainingUserId]);
    }

    if ($projectId !== null) {
        $delete = $database->prepare('DELETE FROM projects WHERE id = :id');
        $delete->execute(['id' => $projectId]);
    }

    $deleteAudit = $database->prepare('DELETE FROM audit_events WHERE id > :baseline');
    $deleteAudit->execute(['baseline' => $auditBaseline]);

    if ($originalToken === false) {
        putenv('ADMIN_API_TOKEN');
        unset($_ENV['ADMIN_API_TOKEN']);
    } else {
        putenv('ADMIN_API_TOKEN=' . $originalToken);
        $_ENV['ADMIN_API_TOKEN'] = $originalToken;
    }
}

echo sprintf("Administração de usuários validada com %d asserções.\n", $assertions);
