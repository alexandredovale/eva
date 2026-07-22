<?php

declare(strict_types=1);

namespace Eva\Application\Cognitive;

final readonly class EmbeddingInputGuard
{
    private const SAFETY_RATIO = 0.90;

    public function __construct(private int $providerTokenLimit)
    {
        if ($this->providerTokenLimit < 1) {
            throw new CognitiveBuildException('O limite de tokens por unidade de embedding é inválido.');
        }
    }

    public function isCompatible(StructuredEmbeddingUnit $unit): bool
    {
        return $this->estimateTokens($unit->text) <= $this->safeTokenLimit();
    }

    public function safeTokenLimit(): int
    {
        return max(1, (int) floor($this->providerTokenLimit * self::SAFETY_RATIO));
    }

    public function estimateTokens(string $text): int
    {
        $matched = preg_match_all('/\p{L}+|\p{N}+|[^\s\p{L}\p{N}]/u', $text, $parts);

        if ($matched === false) {
            return max(1, (int) ceil(strlen($text) / 3));
        }

        $lexicalEstimate = 0;

        foreach ($parts[0] as $part) {
            if (preg_match('/^[\p{L}\p{N}]+$/u', $part) === 1) {
                $lexicalEstimate += max(1, (int) ceil(mb_strlen($part, 'UTF-8') / 4));
                continue;
            }

            $lexicalEstimate += max(1, (int) ceil(strlen($part) / 3));
        }

        return max($lexicalEstimate, (int) ceil(strlen($text) / 3));
    }
}
