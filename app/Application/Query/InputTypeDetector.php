<?php

declare(strict_types=1);

namespace Eva\Application\Query;

final class InputTypeDetector
{
    private const RELATIONAL_LEXEMES_PATTERN = '/\b(?:'
        . 'intera(?:c\p{L}*|g\p{L}*|t(?:e|ion|ions|ing|ed|s))|relac\p{L}*|'
        . 'relat(?:e\p{L}*|ion\p{L}*|ionship\p{L}*)|associ\p{L}*|conect\p{L}*|conex\p{L}*|vincul\p{L}*|'
        . 'compar\p{L}*|between|entre|versus|vs|simetr\p{L}*|assimetr\p{L}*|simetry|assimetry'
        . ')\b/u';

    private const RELATIONAL_OPERATOR_PATTERN = '/(?:<[-=]?>|[-=]>|[↔⇄⇆→←])/u';

    public function detect(string $input): InputUnderstanding
    {
        $input = trim($input);

        if ($input === '') {
            throw new QueryException('O input da consulta é obrigatório.');
        }

        preg_match_all('/\bEVA-[ED]\d{6,}\b/i', $input, $referenceMatches);
        $references = array_values(array_unique(array_map('strtoupper', $referenceMatches[0] ?? [])));
        $normalized = mb_strtolower($input, 'UTF-8');
        $types = [];

        if ($references !== [] || preg_match('/["“][^"”]+["”]/u', $input) === 1) {
            $types[] = InputType::Direct;
        }

        if (preg_match('/\b(cap[ií]tulo|se[cç][aã]o|parte|subt[ií]tulo|t[ií]tulo|obra|caminho|estrutura|chapter|section|part|subtitle|title|work|path|structure)\b/u', $normalized) === 1
            || preg_match('~(?:^|\s)/[\p{L}\p{N}/_-]+~u', $input) === 1) {
            $types[] = InputType::Structural;
        }

        if ($this->isRelational($normalized)) {
            $types[] = InputType::Relational;
        }

        if (preg_match('/\b(vis[aã]o geral|panorama|resumo geral|obra inteira|documento inteiro|em linhas gerais|overview|general summary|entire work|entire document|in general terms)\b/u', $normalized) === 1) {
            $types[] = InputType::Broad;
        }

        if ($types === [] || (in_array(InputType::Relational, $types, true)
            && !in_array(InputType::Direct, $types, true)
            && !in_array(InputType::Structural, $types, true))) {
            $types[] = InputType::Conceptual;
        }

        return new InputUnderstanding(array_values(array_unique($types, SORT_REGULAR)), $references);
    }

    private function isRelational(string $normalizedInput): bool
    {
        if (preg_match(self::RELATIONAL_OPERATOR_PATTERN, $normalizedInput) === 1) {
            return true;
        }

        return preg_match(
            self::RELATIONAL_LEXEMES_PATTERN,
            $this->normalizeRelationalLexemes($normalizedInput)
        ) === 1;
    }

    private function normalizeRelationalLexemes(string $input): string
    {
        return strtr($input, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);
    }
}
