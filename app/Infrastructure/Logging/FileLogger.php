<?php

declare(strict_types=1);

namespace Eva\Infrastructure\Logging;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;

final class FileLogger
{
    public function __construct(private readonly string $path)
    {
    }

    /** @param array<string, mixed> $context */
    public function info(string $event, array $context = []): void
    {
        $this->write('info', $event, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $event, array $context = []): void
    {
        $this->write('warning', $event, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $event, array $context = []): void
    {
        $this->write('error', $event, $context);
    }

    /** @param array<string, mixed> $context */
    private function write(string $level, string $event, array $context): void
    {
        $directory = dirname($this->path);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            error_log('Cnode: não foi possível criar o diretório de logs.');
            return;
        }

        try {
            $line = json_encode([
                'timestamp' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM),
                'level' => $level,
                'event' => $event,
                'context' => $this->sanitize($context),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            error_log('Cnode: não foi possível serializar um evento de log.');
            return;
        }

        if (file_put_contents($this->path, $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            error_log('Cnode: não foi possível gravar um evento de log.');
        }
    }

    private function sanitize(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitiveKey($key)) {
            return '[REDACTED]';
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $childKey => $childValue) {
                $sanitized[$childKey] = $this->sanitize($childValue, (string) $childKey);
            }

            return $sanitized;
        }

        if (is_string($value)) {
            $value = preg_replace('/\bBearer\s+[^\s]+/i', 'Bearer [REDACTED]', $value) ?? $value;
            $value = preg_replace('/\bsk-[A-Za-z0-9_-]{8,}\b/', '[REDACTED]', $value) ?? $value;

            return mb_substr($value, 0, 500, 'UTF-8');
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return get_debug_type($value);
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = str_replace('-', '_', mb_strtolower($key, 'UTF-8'));

        if (preg_match('/(?:^|_)(?:password|secret|token|api_key|authorization)(?:$|_)/', $normalized) === 1) {
            return true;
        }

        return in_array($normalized, [
            'message',
            'prompt',
            'input',
            'content',
            'body',
            'raw_body',
            'request_body',
            'response_body',
            'query',
            'query_text',
            'document',
            'document_text',
            'evidence',
            'evidence_text',
        ], true) || preg_match('/_(?:prompt|content|body|text)$/', $normalized) === 1;
    }
}
