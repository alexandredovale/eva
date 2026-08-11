<?php

declare(strict_types=1);

namespace Eva\Application\Query;

final class FigureContractParser
{
    public function parse(string $nodeTitle, string $content): ?FigureContract
    {
        $fields = [];
        $lines = preg_split('/\R/u', $content) ?: [];

        foreach ($lines as $line) {
            if (preg_match('/^\s*([^:\r\n]{2,64}):[\t ]*(.*?)\s*$/u', $line, $match) !== 1) {
                continue;
            }

            $key = $this->normalizeKey($match[1]);
            $value = trim($match[2], " \t\n\r\0\x0B\"'");

            if ($value !== '') {
                $fields[$key] = $value;
            }
        }

        return $this->parseFields($nodeTitle, $fields);
    }

    /** @param array<string, mixed> $fields */
    public function parseFields(string $nodeTitle, array $fields): ?FigureContract
    {
        $nodeTitle = trim($nodeTitle);

        if (!$this->isFigureTitle($nodeTitle)) {
            return null;
        }

        $normalizedFields = [];

        foreach ($fields as $key => $value) {
            if (!is_scalar($value) || is_bool($value)) {
                continue;
            }

            $value = trim((string) $value, " \t\n\r\0\x0B\"'");

            if ($value !== '') {
                $normalizedFields[$this->canonicalKey((string) $key)] = $value;
            }
        }

        $declaredPath = $normalizedFields['file'] ?? null;

        if (!is_string($declaredPath) || $declaredPath === '') {
            return null;
        }

        return new FigureContract(
            $nodeTitle,
            $declaredPath,
            $normalizedFields['type'] ?? null,
            $normalizedFields['factual_description'] ?? null,
            $normalizedFields['visible_text'] ?? null,
            $normalizedFields['represented_relationships'] ?? null
        );
    }

    public function isFigureTitle(string $title): bool
    {
        return preg_match('/^(?:Figura|Figure)\b/iu', trim($title)) === 1;
    }

    private function canonicalKey(string $key): string
    {
        return match ($this->normalizeKey($key)) {
            'arquivo', 'file' => 'file',
            'tipo', 'type' => 'type',
            'descricao factual', 'factual description' => 'factual_description',
            'texto visivel', 'visible text' => 'visible_text',
            'relacoes representadas', 'represented relationships' => 'represented_relationships',
            default => $this->normalizeKey($key),
        };
    }

    private function normalizeKey(string $key): string
    {
        $key = mb_strtolower(trim($key), 'UTF-8');
        $key = strtr($key, [
            'á' => 'a',
            'à' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'é' => 'e',
            'ê' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ú' => 'u',
            'ç' => 'c',
        ]);

        $key = preg_replace('/[_-]+/u', ' ', $key) ?? $key;

        return preg_replace('/\s+/u', ' ', trim($key)) ?? trim($key);
    }
}
