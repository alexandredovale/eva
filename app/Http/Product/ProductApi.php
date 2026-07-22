<?php

declare(strict_types=1);

namespace Eva\Http\Product;

use Eva\Application\Access\AccessManagementService;
use Eva\Application\Access\AuthService;
use Eva\Application\Access\ScopeAccessService;
use Eva\Application\Ingestion\DocumentIngestionService;
use Eva\Application\Product\BrandingPresenter;
use Eva\Application\Product\ContentDeletionService;
use Eva\Application\Product\ProductReadService;
use Eva\Application\Query\DocumentContextRetriever;
use Eva\Application\Query\DocumentQueryService;
use Eva\Application\Query\InputType;
use Eva\Application\Query\InputTypeDetector;
use Eva\Application\Query\QueryException;
use Eva\Application\Queue\CognitiveJobPlanner;
use Eva\Application\Queue\ProcessingQueueService;
use Eva\Application\Queue\QueueException;
use Eva\Http\Security\AccessException;
use Eva\Http\Security\ActorContext;
use Eva\Http\Security\AuthGuard;
use Eva\Http\Upload\DocumentUploadHandler;
use Eva\Http\Upload\DocumentUploadValidator;
use Eva\Infrastructure\Ai\AiProviderException;
use Eva\Infrastructure\Ai\CognitiveProviderFactory;
use Eva\Infrastructure\Audit\AuditRecorder;
use Eva\Infrastructure\Logging\FileLogger;
use Eva\Infrastructure\Logging\SafeFailureDiagnostics;
use Eva\Infrastructure\Storage\DocumentStorage;
use PDO;
use Throwable;

