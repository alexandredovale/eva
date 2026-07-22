<?php

declare(strict_types=1);

namespace Eva\Http\Security;

final readonly class ActorContext
{
    public function __construct(
        public string $fingerprint,
        public string $role = 'superadmin',
        public ?int $userId = null,
        public string $username = 'superadmin',
        public ?string $sessionHash = null
    ) {
    }

    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }
}
