<?php

declare(strict_types=1);

use Eva\Infrastructure\Ai\AiProviderException;
use Eva\Infrastructure\Logging\FileLogger;
use Eva\Infrastructure\Logging\SafeFailureDiagnostics;

require __DIR__ . '/bootstrap.php';

$logPath = dirname(__DIR__) . '/storage/logs/security-test-' . bin2hex(random_bytes(6)) . '.log';
$assertions = 0;

function assertLogging(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $logger = new FileLogger($logPath);
    $logger->error('safe_diagnostic_test', [
        'request_id' => 'request-safe-123',
        'input_types' => ['relational', 'conceptual'],
        'password' => 'password-value-must-not-appear',
        'api_token' => 'token-value-must-not-appear',
        'prompt' => 'prompt-value-must-not-appear',
        'document_content' => 'document-value-must-not-appear',
        'raw_body' => 'body-value-must-not-appear',
        'message' => 'message-value-must-not-appear',
        'detail' => 'Cabeçalho Bearer bearer-value-must-not-appear e sk-testsecret123456.',
    ]);

    $line = file_get_contents($logPath);
    assertLogging(is_string($line) && trim($line) !== '', 'O evento de teste não foi gravado.');
    $decoded = json_decode(trim((string) $line), true, 32, JSON_THROW_ON_ERROR);
    $context = $decoded['context'] ?? [];

    foreach (['password', 'api_token', 'prompt', 'document_content', 'raw_body', 'message'] as $key) {
        assertLogging(($context[$key] ?? null) === '[REDACTED]', 'O campo sensível não foi redigido: ' . $key);
    }

    assertLogging(
        ($context['input_types'] ?? null) === ['relational', 'conceptual'],
        'Metadados cognitivos seguros foram removidos do diagnóstico.'
    );
    assertLogging(
        ($context['detail'] ?? null) !== '[REDACTED]'
            && !str_contains((string) ($context['detail'] ?? ''), 'bearer-value-must-not-appear')
            && !str_contains((string) ($context['detail'] ?? ''), 'sk-testsecret123456'),
        'Uma credencial embutida em texto livre permaneceu no log.'
    );

    $truncation = SafeFailureDiagnostics::context(new AiProviderException(
        'O provedor de respostas truncou a consulta no limite de saída após a regeneração compacta.'
    ));
    assertLogging(
        ($truncation['failure_category'] ?? null) === 'ai_output_truncated',
        'Truncamento não recebeu categoria operacional própria.'
    );
    assertLogging(!array_key_exists('message', $truncation), 'A mensagem da exceção não deve integrar o diagnóstico seguro.');

    $httpFailure = SafeFailureDiagnostics::context(new AiProviderException(
        'O provedor de IA respondeu HTTP 429: detalhe que não deve ser registrado.'
    ));
    assertLogging(
        ($httpFailure['failure_category'] ?? null) === 'ai_provider_http'
            && ($httpFailure['provider_http_status'] ?? null) === 429,
        'O status HTTP do provedor não foi categorizado com segurança.'
    );

    $transport = SafeFailureDiagnostics::context(new AiProviderException(
        'Falha de transporte ao acessar o provedor de IA.'
    ));
    assertLogging(
        ($transport['failure_category'] ?? null) === 'ai_transport',
        'Falha de transporte não recebeu categoria operacional própria.'
    );
} finally {
    if (is_file($logPath)) {
        unlink($logPath);
    }
}

echo sprintf("Logs seguros validados com %d asserções.\n", $assertions);
