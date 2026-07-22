<?php

declare(strict_types=1);

namespace Eva\Infrastructure\Ai;

use Eva\Application\Cognitive\StructuredSummaryUnit;
use Eva\Application\Cognitive\SummaryProviderInterface;
use Eva\Application\Cognitive\SummaryResult;
use JsonException;

final class SummaryProvider implements SummaryProviderInterface
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Você produz sínteses hierárquicas neutras para o EVA. Compreenda apenas as relações semânticas explicitamente presentes no conteúdo organizado fornecido. Não julgue, não atribua pesos, confiança, importância, qualidade ou verdade. Não invente relações nem complete lacunas. Preserve conceitos, distinções e orientação explicitamente declarados. Responda somente com JSON válido no formato {"summary":"..."}.
PROMPT;

    public function __construct(
        private readonly JsonHttpClientInterface $http,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $endpoint,
        private readonly int $maxOutputTokens = 500,
        private readonly int $timeoutSeconds = 30
    ) {
        if (trim($this->apiKey) === '') {
            throw new AiProviderException('A credencial do provedor de sínteses não está configurada.');
        }

        if (trim($this->model) === '' || filter_var($this->endpoint, FILTER_VALIDATE_URL) === false
            || $this->maxOutputTokens < 1 || $this->timeoutSeconds < 1) {
            throw new AiProviderException('A configuração do provedor de sínteses é inválida.');
        }
    }

    public function model(): string
    {
        return $this->model;
    }

    public function summarize(StructuredSummaryUnit $unit): SummaryResult
    {
        try {
            $organizedContent = json_encode(
                $unit->toArray(),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException $exception) {
            throw new AiProviderException('Não foi possível serializar a unidade estrutural.', 0, $exception);
        }

        $response = $this->http->post(
            $this->endpoint,
            ['Authorization: Bearer ' . $this->apiKey],
            [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    [
                        'role' => 'user',
                        'content' => "Resuma a unidade estrutural completa abaixo. Responda em JSON.\n" . $organizedContent,
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
                'thinking' => ['type' => 'disabled'],
                'temperature' => 0,
                'max_tokens' => $this->maxOutputTokens,
            ],
            $this->timeoutSeconds
        );

        $content = $response['choices'][0]['message']['content'] ?? null;

        if (!is_string($content) || trim($content) === '') {
            throw new AiProviderException('O provedor de sínteses não retornou conteúdo para o resumo.');
        }

        if (($response['choices'][0]['finish_reason'] ?? null) === 'length') {
            throw new AiProviderException('O provedor de sínteses truncou o resumo no limite de saída configurado.');
        }

        $content = $this->extractJsonEnvelope($content);

        try {
            $decoded = json_decode($content, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            try {
                $decoded = json_decode(
                    $this->escapeControlCharactersInsideStrings($content),
                    true,
                    32,
                    JSON_THROW_ON_ERROR
                );
            } catch (JsonException) {
                $recoveredSummary = $this->recoverSummaryEnvelope($content);

                if ($recoveredSummary === null) {
                    throw new AiProviderException('O provedor de sínteses retornou um resumo fora do JSON exigido.', 0, $exception);
                }

                $decoded = ['summary' => $recoveredSummary];
            }
        }

        $summary = is_array($decoded) ? ($decoded['summary'] ?? null) : null;

        if (!is_string($summary) || trim($summary) === '') {
            throw new AiProviderException('O provedor de sínteses retornou um resumo vazio.');
        }

        $inputTokens = $response['usage']['prompt_tokens'] ?? 0;
        $outputTokens = $response['usage']['completion_tokens'] ?? 0;
        $model = is_string($response['model'] ?? null) ? $response['model'] : $this->model;

        return new SummaryResult(
            trim($summary),
            $model,
            is_int($inputTokens) ? $inputTokens : 0,
            is_int($outputTokens) ? $outputTokens : 0
        );
    }

    private function extractJsonEnvelope(string $content): string
    {
        $content = trim($content);
        $content = preg_replace('/^\xEF\xBB\xBF/u', '', $content) ?? $content;

        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/isu', $content, $fence) === 1) {
            $content = trim($fence[1]);
        }

        $start = strpos($content, '{');

        if ($start === false) {
            return $content;
        }

        $depth = 0;
        $insideString = false;
        $escaped = false;
        $length = strlen($content);

        for ($index = $start; $index < $length; $index++) {
            $character = $content[$index];

            if ($insideString) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }

                if ($character === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($character === '"') {
                    $insideString = false;
                }

                continue;
            }

            if ($character === '"') {
                $insideString = true;
                continue;
            }

            if ($character === '{') {
                $depth++;
                continue;
            }

            if ($character === '}') {
                $depth--;

                if ($depth === 0) {
                    return substr($content, $start, $index - $start + 1);
                }
            }
        }

        $end = strrpos($content, '}');

        return $end !== false && $end > $start
            ? substr($content, $start, $end - $start + 1)
            : $content;
    }

    private function escapeControlCharactersInsideStrings(string $json): string
    {
        $result = '';
        $insideString = false;
        $escaped = false;
        $length = strlen($json);

        for ($index = 0; $index < $length; $index++) {
            $character = $json[$index];
            $code = ord($character);

            if (!$insideString) {
                $result .= $character;

                if ($character === '"') {
                    $insideString = true;
                }

                continue;
            }

            if ($escaped) {
                $result .= $character;
                $escaped = false;
                continue;
            }

            if ($character === '\\') {
                $result .= $character;
                $escaped = true;
                continue;
            }

            if ($character === '"') {
                $result .= $character;
                $insideString = false;
                continue;
            }

            if ($code < 0x20) {
                $result .= match ($character) {
                    "\n" => '\\n',
                    "\r" => '\\r',
                    "\t" => '\\t',
                    "\x08" => '\\b',
                    "\x0C" => '\\f',
                    default => sprintf('\\u%04x', $code),
                };
                continue;
            }

            $result .= $character;
        }

        return $result;
    }

    private function recoverSummaryEnvelope(string $json): ?string
    {
        if (preg_match('/^\s*\{\s*"summary"\s*:\s*"(.*)"\s*\}\s*$/su', $json, $match) !== 1) {
            return null;
        }

        $raw = $match[1];
        $encoded = '';
        $length = strlen($raw);

        for ($index = 0; $index < $length; $index++) {
            $character = $raw[$index];
            $code = ord($character);

            if ($character === '\\') {
                $next = $raw[$index + 1] ?? '';

                if (str_contains('"\\/bfnrtu', $next)) {
                    $encoded .= $character . $next;
                    $index++;
                } else {
                    $encoded .= '\\\\';
                }

                continue;
            }

            if ($character === '"') {
                $encoded .= '\\"';
                continue;
            }

            if ($code < 0x20) {
                $encoded .= match ($character) {
                    "\n" => '\\n',
                    "\r" => '\\r',
                    "\t" => '\\t',
                    "\x08" => '\\b',
                    "\x0C" => '\\f',
                    default => sprintf('\\u%04x', $code),
                };
                continue;
            }

            $encoded .= $character;
        }

        try {
            $summary = json_decode('"' . $encoded . '"', true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_string($summary) && trim($summary) !== '' ? $summary : null;
    }
}
