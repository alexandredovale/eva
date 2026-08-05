<?php

declare(strict_types=1);

namespace Eva\ModuleRuntime;

interface ModuleAccessInterface
{
    /** @param array{user_id: int|null, role: string} $actor */
    public function canAccess(ModuleContext $context, array $actor): bool;
}
