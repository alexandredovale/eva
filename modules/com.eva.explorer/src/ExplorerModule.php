<?php

declare(strict_types=1);

namespace EvaModule\Explorer;

use Eva\ModuleRuntime\DashboardModuleInterface;
use Eva\ModuleRuntime\ModuleAccessInterface;
use Eva\ModuleRuntime\ModuleActionInterface;
use Eva\ModuleRuntime\ModuleContext;
use Eva\ModuleRuntime\ModuleEvent;
use Eva\ModuleRuntime\ModuleException;
use Eva\ModuleRuntime\ModuleInterface;
use EvaModule\Explorer\Dashboard\ExplorerDashboardPresenter;
use EvaModule\Explorer\Learning\LearningInteractionService;
use EvaModule\Explorer\Storage\ExplorerRepository;
use EvaModule\Explorer\Storage\ExplorerSchema;
use Throwable;

final class ExplorerModule implements ModuleInterface, DashboardModuleInterface, ModuleAccessInterface, ModuleActionInterface
{
    private const TYPES = ['quiz', 'nodes', 'prova'];

    public function id(): string
    {
        return 'com.eva.explorer';
    }

    public function install(ModuleContext $context): void
    {
        (new ExplorerSchema())->install($context->storage);
    }

    public function handle(ModuleEvent $event, ModuleContext $context): void
    {
    }

    public function canAccess(ModuleContext $context, array $actor): bool
    {
        $userId = (int) ($actor['user_id'] ?? 0);

        return $userId > 0
            && (new ExplorerRepository($context->storage))->activeProfileForUser($userId) !== null;
    }

    public function dashboard(ModuleContext $context, array $actor, array $filters): array
    {
        $repository = new ExplorerRepository($context->storage);
        $presenter = new ExplorerDashboardPresenter();

        if (($actor['role'] ?? '') === 'superadmin') {
            return $presenter->admin(
                $repository->profiles(),
                $context->core->users(),
                $repository->assignments()
            );
        }

        $userId = $this->actorUserId($actor);
        $profile = $repository->activeProfileForUser($userId);

        if ($profile === null) {
            throw new ModuleException('O usuário não possui um perfil habilitado no módulo EXPLORER.');
        }

        return match ($profile['name']) {
            'Professor' => $this->professorDashboard($context, $repository, $presenter, $profile, $userId),
            'Aluno' => $this->studentDashboard($context, $repository, $presenter, $profile, $userId),
            'Secretaria' => $this->secretariaDashboard($context, $repository, $presenter, $profile, $filters),
            default => throw new ModuleException('O perfil informado não está disponível no módulo EXPLORER.'),
        };
    }