final readonly class ProductApi
{
    /** @param array<string, mixed> $container */
    public function __construct(
        private PDO $database,
        private array $container,
        private FileLogger $logger,
        private string $requestId = ''
    ) {
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $files
     * @param array<string, mixed> $post
     */
    public function handle(
        string $method,
        string $path,
        array $server,
        array $files,
        array $post,
        string $rawBody
    ): HttpResponse {
        $method = strtoupper($method);

        if ($path === '/api/branding') {
            return $method === 'GET'
                ? new HttpResponse(200, ['branding' => (new BrandingPresenter($this->container['branding']))->toArray()])
                : $this->methodNotAllowed('GET');
        }

        $audit = new AuditRecorder($this->database);
        $networkAddress = is_string($server['REMOTE_ADDR'] ?? null) ? $server['REMOTE_ADDR'] : null;

        if ($path === '/api/auth/login' || $path === '/api/auth/recover') {
            if ($method !== 'POST') {
                return $this->methodNotAllowed('POST');
            }

            try {
                $payload = (new JsonRequestParser())->parse($rawBody);
                $auth = new AuthService($this->database, $this->container['security']);

                if ($path === '/api/auth/login') {
                    $result = $auth->login(
                        is_string($payload['username'] ?? null) ? $payload['username'] : '',
                        is_string($payload['password'] ?? null) ? $payload['password'] : ''
                    );
                    $audit->record('user_login', 'user', (string) $result['user']['id'], null, $networkAddress);

                    return new HttpResponse(200, $result);
                }

                $result = $auth->recover(
                    is_string($payload['username'] ?? null) ? $payload['username'] : '',
                    is_string($payload['recovery_code'] ?? null) ? $payload['recovery_code'] : '',
                    is_string($payload['new_password'] ?? null) ? $payload['new_password'] : ''
                );
                $audit->record('password_recovered', 'user', null, null, $networkAddress);

                return new HttpResponse(200, $result);
            } catch (AccessException $exception) {
                $audit->record('access_denied', 'route', $path, null, $networkAddress, [
                    'method' => $method,
                    'http_status' => $exception->httpStatus,
                ]);

                return new HttpResponse($exception->httpStatus, ['error' => $exception->getMessage()]);
            } catch (ProductHttpException $exception) {
                return new HttpResponse($exception->httpStatus, ['error' => $exception->getMessage()]);
            }
        }

        try {
            $actor = (new AuthGuard($this->database, $this->container['security']))->authenticate($server);
        } catch (AccessException $exception) {
            $audit->record('access_denied', 'route', $path, null, $networkAddress, [
                'method' => $method,
                'http_status' => $exception->httpStatus,
            ]);

            return new HttpResponse(
                $exception->httpStatus,
                ['error' => $exception->getMessage()],
                ['WWW-Authenticate' => 'Bearer']
            );
        }

        try {
            return $this->dispatch($method, $path, $server, $files, $post, $rawBody, $actor, $audit);
        } catch (AccessException $exception) {
            return new HttpResponse($exception->httpStatus, ['error' => $exception->getMessage()]);
        } catch (ProductHttpException $exception) {
            return new HttpResponse($exception->httpStatus, ['error' => $exception->getMessage()]);
        } catch (QueueException $exception) {
            return new HttpResponse(409, ['error' => $exception->getMessage()]);
        } catch (QueryException $exception) {
            return new HttpResponse(422, ['error' => $exception->getMessage()]);
        } catch (AiProviderException $exception) {
            $this->logger->warning('product_ai_unavailable', SafeFailureDiagnostics::context($exception, [
                'route' => $path,
                'request_id' => $this->requestId,
                'capability' => 'query',
            ]));
            return new HttpResponse(503, ['error' => 'O provedor cognitivo está indisponível.']);
        } catch (Throwable $exception) {
            $this->logger->error('product_api_failed', SafeFailureDiagnostics::context($exception, [
                'route' => $path,
                'request_id' => $this->requestId,
            ]));
            return new HttpResponse(500, ['error' => 'Erro interno da aplicação.']);
        }
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $files
     * @param array<string, mixed> $post
     */
    private function dispatch(
        string $method,
        string $path,
        array $server,
        array $files,
        array $post,
        string $rawBody,
        ActorContext $actor,
        AuditRecorder $audit
    ): HttpResponse {
        $read = new ProductReadService($this->database);
        $networkAddress = is_string($server['REMOTE_ADDR'] ?? null) ? $server['REMOTE_ADDR'] : null;
        $auth = new AuthService($this->database, $this->container['security']);
        $scopeAccess = new ScopeAccessService($this->database);

        if ($path === '/api/me') {
            return $method === 'GET'
                ? new HttpResponse(200, ['user' => [
                    'id' => $actor->userId,
                    'username' => $actor->username,
                    'role' => $actor->role,
                ]])
                : $this->methodNotAllowed('GET');
        }

        if ($path === '/api/logout') {
            if ($method !== 'POST') {
                return $this->methodNotAllowed('POST');
            }

            $auth->logout($actor);
            $audit->record('user_logout', 'user', $actor->userId === null ? null : (string) $actor->userId, $actor->fingerprint, $networkAddress);

            return new HttpResponse(200, ['status' => 'ok']);
        }

        if ($path === '/api/me/password') {
            if ($method !== 'POST') {
                return $this->methodNotAllowed('POST');
            }

            $payload = (new JsonRequestParser())->parse($rawBody);
            $auth->changePassword(
                $actor,
                is_string($payload['current_password'] ?? null) ? $payload['current_password'] : '',
                is_string($payload['new_password'] ?? null) ? $payload['new_password'] : ''
            );
            $audit->record('password_changed', 'user', (string) $actor->userId, $actor->fingerprint, $networkAddress);

            return new HttpResponse(200, ['status' => 'ok']);
        }

        if ($path === '/api/me/recovery-code') {
            if ($method !== 'POST') {
                return $this->methodNotAllowed('POST');
            }

            $payload = (new JsonRequestParser())->parse($rawBody);
            $code = $auth->rotateRecoveryCode(
                $actor,
                is_string($payload['current_password'] ?? null) ? $payload['current_password'] : ''
            );
            $audit->record('recovery_code_rotated', 'user', (string) $actor->userId, $actor->fingerprint, $networkAddress);

            return new HttpResponse(200, ['recovery_code' => $code]);
        }

        if ($path === '/api/scopes') {
            return $method === 'GET'
                ? new HttpResponse(200, ['scopes' => $scopeAccess->scopes($actor)])
                : $this->methodNotAllowed('GET');
        }

        if ($path === '/api/query') {
            if ($method !== 'POST') {
                return $this->methodNotAllowed('POST');
            }

            $payload = (new JsonRequestParser())->parse($rawBody);
            $input = is_string($payload['input'] ?? null) ? trim($payload['input']) : '';

            if ($input === '' || strlen($input) > 20_000) {
                throw new ProductHttpException('Os dados da consulta são inválidos.', 422);
            }

            $selectedScopes = is_array($payload['scopes'] ?? null) ? $payload['scopes'] : [];

            if ($selectedScopes !== []) {
                $documentIds = $scopeAccess->resolveSelections($actor, $selectedScopes);
            } else {
                $scopeType = is_string($payload['scope_type'] ?? null) ? $payload['scope_type'] : 'document';
                $scopeValue = $payload['scope_id'] ?? $payload['document_id'] ?? null;
                $scopeId = filter_var($scopeValue, FILTER_VALIDATE_INT);

                if ($scopeId === false || $scopeId < 1) {
                    throw new ProductHttpException('Os dados da consulta são inválidos.', 422);
                }

                $selectedScopes = [['type' => $scopeType, 'id' => (int) $scopeId]];
                $documentIds = $scopeAccess->resolveDocumentIds($actor, $scopeType, (int) $scopeId);
            }

            $factory = new CognitiveProviderFactory($this->container['ai']);
            $detector = new InputTypeDetector();
            $understanding = $detector->detect($input);
            $needsEmbedding = $understanding->has(InputType::Conceptual)
                || $understanding->has(InputType::Relational);
            $retriever = new DocumentContextRetriever(
                $this->database,
                $needsEmbedding ? $factory->embeddings() : null,
                $detector
            );
            $result = (new DocumentQueryService($retriever, $factory->queryAnswers()))->queryDocuments(
                $documentIds,
                $input,
                (int) $this->container['ai']['query']['max_evidence'],
                (int) $this->container['ai']['query']['max_interactions']
            );
            $audit->record('document_queried', 'scope_selection', null, $actor->fingerprint, $networkAddress, [
                'scope_count' => count($selectedScopes),
                'document_count' => count($documentIds),
                'input_types' => $result->understanding->toArray()['types'],
                'evidence_count' => count($result->usedEvidences),
                'simetry_count' => count($result->simetryInteractions),
                'assimetry_count' => count($result->assimetryInteractions),
            ]);

            return new HttpResponse(200, ['query' => $result->toArray()]);
        }

        if (!$actor->isSuperadmin()) {
            throw new AccessException('Esta funcionalidade é exclusiva do superadmin.', 403);
        }

        $management = new AccessManagementService($this->database, $auth);

        if ($path === '/api/admin/users') {
            if ($method === 'GET') {
                return new HttpResponse(200, ['users' => $management->users()]);
            }

            if ($method === 'POST') {
                $payload = (new JsonRequestParser())->parse($rawBody);
                $result = $management->createUser(
                    is_string($payload['username'] ?? null) ? $payload['username'] : '',
                    is_string($payload['password'] ?? null) ? $payload['password'] : ''
                );
                $audit->record('user_created', 'user', (string) $result['user']['id'], $actor->fingerprint, $networkAddress);

                return new HttpResponse(201, $result);
            }

            return $this->methodNotAllowed('GET, POST');
        }

        if (preg_match('~^/api/admin/users/(\d+)$~', $path, $matches) === 1) {
            if ($method !== 'PATCH') {
                return $this->methodNotAllowed('PATCH');
            }

            $payload = (new JsonRequestParser())->parse($rawBody);

            if (!is_bool($payload['active'] ?? null)) {
                throw new ProductHttpException('O estado do usuário é inválido.', 422);
            }

            $management->setUserActive((int) $matches[1], $payload['active']);
            $audit->record('user_status_changed', 'user', $matches[1], $actor->fingerprint, $networkAddress, ['active' => $payload['active']]);

            return new HttpResponse(200, ['status' => 'ok']);
        }

        if (preg_match('~^/api/admin/users/(\d+)/reset-password$~', $path, $matches) === 1) {
            if ($method !== 'POST') {
                return $this->methodNotAllowed('POST');
            }

            $payload = (new JsonRequestParser())->parse($rawBody);
            $result = $management->resetPassword(
                (int) $matches[1],
                is_string($payload['password'] ?? null) ? $payload['password'] : ''
            );
            $audit->record('password_reset_by_admin', 'user', $matches[1], $actor->fingerprint, $networkAddress);

            return new HttpResponse(200, $result);
        }

        if (preg_match('~^/api/admin/users/(\d+)/permissions$~', $path, $matches) === 1) {
            if ($method !== 'PUT') {
                return $this->methodNotAllowed('PUT');
            }

            $payload = (new JsonRequestParser())->parse($rawBody);
            $management->setPermissions(
                (int) $matches[1],
                is_array($payload['project_ids'] ?? null) ? $payload['project_ids'] : [],
                is_array($payload['document_ids'] ?? null) ? $payload['document_ids'] : []
            );
            $audit->record('user_permissions_changed', 'user', $matches[1], $actor->fingerprint, $networkAddress, [
                'project_count' => count($payload['project_ids'] ?? []),
                'document_count' => count($payload['document_ids'] ?? []),
            ]);

            return new HttpResponse(200, ['status' => 'ok']);
        }

        if ($path === '/api/admin/projects') {
            if ($method === 'GET') {
                return new HttpResponse(200, ['projects' => $management->projects()]);
            }

            if ($method === 'POST') {
                $payload = (new JsonRequestParser())->parse($rawBody);
                $project = $management->saveProject(
                    null,
                    is_string($payload['name'] ?? null) ? $payload['name'] : '',
                    is_array($payload['document_ids'] ?? null) ? $payload['document_ids'] : []
                );
                $audit->record('project_created', 'project', (string) $project['id'], $actor->fingerprint, $networkAddress);

                return new HttpResponse(201, ['project' => $project]);
            }

            return $this->methodNotAllowed('GET, POST');
        }

        if (preg_match('~^/api/admin/projects/(\d+)$~', $path, $matches) === 1) {
            if ($method === 'DELETE') {
                $result = (new ContentDeletionService(
                    $this->database,
                    new DocumentStorage($this->container['ingestion']['document_storage'])
                ))->deleteProject((int) $matches[1]);
                $audit->record('project_deleted', 'project', $matches[1], $actor->fingerprint, $networkAddress, $result);

                if ($result['storage_cleanup_failures'] > 0) {
                    $this->logger->warning('document_storage_cleanup_incomplete', [
                        'entity_type' => 'project',
                        'entity_id' => $matches[1],
                        'failure_count' => $result['storage_cleanup_failures'],
                    ]);
                }

                return new HttpResponse(200, ['deletion' => $result]);
            }

            if ($method !== 'PUT') {
                return $this->methodNotAllowed('PUT, DELETE');
            }

            $payload = (new JsonRequestParser())->parse($rawBody);
            $project = $management->saveProject(
                (int) $matches[1],
                is_string($payload['name'] ?? null) ? $payload['name'] : '',
                is_array($payload['document_ids'] ?? null) ? $payload['document_ids'] : []
            );
            $audit->record('project_updated', 'project', $matches[1], $actor->fingerprint, $networkAddress);

            return new HttpResponse(200, ['project' => $project]);
        }

        if ($path === '/api/documents' && $method === 'GET') {
            return new HttpResponse(200, ['documents' => $read->documents()]);
        }

        if ($path === '/api/documents' && $method === 'POST') {
            $storage = new DocumentStorage($this->container['ingestion']['document_storage']);
            $handler = new DocumentUploadHandler(
                new DocumentUploadValidator($this->container['ingestion']['max_document_bytes']),
                new DocumentIngestionService(
                    $this->database,
                    $storage,
                    $this->container['ingestion']['max_document_bytes']
                ),
                $this->logger
            );
            $response = $handler->handle($files, $post);

            if ($response['status'] === 201) {
                $document = $response['payload']['document'];
                $audit->record(
                    'document_uploaded',
                    'document',
                    (string) $document['document_public_id'],
                    $actor->fingerprint,
                    $networkAddress,
                    ['format' => $document['format'], 'node_count' => $document['node_count']]
                );
            }

            return new HttpResponse($response['status'], $response['payload']);
        }

        if ($path === '/api/metrics') {
            return $method === 'GET'
                ? new HttpResponse(200, ['metrics' => $read->metrics()])
                : $this->methodNotAllowed('GET');
        }

        if ($path === '/api/jobs') {
            return $method === 'GET'
                ? new HttpResponse(200, ['jobs' => $read->jobs()])
                : $this->methodNotAllowed('GET');
        }

        if ($path === '/api/audit') {
            return $method === 'GET'
                ? new HttpResponse(200, ['events' => $read->auditEvents()])
                : $this->methodNotAllowed('GET');
        }

        if (preg_match('~^/api/documents/(\d+)$~', $path, $matches) === 1) {
            if ($method !== 'DELETE') {
                return $this->methodNotAllowed('DELETE');
            }

            $result = (new ContentDeletionService(
                $this->database,
                new DocumentStorage($this->container['ingestion']['document_storage'])
            ))->deleteDocument((int) $matches[1]);
            $audit->record('document_deleted', 'document', (string) $result['public_id'], $actor->fingerprint, $networkAddress, $result);

            if ($result['storage_cleanup_failures'] > 0) {
                $this->logger->warning('document_storage_cleanup_incomplete', [
                    'entity_type' => 'document',
                    'entity_id' => $result['public_id'],
                    'failure_count' => $result['storage_cleanup_failures'],
                ]);
            }

            return new HttpResponse(200, ['deletion' => $result]);
        }

        if (preg_match('~^/api/documents/(\d+)/process$~', $path, $matches) === 1) {
            if ($method !== 'POST') {
                return $this->methodNotAllowed('POST');
            }

            $queue = new ProcessingQueueService(
                $this->database,
                (int) $this->container['queue']['max_failures']
            );
            $jobs = (new CognitiveJobPlanner($queue, $this->container['ai']))
                ->enqueueDocument((int) $matches[1]);
            $audit->record('document_processing_enqueued', 'document', $matches[1], $actor->fingerprint, $networkAddress, [
                'job_ids' => array_map(static fn ($job): string => $job->publicId, $jobs),
            ]);

            return new HttpResponse(202, [
                'jobs' => array_map(static fn ($job): array => $job->toArray(), $jobs),
            ]);
        }

        if (preg_match('~^/api/jobs/(EVA-J\d{6,})/retry$~', $path, $matches) === 1) {
            if ($method !== 'POST') {
                return $this->methodNotAllowed('POST');
            }

            $queue = new ProcessingQueueService(
                $this->database,
                (int) $this->container['queue']['max_failures']
            );
            $job = $queue->retry($matches[1]);
            $audit->record('processing_job_retried', 'processing_job', $job->publicId, $actor->fingerprint, $networkAddress, [
                'document_id' => $job->documentId,
                'stage' => $job->stage,
                'failure_count' => $job->failureCount,
            ]);

            return new HttpResponse(202, ['job' => $job->toArray()]);
        }

        if ($path === '/api/documents') {
            return $this->methodNotAllowed('GET, POST');
        }

        return new HttpResponse(404, ['error' => 'Rota não encontrada.']);
    }

    private function methodNotAllowed(string $allowed): HttpResponse
    {
        return new HttpResponse(405, ['error' => 'Método não permitido.'], ['Allow' => $allowed]);
    }
}
