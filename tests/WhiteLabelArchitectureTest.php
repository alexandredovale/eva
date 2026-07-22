<?php

declare(strict_types=1);

$container = require __DIR__ . '/bootstrap.php';
$root = dirname(__DIR__);
$assertions = 0;

function assertWhiteLabel(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$genericImplementations = [
    'EmbeddingProvider.php',
    'SummaryProvider.php',
    'QueryAnswerProvider.php',
    'CognitiveProviderFactory.php',
];

foreach ($genericImplementations as $filename) {
    assertWhiteLabel(
        is_file($root . '/app/Infrastructure/Ai/' . $filename),
        'Implementação white label ausente: ' . $filename
    );
}

$scanTargets = [
    $root . '/app',
    $root . '/bin',
    $root . '/config',
    $root . '/docs',
    $root . '/public',
    $root . '/tests',
];
$forbidden = [strtolower('Open' . 'AI'), strtolower('Deep' . 'Seek')];
$violations = [];

foreach ($scanTargets as $target) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target));

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $content = file_get_contents($file->getPathname());

        if (!is_string($content)) {
            continue;
        }

        $normalized = strtolower($content);

        foreach ($forbidden as $name) {
            if (str_contains($normalized, $name)) {
                $violations[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                break;
            }
        }
    }
}

$readme = file_get_contents($root . '/README.md');

foreach ($forbidden as $name) {
    if (is_string($readme) && str_contains(strtolower($readme), $name)) {
        $violations[] = 'README.md';
    }
}

assertWhiteLabel($violations === [], 'Marcas fora dos arquivos gerenciais: ' . implode(', ', array_unique($violations)));

$environmentTemplate = file_get_contents($root . '/.env.example');
assertWhiteLabel(is_string($environmentTemplate), 'The sanitized .env.example template is required.');
$environment = [];

if (is_string($environmentTemplate)) {
    preg_match_all(
        '/^[ \t]*([A-Z][A-Z0-9_]*)[ \t]*=[ \t]*([^\r\n]*)$/m',
        $environmentTemplate,
        $environmentMatches,
        PREG_SET_ORDER
    );

    foreach ($environmentMatches as $environmentMatch) {
        $environment[$environmentMatch[1]] = trim($environmentMatch[2], " \t\n\r\0\x0B\"'");
    }
}

$credentialNames = [];

foreach (['AI_EMBEDDING_API_KEY_ENV', 'AI_SUMMARY_API_KEY_ENV', 'AI_QUERY_API_KEY_ENV'] as $referenceName) {
    $credentialName = $environment[$referenceName] ?? '';
    assertWhiteLabel(
        preg_match('/^[A-Z][A-Z0-9_]*$/', $credentialName) === 1,
        'Invalid neutral credential reference: ' . $referenceName
    );
    assertWhiteLabel(array_key_exists($credentialName, $environment), 'Referenced credential placeholder is missing.');
    assertWhiteLabel($environment[$credentialName] === '', 'Public credential placeholders must be empty.');
    $credentialNames[] = $credentialName;
}

assertWhiteLabel(!is_file($root . '/api_key.md'), 'api_key.md must not exist in the public package.');

foreach ($credentialNames as $credentialName) {
    foreach ($forbidden as $name) {
        assertWhiteLabel(
            !str_contains(strtolower($credentialName), $name),
            'Nome de credencial associado a fornecedor.'
        );
    }
}

foreach (['embeddings', 'summaries', 'query_answers'] as $capability) {
    $config = $container['ai']['providers'][$capability] ?? null;
    assertWhiteLabel(is_array($config), 'Capacidade sem configuração: ' . $capability);
    assertWhiteLabel(
        !isset($config['api_key']),
        'Referência segura de credencial inválida: ' . $capability
    );
}

echo sprintf("Arquitetura white label validada com %d asserções.\n", $assertions);
