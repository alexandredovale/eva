<?php

declare(strict_types=1);

use Eva\Http\Product\ProductApi;
use Eva\Http\Product\HttpResponse;
use Eva\Infrastructure\Database\Connection;
use Eva\Infrastructure\Logging\FileLogger;
use Eva\Support\Env;

$container = require __DIR__ . '/bootstrap.php';
$arguments = array_slice($argv, 1);
$liveConfirmed = in_array('--live', $arguments, true);
$relationalOnly = in_array('--relational-only', $arguments, true);
$canonicalRelational = in_array('--canonical-relational', $arguments, true);
$reportArgument = array_values(array_filter(
    $arguments,
    static fn (string $argument): bool => str_starts_with($argument, '--report=')
));
$reportPath = $reportArgument === []
    ? null
    : trim(substr($reportArgument[0], strlen('--report=')));

if (!$liveConfirmed || ($container['ai']['live_enabled'] ?? false) !== true) {
    fwrite(STDERR, "Este teste executa chamadas reais. Exige AI_LIVE_ENABLED=true e --live.\n");
    exit(2);
}

$adminToken = (string) Env::get('ADMIN_API_TOKEN', '');

if (strlen($adminToken) < 24) {
    fwrite(STDERR, "ADMIN_API_TOKEN não está configurado para o teste.\n");
    exit(2);
}

$database = Connection::create($container['database']);
$api = new ProductApi($database, $container, new FileLogger($container['logging']['path']));
$runId = 'golive_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
$username = $runId;
$password = 'GoLive-' . bin2hex(random_bytes(8)) . '!';
$userId = null;
$userToken = null;
$assertions = 0;
$failures = [];
$results = [];
$startedAt = microtime(true);
$baseline = readinessCounts($database);

/** @return array<string, mixed> */
function readinessServer(string $token, string $address): array
{
    return ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'REMOTE_ADDR' => $address];
}

function readinessAssert(bool $condition, string $message): void
{
    global $assertions, $failures;
    $assertions++;

    if (!$condition) {
        $failures[] = $message;
    }
}

/** @return array{users: int, user_projects: int, user_documents: int} */
function readinessCounts(PDO $database): array
{
    return [
        'users' => (int) $database->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'user_projects' => (int) $database->query('SELECT COUNT(*) FROM user_projects')->fetchColumn(),
        'user_documents' => (int) $database->query('SELECT COUNT(*) FROM user_documents')->fetchColumn(),
    ];
}

/** @param array<string, mixed> $payload */
function readinessRequest(
    ProductApi $api,
    string $method,
    string $path,
    string $token,
    array $payload = [],
    string $address = '198.51.100.80'
): HttpResponse {
    return $api->handle(
        $method,
        $path,
        readinessServer($token, $address),
        [],
        [],
        $payload === [] ? '' : json_encode($payload, JSON_THROW_ON_ERROR)
    );
}

/** @param list<int> $projectIds @param list<int> $documentIds */
function readinessSetPermissions(
    ProductApi $api,
    string $adminToken,
    int $userId,
    array $projectIds,
    array $documentIds
): void {
    $response = readinessRequest(
        $api,
        'PUT',
        "/api/admin/users/{$userId}/permissions",
        $adminToken,
        ['project_ids' => $projectIds, 'document_ids' => $documentIds]
    );
    readinessAssert($response->status === 200, 'Não foi possível alterar a matriz de permissões do usuário temporário.');
}

/**
 * @param list<array{type: string, id: int}> $scopes
 * @param list<string> $allowedDocuments
 * @param array{kind: string, input: string} $question
 */