    public function action(
        ModuleContext $context,
        array $actor,
        string $action,
        array $input,
        string $requestId
    ): array {
        $repository = new ExplorerRepository($context->storage);

        if (($actor['role'] ?? '') === 'superadmin') {
            return $this->adminAction($context, $repository, $actor, $action, $input);
        }

        $userId = $this->actorUserId($actor);
        $profile = $repository->activeProfileForUser($userId);

        if ($profile === null) {
            throw new ModuleException('O usuário não possui um perfil habilitado no módulo EXPLORER.');
        }

        return match ($profile['name']) {
            'Professor' => $this->professorAction($context, $repository, $profile, $userId, $action, $input, $requestId),
            'Aluno' => $this->studentAction($context, $repository, $profile, $userId, $action, $input, $requestId),
            'Secretaria' => throw new ModuleException('O perfil Secretaria possui acesso exclusivamente consultivo ao EXPLORER.'),
            default => throw new ModuleException('O perfil informado não está disponível no módulo EXPLORER.'),
        };
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function adminAction(
        ModuleContext $context,
        ExplorerRepository $repository,
        array $actor,
        string $action,
        array $input
    ): array {
        if ($action !== 'assign_profile') {
            throw new ModuleException('A ação administrativa solicitada não existe no módulo EXPLORER.');
        }

        $this->assertOnlyKeys($input, ['user_id', 'profile_id']);
        $userId = $this->positiveInteger($input['user_id'] ?? null, 'usuário');

        if ($context->core->user($userId) === null) {
            throw new ModuleException('O usuário selecionado não existe no Core.');
        }

        $rawProfileId = is_string($input['profile_id'] ?? null) ? trim($input['profile_id']) : $input['profile_id'] ?? null;
        $repository->assignProfile(
            $userId,
            $rawProfileId === '' || $rawProfileId === null
                ? null
                : $this->positiveInteger($rawProfileId, 'perfil')
        );

        return [
            'contract' => 'eva.module.action/1',
            'dashboard' => $this->dashboard($context, $actor, []),
            'notice' => ['type' => 'success', 'message' => 'Perfil do EXPLORER atualizado.'],
        ];
    }

    /** @param array<string, mixed> $profile @param array<string, mixed> $input @return array<string, mixed> */
    private function professorAction(
        ModuleContext $context,
        ExplorerRepository $repository,
        array $profile,
        int $userId,
        string $action,
        array $input,
        string $requestId
    ): array {
        if ($action === 'set_theme_active') {
            $this->assertOnlyKeys($input, ['theme_id', 'active']);
            $active = ($input['active'] ?? null) === '1';
            $repository->setThemeActive(
                $this->positiveInteger($input['theme_id'] ?? null, 'Tema de Aprendizado'),
                $userId,
                $active
            );

            return $this->professorActionResult(
                $context,
                $repository,
                $profile,
                $userId,
                $active ? 'Tema de Aprendizado reativado.' : 'Tema de Aprendizado pausado.'
            );
        }

        if ($action !== 'create_theme') {
            throw new ModuleException('A ação solicitada não está disponível para o perfil Professor.');
        }

        $this->assertOnlyKeys($input, ['scope_key', 'title', 'description']);
        $scope = $this->scopeFromSelection($context, $input['scope_key'] ?? null);

        if ($scope === null) {
            throw new ModuleException('Selecione um projeto e um documento base válidos.');
        }

        $title = $this->boundedText($input['title'] ?? null, 'título', 180);
        $description = $this->boundedText($input['description'] ?? null, 'descrição', 4000);
        $inputHash = hash('sha256', $userId . "\n" . $scope['key'] . "\n" . $title . "\n" . $description);
        $existing = $repository->actionRequest($requestId);

        if ($existing !== null) {
            $this->assertSameRequest($existing, 'create_theme', $userId, $inputHash);

            return $this->professorActionResult(
                $context,
                $repository,
                $profile,
                $userId,
                'Esta publicação já havia sido processada.',
                'info'
            );
        }

        $repository->beginAction($requestId, 'create_theme', $userId, $inputHash);

        try {
            $user = $context->core->user($userId);
            $repository->createTheme(
                $requestId,
                $userId,
                (string) ($user['username'] ?? ('Professor #' . $userId)),
                $scope,
                $title,
                $description
            );
        } catch (Throwable $exception) {
            $repository->failRequest($requestId);
            throw $exception;
        }

        return $this->professorActionResult(
            $context,
            $repository,
            $profile,
            $userId,
            'Tema de Aprendizado publicado para os alunos.'
        );
    }

    /** @param array<string, mixed> $profile @param array<string, mixed> $input @return array<string, mixed> */
    private function studentAction(
        ModuleContext $context,
        ExplorerRepository $repository,
        array $profile,
        int $userId,
        string $action,
        array $input,
        string $requestId
    ): array {
        if (!in_array($action, ['prepare_interaction', 'submit_interaction'], true)) {
            throw new ModuleException('A ação solicitada não está disponível para o perfil Aluno.');
        }

        $allowed = $action === 'prepare_interaction'
            ? ['theme_id', 'interaction_type']
            : ['theme_id', 'interaction_type', 'response_text'];
        $this->assertOnlyKeys($input, $allowed);
        $themeId = $this->positiveInteger($input['theme_id'] ?? null, 'Tema de Aprendizado');
        $type = is_string($input['interaction_type'] ?? null) ? trim($input['interaction_type']) : '';

        if (!in_array($type, self::TYPES, true)) {
            throw new ModuleException('A relação cognitiva selecionada não existe no EXPLORER.');
        }

        $theme = $repository->theme($themeId, true);

        if ($theme === null) {
            throw new ModuleException('O Tema de Aprendizado não está mais disponível.');
        }

        $theme = $this->resolveStoredScope($context, $theme);
        $interaction = $repository->interaction($themeId, $userId, $type);

        if ($action === 'prepare_interaction') {
            if ($interaction !== null) {
                return $this->studentActionResult($context, $repository, $profile, $userId, $theme, $interaction, 'Resultado recuperado.', 'info');
            }

            $prompt = $repository->prompt($themeId, $userId, $type);

            if ($prompt === null) {
                $generated = (new LearningInteractionService())->generatePrompt($context, $theme, $type);
                $repository->savePrompt(
                    $themeId,
                    $userId,
                    $type,
                    (string) $generated['prompt'],
                    $generated['options'],
                    $generated['correct_option'],
                    $generated['answer_contract'] ?? [],
                    $generated['evidences']
                );
                $prompt = $repository->prompt($themeId, $userId, $type);
            }

            if ($prompt === null) {
                throw new ModuleException('Não foi possível preparar a etapa de aprendizado.');
            }

            return $this->studentActionResult($context, $repository, $profile, $userId, $theme, $prompt);
        }

        if ($interaction !== null) {
            return $this->studentActionResult($context, $repository, $profile, $userId, $theme, $interaction, 'Esta etapa já havia sido concluída.', 'info');
        }

        $prompt = $repository->prompt($themeId, $userId, $type);

        if ($prompt === null) {
            throw new ModuleException('Abra a etapa antes de enviar sua resposta.');
        }

        $response = $this->boundedText($input['response_text'] ?? null, 'resposta', $type === 'prova' ? 10 : 5000);

        if ($type === 'prova' && !array_key_exists(strtoupper($response), $prompt['options'])) {
            throw new ModuleException('Selecione uma das alternativas disponíveis.');
        }

        $response = $type === 'prova' ? strtoupper($response) : $response;
        $inputHash = hash('sha256', $userId . "\n" . $themeId . "\n" . $type . "\n" . $response);
        $existing = $repository->actionRequest($requestId);

        if ($existing !== null) {
            $this->assertSameRequest($existing, 'submit_interaction', $userId, $inputHash);
            $interaction = $repository->interaction($themeId, $userId, $type);

            return $this->studentActionResult($context, $repository, $profile, $userId, $theme, $interaction ?? $prompt, 'Esta resposta já havia sido processada.', 'info');
        }

        $repository->beginAction($requestId, 'submit_interaction', $userId, $inputHash);

        try {
            $analysis = (new LearningInteractionService())->analyze($context, $theme, $prompt, $response);
            $repository->saveInteraction(
                $requestId,
                $prompt,
                $userId,
                $response,
                $analysis['outcome'],
                $analysis['analysis'],
                $analysis['evidences']
            );
        } catch (Throwable $exception) {
            $repository->failRequest($requestId);
            throw $exception;
        }

        $interaction = $repository->interaction($themeId, $userId, $type);

        if ($interaction === null) {
            throw new ModuleException('A análise foi concluída, mas o resultado não pôde ser recuperado.');
        }

        return $this->studentActionResult(
            $context,
            $repository,
            $profile,
            $userId,
            $theme,
            $interaction,
            'Etapa concluída e análise qualitativa disponível.'
        );
    }

    /** @param array<string, mixed> $profile */
    private function professorDashboard(
        ModuleContext $context,
        ExplorerRepository $repository,
        ExplorerDashboardPresenter $presenter,
        array $profile,
        int $userId
    ): array {
        $themes = $repository->themesByProfessor($userId);

        return $presenter->professor(
            $profile,
            $this->actorScopes($context),
            $themes,
            $repository->interactionsForThemes(array_column($themes, 'id')),
            $context->core->users(),
            $repository->userIdsForProfile('Aluno')
        );
    }

    /** @param array<string, mixed> $profile @param array<string, mixed>|null $openItem */
    private function studentDashboard(
        ModuleContext $context,
        ExplorerRepository $repository,
        ExplorerDashboardPresenter $presenter,
        array $profile,
        int $userId,
        ?array $openTheme = null,
        ?array $openItem = null
    ): array {
        return $presenter->student(
            $profile,
            $repository->activeThemes(),
            $repository->interactionsForStudent($userId),
            $openTheme,
            $openItem
        );
    }

    /** @param array<string, mixed> $profile @param array<string, mixed> $filters */
    private function secretariaDashboard(
        ModuleContext $context,
        ExplorerRepository $repository,
        ExplorerDashboardPresenter $presenter,
        array $profile,
        array $filters
    ): array {
        $period = is_string($filters['period'] ?? null) ? $filters['period'] : '30';
        $period = in_array($period, ['7', '30', '90', 'all'], true) ? $period : '30';
        $professor = filter_var($filters['professor_id'] ?? null, FILTER_VALIDATE_INT);
        $professorId = $professor !== false && $professor > 0 ? (int) $professor : null;
        $professorIds = $repository->userIdsForProfile('Professor');

        if ($professorId !== null && !in_array($professorId, $professorIds, true)) {
            $professorId = null;
        }

        $themes = $repository->allThemes($professorId, $period === 'all' ? null : (int) $period);

        return $presenter->secretaria(
            $profile,
            $themes,
            $repository->interactionsForThemes(array_column($themes, 'id'), $period === 'all' ? null : (int) $period),
            $context->core->users(),
            $professorIds,
            $repository->userIdsForProfile('Aluno'),
            ['period' => $period, 'professor_id' => $professorId]
        );
    }

    /** @return list<array<string, mixed>> */
    private function availableScopes(ModuleContext $context): array
    {
        $scopes = [];

        foreach ($context->core->projects() as $project) {
            if (!(bool) ($project['active'] ?? false)) {
                continue;
            }

            foreach ($context->core->projectDocuments((int) $project['id']) as $document) {
                if (($document['status'] ?? null) !== 'ready' || !is_string($document['source_hash'] ?? null)) {
                    continue;
                }

                $scopes[] = [
                    'key' => (int) $project['id'] . ':' . (string) $document['public_id'],
                    'project_id' => (int) $project['id'],
                    'project_name' => (string) $project['name'],
                    'document_id' => (int) $document['id'],
                    'document_public_id' => (string) $document['public_id'],
                    'document_title' => (string) $document['title'],
                    'document_source_hash' => (string) $document['source_hash'],
                ];
            }
        }

        usort($scopes, static fn (array $left, array $right): int =>
            strcasecmp($left['project_name'] . $left['document_title'], $right['project_name'] . $right['document_title'])
        );

        return $scopes;
    }

    /** @return array<string, mixed>|null */
    private function scopeFromSelection(ModuleContext $context, mixed $selection): ?array
    {
        if (!is_string($selection) || trim($selection) === '' || mb_strlen($selection, 'UTF-8') > 200) {
            return null;
        }

        foreach ($this->actorScopes($context) as $scope) {
            if (hash_equals($scope['key'], trim($selection))) {
                return $scope;
            }
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    private function actorScopes(ModuleContext $context): array
    {
        $authorizedDocuments = [];
        $actorScopes = $context->core->actorScopes();

        foreach ($actorScopes['projects'] as $project) {
            foreach (($project['documents'] ?? []) as $document) {
                if (is_array($document) && isset($document['id'])) {
                    $authorizedDocuments[(int) $document['id']] = true;
                }
            }
        }

        foreach ($actorScopes['documents'] as $document) {
            if (is_array($document) && isset($document['id'])) {
                $authorizedDocuments[(int) $document['id']] = true;
            }
        }

        return array_values(array_filter(
            $this->availableScopes($context),
            static fn (array $scope): bool => isset($authorizedDocuments[(int) $scope['document_id']])
        ));
    }

    /** @param array<string, mixed> $theme @return array<string, mixed> */
    private function resolveStoredScope(ModuleContext $context, array $theme): array
    {
        foreach ($this->availableScopes($context) as $scope) {
            if ((int) $scope['project_id'] === (int) $theme['project_id']
                && $scope['document_public_id'] === $theme['document_public_id']
                && hash_equals($scope['document_source_hash'], (string) $theme['document_source_hash'])) {
                $theme['document_id'] = $scope['document_id'];
                return $theme;
            }
        }

        throw new ModuleException('O documento base deste Tema de Aprendizado não está pronto ou foi substituído.');
    }

    /** @param array<string, mixed> $profile @return array<string, mixed> */
    private function professorActionResult(
        ModuleContext $context,
        ExplorerRepository $repository,
        array $profile,
        int $userId,
        string $message,
        string $type = 'success'
    ): array {
        $presenter = new ExplorerDashboardPresenter();

        return [
            'contract' => 'eva.module.action/1',
            'dashboard' => $this->professorDashboard($context, $repository, $presenter, $profile, $userId),
            'notice' => ['type' => $type, 'message' => $message],
        ];
    }

    /** @param array<string, mixed> $profile @param array<string, mixed> $theme @param array<string, mixed> $item */
    private function studentActionResult(
        ModuleContext $context,
        ExplorerRepository $repository,
        array $profile,
        int $userId,
        array $theme,
        array $item,
        ?string $message = null,
        string $type = 'success'
    ): array {
        $result = [
            'contract' => 'eva.module.action/1',
            'dashboard' => $this->studentDashboard(
                $context,
                $repository,
                new ExplorerDashboardPresenter(),
                $profile,
                $userId,
                $theme,
                $item
            ),
        ];

        if ($message !== null) {
            $result['notice'] = ['type' => $type, 'message' => $message];
        }

        return $result;
    }

    /** @param array<string, mixed> $existing */
    private function assertSameRequest(array $existing, string $action, int $userId, string $hash): void
    {
        if (($existing['action_id'] ?? null) !== $action
            || (int) ($existing['actor_user_id'] ?? 0) !== $userId
            || !hash_equals((string) ($existing['input_hash'] ?? ''), $hash)) {
            throw new ModuleException('O identificador da requisição já foi usado com outro conteúdo.');
        }
    }

    /** @param array<string, mixed> $input @param list<string> $allowed */
    private function assertOnlyKeys(array $input, array $allowed): void
    {
        if (array_diff(array_keys($input), $allowed) !== []) {
            throw new ModuleException('A ação contém campos não reconhecidos pelo módulo EXPLORER.');
        }
    }

    private function actorUserId(array $actor): int
    {
        $userId = (int) ($actor['user_id'] ?? 0);

        if ($userId < 1) {
            throw new ModuleException('O usuário autenticado é inválido para o módulo EXPLORER.');
        }

        return $userId;
    }

    private function positiveInteger(mixed $value, string $label): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if ($integer === false || $integer < 1) {
            throw new ModuleException('O ' . $label . ' informado é inválido.');
        }

        return (int) $integer;
    }

    private function boundedText(mixed $value, string $label, int $maximum): string
    {
        if (!is_string($value)) {
            throw new ModuleException('O campo ' . $label . ' é inválido.');
        }

        $value = trim($value);

        if ($value === '' || mb_strlen($value, 'UTF-8') > $maximum) {
            throw new ModuleException('O campo ' . $label . ' é inválido ou excede o limite permitido.');
        }

        return $value;
    }
}
