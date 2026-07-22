<?php

declare(strict_types=1);

namespace Eva\Http\Security;

use Eva\Support\Env;
use PDO;

final readonly class AuthGuard
{
    /** @param array<string, mixed> $config */
    public function __construct(private PDO $database, private array $config)
    {
    }

    /** @param array<string, mixed> $server */
    public function authenticate(array $server): ActorContext
    {
        $header = $server['HTTP_AUTHORIZATION'] ?? $server['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

        if (!is_string($header) || preg_match('/^Bearer\s+([^\s]+)$/i', trim($header), $matches) !== 1) {
            throw new AccessException('Autenticação obrigatória.', 401);
        }

        $provided = $matches[1];
        $environmentName = (string) ($this->config['admin_token_environment'] ?? 'ADMIN_API_TOKEN');
        $minimumLength = (int) ($this->config['minimum_token_length'] ?? 24);
        $adminToken = (string) Env::get($environmentName, '');

        if (strlen($adminToken) >= $minimumLength && hash_equals($adminToken, $provided)) {
            return new ActorContext(hash('sha256', $adminToken));
        }

        $tokenHash = hash('sha256', $provided);
        $statement = $this->database->prepare(
            'SELECT s.id, s.user_id, u.username
               FROM user_sessions s
               JOIN users u ON u.id = s.user_id
              WHERE s.token_hash = :token_hash
                AND s.expires_at > CURRENT_TIMESTAMP
                AND u.active = 1
              LIMIT 1'
        );
        $statement->execute(['token_hash' => $tokenHash]);
        $session = $statement->fetch();

        if (!is_array($session)) {
            throw new AccessException('Credencial inválida ou expirada.', 401);
        }

        $touch = $this->database->prepare('UPDATE user_sessions SET last_used_at = CURRENT_TIMESTAMP WHERE id = :id');
        $touch->execute(['id' => (int) $session['id']]);

        return new ActorContext(
            hash('sha256', 'user:' . $session['user_id']),
            'user',
            (int) $session['user_id'],
            (string) $session['username'],
            $tokenHash
        );
    }
}