function readinessRunQuery(
    ProductApi $api,
    string $token,
    string $role,
    string $scenario,
    int $questionNumber,
    array $scopes,
    array $allowedDocuments,
    array $question
): array {
    global $failures, $results;
    $failureCountBefore = count($failures);
    $start = microtime(true);
    $response = readinessRequest(
        $api,
        'POST',
        '/api/query',
        $token,
        ['scopes' => $scopes, 'input' => $question['input']],
        $role === 'superadmin' ? '198.51.100.81' : '198.51.100.82'
    );
    $elapsedMs = (int) round((microtime(true) - $start) * 1000);
    $record = [
        'scenario' => $scenario,
        'role' => $role,
        'question_number' => $questionNumber,
        'question_kind' => $question['kind'],
        'question' => $question['input'],
        'http_status' => $response->status,
        'elapsed_ms' => $elapsedMs,
        'passed' => false,
    ];

    if ($response->status !== 200) {
        readinessAssert(false, "{$scenario}/{$role}/Q{$questionNumber} retornou HTTP {$response->status}.");
        $record['error'] = (string) ($response->payload['error'] ?? 'Resposta sem erro descritivo.');
        $results[] = $record;
        printf("FAIL %-32s %-10s Q%d HTTP %d %dms\n", $scenario, $role, $questionNumber, $response->status, $elapsedMs);

        return $record;
    }

    $query = is_array($response->payload['query'] ?? null) ? $response->payload['query'] : [];
    $answer = is_string($query['answer'] ?? null) ? trim($query['answer']) : '';
    $evidences = is_array($query['evidences_used'] ?? null) ? $query['evidences_used'] : [];
    $simetry = is_array($query['simetry_interactions'] ?? null) ? $query['simetry_interactions'] : [];
    $assimetry = is_array($query['assimetry_interactions'] ?? null) ? $query['assimetry_interactions'] : [];
    $limitations = is_array($query['limitations'] ?? null) ? $query['limitations'] : [];
    $inputTypes = is_array($query['input']['types'] ?? null) ? $query['input']['types'] : [];
    $evidenceIds = [];
    $usedDocuments = [];

    readinessAssert($answer !== '', "{$scenario}/{$role}/Q{$questionNumber} retornou resposta vazia.");
    readinessAssert($evidences !== [], "{$scenario}/{$role}/Q{$questionNumber} não utilizou evidência documental.");
    readinessAssert(count($evidences) <= 10, "{$scenario}/{$role}/Q{$questionNumber} excedeu QUERY_MAX_EVIDENCE.");
    readinessAssert(
        count($simetry) + count($assimetry) <= 20,
        "{$scenario}/{$role}/Q{$questionNumber} excedeu QUERY_MAX_INTERACTIONS."
    );

    foreach ($evidences as $evidence) {
        $evidenceId = is_array($evidence) && is_string($evidence['id'] ?? null) ? $evidence['id'] : '';
        $document = is_array($evidence) && is_string($evidence['document'] ?? null) ? $evidence['document'] : '';
        readinessAssert(
            preg_match('/^EVA-E\d{6,}$/', $evidenceId) === 1,
            "{$scenario}/{$role}/Q{$questionNumber} retornou identificador de evidência inválido."
        );
        readinessAssert(
            in_array($document, $allowedDocuments, true),
            "{$scenario}/{$role}/Q{$questionNumber} vazou evidência de obra fora do escopo: {$document}."
        );
        readinessAssert(
            $evidenceId !== '' && str_contains($answer, '[' . $evidenceId . ']'),
            "{$scenario}/{$role}/Q{$questionNumber} não exibiu a citação {$evidenceId}."
        );
        $evidenceIds[$evidenceId] = true;
        $usedDocuments[$document] = true;
    }

    foreach (array_merge($simetry, $assimetry) as $interaction) {
        $associations = is_array($interaction) && is_array($interaction['evidences'] ?? null)
            ? $interaction['evidences']
            : [];
        readinessAssert(
            count($associations) === 2,
            "{$scenario}/{$role}/Q{$questionNumber} retornou interação sem dois participantes."
        );

        foreach ($associations as $association) {
            $evidenceId = is_array($association) && is_string($association['evidence_id'] ?? null)
                ? $association['evidence_id']
                : '';
            $excerpt = is_array($association) && is_string($association['excerpt'] ?? null)
                ? trim($association['excerpt'])
                : '';
            readinessAssert(
                isset($evidenceIds[$evidenceId]),
                "{$scenario}/{$role}/Q{$questionNumber} retornou interação com evidência não utilizada."
            );
            readinessAssert(
                $excerpt !== '',
                "{$scenario}/{$role}/Q{$questionNumber} retornou interação sem fragmento literal."
            );
        }
    }

    if ($question['kind'] === 'relational') {
        readinessAssert(
            in_array('relational', $inputTypes, true),
            "{$scenario}/{$role}/Q{$questionNumber} não detectou o input relacional."
        );
        readinessAssert(
            count($simetry) + count($assimetry) > 0 || $limitations !== [],
            "{$scenario}/{$role}/Q{$questionNumber} não retornou interação nem limitação relacional."
        );
    }

    $record = array_merge($record, [
        'passed' => count($failures) === $failureCountBefore,
        'input_types' => $inputTypes,
        'evidence_count' => count($evidences),
        'used_documents' => array_keys($usedDocuments),
        'simetry_count' => count($simetry),
        'assimetry_count' => count($assimetry),
        'limitation_count' => count($limitations),
    ]);
    $results[] = $record;
    printf(
        "%s %-32s %-10s Q%d ev=%d int=%d lim=%d %dms\n",
        $record['passed'] ? 'PASS' : 'FAIL',
        $scenario,
        $role,
        $questionNumber,
        count($evidences),
        count($simetry) + count($assimetry),
        count($limitations),
        $elapsedMs
    );

    return $record;
}

