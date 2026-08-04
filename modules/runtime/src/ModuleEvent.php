<?php

declare(strict_types=1);

namespace Eva\ModuleRuntime;

use DateTimeImmutable;
use JsonException;

final readonly class ModuleEvent
{
    public const CONTRACT_VERSION = 1;
    public const MAX_PAYLOAD_BYTES = 1_000_000;

    /**
     * @param array{user_id: int|null, role: string} $actor
     * @param array{projects: list<array<string, mixed>>, documents: list<array<string, mixed>>} $scope
     * @param array{current_input: string, contextual_input: string, answer: string, input_types?: list<string>, direct_references?: list<string>, routing_points?: list<string>} $interaction
     * @param list<array<string, mixed>> $evidences
     * @param list<string> $limitations
     */
    public function __construct(
        public string $eventId,
        public string $eventType,
        public int $contractVersion,
        public string $occurredAt,
        public array $actor,
        public array $scope,
        public array $interaction,
        public array $evidences,
        public array $limitations
    ) {
        $this->assertValid();
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        return new self(
            is_string($payload['event_id'] ?? null) ? $payload['event_id'] : '',
            is_string($payload['event_type'] ?? null) ? $payload['event_type'] : '',
            is_int($payload['contract_version'] ?? null) ? $payload['contract_version'] : 0,
            is_string($payload['occurred_at'] ?? null) ? $payload['occurred_at'] : '',
            is_array($payload['actor'] ?? null) ? $payload['actor'] : [],
            is_array($payload['scope'] ?? null) ? $payload['scope'] : [],
            is_array($payload['interaction'] ?? null) ? $payload['interaction'] : [],
            is_array($payload['evidences'] ?? null) ? array_values($payload['evidences']) : [],
            is_array($payload['limitations'] ?? null) ? array_values($payload['limitations']) : []
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_type' => $this->eventType,
            'contract_version' => $this->contractVersion,
            'occurred_at' => $this->occurredAt,
            'actor' => $this->actor,
            'scope' => $this->scope,
            'interaction' => $this->interaction,
            'evidences' => $this->evidences,
            'limitations' => $this->limitations,
        ];
    }

    private function assertValid(): void
    {
        if (preg_match('/^EVA-EVT-[A-F0-9]{24}$/', $this->eventId) !== 1) {
            throw new ModuleException('O identificador do evento modular é inválido.');
        }

        if (preg_match('/^[a-z][a-z0-9_.-]{2,79}$/', $this->eventType) !== 1) {
            throw new ModuleException('O tipo do evento modular é inválido.');
        }

        if ($this->contractVersion !== self::CONTRACT_VERSION) {
            throw new ModuleException('A versão do contrato modular não é compatível.');
        }

        if (DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $this->occurredAt) === false) {
            throw new ModuleException('A data do evento modular é inválida.');
        }

        $userId = $this->actor['user_id'] ?? null;
        $role = $this->actor['role'] ?? null;

        if (array_diff(array_keys($this->actor), ['user_id', 'role']) !== []
            || ($userId !== null && (!is_int($userId) || $userId < 1))
            || !is_string($role)
            || !in_array($role, ['user', 'superadmin'], true)) {
            throw new ModuleException('O ator do evento modular é inválido.');
        }

        if (array_diff(array_keys($this->scope), ['projects', 'documents']) !== []
            || !is_array($this->scope['projects'] ?? null)
            || !is_array($this->scope['documents'] ?? null)
            || count($this->scope['projects']) > 100
            || count($this->scope['documents']) > 200) {
            throw new ModuleException('O escopo do evento modular é inválido.');
        }

        foreach (['current_input', 'contextual_input', 'answer'] as $field) {
            if (!is_string($this->interaction[$field] ?? null)) {
                throw new ModuleException('A interação do evento modular é inválida.');
            }
        }

        if (array_diff(array_keys($this->interaction), [
            'current_input',
            'contextual_input',
            'answer',
            'input_types',
            'direct_references',
            'routing_points',
        ]) !== []) {
            throw new ModuleException('A interação contém campos não reconhecidos.');
        }

        foreach (['input_types' => 20, 'direct_references' => 100, 'routing_points' => 100] as $field => $maximum) {
            if (!array_key_exists($field, $this->interaction)) {
                continue;
            }

            if (!is_array($this->interaction[$field]) || count($this->interaction[$field]) > $maximum) {
                throw new ModuleException('Os metadados da interação modular são inválidos.');
            }

            foreach ($this->interaction[$field] as $item) {
                if (!is_string($item) || mb_strlen($item, 'UTF-8') > 500) {
                    throw new ModuleException('Os metadados da interação modular são inválidos.');
                }
            }
        }

        if (strlen($this->interaction['current_input']) > 20_000
            || strlen($this->interaction['contextual_input']) > 20_000
            || strlen($this->interaction['answer']) > 100_000
            || count($this->evidences) > 100
            || count($this->limitations) > 100) {
            throw new ModuleException('O evento modular excede os limites permitidos.');
        }

        foreach ($this->limitations as $limitation) {
            if (!is_string($limitation) || strlen($limitation) > 2_000) {
                throw new ModuleException('Uma limitação do evento modular é inválida.');
            }
        }

        foreach ($this->evidences as $evidence) {
            if (!is_array($evidence)) {
                throw new ModuleException('Uma evidência do evento modular é inválida.');
            }
        }

        foreach (['projects', 'documents'] as $scopeType) {
            foreach ($this->scope[$scopeType] as $scopeItem) {
                if (!is_array($scopeItem)) {
                    throw new ModuleException('Um snapshot de escopo do evento modular é inválido.');
                }
            }
        }

        try {
            $encoded = json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new ModuleException('O evento modular não pode ser serializado.', 0, $exception);
        }

        if (strlen($encoded) > self::MAX_PAYLOAD_BYTES) {
            throw new ModuleException('O payload do evento modular excede o limite global.');
        }

        $sensitiveKeys = ['password', 'secret', 'token', 'api_key', 'authorization'];
        $this->assertNoSensitiveKeys($this->toArray(), $sensitiveKeys);
    }

    /** @param array<mixed> $value @param list<string> $sensitiveKeys */
    private function assertNoSensitiveKeys(array $value, array $sensitiveKeys): void
    {
        foreach ($value as $key => $child) {
            $normalized = strtolower(str_replace('-', '_', (string) $key));

            foreach ($sensitiveKeys as $sensitiveKey) {
                if (str_contains($normalized, $sensitiveKey)) {
                    throw new ModuleException('O evento modular contém um campo sensível não permitido.');
                }
            }

            if (is_array($child)) {
                $this->assertNoSensitiveKeys($child, $sensitiveKeys);
            }
        }
    }
}
