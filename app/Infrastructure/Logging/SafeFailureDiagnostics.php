<?php

declare(strict_types=1);

namespace Eva\Infrastructure\Logging;

use Eva\Infrastructure\Ai\AiProviderException;
use JsonException;
use PDOException;
use Throwable;

final class SafeFailureDiagnostics
{
    /** @param array<string, mixed> $context @return array<string, mixed> */
    public static function context(Throwable $exception, array $context = []): array
    {
        $diagnostics = [
            'failure_category' => self::category($exception),
            'exception_class' => $exception::class,
        ];

        if ($exception instanceof AiProviderException
            && preg_match('/\bHTTP\s+(\d{3})\b/i', $exception->getMessage(), $matches) === 1) {
            $diagnostics['provider_http_status'] = (int) $matches[1];
        }

        return array_merge($context, $diagnostics);
    }

    private static function category(Throwable $exception): string
    {
        if ($exception instanceof AiProviderException) {
            $message = mb_strtolower($exception->getMessage(), 'UTF-8');

            return match (true) {
                str_contains($message, 'truncou'), str_contains($message, 'limite de saída') => 'ai_output_truncated',
                str_contains($message, 'http ') => 'ai_provider_http',
                str_contains($message, 'transporte'), str_contains($message, 'timeout'),
                str_contains($message, 'conexão'), str_contains($message, 'curl') => 'ai_transport',
                str_contains($message, 'json'), str_contains($message, 'contrato'),
                str_contains($message, 'resposta inválida') => 'ai_invalid_response',
                str_contains($message, 'serializar') => 'ai_serialization',
                str_contains($message, 'configuração'), str_contains($message, 'credencial'),
                str_contains($message, 'desativadas'), str_contains($message, 'https') => 'ai_configuration',
                default => 'ai_provider',
            };
        }

        if ($exception instanceof PDOException) {
            return 'database';
        }

        if ($exception instanceof JsonException) {
            return 'serialization';
        }

        return 'application';
    }
}
