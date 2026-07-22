<?php

declare(strict_types=1);

namespace Eva\Application\Access;

use Eva\Http\Security\AccessException;
use Eva\Http\Security\ActorContext;
use PDO;

final readonly class AuthService
{
    private const RECOVERY_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** @param array<string, mixed> $config */
    public function __construct(private PDO $database, private array $config)
    {
    }

    /** @return array{token: string, user: array<string, mixed>} */
    public function login(string $username, string $password): array
    {
        $username = trim($username);
        $statement = $this->database->prepare(
            'SELECT id, username, password_hash, active FROM users WHERE username = :username LIMIT 1'
        );
        $statement->execute(['username' => $username]);
        $user = $statement->fetch();

        if (!is_array($user) || (int) $user['active'] !== 1
            || !password_verify($password, (string) $user['password_hash'])) {
            throw new AccessException('Username ou senha inválidos.', 401);
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $rehash = $this->database->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
            $rehash->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => (int) $user['id']]);
        }

        $token = $this->createSession((int) $user['id']);
        $update = $this->database->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id');
        $update->execute(['id' => (int) $user['id']]);

        return [
            'token' => $token,
            'user' => ['id' => (int) $user['id'], 'username' => (string) $user['username'], 'role' => 'user'],
        ];
    }

    /** @return array{recovery_code: string} */
    public function recover(string $username, string $recoveryCode, string $newPassword): array
    {
        $this->assertPassword($newPassword);
        $statement = $this->database->prepare(
            'SELECT id, recovery_code_hash, active FROM users WHERE username = :username LIMIT 1'
        );
        $statement->execute(['username' => trim($username)]);
        $user = $statement->fetch();
        $normalizedCode = $this->normalizeRecoveryCode($recoveryCode);

        if (!is_array($user) || (int) $user['active'] !== 1
            || !password_verify($normalizedCode, (string) $user['recovery_code_hash'])) {
            throw new AccessException('Username ou código de recuperação inválidos.', 401);
        }

        $newCode = $this->generateRecoveryCode();
        $this->database->beginTransaction();

        try {
            $update = $this->database->prepare(
                'UPDATE users
                    SET password_hash = :password_hash, recovery_code_hash = :recovery_hash
                  WHERE id = :id'
            );
            $update->execute([
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                'recovery_hash' => password_hash($newCode, PASSWORD_DEFAULT),
                'id' => (int) $user['id'],
            ]);
            $delete = $this->database->prepare('DELETE FROM user_sessions WHERE user_id = :user_id');
            $delete->execute(['user_id' => (int) $user['id']]);
            $this->database->commit();
        } catch (\Throwable $exception) {
            $this->database->rollBack();
            throw $exception;
        }

        return ['recovery_code' => $newCode];
    }

    public function changePassword(ActorContext $actor, string $currentPassword, string $newPassword): void
    {
        if ($actor->userId === null) {
            throw new AccessException('A senha do superadmin é gerenciada no ambiente do servidor.', 403);
        }

        $this->assertPassword($newPassword);
        $statement = $this->database->prepare('SELECT password_hash FROM users WHERE id = :id AND active = 1');
        $statement->execute(['id' => $actor->userId]);
        $currentHash = $statement->fetchColumn();

        if (!is_string($currentHash) || !password_verify($currentPassword, $currentHash)) {
            throw new AccessException('A senha atual não confere.', 422);
        }

        $update = $this->database->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $update->execute(['hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $actor->userId]);
    }

    public function rotateRecoveryCode(ActorContext $actor, string $currentPassword): string
    {
        if ($actor->userId === null) {
            throw new AccessException('Operação disponível apenas para usuários cadastrados.', 403);
        }

        $statement = $this->database->prepare('SELECT password_hash FROM users WHERE id = :id AND active = 1');
        $statement->execute(['id' => $actor->userId]);
        $passwordHash = $statement->fetchColumn();

        if (!is_string($passwordHash) || !password_verify($currentPassword, $passwordHash)) {
            throw new AccessException('A senha atual não confere.', 422);
        }

        $code = $this->generateRecoveryCode();
        $update = $this->database->prepare('UPDATE users SET recovery_code_hash = :hash WHERE id = :id');
        $update->execute(['hash' => password_hash($code, PASSWORD_DEFAULT), 'id' => $actor->userId]);

        return $code;
    }

    public function logout(ActorContext $actor): void
    {
        if ($actor->sessionHash === null) {
            return;
        }

        $statement = $this->database->prepare('DELETE FROM user_sessions WHERE token_hash = :token_hash');
        $statement->execute(['token_hash' => $actor->sessionHash]);
    }

    public function assertPassword(string $password): void
    {
        $minimum = (int) ($this->config['minimum_password_length'] ?? 8);

        if (strlen($password) < $minimum || strlen($password) > 200) {
            throw new AccessException("A senha deve possuir entre {$minimum} e 200 caracteres.", 422);
        }
    }

    public function generateRecoveryCode(): string
    {
        $code = '';
        $maximum = strlen(self::RECOVERY_ALPHABET) - 1;

        for ($index = 0; $index < 16; $index++) {
            $code .= self::RECOVERY_ALPHABET[random_int(0, $maximum)];
        }

        return $code;
    }

    private function normalizeRecoveryCode(string $code): string
    {
        $normalized = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($code))) ?? '';

        return strlen($normalized) === 16 ? $normalized : '';
    }

    private function createSession(int $userId): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $hours = max(1, min(168, (int) ($this->config['session_hours'] ?? 12)));
        $statement = $this->database->prepare(
            'INSERT INTO user_sessions (user_id, token_hash, expires_at)
             VALUES (:user_id, :token_hash, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL ' . $hours . ' HOUR))'
        );
        $statement->execute(['user_id' => $userId, 'token_hash' => hash('sha256', $token)]);

        return $token;
    }
}
