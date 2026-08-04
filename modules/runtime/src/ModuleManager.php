<?php

declare(strict_types=1);

namespace Eva\ModuleRuntime;

final readonly class ModuleManager
{
    public function __construct(private RuntimeFactory $factory)
    {
    }

    /** @return list<array<string, mixed>> */
    public function installed(): array
    {
        return $this->factory->registry()->listForAdministration();
    }

    /** @return list<array{id: string, name: string, order: int}> */
    public function dashboards(): array
    {
        $dashboards = [];

        foreach ($this->factory->registry()->activeManifests() as $manifest) {
            if (($manifest->dashboard['enabled'] ?? false) !== true) {
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

    /** @param array{user_id: int|null, role: string} $actor @param array<string, mixed> $filters @return array<string, mixed> */
    public function dashboard(string $moduleId, array $actor, array $filters): array
    {
        $manifest = $this->factory->registry()->find($moduleId);

        if (!$this->factory->state()->isActive($moduleId) || ($manifest->dashboard['enabled'] ?? false) !== true) {
            throw new ModuleException('O dashboard do módulo não está disponível.');
        }

        $module = $this->factory->registry()->load($manifest);

        if (!$module instanceof DashboardModuleInterface) {
            throw new ModuleException('O módulo não fornece um dashboard compatível.');
        }

        // Atualiza os bancos privados dos módulos antes da leitura, sem executar
        // interpretação por IA e sem acoplar o processamento à consulta do Core.
        $this->factory->dispatcher()->consumeOnce(500);

        $dashboard = $module->dashboard($this->factory->context($manifest), $actor, $filters);

        if (($dashboard['contract'] ?? null) !== 'eva.module.dashboard/1'
            || !is_string($dashboard['html'] ?? null)
            || !is_string($dashboard['css'] ?? null)) {
            throw new ModuleException('O módulo retornou um dashboard incompatível com o contrato EVA.');
        }

        return $dashboard;
    }

}
