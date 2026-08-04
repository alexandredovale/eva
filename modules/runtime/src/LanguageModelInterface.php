<?php

declare(strict_types=1);

namespace Eva\ModuleRuntime;

interface LanguageModelInterface
{
    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function generateJson(string $systemInstruction, array $input): array;
}
