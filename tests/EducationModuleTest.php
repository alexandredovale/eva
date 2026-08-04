<?php

declare(strict_types=1);

use Eva\ModuleRuntime\CoreReadApi;
use Eva\ModuleRuntime\LanguageModelInterface;
use Eva\ModuleRuntime\ModuleContext;
use Eva\ModuleRuntime\ModuleEvent;
use Eva\ModuleRuntime\ModuleException;
use Eva\ModuleRuntime\ModuleManifest;
use Eva\ModuleRuntime\ModuleStorageFactory;
use EvaModule\Education\EducationModule;
use EvaModule\Education\Governance\GovernancePolicy;
use EvaModule\Education\Interpreter\LearningInterpreter;

$container = require __DIR__ . '/bootstrap.php';
$moduleDirectory = dirname(__DIR__) . '/modules/com.eva.education';
$module = require $moduleDirectory . '/bootstrap.php';
$manifest = ModuleManifest::fromDirectory($moduleDirectory);
$temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'eva-module-runtime-' . bin2hex(random_bytes(6));
$storage = (new ModuleStorageFactory($temporaryRoot))->open($manifest);
$core = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$core->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT NOT NULL, active INTEGER NOT NULL, created_at TEXT NOT NULL)');
$core->exec("INSERT INTO users (id, username, active, created_at) VALUES (1, 'usuario-1', 1, '2026-08-03 00:00:00'), (2, 'usuario-2', 1, '2026-08-03 00:00:00')");
$language = new class implements LanguageModelInterface {
    public function generateJson(string $systemInstruction, array $input): array
    {
        $evidenceId = $input['evidences'][0]['id'] ?? '';

        return [
            'language' => 'pt-BR',
            'labels' => [
                'pending' => 'pendente',
                'completed' => 'concluído',
                'failed' => 'falhou',
                'evidences' => 'Evidências',
                'scope' => 'Escopo',
                'document' => 'Documento',
                'projects' => 'Projetos',
                'documents' => 'Documentos',
                'direct_references' => 'Referências diretas',
                'concepts' => 'Conceitos',
                'none' => 'nenhum',
                'no_direct_references' => 'nenhuma',
                'interpretation_pending' => 'Interpretação ainda não disponível.',
            ],
            'observations' => [[
                'dimension' => 'evidence_use',
                'dimension_label' => 'Uso de evidências',
                'state' => 'observed',
                'state_label' => 'observado',
                'description' => 'A resposta mobiliza uma evidência identificada na interação.',
                'evidence_refs' => [$evidenceId],
            ]],
            'linguistic_analysis' => [
                'units' => [[
                    'id' => 'u1',
                    'surface' => 'Pergunta atual',
                    'canonical' => 'pergunta atual',
                    'role' => 'subject',
                    'source' => 'question',
                    'evidence_refs' => [],
                ], [
                    'id' => 'u2',
                    'surface' => 'Resposta',
                    'canonical' => 'resposta',
                    'role' => 'subject',
                    'source' => 'answer',
                    'evidence_refs' => [$evidenceId],
                ]],
                'relations' => [],
                'concepts' => [[
                    'term' => 'pergunta atual',
                    'canonical' => 'pergunta atual',
                    'unit_ids' => ['u1'],
                    'evidence_refs' => [],
                ], [
                    'term' => 'resposta',
                    'canonical' => 'resposta',
                    'unit_ids' => ['u2'],
                    'evidence_refs' => [$evidenceId],
                ]],
            ],
            'limitations' => ['A observação descreve somente esta interação.'],
        ];
    }
};
$context = new ModuleContext($manifest, $storage, new CoreReadApi($core, $manifest->capabilities), $language);
$assertions = 0;