/**
 * @param list<array{type: string, id: int}> $adminScopes
 * @param list<array{type: string, id: int}> $userScopes
 * @param list<string> $allowedDocuments
 * @param list<array{kind: string, input: string}> $questions
 */
function readinessRunPairedScenario(
    ProductApi $api,
    string $adminToken,
    string $userToken,
    string $scenario,
    array $adminScopes,
    array $userScopes,
    array $allowedDocuments,
    array $questions,
    bool $requireAllDocuments
): void {
    $coverage = ['superadmin' => [], 'user' => []];

    foreach ($questions as $index => $question) {
        foreach ([
            ['role' => 'superadmin', 'token' => $adminToken, 'scopes' => $adminScopes],
            ['role' => 'user', 'token' => $userToken, 'scopes' => $userScopes],
        ] as $actor) {
            $record = readinessRunQuery(
                $api,
                $actor['token'],
                $actor['role'],
                $scenario,
                $index + 1,
                $actor['scopes'],
                $allowedDocuments,
                $question
            );

            foreach ($record['used_documents'] ?? [] as $document) {
                $coverage[$actor['role']][$document] = true;
            }
        }
    }

    if ($requireAllDocuments) {
        foreach ($coverage as $role => $documents) {
            foreach ($allowedDocuments as $document) {
                readinessAssert(
                    isset($documents[$document]),
                    "{$scenario}/{$role} não cobriu a obra {$document} no conjunto das três perguntas."
                );
            }
        }
    }
}

/** @param list<array{kind: string, input: string}> $questions @param list<array{type: string, id: int}> $scopes */
function readinessRunDeniedQueries(
    ProductApi $api,
    string $userToken,
    string $scenario,
    array $scopes,
    array $questions
): void {
    global $results;

    foreach ($questions as $index => $question) {
        $start = microtime(true);
        $response = readinessRequest(
            $api,
            'POST',
            '/api/query',
            $userToken,
            ['scopes' => $scopes, 'input' => $question['input']],
            '198.51.100.83'
        );
        $elapsedMs = (int) round((microtime(true) - $start) * 1000);
        readinessAssert(
            $response->status === 403,
            "{$scenario}/Q" . ($index + 1) . " deveria retornar 403, mas retornou {$response->status}."
        );
        $results[] = [
            'scenario' => $scenario,
            'role' => 'user',
            'question_number' => $index + 1,
            'question_kind' => $question['kind'],
            'question' => $question['input'],
            'http_status' => $response->status,
            'elapsed_ms' => $elapsedMs,
            'passed' => $response->status === 403,
            'expected_denial' => true,
        ];
        printf(
            "%s %-32s user       Q%d HTTP %d %dms\n",
            $response->status === 403 ? 'PASS' : 'FAIL',
            $scenario,
            $index + 1,
            $response->status,
            $elapsedMs
        );
    }
}

