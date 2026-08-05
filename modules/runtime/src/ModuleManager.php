<?php

declare(strict_types=1);

namespace Eva\ModuleRuntime;

use Eva\Http\Security\ActorContext;
use JsonException;

final readonly class ModuleManager
{
    private const MAX_ACTION_PAYLOAD_BYTES = 131_072;
    private const MAX_DASHBOARD_HTML_BYTES = 1_048_576;
    private const MAX_DASHBOARD_CSS_BYTES = 262_144;

    public function __construct(private RuntimeFactory $factory)
    {
    }

    /** @return list<array<string, mixed>> */
    public function installed(): array
    {
        return $this->factory->registry()->listForAdministration();
    }

    /** @return list<array{id: string, name: string, order: int}> */
    public function dashboards(ActorContext $actor): array
    {
        $dashboards = [];

        foreach ($this->factory->registry()->activeManifests() as $manifest) {
            if (($manifest->dashboard['enabled'] ?? false) !== true) {
                continue;
            }

            $module = $this->factory->registry()->load($manifest);
            $context = $this->factory->context($manifest, $actor);
            $module->install($context);

            if (!$this->canAccess($module, $context, $actor)) {
                continue;
            }

            $dashboards[] = [
                'id' => $manifest->id,
                'name' => $manifest->name,
                'order' => (int) ($manifest->dashboard['order'] ?? 1000),
            ];
        }

        usort($dashboards, static fn (array $left, array $right): int =>
            [$left['order'], mb_strtolower($left['name'], 'UTF-8')]
            <=> [$right['order'], mb_strtolower($right['name'], 'UTF-8')]
        );

        return $dashboards;
    }

    /** @return array<string, mixed> */
    public function setActive(string $moduleId, bool $active): array
    {
        $manifest = $this->factory->registry()->find($moduleId);
        $module = $this->factory->registry()->load($manifest);

        if ($active) {
            $module->install($this->factory->context($manifest));
        }

        $this->factory->state()->setActive($moduleId, $active);

        return $manifest->toArray($active);
    }

    /** @return array{module_id: string, package_entries_deleted: int, data_entries_deleted: int} */
    public function delete(string $moduleId): array
    {
        $manifest = $this->factory->registry()->find($moduleId);
        $this->factory->state()->setActive($moduleId, false);
        $dataDeleted = $this->factory->storage()->deleteData($manifest);
        $packageDeleted = $this->factory->registry()->deletePackage($manifest);
        $this->factory->state()->remove($moduleId);

        return [
            'module_id' => $moduleId,
            'package_entries_deleted' => $packageDeleted,
            'data_entries_deleted' => $dataDeleted,
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function dashboard(string $moduleId, ActorContext $actor, array $filters): array
    {
        $manifest = $this->factory->registry()->find($moduleId);

        if (!$this->factory->state()->isActive($moduleId) || ($manifest->dashboard['enabled'] ?? false) !== true) {
            throw new ModuleException('O dashboard do módulo não está disponível.');
        }

        $module = $this->factory->registry()->load($manifest);

        if (!$module instanceof DashboardModuleInterface) {
            throw new ModuleException('O módulo não fornece um dashboard compatível.');
        }

        $context = $this->factory->context($manifest, $actor);
        $module->install($context);
        $this->assertAccessible($module, $context, $actor);

        // Atualiza os bancos privados dos módulos antes da leitura, sem executar
        // interpretação por IA e sem acoplar o processamento à consulta do Core.
        $this->factory->dispatcher()->consumeOnce(500);

        $dashboard = $module->dashboard($context, $this->actorArray($actor), $filters);
        $this->assertDashboard($dashboard);

        return $dashboard;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function action(
        string $moduleId,
        ActorContext $actor,
        string $action,
        array $input,
        string $requestId
    ): array {
        $manifest = $this->factory->registry()->find($moduleId);

        if (!$this->factory->state()->isActive($moduleId)
            || ($manifest->dashboard['enabled'] ?? false) !== true) {
            throw new ModuleException('As ações do módulo não estão disponíveis.');
        }

        if (preg_match('/^[a-z][a-z0-9_.-]{2,79}$/', $action) !== 1) {
            throw new ModuleException('O identificador da ação modular é inválido.');
        }

        if (preg_match('/^[A-Za-z0-9._:-]{8,120}$/', $requestId) !== 1) {
            throw new ModuleException('O identificador da requisição modular é inválido.');
        }

        $this->assertActionInput($input);
        $module = $this->factory->registry()->load($manifest);

        if (!$module instanceof ModuleActionInterface) {
            throw new ModuleException('O módulo não fornece ações interativas compatíveis.');
        }

        $context = $this->factory->context($manifest, $actor);
        $module->install($context);
        $this->assertAccessible($module, $context, $actor);
        $result = $module->action(
            $context,
            $this->actorArray($actor),
            $action,
            $input,
            $requestId
        );
        $this->assertActionResult($result);

        return $result;
    }

    private function canAccess(
        ModuleInterface $module,
        ModuleContext $context,
        ActorContext $actor
    ): bool {
        return $actor->isSuperadmin()
            || !$module instanceof ModuleAccessInterface
            || $module->canAccess($context, $this->actorArray($actor));
    }

    private function assertAccessible(
        ModuleInterface $module,
        ModuleContext $context,
        ActorContext $actor
    ): void {
        if (!$this->canAccess($module, $context, $actor)) {
            throw new ModuleAccessDeniedException('Este módulo não está disponível para o usuário.');
        }
    }

    /** @return array{user_id: int|null, role: string} */
    private function actorArray(ActorContext $actor): array
    {
        return ['user_id' => $actor->userId, 'role' => $actor->role];
    }

    /** @param array<string, mixed> $dashboard */
    private function assertDashboard(array $dashboard): void
    {
        if (array_diff(array_keys($dashboard), ['contract', 'html', 'css']) !== []
            || ($dashboard['contract'] ?? null) !== 'eva.module.dashboard/1'
            || !is_string($dashboard['html'] ?? null)
            || !is_string($dashboard['css'] ?? null)
            || strlen($dashboard['html']) > self::MAX_DASHBOARD_HTML_BYTES
            || strlen($dashboard['css']) > self::MAX_DASHBOARD_CSS_BYTES) {
            throw new ModuleException('O módulo retornou um dashboard incompatível com o contrato EVA.');
        }
    }

    /** @param array<string, mixed> $result */
    private function assertActionResult(array $result): void
    {
        if (array_diff(array_keys($result), ['contract', 'dashboard', 'notice']) !== []
            || ($result['contract'] ?? null) !== 'eva.module.action/1'
            || !is_array($result['dashboard'] ?? null)) {
            throw new ModuleException('O módulo retornou uma ação incompatível com o contrato EVA.');
        }

        $this->assertDashboard($result['dashboard']);
        $notice = $result['notice'] ?? null;

        if ($notice === null) {
            return;
        }

        if (!is_array($notice)
            || array_diff(array_keys($notice), ['type', 'message']) !== []
            || !in_array($notice['type'] ?? null, ['success', 'info', 'warning'], true)
            || !is_string($notice['message'] ?? null)
            || trim($notice['message']) === ''
            || mb_strlen($notice['message'], 'UTF-8') > 500) {
            throw new ModuleException('O módulo retornou uma notificação incompatível.');
        }
    }

    /** @param array<string, mixed> $input */
    private function assertActionInput(array $input): void
    {
        $this->assertNoSensitiveKeys($input);

        try {
            $encoded = json_encode(
                $input,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                32
            );
        } catch (JsonException $exception) {
            throw new ModuleException('O payload da ação modular é inválido.', 0, $exception);
        }

        if (strlen($encoded) > self::MAX_ACTION_PAYLOAD_BYTES) {
            throw new ModuleException('O payload da ação modular excede o limite permitido.');
        }
    }

    /** @param array<mixed> $value */
    private function assertNoSensitiveKeys(array $value): void
    {
        foreach ($value as $key => $child) {
            $normalized = strtolower(str_replace('-', '_', (string) $key));

            foreach (['password', 'secret', 'token', 'api_key', 'authorization'] as $sensitiveKey) {
                if (str_contains($normalized, $sensitiveKey)) {
                    throw new ModuleException('O payload da ação modular contém um campo sensível não permitido.');
                }
            }

            if (is_array($child)) {
                $this->assertNoSensitiveKeys($child);
            }
        }
    }

}
