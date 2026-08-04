<?php

declare(strict_types=1);

namespace Eva\ModuleRuntime;

use DateTimeImmutable;

final readonly class ModuleBridge
{
    public function __construct(private ModuleEventRepository $events)
    {
    }

    /**
     * @param array{user_id: int|null, role: string} $actor
     * @param array{projects: list<array<string, mixed>>, documents: list<array<string, mixed>>} $scope
     * @param array{current_input: string, contextual_input: string, answer: string, input_types?: list<string>, direct_references?: list<string>, routing_points?: list<string>} $interaction
     * @param list<array<string, mixed>> $evidences
     * @param list<string> $limitations
     */
    public function emit(
        string $eventType,
        array $actor,
        array $scope,
        array $interaction,
        array $evidences = [],
        array $limitations = [],
        ?string $eventId = null
    ): ModuleEvent {
        $event = new ModuleEvent(
            $eventId ?? 'EVA-EVT-' . strtoupper(bin2hex(random_bytes(12))),
            $eventType,
            ModuleEvent::CONTRACT_VERSION,
            (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
            $actor,
            $scope,
            $interaction,
            $evidences,
            $limitations
        );
        $this->events->append($event);

        return $event;
    }
}
