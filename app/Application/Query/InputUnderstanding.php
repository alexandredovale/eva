<?php

declare(strict_types=1);

namespace Eva\Application\Query;

final readonly class InputUnderstanding
{
    /**
     * @param list<InputType> $types
     * @param list<string> $directReferences
     */
    public function __construct(
        public array $types,
        public array $directReferences
    ) {
        if ($this->types === []) {
            throw new QueryException('O input deve possuir ao menos um tipo de recuperação.');
        }
    }

    public function has(InputType $type): bool
    {
        return in_array($type, $this->types, true);
    }

    /** @return array{types: list<string>, direct_references: list<string>} */
    public function toArray(): array
    {
        return [
            'types' => array_map(static fn (InputType $type): string => $type->value, $this->types),
            'direct_references' => $this->directReferences,
        ];
    }
}
