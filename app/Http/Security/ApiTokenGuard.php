<?php

declare(strict_types=1);

namespace Eva\Http\Security;

use Eva\Support\Env;

final readonly class ApiTokenGuard
{
    /** @param array<string, mixed> $config */
    public function __construct(private array $config)
    {
    }

    /** @param array<string, mixed> $server */
    public function authenticate(array $server): ActorContext
    {
        $environmentName = (string) ($this->config['admin_token_environment'] ?? 'ADMIN_API_TOKEN');
        $minimumLength = (int) ($this->config['minimum_token_length'] ?? 24);
        $expected = (string) Env::get($environmentName, '');

        if (strlen($expected) < $minimumLength) {
            throw new AccessException('O acesso administrativo ainda não foi configurado.', 503);
        }

        $header = $server['HTTP_AUTHORIZATION'] ?? $server['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

        if (!is_string($header) || preg_match('/^Bearer\s+([^\s]+)$/i', trim($header), $matches) !== 1) {
            throw new AccessException('Autenticação administrativa obrigatória.', 401);
        }

        $provided = $matches[1];

        if (!hash_equals($expected, $provided)) {
            throw new AccessException('Credencial administrativa inválida.', 401);
        }

        return new ActorContext(hash('sha256', $expected));
    }
}
