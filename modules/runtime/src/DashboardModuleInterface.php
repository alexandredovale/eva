<?php

declare(strict_types=1);

namespace Eva\ModuleRuntime;

interface DashboardModuleInterface
{
    /**
     * @param array{user_id: int|null, role: string} $actor
     * @param array<string, mixed> $filters
     * @return array{contract: 'eva.module.dashboard/1', html: string, css: string}
     */
    public function dashboard(ModuleContext $context, array $actor, array $filters): array;
}
