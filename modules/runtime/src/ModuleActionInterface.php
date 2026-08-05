<?php

declare(strict_types=1);

namespace Eva\ModuleRuntime;

interface ModuleActionInterface
{
    /**
     * @param array{user_id: int|null, role: string} $actor
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function action(
        ModuleContext $context,
        array $actor,
        string $action,
        array $input,
        string $requestId
    ): array;
}
