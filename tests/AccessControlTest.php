<?php

declare(strict_types=1);

use Eva\Application\Access\ScopeAccessService;
use Eva\Application\Ingestion\DocumentIngestionService;
use Eva\Http\Product\ProductApi;
use Eva\Http\Security\ActorContext;
use Eva\Infrastructure\Database\Connection;
use Eva\Infrastructure\Logging\FileLogger;
use Eva\Infrastructure\Storage\DocumentStorage;

$container = require __DIR__ . '/bootstrap.php';
$container['ai']['live_enabled'] = false;
$database = Connection::create($container['database']);
$storage = new DocumentStorage($container['ingestion']['document_storage']);
$api = new ProductApi($database, $container, new FileLogger($container['logging']['path']));
$assertions = 0;
$originalToken = getenv('ADMIN_API_TOKEN');
$adminToken = str_repeat('A', 32);
$userId = null;
$projectId = null;
$documents = [];

putenv('ADMIN_API_TOKEN=' . $adminToken);
$_ENV['ADMIN_API_TOKEN'] = $adminToken;

function assertAccess(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array<string, mixed> */
function accessServer(string $token): array
{
    return ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'REMOTE_ADDR' => '198.51.100.31'];
}

try {
    $ingestion = new DocumentIngestionService(
        $database,
        $storage,
        $container['ingestion']['max_document_bytes']
    );

    foreach (['Obra permitida', 'Obra bloqueada'] as $index => $title) {
        $result = $ingestion->ingest(
            'access-' . bin2hex(random_bytes(5)) . '.md',
            "# {$title}\n\nConteúdo documental de controle de acesso {$index}.",
            $title
        );
        $documents[] = $result;
    }

    $admin = accessServer($adminToken);
    $username = 'usuario_' . bin2hex(random_bytes(4));
    $created = $api->handle(
        'POST',
        '/api/admin/users',
        $admin,
        [],
        [],
        json_encode(['username' => $username, 'password' => 'Senha-inicial-123'], JSON_THROW_ON_ERROR)
    );
    assertAccess($created->status === 201, 'O superadmin não conseguiu cadastrar o usuário.');
    $userId = (int) $created->payload['user']['id'];
    $initialRecoveryCode = (string) $created->payload['recovery_code'];
    assertAccess(strlen($initialRecoveryCode) === 16, 'O código inicial não possui 16 caracteres.');

    $project = $api->handle(
        'POST',
        '/api/admin/projects',
        $admin,
        [],
        [],
        json_encode([
            'name' => 'Projeto de acesso ' . bin2hex(random_bytes(3)),
            'document_ids' => [$documents[0]->documentId],
        ], JSON_THROW_ON_ERROR)
    );
    assertAccess($project->status === 201, 'O projeto não foi criado.');
    $projectId = (int) $project->payload['project']['id'];

    $adminScopes = $api->handle('GET', '/api/scopes', $admin, [], [], '');
    $adminIndividualIds = array_map(
        'intval',
        array_column($adminScopes->payload['scopes']['documents'], 'id')
    );
    assertAccess(
        !in_array($documents[0]->documentId, $adminIndividualIds, true),
        'A obra vinculada ao projeto apareceu também como obra individual para o superadmin.'
    );
    assertAccess(
        in_array($documents[1]->documentId, $adminIndividualIds, true),
        'A obra sem projeto não apareceu como obra individual para o superadmin.'
    );

    $permissions = $api->handle(
        'PUT',
        "/api/admin/users/{$userId}/permissions",
        $admin,
        [],
        [],
        json_encode(['project_ids' => [$projectId], 'document_ids' => []], JSON_THROW_ON_ERROR)
    );
    assertAccess($permissions->status === 200, 'As permissões do usuário não foram salvas.');

    $login = $api->handle(
        'POST',
        '/api/auth/login',
        ['REMOTE_ADDR' => '198.51.100.32'],
        [],
        [],
        json_encode(['username' => $username, 'password' => 'Senha-inicial-123'], JSON_THROW_ON_ERROR)
    );
    assertAccess($login->status === 200, 'O usuário cadastrado não conseguiu entrar.');
    $userToken = (string) $login->payload['token'];
    $userServer = accessServer($userToken);

    $scopes = $api->handle('GET', '/api/scopes', $userServer, [], [], '');
    assertAccess($scopes->status === 200 && count($scopes->payload['scopes']['projects']) === 1, 'O projeto permitido não apareceu no chat.');
    assertAccess(
        $scopes->payload['scopes']['projects'][0]['full_access'] === true,
        'O projeto concedido não foi identificado como acesso completo.'
    );
    assertAccess(
        (int) $scopes->payload['scopes']['projects'][0]['documents'][0]['id'] === $documents[0]->documentId,
        'A obra do projeto permitido não apareceu no chat.'
    );

    $actor = new ActorContext(hash('sha256', 'test-user'), 'user', $userId, $username);
    $access = new ScopeAccessService($database);
    assertAccess(
        $access->resolveDocumentIds($actor, 'project', $projectId) === [$documents[0]->documentId],
        'A consulta pelo projeto não resolveu a obra permitida.'
    );
    assertAccess(
        $access->resolveSelections($actor, [
            ['type' => 'project', 'id' => $projectId],
            ['type' => 'document', 'id' => $documents[0]->documentId],
        ]) === [$documents[0]->documentId],
        'A seleção múltipla não eliminou obras duplicadas.'
    );

    $individualPermissions = $api->handle(
        'PUT',
        "/api/admin/users/{$userId}/permissions",
        $admin,
        [],
        [],
        json_encode(['project_ids' => [], 'document_ids' => [$documents[0]->documentId]], JSON_THROW_ON_ERROR)
    );
    assertAccess($individualPermissions->status === 200, 'A permissão individual não foi salva.');
    $individualScopes = $access->scopes($actor);
    assertAccess(
        count($individualScopes['projects']) === 1
        && $individualScopes['projects'][0]['full_access'] === false
        && (int) $individualScopes['projects'][0]['documents'][0]['id'] === $documents[0]->documentId,
        'A obra individual não apareceu sob seu projeto sem conceder o projeto completo.'
    );

    $blockedProject = false;

    try {
        $access->resolveDocumentIds($actor, 'project', $projectId);
    } catch (Eva\Http\Security\AccessException $exception) {
        $blockedProject = $exception->httpStatus === 403;
    }

    assertAccess($blockedProject, 'A permissão individual concedeu acesso indevido ao projeto completo.');
    assertAccess(
        $access->resolveDocumentIds($actor, 'document', $documents[0]->documentId) === [$documents[0]->documentId],
        'A obra concedida individualmente não pôde ser consultada.'
    );

    $restoredPermissions = $api->handle(
        'PUT',
        "/api/admin/users/{$userId}/permissions",
        $admin,
        [],
        [],
        json_encode(['project_ids' => [$projectId], 'document_ids' => []], JSON_THROW_ON_ERROR)
    );
    assertAccess($restoredPermissions->status === 200, 'A permissão completa do projeto não foi restaurada.');

    $duplicatePermission = $database->prepare(
        'INSERT INTO user_documents (user_id, document_id) VALUES (:user_id, :document_id)'
    );
    $duplicatePermission->execute([
        'user_id' => $userId,
        'document_id' => $documents[0]->documentId,
    ]);
    $deduplicatedScopes = $access->scopes($actor);
    assertAccess(
        $deduplicatedScopes['documents'] === [],
        'A mesma obra apareceu como projeto e permissão individual para o usuário.'
    );

    $blocked = false;

    try {
        $access->resolveDocumentIds($actor, 'document', $documents[1]->documentId);
    } catch (Eva\Http\Security\AccessException $exception) {
        $blocked = $exception->httpStatus === 403;
    }

    assertAccess($blocked, 'O usuário conseguiu resolver uma obra não permitida.');

    $adminEndpoint = $api->handle('GET', '/api/admin/users', $userServer, [], [], '');
    assertAccess($adminEndpoint->status === 403, 'O usuário comum acessou uma função do superadmin.');

    $rotated = $api->handle(
        'POST',
        '/api/me/recovery-code',
        $userServer,
        [],
        [],
        json_encode(['current_password' => 'Senha-inicial-123'], JSON_THROW_ON_ERROR)
    );
    assertAccess($rotated->status === 200 && strlen((string) $rotated->payload['recovery_code']) === 16, 'A rotação do código de recuperação falhou.');
    $newRecoveryCode = (string) $rotated->payload['recovery_code'];

    $recovered = $api->handle(
        'POST',
        '/api/auth/recover',
        ['REMOTE_ADDR' => '198.51.100.33'],
        [],
        [],
        json_encode([
            'username' => $username,
            'recovery_code' => $newRecoveryCode,
            'new_password' => 'Senha-recuperada-456',
        ], JSON_THROW_ON_ERROR)
    );
    assertAccess($recovered->status === 200 && strlen((string) $recovered->payload['recovery_code']) === 16, 'A recuperação sem SMTP falhou.');

    $oldSession = $api->handle('GET', '/api/me', $userServer, [], [], '');
    assertAccess($oldSession->status === 401, 'A recuperação não encerrou a sessão anterior.');

    $newLogin = $api->handle(
        'POST',
        '/api/auth/login',
        [],
        [],
        [],
        json_encode(['username' => $username, 'password' => 'Senha-recuperada-456'], JSON_THROW_ON_ERROR)
    );
    assertAccess($newLogin->status === 200, 'A nova senha recuperada não permitiu login.');
} finally {
    if ($userId !== null) {
        $statement = $database->prepare('DELETE FROM users WHERE id = :id');
        $statement->execute(['id' => $userId]);
    }

    if ($projectId !== null) {
        $statement = $database->prepare('DELETE FROM projects WHERE id = :id');
        $statement->execute(['id' => $projectId]);
    }

    foreach ($documents as $document) {
        $statement = $database->prepare('DELETE FROM documents WHERE id = :id');
        $statement->execute(['id' => $document->documentId]);
        $storage->remove($document->storagePath);
    }

    if ($originalToken === false) {
        putenv('ADMIN_API_TOKEN');
        unset($_ENV['ADMIN_API_TOKEN']);
    } else {
        putenv('ADMIN_API_TOKEN=' . $originalToken);
        $_ENV['ADMIN_API_TOKEN'] = $originalToken;
    }
}

echo sprintf("Controle de acesso validado com %d asserções.\n", $assertions);