function assertEducation(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function removeEducationTemporaryDirectory(string $path): void
{
    $resolved = realpath($path);
    $temporary = rtrim(realpath(sys_get_temp_dir()) ?: sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if ($resolved === false || !str_starts_with($resolved . DIRECTORY_SEPARATOR, $temporary)
        || !str_starts_with(basename($resolved), 'eva-module-runtime-')) {
        throw new RuntimeException('A limpeza educacional recusou um caminho inesperado.');
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

try {
    assertEducation($module instanceof EducationModule, 'O entrypoint educacional não retornou o módulo esperado.');
    $module->install($context);
    $activeGovernance = (new GovernancePolicy())->validate([]);
    assertEducation(
        $activeGovernance['dimensions'] === [
            'conceptual_articulation',
            'evidence_use',
            'contextual_connection',
        ],
        'A governança ainda contém uma dimensão pedagógica redundante.'
    );

    foreach ([1, 2] as $userId) {
        $event = new ModuleEvent(
            'EVA-EVT-' . str_repeat((string) $userId, 24),
            'interaction.completed',
            1,
            sprintf('2026-08-03T12:0%d:00-03:00', $userId),
            ['user_id' => $userId, 'role' => 'user'],
            [
                'projects' => [['id' => 10, 'name' => 'Projeto contextual']],
                'documents' => [['id' => 20, 'public_id' => 'EVA-DOC-20', 'title' => 'Documento contextual']],
            ],
            [
                'current_input' => 'Pergunta atual ' . $userId,
                'contextual_input' => 'Contexto conversacional ' . $userId,
                'answer' => 'Resposta ' . $userId,
            ],
            [['id' => 'EVA-EVID-' . $userId, 'document' => 'Documento contextual']],
            ['Limitação documental']
        );
        $module->handle($event, $context);
    }

    assertEducation((int) $storage->query('SELECT COUNT(*) FROM interactions')->fetchColumn() === 2, 'O Learning Observer não persistiu as interações.');
    $observed = $storage->query('SELECT * FROM interactions WHERE user_id = 1')->fetch();
    assertEducation($observed['current_input'] === 'Pergunta atual 1', 'A pergunta atual não foi preservada separadamente.');
    assertEducation($observed['contextual_input'] === 'Contexto conversacional 1', 'O contexto conversacional não foi preservado separadamente.');
    assertEducation($observed['processing_status'] === 'completed', 'A interação não foi concluída no próprio processamento do evento.');

    $forbiddenConfigurationRejected = false;

    try {
        (new GovernancePolicy())->validate(['weights' => ['evidence_use' => 2]]);
    } catch (ModuleException) {
        $forbiddenConfigurationRejected = true;
    }

    assertEducation($forbiddenConfigurationRejected, 'A governança aceitou peso pedagógico.');
    $hiddenScoringRejected = false;

    try {
        (new GovernancePolicy())->validate(['evidence_policy' => 'Assign a score to every observation.']);
    } catch (ModuleException) {
        $hiddenScoringRejected = true;
    }

    assertEducation($hiddenScoringRejected, 'A governança aceitou scoring oculto em texto.');
    $result = (new LearningInterpreter())->processPending($context, 10);
    assertEducation($result === ['processed' => 0, 'failed' => 0], 'O fluxo imediato deixou interações pendentes.');
    assertEducation((int) $storage->query('SELECT COUNT(*) FROM interpretations')->fetchColumn() === 2, 'As interpretações não foram versionadas.');
    $legacyPayload = json_decode(
        (string) $storage->query('SELECT observations_json FROM interpretations ORDER BY id LIMIT 1')->fetchColumn(),
        true,
        64,
        JSON_THROW_ON_ERROR
    );
    $legacyPayload['items'][] = [
        'dimension' => 'question_refinement',
        'dimension_label' => 'Refinamento da Pergunta',
        'state' => 'observed',
        'state_label' => 'observado',
        'description' => 'Registro legado redundante.',
        'evidence_refs' => [],
    ];
    $legacyUpdate = $storage->prepare(
        'UPDATE interpretations SET observations_json = :observations_json WHERE id = (SELECT MIN(id) FROM interpretations)'
    );
    $legacyUpdate->execute([
        'observations_json' => json_encode(
            $legacyPayload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ),
    ]);
    $legacyUpdate->closeCursor();
    unset($legacyUpdate);
    $storage->exec("UPDATE module_settings SET value_json = '1' WHERE setting_key = 'schema_version'");
    $module->install($context);
    $migratedInterpretations = implode(
        ' ',
        $storage->query('SELECT observations_json FROM interpretations')->fetchAll(PDO::FETCH_COLUMN)
    );
    assertEducation(
        !str_contains($migratedInterpretations, 'question_refinement')
            && !str_contains($migratedInterpretations, 'Refinamento da Pergunta'),
        'A migração preservou observações redundantes no histórico educacional.'
    );
    $interpretationJson = (string) $storage->query('SELECT observations_json FROM interpretations LIMIT 1')->fetchColumn();
    assertEducation(!preg_match('/score|weight|peso|nota|rank|confidence|percent|mastery/i', $interpretationJson), 'A interpretação contém valor subjetivo proibido.');
    $interpretationPayload = json_decode($interpretationJson, true, 64, JSON_THROW_ON_ERROR);
    assertEducation(
        $interpretationPayload['language'] === 'pt-BR'
        && $interpretationPayload['items'][0]['dimension_label'] === 'Uso de evidências'
        && $interpretationPayload['items'][0]['state_label'] === 'observado',
        'A interpretação não preservou os rótulos no idioma da pergunta.'
    );
    assertEducation(
        !str_contains(implode(' ', $interpretationPayload['labels']), '_'),
        'A interpretação persistiu rótulos técnicos com underscore.'
    );
    assertEducation(
        $interpretationPayload['linguistic_analysis']['units'][0]['role'] === 'subject'
        && $interpretationPayload['linguistic_analysis']['concepts'][0]['unit_ids'] === ['u1'],
        'A extração conceitual não permaneceu vinculada à unidade linguística de origem.'
    );
    $ungroundedOutput = [
        'language' => $interpretationPayload['language'],
        'labels' => $interpretationPayload['labels'],
        'observations' => $interpretationPayload['items'],
        'linguistic_analysis' => $interpretationPayload['linguistic_analysis'],
        'limitations' => json_decode(
            (string) $storage->query('SELECT limitations_json FROM interpretations LIMIT 1')->fetchColumn(),
            true,
            64,
            JSON_THROW_ON_ERROR
        ),
    ];
    $ungroundedOutput['linguistic_analysis']['concepts'][0]['unit_ids'] = ['u999'];
    $validator = new ReflectionMethod(LearningInterpreter::class, 'validateOutput');
    $normalizedUngroundedOutput = $validator->invoke(
        new LearningInterpreter(),
        $ungroundedOutput,
        (new GovernancePolicy())->validate([]),
        json_decode($observed['evidences_json'], true, 64, JSON_THROW_ON_ERROR),
        $observed['current_input'],
        $observed['answer']
    );
    assertEducation(
        !in_array(['u999'], array_column($normalizedUngroundedOutput['linguistic_analysis']['concepts'], 'unit_ids'), true),
        'A normalização persistiu um conceito sem unidade linguística de origem.'
    );
    $questionCoverageOutput = [
        'language' => $interpretationPayload['language'],
        'labels' => $interpretationPayload['labels'],
        'observations' => $interpretationPayload['items'],
        'linguistic_analysis' => $interpretationPayload['linguistic_analysis'],
        'limitations' => $ungroundedOutput['limitations'],
    ];
    $questionCoverageOutput['linguistic_analysis']['concepts'] = [
        $questionCoverageOutput['linguistic_analysis']['concepts'][1],
    ];
    $completedCoverageOutput = $validator->invoke(
        new LearningInterpreter(),
        $questionCoverageOutput,
        (new GovernancePolicy())->validate([]),
        json_decode($observed['evidences_json'], true, 64, JSON_THROW_ON_ERROR),
        $observed['current_input'],
        $observed['answer']
    );
    $completedConceptUnitIds = array_merge(
        ...array_column($completedCoverageOutput['linguistic_analysis']['concepts'], 'unit_ids')
    );
    assertEducation(
        in_array('u1', $completedConceptUnitIds, true) && in_array('u2', $completedConceptUnitIds, true),
        'A normalização não completou a cobertura conceitual de pergunta e resposta.'
    );

    $ownDashboard = $module->dashboard($context, ['user_id' => 1, 'role' => 'user'], ['user_id' => 2]);
    assertEducation(
        $ownDashboard['contract'] === 'eva.module.dashboard/1'
        && str_contains($ownDashboard['html'], 'data-dashboard-user-id="1"')
        && substr_count($ownDashboard['html'], ' data-module-entry>') === 1,
        'O usuário comum acessou trajeto alheio ou o dashboard perdeu o contrato visual.'
    );
    assertEducation(
        !str_contains($ownDashboard['html'], ' · Observado')
        && str_contains($ownDashboard['html'], '</span><small>Evidências:')
        && str_contains($ownDashboard['css'], '.learning-observations small')
        && str_contains($ownDashboard['css'], 'display: block'),
        'O card observado repetiu o estado padrão ou manteve as evidências na linha da descrição.'
    );
    $adminDashboard = $module->dashboard($context, ['user_id' => null, 'role' => 'superadmin'], ['user_id' => 2]);
    assertEducation(
        str_contains($adminDashboard['html'], 'data-dashboard-user-id="2"')
        && substr_count($adminDashboard['html'], ' data-module-entry>') === 1,
        'O filtro administrativo de usuário falhou.'
    );
    $emptyAdminDashboard = $module->dashboard($context, ['user_id' => null, 'role' => 'superadmin'], []);
    assertEducation(
        str_contains($emptyAdminDashboard['html'], 'data-module-filter="user_id"')
        && str_contains($emptyAdminDashboard['html'], 'Selecione um usuário'),
        'O próprio módulo não forneceu o seletor administrativo.'
    );

    $storage->exec('PRAGMA wal_checkpoint(TRUNCATE)');
    $integrity = (string) $storage->query('PRAGMA integrity_check')->fetchColumn();
    assertEducation($integrity === 'ok', 'A integridade do SQLite educacional falhou.');
    $databasePath = $temporaryRoot . DIRECTORY_SEPARATOR . $manifest->id . DIRECTORY_SEPARATOR . 'module.sqlite';
    $backupPath = $temporaryRoot . DIRECTORY_SEPARATOR . 'education-backup.sqlite';
    assertEducation(copy($databasePath, $backupPath), 'O backup do SQLite educacional não pôde ser criado.');
    $restored = new PDO('sqlite:' . $backupPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    assertEducation((string) $restored->query('PRAGMA integrity_check')->fetchColumn() === 'ok', 'O SQLite educacional restaurado perdeu integridade.');
    assertEducation((int) $restored->query('SELECT COUNT(*) FROM interactions')->fetchColumn() === 2, 'O backup restaurado perdeu interações.');
    unset($restored);

    echo sprintf("Módulo Educacional validado com %d asserções.\n", $assertions);
} finally {
    $storage->exec('PRAGMA wal_checkpoint(TRUNCATE)');
    unset($context, $storage, $module, $core);
    gc_collect_cycles();
    usleep(100_000);
    removeEducationTemporaryDirectory($temporaryRoot);
}