try {
    $projectStatement = $database->query(
        "SELECT p.id, p.name
           FROM projects p
           JOIN project_documents pd ON pd.project_id = p.id
           JOIN documents d ON d.id = pd.document_id AND d.status = 'ready'
          WHERE p.active = 1
          GROUP BY p.id, p.name
         HAVING COUNT(DISTINCT d.id) >= 2
          ORDER BY p.id
          LIMIT 1"
    );
    $project = $projectStatement->fetch();

    if (!is_array($project)) {
        throw new RuntimeException('O teste exige um projeto ativo com pelo menos duas obras prontas.');
    }

    $projectId = (int) $project['id'];
    $documentsStatement = $database->prepare(
        "SELECT d.id, d.public_id, d.title
           FROM project_documents pd
           JOIN documents d ON d.id = pd.document_id AND d.status = 'ready'
          WHERE pd.project_id = :project_id
          ORDER BY d.id
          LIMIT 2"
    );
    $documentsStatement->execute(['project_id' => $projectId]);
    $documents = $documentsStatement->fetchAll();

    if (count($documents) !== 2) {
        throw new RuntimeException('Não foi possível resolver as duas obras do projeto de teste.');
    }

    $documentOne = ['id' => (int) $documents[0]['id'], 'title' => (string) $documents[0]['title']];
    $documentTwo = ['id' => (int) $documents[1]['id'], 'title' => (string) $documents[1]['title']];
    $allTitles = [$documentOne['title'], $documentTwo['title']];
    $projectQuestions = [
        [
            'kind' => 'broad',
            'input' => "Apresente uma visão geral conjunta de {$documentOne['title']} e {$documentTwo['title']}, citando as fontes.",
        ],
        [
            'kind' => 'conceptual',
            'input' => 'O que as obras selecionadas ensinam sobre a natureza dos Espíritos e a comunicação mediúnica?',
        ],
        [
            'kind' => 'relational',
            'input' => $canonicalRelational
                ? 'Qual é a relação entre livre-arbítrio, responsabilidade moral e prática mediúnica nas obras selecionadas?'
                : 'Como o livre-arbítrio e a responsabilidade moral se relacionam com a prática mediúnica nas obras selecionadas?',
        ],
    ];
    $documentOneQuestions = [
        ['kind' => 'broad', 'input' => "Apresente uma visão geral dos temas centrais de {$documentOne['title']}."],
        ['kind' => 'conceptual', 'input' => "O que {$documentOne['title']} ensina sobre mediunidade e formação dos médiuns?"],
        [
            'kind' => 'relational',
            'input' => $canonicalRelational
                ? "Qual é a relação entre disciplina, educação do médium e prevenção da mistificação em {$documentOne['title']}?"
                : "Como disciplina e educação do médium se relacionam com a prevenção da mistificação em {$documentOne['title']}?",
        ],
    ];
    $documentTwoQuestions = [
        ['kind' => 'broad', 'input' => "Apresente uma visão geral dos temas centrais de {$documentTwo['title']}."],
        ['kind' => 'conceptual', 'input' => "O que {$documentTwo['title']} ensina sobre natureza e progressão dos Espíritos?"],
        [
            'kind' => 'relational',
            'input' => $canonicalRelational
                ? "Qual é a relação entre livre-arbítrio e responsabilidade moral em {$documentTwo['title']}?"
                : "Como livre-arbítrio e responsabilidade moral se relacionam em {$documentTwo['title']}?",
        ],
    ];

    if ($relationalOnly) {
        $projectQuestions = array_values(array_filter(
            $projectQuestions,
            static fn (array $question): bool => $question['kind'] === 'relational'
        ));
        $documentOneQuestions = array_values(array_filter(
            $documentOneQuestions,
            static fn (array $question): bool => $question['kind'] === 'relational'
        ));
        $documentTwoQuestions = array_values(array_filter(
            $documentTwoQuestions,
            static fn (array $question): bool => $question['kind'] === 'relational'
        ));
    }

    $adminMe = readinessRequest($api, 'GET', '/api/me', $adminToken);
    readinessAssert(
        $adminMe->status === 200 && ($adminMe->payload['user']['role'] ?? null) === 'superadmin',
        'O token administrativo não autenticou o superadmin.'
    );
    $adminScopes = readinessRequest($api, 'GET', '/api/scopes', $adminToken);
    $adminProject = null;

    foreach ($adminScopes->payload['scopes']['projects'] ?? [] as $scopeProject) {
        if ((int) ($scopeProject['id'] ?? 0) === $projectId) {
            $adminProject = $scopeProject;
            break;
        }
    }

    readinessAssert($adminScopes->status === 200, 'O superadmin não conseguiu listar os escopos.');
    readinessAssert(
        is_array($adminProject) && ($adminProject['full_access'] ?? false) === true
            && count($adminProject['documents'] ?? []) >= 2,
        'O superadmin não recebeu acesso completo ao projeto e suas obras.'
    );

    $created = readinessRequest(
        $api,
        'POST',
        '/api/admin/users',
        $adminToken,
        ['username' => $username, 'password' => $password]
    );
    readinessAssert($created->status === 201, 'O superadmin não conseguiu criar o usuário temporário.');

    if ($created->status !== 201) {
        throw new RuntimeException('Sem usuário temporário não é possível prosseguir com a matriz.');
    }

    $userId = (int) $created->payload['user']['id'];
    $login = $api->handle(
        'POST',
        '/api/auth/login',
        ['REMOTE_ADDR' => '198.51.100.82'],
        [],
        [],
        json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR)
    );
    readinessAssert($login->status === 200, 'O usuário temporário não conseguiu autenticar.');

    if ($login->status !== 200) {
        throw new RuntimeException('Sem sessão do usuário não é possível prosseguir com a matriz.');
    }

    $userToken = (string) $login->payload['token'];
    $userMe = readinessRequest($api, 'GET', '/api/me', $userToken);
    readinessAssert(
        $userMe->status === 200 && ($userMe->payload['user']['role'] ?? null) === 'user',
        'A sessão cadastrada não foi reconhecida como usuário comum.'
    );

    echo "\nMATRIZ 1 — permissão de projeto completo\n";
    readinessSetPermissions($api, $adminToken, $userId, [$projectId], []);
    $projectPermissionScopes = readinessRequest($api, 'GET', '/api/scopes', $userToken);
    $userProject = $projectPermissionScopes->payload['scopes']['projects'][0] ?? null;
    readinessAssert(
        $projectPermissionScopes->status === 200 && is_array($userProject)
            && ($userProject['full_access'] ?? false) === true
            && count($userProject['documents'] ?? []) === 2,
        'A permissão de projeto não expôs o projeto completo e suas duas obras.'
    );

    readinessRunPairedScenario(
        $api,
        $adminToken,
        $userToken,
        'projeto_completo',
        [['type' => 'project', 'id' => $projectId]],
        [['type' => 'project', 'id' => $projectId]],
        $allTitles,
        $projectQuestions,
        true
    );

    echo "\nMATRIZ 2 — obra filha com permissão herdada do projeto\n";
    readinessRunPairedScenario(
        $api,
        $adminToken,
        $userToken,
        'obra_via_projeto',
        [['type' => 'document', 'id' => $documentOne['id']]],
        [['type' => 'document', 'id' => $documentOne['id']]],
        [$documentOne['title']],
        $documentOneQuestions,
        false
    );

    echo "\nMATRIZ 3 — permissão individual de uma única obra\n";
    readinessSetPermissions($api, $adminToken, $userId, [], [$documentTwo['id']]);
    $individualScopes = readinessRequest($api, 'GET', '/api/scopes', $userToken);
    $partialProject = $individualScopes->payload['scopes']['projects'][0] ?? null;
    readinessAssert(
        $individualScopes->status === 200 && is_array($partialProject)
            && ($partialProject['full_access'] ?? true) === false
            && count($partialProject['documents'] ?? []) === 1
            && (int) ($partialProject['documents'][0]['id'] ?? 0) === $documentTwo['id'],
        'A permissão individual não restringiu a árvore à única obra concedida.'
    );
    readinessRunPairedScenario(
        $api,
        $adminToken,
        $userToken,
        'obra_individual',
        [['type' => 'document', 'id' => $documentTwo['id']]],
        [['type' => 'document', 'id' => $documentTwo['id']]],
        [$documentTwo['title']],
        $documentTwoQuestions,
        false
    );
    readinessRunDeniedQueries(
        $api,
        $userToken,
        'nega_projeto_sem_permissao',
        [['type' => 'project', 'id' => $projectId]],
        $projectQuestions
    );
    readinessRunDeniedQueries(
        $api,
        $userToken,
        'nega_outra_obra',
        [['type' => 'document', 'id' => $documentOne['id']]],
        $documentOneQuestions
    );

    echo "\nMATRIZ 4 — duas obras individuais combinadas, sem permissão de projeto\n";
    readinessSetPermissions($api, $adminToken, $userId, [], [$documentOne['id'], $documentTwo['id']]);
    $combinedScopes = readinessRequest($api, 'GET', '/api/scopes', $userToken);
    $combinedProject = $combinedScopes->payload['scopes']['projects'][0] ?? null;
    readinessAssert(
        $combinedScopes->status === 200 && is_array($combinedProject)
            && ($combinedProject['full_access'] ?? true) === false
            && count($combinedProject['documents'] ?? []) === 2,
        'As duas permissões individuais não apareceram como projeto parcial com duas obras.'
    );
    readinessRunPairedScenario(
        $api,
        $adminToken,
        $userToken,
        'duas_obras_individuais',
        [
            ['type' => 'document', 'id' => $documentOne['id']],
            ['type' => 'document', 'id' => $documentTwo['id']],
        ],
        [
            ['type' => 'document', 'id' => $documentOne['id']],
            ['type' => 'document', 'id' => $documentTwo['id']],
        ],
        $allTitles,
        $projectQuestions,
        true
    );
    readinessRunDeniedQueries(
        $api,
        $userToken,
        'nega_projeto_com_obras_individuais',
        [['type' => 'project', 'id' => $projectId]],
        $projectQuestions
    );

    echo "\nSESSÃO E ESTADO DO USUÁRIO\n";
    $logout = readinessRequest($api, 'POST', '/api/logout', $userToken);
    readinessAssert($logout->status === 200, 'O logout do usuário falhou.');
    $oldSession = readinessRequest($api, 'GET', '/api/me', $userToken);
    readinessAssert($oldSession->status === 401, 'A sessão permaneceu válida após o logout.');
    $relogin = $api->handle(
        'POST',
        '/api/auth/login',
        ['REMOTE_ADDR' => '198.51.100.82'],
        [],
        [],
        json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR)
    );
    readinessAssert($relogin->status === 200, 'O usuário não conseguiu iniciar uma nova sessão após logout.');
    $userToken = (string) ($relogin->payload['token'] ?? '');
    $scopesAfterRelogin = readinessRequest($api, 'GET', '/api/scopes', $userToken);
    readinessAssert(
        $scopesAfterRelogin->status === 200
            && ($scopesAfterRelogin->payload['scopes']['projects'][0]['full_access'] ?? true) === false
            && count($scopesAfterRelogin->payload['scopes']['projects'][0]['documents'] ?? []) === 2,
        'A nova sessão não preservou corretamente as permissões individuais vigentes.'
    );

    $deactivated = readinessRequest(
        $api,
        'PATCH',
        "/api/admin/users/{$userId}",
        $adminToken,
        ['active' => false]
    );
    readinessAssert($deactivated->status === 200, 'O superadmin não conseguiu desativar o usuário temporário.');
    $revokedSession = readinessRequest($api, 'GET', '/api/me', $userToken);
    readinessAssert($revokedSession->status === 401, 'A desativação não revogou a sessão vigente.');
    $blockedLogin = $api->handle(
        'POST',
        '/api/auth/login',
        ['REMOTE_ADDR' => '198.51.100.82'],
        [],
        [],
        json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR)
    );
    readinessAssert($blockedLogin->status === 401, 'O usuário desativado ainda conseguiu autenticar.');
    $reactivated = readinessRequest(
        $api,
        'PATCH',
        "/api/admin/users/{$userId}",
        $adminToken,
        ['active' => true]
    );
    readinessAssert($reactivated->status === 200, 'O superadmin não conseguiu reativar o usuário temporário.');
    $activeLogin = $api->handle(
        'POST',
        '/api/auth/login',
        ['REMOTE_ADDR' => '198.51.100.82'],
        [],
        [],
        json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR)
    );
    readinessAssert($activeLogin->status === 200, 'O usuário reativado não conseguiu autenticar.');
} catch (Throwable $exception) {
    $failures[] = 'Falha estrutural do teste: ' . $exception->getMessage();
} finally {
    if ($userId !== null) {
        $delete = $database->prepare('DELETE FROM users WHERE id = :id AND username = :username');
        $delete->execute(['id' => $userId, 'username' => $username]);
    }

    $afterCleanup = readinessCounts($database);
    readinessAssert($afterCleanup === $baseline, 'A limpeza não restaurou as contagens originais de usuários e permissões.');
    $leftover = $database->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
    $leftover->execute(['username' => $username]);
    readinessAssert((int) $leftover->fetchColumn() === 0, 'O usuário temporário permaneceu no banco.');
}

