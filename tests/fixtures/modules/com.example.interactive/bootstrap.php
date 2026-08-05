<?php

declare(strict_types=1);

use Eva\ModuleRuntime\DashboardModuleInterface;
use Eva\ModuleRuntime\ModuleAccessInterface;
use Eva\ModuleRuntime\ModuleActionInterface;
use Eva\ModuleRuntime\ModuleContext;
use Eva\ModuleRuntime\ModuleEvent;
use Eva\ModuleRuntime\ModuleException;
use Eva\ModuleRuntime\ModuleInterface;

return new class implements ModuleInterface, DashboardModuleInterface, ModuleAccessInterface, ModuleActionInterface {
    public function id(): string
    {
        return 'com.example.interactive';
    }

    public function install(ModuleContext $context): void
    {
        $context->storage->exec('CREATE TABLE IF NOT EXISTS enabled_users (user_id INTEGER PRIMARY KEY)');
        $context->storage->exec(
            'CREATE TABLE IF NOT EXISTS action_requests (
                request_id TEXT PRIMARY KEY,
                action_id TEXT NOT NULL,
                value_text TEXT NOT NULL
            )'
        );
        $context->storage->exec('INSERT OR IGNORE INTO enabled_users (user_id) VALUES (1)');
    }

    public function handle(ModuleEvent $event, ModuleContext $context): void
    {
    }

    public function canAccess(ModuleContext $context, array $actor): bool
    {
        $statement = $context->storage->prepare('SELECT 1 FROM enabled_users WHERE user_id = :user_id');
        $statement->execute(['user_id' => $actor['user_id']]);

        return $statement->fetchColumn() !== false;
    }

    public function dashboard(ModuleContext $context, array $actor, array $filters): array
    {
        return $this->renderDashboard();
    }

    public function action(
        ModuleContext $context,
        array $actor,
        string $action,
        array $input,
        string $requestId
    ): array {
        if ($action !== 'save_value' || !is_string($input['value'] ?? null)) {
            throw new ModuleException('Ação de fixture inválida.');
        }

        $statement = $context->storage->prepare(
            'INSERT OR IGNORE INTO action_requests (request_id, action_id, value_text)
             VALUES (:request_id, :action_id, :value_text)'
        );
        $statement->execute([
            'request_id' => $requestId,
            'action_id' => $action,
            'value_text' => $input['value'],
        ]);

        return [
            'contract' => 'eva.module.action/1',
            'dashboard' => $this->renderDashboard(),
            'notice' => ['type' => 'success', 'message' => 'Fixture atualizada.'],
        ];
    }

    private function renderDashboard(): array
    {
        return [
            'contract' => 'eva.module.dashboard/1',
            'html' => '<form data-module-action-form><input name="value"><button type="submit" data-module-action="save_value">Salvar</button></form>',
            'css' => '.interactive-fixture { display: block; }',
        ];
    }
};
