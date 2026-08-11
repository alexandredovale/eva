<?php

declare(strict_types=1);

use Eva\ModuleRuntime\CoreReadApi;
use Eva\Http\Security\ActorContext;
use Eva\ModuleRuntime\LanguageModelInterface;
use Eva\ModuleRuntime\ModuleContext;
use Eva\ModuleRuntime\ModuleManifest;
use Eva\ModuleRuntime\ModuleStorageFactory;
use EvaModule\Explorer\ExplorerModule;
use EvaModule\Explorer\Storage\ExplorerRepository;

require __DIR__ . '/bootstrap.php';

$moduleDirectory = dirname(__DIR__) . '/modules/com.eva.explorer';
$module = require $moduleDirectory . '/bootstrap.php';
$manifest = ModuleManifest::fromDirectory($moduleDirectory);
$temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'eva-explorer-module-' . bin2hex(random_bytes(6));
$storage = (new ModuleStorageFactory($temporaryRoot))->open($manifest);
$core = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$core->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT NOT NULL, active INTEGER NOT NULL, created_at TEXT NOT NULL)');
$core->exec('CREATE TABLE projects (id INTEGER PRIMARY KEY, name TEXT NOT NULL, active INTEGER NOT NULL, created_at TEXT NOT NULL)');
$core->exec('CREATE TABLE documents (
    id INTEGER PRIMARY KEY, public_id TEXT NOT NULL, title TEXT NOT NULL, format TEXT NOT NULL,
    source_hash TEXT NOT NULL, status TEXT NOT NULL, created_at TEXT NOT NULL
)');
$core->exec('CREATE TABLE project_documents (project_id INTEGER NOT NULL, document_id INTEGER NOT NULL)');
$core->exec('CREATE TABLE user_projects (user_id INTEGER NOT NULL, project_id INTEGER NOT NULL)');
$core->exec('CREATE TABLE user_documents (user_id INTEGER NOT NULL, document_id INTEGER NOT NULL)');
$core->exec("INSERT INTO users VALUES
    (1, 'professora-ana', 1, '2026-08-07 00:00:00'),
    (2, 'aluno-caio', 1, '2026-08-07 00:00:00'),
    (3, 'secretaria-lia', 1, '2026-08-07 00:00:00')");
$core->exec("INSERT INTO projects VALUES
    (10, 'Ciências Humanas', 1, '2026-08-07 00:00:00'),
    (11, 'Projeto restrito', 1, '2026-08-07 00:00:00')");
$core->exec("INSERT INTO documents VALUES
    (20, 'EVA-D000020', 'Aprendizagem e linguagem', 'md', 'learning-hash', 'ready', '2026-08-07 00:00:00'),
    (21, 'EVA-D000021', 'Documento sem acesso', 'md', 'restricted-hash', 'ready', '2026-08-07 00:00:00')");
$core->exec('INSERT INTO project_documents VALUES (10, 20), (11, 21)');
$core->exec('INSERT INTO user_projects VALUES (1, 10)');
$language = new class implements LanguageModelInterface {
    public function generateJson(string $systemInstruction, array $input): array
    {
        return [];
    }
};
$professorActor = new ActorContext('explorer-test', 'user', 1, 'professora-ana');
$context = new ModuleContext(
    $manifest,
    $storage,
    new CoreReadApi($core, $manifest->capabilities, $professorActor),
    $language
);
$assertions = 0;

function assertExplorer(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function removeExplorerTemporaryDirectory(string $path): void
{
    $resolved = realpath($path);
    $temporary = rtrim(realpath(sys_get_temp_dir()) ?: sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if ($resolved === false || !str_starts_with($resolved . DIRECTORY_SEPARATOR, $temporary)
        || !str_starts_with(basename($resolved), 'eva-explorer-module-')) {
        throw new RuntimeException('A limpeza do teste EXPLORER recusou um caminho inesperado.');
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
    assertExplorer($module instanceof ExplorerModule, 'O entrypoint não retornou o módulo EXPLORER.');
    assertExplorer($manifest->subscribedEvents === [], 'O EXPLORER passou a observar eventos do Core.');
    assertExplorer(
        $manifest->capabilities === [
            'ai.language.generate',
            'core.read.users',
            'core.read.projects',
            'core.read.documents',
            'core.query.scoped',
        ],
        'As capacidades do EXPLORER divergiram do contrato esperado.'
    );
    $learningService = new EvaModule\Explorer\Learning\LearningInteractionService();
    $validatePrompt = new ReflectionMethod($learningService, 'validatePrompt');
    $validatePrompt->setAccessible(true);
    $learnerFacingAnalysis = new ReflectionMethod($learningService, 'learnerFacingAnalysis');
    $learnerFacingAnalysis->setAccessible(true);
    $preservesCitations = new ReflectionMethod($learningService, 'preservesDocumentaryCitations');
    $preservesCitations->setAccessible(true);
    $fixtureEvidences = [[
        'id' => 'EVA-E000001',
        'document' => 'Aprendizagem e linguagem',
        'node' => 'Organização da fala',
        'source_reference' => 'linhas 10-18',
        'content' => 'A organização coerente das ideias favorece a compreensão da fala pelo público.',
    ]];
    $nodesFallback = $validatePrompt->invoke(
        $learningService,
        [],
        'nodes',
        $fixtureEvidences,
        'Relacione planejamento do discurso e conexão com o público.'
    );
    assertExplorer(
        ($nodesFallback['prompt'] ?? null) === 'Relacione planejamento do discurso e conexão com o público.',
        'Nodes ainda depende de uma estrutura JSON rígida depois da consulta documental.'
    );
    $directNodes = $validatePrompt->invoke(
        $learningService,
        [],
        'nodes',
        $fixtureEvidences,
        'Proposta para o aluno: "Construa, com suas próprias palavras, uma conexão conceitual entre planejamento do discurso e uso da voz." Fundamentação documental interna: o roteiro orienta a fala e a voz favorece o entendimento [EVA-E000001].'
    );
    assertExplorer(
        ($directNodes['prompt'] ?? null) === 'Construa, com suas próprias palavras, uma conexão conceitual entre planejamento do discurso e uso da voz.'
            && ($directNodes['answer_contract']['contract'] ?? null) === 'eva.explorer.nodes-correction-support/1'
            && str_contains((string) ($directNodes['answer_contract']['documentary_support'] ?? ''), '[EVA-E000001]')
            && ($directNodes['answer_contract']['evidence_ids'] ?? []) === ['EVA-E000001'],
        'Nodes não separou o comando direto de seu objeto auxiliar documental.'
    );
    $conciseQuiz = $validatePrompt->invoke(
        $learningService,
        [],
        'quiz',
        $fixtureEvidences,
        'Com base no documento, elaborei uma atividade. A pergunta é: "Como uma voz clara favorece a compreensão do público?" Essa questão foi construída com base na evidência EVA-E000001 e espera uma reflexão do aluno.'
    );
    assertExplorer(
        ($conciseQuiz['prompt'] ?? null) === 'Como uma voz clara favorece a compreensão do público?',
        'O Quizz ainda expõe ao aluno justificativas e metacomentários da geração.'
    );
    $coreCompatibleQuiz = $validatePrompt->invoke(
        $learningService,
        [],
        'quiz',
        $fixtureEvidences,
        'Pergunta para o aluno: "Como uma voz clara favorece a compreensão do público?" Fundamentação documental interna: a clareza vocal facilita o entendimento da mensagem [EVA-E000001].'
    );
    assertExplorer(
        ($coreCompatibleQuiz['prompt'] ?? null) === 'Como uma voz clara favorece a compreensão do público?'
            && ($coreCompatibleQuiz['answer_contract']['contract'] ?? null) === 'eva.explorer.quiz-correction-support/1'
            && ($coreCompatibleQuiz['answer_contract']['student_prompt'] ?? null) === 'Como uma voz clara favorece a compreensão do público?'
            && str_contains((string) ($coreCompatibleQuiz['answer_contract']['documentary_support'] ?? ''), 'Fundamentação documental interna')
            && str_contains((string) ($coreCompatibleQuiz['answer_contract']['documentary_support'] ?? ''), '[EVA-E000001]')
            && ($coreCompatibleQuiz['answer_contract']['evidence_ids'] ?? []) === ['EVA-E000001'],
        'O Quizz não preservou a fundamentação documental em seu objeto auxiliar de correção.'
    );
    $directFeedback = $learnerFacingAnalysis->invoke(
        $learningService,
        'A resposta do estudante está adequada. O treinamento mencionado pelo estudante favorece a confiança. Além disso, o aluno citou o planejamento.'
    );
    assertExplorer(
        $directFeedback === 'Sua resposta está adequada. O treinamento mencionado por você favorece a confiança. Além disso, você citou o planejamento.'
            && !preg_match('/\b(?:estudante|aluno|aluna)\b/iu', $directFeedback),
        'A devolutiva ainda trata quem respondeu como objeto de relatório.'
    );
    assertExplorer(
        !$preservesCitations->invoke(
            $learningService,
            'A conexão conceitual foi construída adequadamente.',
            'A conexão conceitual foi construída adequadamente [EVA-E000001].'
        )
            && $preservesCitations->invoke(
                $learningService,
                'A conexão foi construída [EVA-E000001] e pode ser ampliada [EVA-E000002].',
                'A conexão foi construída [EVA-E000001] e pode ser ampliada [EVA-E000002].'
            ),
        'A normalização ainda pode remover citações documentais do corpo da análise.'
    );
    $learningSource = file_get_contents($moduleDirectory . '/src/Learning/LearningInteractionService.php');
    assertExplorer(
        is_string($learningSource)
            && str_contains($learningSource, 'proponha ao aluno que construa')
            && str_contains($learningSource, 'não explique a relação')
            && str_contains($learningSource, 'não ofereça uma resposta-modelo'),
        'Nodes deixou de separar a proposta da conexão que deve ser construída pelo aluno.'
    );
    $flexibleExam = $validatePrompt->invoke(
        $learningService,
        [
            'question' => 'Qual elemento favorece a compreensão da fala?',
            'student_facing' => [
                'context_paragraphs' => [
                    'A preparação organiza os elementos centrais de uma apresentação oral.',
                    'Um roteiro pode orientar a sequência das ideias durante a fala.',
                    'Este terceiro parágrafo não deve ser exibido ao aluno.',
                ],
                'command' => 'Assinale a alternativa que representa melhor essa preparação.',
            ],
            'alternatives' => [
                ['letter' => 'A', 'text' => 'Improviso sem propósito'],
                ['letter' => 'B', 'text' => 'Organização coerente das ideias'],
                ['letter' => 'C', 'text' => 'Vocabulário inadequado ao público'],
                ['letter' => 'D', 'text' => 'Ausência de conexão entre argumentos'],
            ],
            'gabarito' => 'Alternativa B',
            'option_justifications' => [
                'A' => ['justification' => 'O improviso sem propósito reduz a clareza.', 'evidence_ids' => ['EVA-E000001']],
                'B' => ['justification' => 'A organização coerente favorece a compreensão.', 'evidence_ids' => ['EVA-E000001']],
                'C' => ['justification' => 'A inadequação ao público prejudica a compreensão.', 'evidence_ids' => ['EVA-E000001']],
                'D' => ['justification' => 'A falta de conexão rompe a progressão das ideias.', 'evidence_ids' => ['EVA-E000001']],
            ],
        ],
        'prova',
        $fixtureEvidences,
        ''
    );
    assertExplorer(
        ($flexibleExam['correct_option'] ?? null) === 'B'
            && ($flexibleExam['options']['B'] ?? null) === 'Organização coerente das ideias'
            && ($flexibleExam['prompt'] ?? null) === "A preparação organiza os elementos centrais de uma apresentação oral.\n\nUm roteiro pode orientar a sequência das ideias durante a fala.\n\nAssinale a alternativa que representa melhor essa preparação."
            && !str_contains((string) ($flexibleExam['prompt'] ?? ''), 'terceiro parágrafo')
            && ($flexibleExam['answer_contract']['contract'] ?? null) === 'eva.explorer.prova-answer/1'
            && ($flexibleExam['answer_contract']['options']['B']['role'] ?? null) === 'correct'
            && ($flexibleExam['answer_contract']['options']['A']['role'] ?? null) === 'distractor'
            && ($flexibleExam['answer_contract']['options']['D']['evidence_ids'] ?? []) === ['EVA-E000001'],
        'A Prova não preservou seu contrato interno flexível, fundamentado e completo.'
    );
    $provaCorrectionBasis = new ReflectionMethod($learningService, 'provaCorrectionBasis');
    $provaCorrectionBasis->setAccessible(true);
    $provaPromptFixture = [
        'prompt_text' => $flexibleExam['prompt'],
        'options' => $flexibleExam['options'],
        'correct_option' => $flexibleExam['correct_option'],
        'answer_contract' => $flexibleExam['answer_contract'],
        'evidences' => $fixtureEvidences,
    ];
    $wrongProva = $provaCorrectionBasis->invoke($learningService, $provaPromptFixture, 'A');
    $correctProva = $provaCorrectionBasis->invoke($learningService, $provaPromptFixture, 'B');
    assertExplorer(
        ($wrongProva['outcome'] ?? null) === 'deepen'
            && str_contains((string) ($wrongProva['analysis'] ?? ''), 'A alternativa correta é B')
            && str_contains((string) ($wrongProva['analysis'] ?? ''), '[EVA-E000001]')
            && ($correctProva['outcome'] ?? null) === 'correct'
            && str_starts_with((string) ($correctProva['analysis'] ?? ''), 'Sua escolha está correta.'),
        'A correção objetiva da Prova ainda depende de uma nova consulta documental.'
    );
    $coreTableCount = (int) $core->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table'")->fetchColumn();
    $module->install($context);
    $promptColumns = array_column($storage->query('PRAGMA table_info(interaction_prompts)')->fetchAll(), 'name');
    assertExplorer(
        in_array('answer_contract_json', $promptColumns, true),
        'A migração do EXPLORER não instalou o contrato interno da Prova.'
    );
    assertExplorer(
        array_column((new ExplorerRepository($storage))->profiles(), 'name') === ['Professor', 'Secretaria', 'Aluno'],
        'Os três perfis fixos não foram instalados.'
    );
    assertExplorer(
        (int) $core->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table'")->fetchColumn() === $coreTableCount,
        'A instalação do EXPLORER alterou o schema do Core.'
    );
    assertExplorer(
        (int) $core->query("SELECT COUNT(*) FROM sqlite_master WHERE lower(name) LIKE '%explorer%'")->fetchColumn() === 0,
        'O EXPLORER criou estrutura de domínio no banco do Core.'
    );

    $admin = ['user_id' => null, 'role' => 'superadmin'];
    $professor = ['user_id' => 1, 'role' => 'user'];
    $student = ['user_id' => 2, 'role' => 'user'];
    $secretaria = ['user_id' => 3, 'role' => 'user'];
    $adminDashboard = $module->dashboard($context, $admin, []);
    assertExplorer(
        str_contains($adminDashboard['html'], 'Configuração modular')
            && str_contains($adminDashboard['html'], '<h3>Professor</h3>')
            && str_contains($adminDashboard['html'], '<h3>Secretaria</h3>')
            && str_contains($adminDashboard['html'], '<h3>Aluno</h3>'),
        'O superadmin não recebeu os três perfis do EXPLORER.'
    );
    $repository = new ExplorerRepository($storage);
    $profilesByName = [];

    foreach ($repository->profiles() as $profile) {
        $profilesByName[$profile['name']] = (int) $profile['id'];
    }

    $repository->assignProfile(1, $profilesByName['Professor']);
    $repository->assignProfile(2, $profilesByName['Aluno']);
    $repository->assignProfile(3, $profilesByName['Secretaria']);
    assertExplorer($module->canAccess($context, $professor), 'O Professor vinculado não recebeu acesso.');
    assertExplorer($module->canAccess($context, $student), 'O Aluno vinculado não recebeu acesso.');
    assertExplorer($module->canAccess($context, $secretaria), 'A Secretaria vinculada não recebeu acesso.');

    $professorDashboard = $module->dashboard($context, $professor, []);
    assertExplorer(
        str_contains($professorDashboard['html'], 'data-module-action-form="create_theme"')
            && str_contains($professorDashboard['html'], 'Ciências Humanas · Aprendizagem e linguagem')
            && !str_contains($professorDashboard['html'], 'Documento sem acesso')
            && !str_contains($professorDashboard['html'], 'Usuários e perfis'),
        'O Professor não recebeu seu ambiente exclusivo de publicação.'
    );
    $unauthorizedSelectionRejected = false;

    try {
        $module->action(
            $context,
            $professor,
            'create_theme',
            [
                'scope_key' => '11:EVA-D000021',
                'title' => 'Tema indevido',
                'description' => 'Este tema não pode usar um documento sem acesso no Core.',
            ],
            'explorer-create-theme-denied'
        );
    } catch (Eva\ModuleRuntime\ModuleException) {
        $unauthorizedSelectionRejected = true;
    }

    assertExplorer(
        $unauthorizedSelectionRejected,
        'O Professor conseguiu forjar a publicação de um TA com documento sem acesso no Core.'
    );
    $module->action(
        $context,
        $professor,
        'create_theme',
        [
            'scope_key' => '10:EVA-D000020',
            'title' => 'Linguagem e construção de sentido',
            'description' => 'Compreender como a linguagem participa da construção social de sentidos.',
        ],
        'explorer-create-theme-001'
    );
    $theme = $repository->activeThemes()[0] ?? null;
    assertExplorer(
        is_array($theme)
            && $theme['professor_name'] === 'professora-ana'
            && $theme['document_public_id'] === 'EVA-D000020'
            && $theme['document_source_hash'] === 'learning-hash',
        'O TA não preservou autoria e referência documental portátil.'
    );

    $studentDashboard = $module->dashboard($context, $student, []);
    assertExplorer(
        str_contains($studentDashboard['html'], '<strong>professora-ana</strong>')
            && str_contains($studentDashboard['html'], 'Linguagem e construção de sentido')
            && substr_count($studentDashboard['html'], 'data-module-action-form="prepare_interaction"') === 3
            && str_contains($studentDashboard['html'], 'Gerando Quizz')
            && str_contains($studentDashboard['html'], 'Gerando Nodes')
            && str_contains($studentDashboard['html'], 'Gerando Prova'),
        'O Aluno não recebeu o card com as três relações cognitivas.'
    );

    $dialogDashboard = (new EvaModule\Explorer\Dashboard\ExplorerDashboardPresenter())->student(
        ['name' => 'Aluno'],
        [$theme],
        [],
        $theme,
        [
            'interaction_type' => 'quiz',
            'prompt_text' => 'Qual conceito central está presente no tema?',
            'options' => [],
        ]
    );
    assertExplorer(
        substr_count($dialogDashboard['html'], 'data-module-refresh') === 2
            && !str_contains($dialogDashboard['html'], 'method="dialog"')
            && str_contains($dialogDashboard['html'], 'aria-modal="true"')
            && str_contains($dialogDashboard['html'], 'aria-label="Fechar"')
            && !str_contains($dialogDashboard['html'], 'autofocus'),
        'Os controles do popup não utilizam o fechamento declarativo do host modular.'
    );
    $analysisParagraphs = new ReflectionMethod(
        EvaModule\Explorer\Dashboard\ExplorerDashboardPresenter::class,
        'analysisParagraphs'
    );
    $analysisParagraphs->setAccessible(true);
    assertExplorer(
        $analysisParagraphs->invoke(
            new EvaModule\Explorer\Dashboard\ExplorerDashboardPresenter(),
            "Primeiro ponto.\n\nSegundo ponto."
        ) === '<p>Primeiro ponto.</p><p>Segundo ponto.</p>',
        'A análise qualitativa não foi separada em parágrafos semânticos.'
    );

    $promptId = $repository->savePrompt(
        (int) $theme['id'],
        2,
        'quiz',
        'Como a linguagem participa da construção de sentido?',
        [],
        null,
        [],
        [[
            'id' => 'EVA-E000001',
            'document' => 'Aprendizagem e linguagem',
            'source_reference' => 'linhas 10-18',
            'content' => 'A linguagem organiza significados compartilhados.',
        ]]
    );
    $storedPrompt = $repository->prompt((int) $theme['id'], 2, 'quiz');
    assertExplorer(
        ($storedPrompt['evidences'][0]['content'] ?? null) === 'A linguagem organiza significados compartilhados.'
            && ($storedPrompt['evidences'][0]['source_reference'] ?? null) === 'linhas 10-18',
        'O EXPLORER não preservou integralmente o trecho e a referência da evidência usada na geração.'
    );
    $repository->beginAction('explorer-submit-quiz-001', 'submit_interaction', 2, hash('sha256', 'fixture'));
    $repository->saveInteraction(
        'explorer-submit-quiz-001',
        $repository->prompt((int) $theme['id'], 2, 'quiz') ?? ['id' => $promptId],
        2,
        'A linguagem organiza significados compartilhados.',
        'correct',
        'A resposta relaciona adequadamente linguagem e produção social de sentido.',
        [['id' => 'EVA-E000001', 'document' => 'Aprendizagem e linguagem']]
    );
    $studentProgress = $module->dashboard($context, $student, []);
    assertExplorer(
        str_contains($studentProgress['html'], 'explorer-step is-complete')
            && str_contains($studentProgress['html'], 'Resposta correta')
            && str_contains($studentProgress['html'], 'A resposta relaciona adequadamente')
            && str_contains($studentProgress['html'], '<details class="explorer-result"><summary><span>Quizz</span>')
            && !str_contains($studentProgress['html'], '<details class="explorer-result" open>')
            && str_contains($studentProgress['html'], 'Evidências utilizadas')
            && str_contains($studentProgress['html'], 'EVA-E000001')
            && str_contains($studentProgress['html'], 'Aprendizagem e linguagem'),
        'O círculo concluído e a análise qualitativa não apareceram para o Aluno.'
    );

    $professorProgress = $module->dashboard($context, $professor, []);
    assertExplorer(
        str_contains($professorProgress['html'], 'aluno-caio')
            && str_contains($professorProgress['html'], '1 de 3 etapas')
            && str_contains($professorProgress['html'], 'Resposta correta'),
        'O Professor não recebeu o progresso individual do aluno.'
    );

    $secretariaDashboard = $module->dashboard($context, $secretaria, []);
    assertExplorer(
        str_contains($secretariaDashboard['html'], 'Acompanhamento institucional')
            && str_contains($secretariaDashboard['html'], 'Filtros operacionais')
            && str_contains($secretariaDashboard['html'], 'Etapas concluídas')
            && str_contains($secretariaDashboard['html'], 'Evolução por card')
            && str_contains($secretariaDashboard['html'], 'somente leitura')
            && !str_contains($secretariaDashboard['html'], 'data-module-action-form="create_theme"'),
        'A Secretaria não recebeu o dashboard estatístico exclusivamente consultivo.'
    );

    $module->action(
        $context,
        $professor,
        'set_theme_active',
        ['theme_id' => (string) $theme['id'], 'active' => '0'],
        'explorer-pause-completed-theme-001'
    );
    $module->action(
        $context,
        $professor,
        'create_theme',
        [
            'scope_key' => '10:EVA-D000020',
            'title' => 'Novo percurso disponível',
            'description' => 'Tema ativo ainda sem etapas concluídas pelo aluno.',
        ],
        'explorer-create-theme-002'
    );
    $activeOnlyProgress = $module->dashboard($context, $student, []);
    assertExplorer(
        str_contains($activeOnlyProgress['html'], '<strong>0 de 3</strong>')
            && str_contains($activeOnlyProgress['html'], '0% do percurso disponível')
            && str_contains($activeOnlyProgress['html'], 'Novo percurso disponível')
            && !str_contains($activeOnlyProgress['html'], 'Linguagem e construção de sentido')
            && !str_contains($activeOnlyProgress['html'], 'A resposta relaciona adequadamente'),
        'O percurso do Aluno ainda contou interações pertencentes a um tema pausado.'
    );
    assertExplorer(
        str_contains($adminDashboard['css'], '.explorer-dashboard .explorer-step.is-complete > span')
            && str_contains($adminDashboard['css'], 'background: var(--accent);')
            && str_contains($adminDashboard['css'], '@keyframes explorer-step-spin')
            && str_contains($adminDashboard['css'], '.explorer-evidence-footer')
            && str_contains($adminDashboard['css'], '.explorer-result[open] > summary')
            && str_contains($adminDashboard['css'], '.explorer-student-analyses details[open] > summary::after')
            && str_contains($adminDashboard['css'], 'align-items: stretch;')
            && str_contains($adminDashboard['css'], 'place-items: start center;')
            && str_contains($adminDashboard['css'], 'body:has(.explorer-dashboard .explorer-dialog)')
            && str_contains($adminDashboard['css'], '.view:has(.explorer-dashboard .explorer-dialog)')
            && str_contains($adminDashboard['css'], '@media (max-width: 680px)'),
        'Os estados visuais e a adaptação responsiva não foram preservados.'
    );

    echo "Módulo EXPLORER validado com {$assertions} asserções.\n";
} finally {
    unset($repository, $context, $storage);
    gc_collect_cycles();
    removeExplorerTemporaryDirectory($temporaryRoot);
}