$passedResults = count(array_filter($results, static fn (array $record): bool => ($record['passed'] ?? false) === true));
$positiveQueries = count(array_filter($results, static fn (array $record): bool => !($record['expected_denial'] ?? false)));
$negativeQueries = count($results) - $positiveQueries;
$report = [
    'run_id' => $runId,
    'started_at' => date(DATE_ATOM, (int) $startedAt),
    'duration_seconds' => round(microtime(true) - $startedAt, 3),
    'configuration' => [
        'max_evidence' => (int) $container['ai']['query']['max_evidence'],
        'max_interactions' => (int) $container['ai']['query']['max_interactions'],
    ],
    'summary' => [
        'assertions' => $assertions,
        'positive_queries' => $positiveQueries,
        'negative_queries' => $negativeQueries,
        'passed_results' => $passedResults,
        'failed_results' => count($results) - $passedResults,
        'failures' => count($failures),
    ],
    'failures' => array_values(array_unique($failures)),
    'results' => $results,
];

if ($reportPath !== null && $reportPath !== '') {
    $encoded = json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

    if (file_put_contents($reportPath, $encoded . PHP_EOL) === false) {
        $failures[] = 'Não foi possível gravar o relatório JSON solicitado.';
    }
}

echo "\nRESUMO\n";
printf(
    "consultas_positivas=%d consultas_negativas=%d resultados_aprovados=%d assercoes=%d falhas=%d duracao=%.3fs\n",
    $positiveQueries,
    $negativeQueries,
    $passedResults,
    $assertions,
    count($failures),
    microtime(true) - $startedAt
);

if ($failures !== []) {
    foreach (array_values(array_unique($failures)) as $failure) {
        fwrite(STDERR, 'FALHA: ' . $failure . PHP_EOL);
    }

    exit(1);
}

echo "Validação de prontidão aprovada.\n";
