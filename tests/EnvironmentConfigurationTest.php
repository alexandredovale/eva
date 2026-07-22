<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$examplePath = $root . '/.env.example';
$envPath = is_file($root . '/.env') ? $root . '/.env' : $examplePath;
$envContent = file_get_contents($envPath);

if (!is_string($envContent)) {
    throw new RuntimeException('Neither .env nor the public .env.example could be read.');
}

$assertions = 0;

function assertEnvironmentConfiguration(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

assertEnvironmentConfiguration(is_file($examplePath), 'The public .env.example file is required.');

$configuredNames = [];
$configuredValues = [];
preg_match_all('/^[ \t]*([A-Z][A-Z0-9_]*)[ \t]*=[ \t]*([^\r\n]*)$/m', $envContent, $envMatches, PREG_SET_ORDER);

foreach ($envMatches as $envMatch) {
    $name = $envMatch[1];
    assertEnvironmentConfiguration(
        !isset($configuredNames[$name]),
        sprintf('Duplicate variable in %s: %s.', basename($envPath), $name)
    );
    $configuredNames[$name] = true;
    $configuredValues[$name] = trim($envMatch[2], " \t\n\r\0\x0B\"'");
}

$requiredNames = ['ADMIN_API_TOKEN'];

foreach (glob($root . '/config/*.php') ?: [] as $configPath) {
    $configContent = file_get_contents($configPath);

    if (!is_string($configContent)) {
        continue;
    }

    preg_match_all(
        '/Env::(?:get|bool)\(\s*[\'\"]([A-Z][A-Z0-9_]*)[\'\"]/',
        $configContent,
        $configMatches
    );
    $requiredNames = array_merge($requiredNames, $configMatches[1] ?? []);
}

foreach (['AI_EMBEDDING_API_KEY_ENV', 'AI_LANGUAGE_API_KEY_ENV', 'AI_SUMMARY_API_KEY_ENV', 'AI_QUERY_API_KEY_ENV'] as $referenceName) {
    $credentialName = $configuredValues[$referenceName] ?? '';

    if (preg_match('/^[A-Z][A-Z0-9_]*$/', $credentialName) === 1) {
        $requiredNames[] = $credentialName;
    }
}

$requiredNames = array_values(array_unique($requiredNames));
sort($requiredNames);

foreach ($requiredNames as $name) {
    assertEnvironmentConfiguration(
        isset($configuredNames[$name]),
        sprintf('Variable recognized by the code is missing from %s: %s.', basename($envPath), $name)
    );
}

assertEnvironmentConfiguration(
    count($configuredNames) === count($requiredNames),
    sprintf('%s contains variables outside the recognized configuration inventory.', basename($envPath))
);

echo sprintf(
    "Environment configuration validated from %s: %d variables, %d assertions.\n",
    basename($envPath),
    count($requiredNames),
    $assertions
);
