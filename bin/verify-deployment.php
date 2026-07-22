<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$arguments = array_slice($argv, 1);
$baseUrl = rtrim((string) ($arguments[0] ?? ''), '/');
$localMode = in_array('--local', $arguments, true);

if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false
    || (!$localMode && !str_starts_with($baseUrl, 'https://'))) {
    fwrite(STDERR, "Uso: php bin/verify-deployment.php https://dominio/caminho [--local]\n");
    exit(2);
}

/** @return array{status: int, headers: array<string, string>, body: string} */
function deploymentRequest(string $url, string $method, bool $localMode): array
{
    $handle = curl_init($url);

    if ($handle === false) {
        throw new RuntimeException('Não foi possível iniciar a verificação HTTP.');
    }

    $headers = [];
    curl_setopt_array($handle, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => !$localMode,
        CURLOPT_SSL_VERIFYHOST => $localMode ? 0 : 2,
        CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers): int {
            $position = strpos($line, ':');

            if ($position !== false) {
                $name = strtolower(trim(substr($line, 0, $position)));
                $headers[$name] = trim(substr($line, $position + 1));
            }

            return strlen($line);
        },
    ]);
    $body = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);

    if ($body === false) {
        throw new RuntimeException('Falha de transporte na verificação: ' . $error);
    }

    return ['status' => $status, 'headers' => $headers, 'body' => $body];
}

$checks = [];
$check = static function (string $name, bool $passed, string $detail = '') use (&$checks): void {
    $checks[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
};

try {
    $application = deploymentRequest($baseUrl . '/', 'GET', $localMode);
    $check('application_http_200', $application['status'] === 200, 'HTTP ' . $application['status']);
    $check(
        'application_surface',
        str_contains($application['body'], 'id="access-panel"')
            && str_contains($application['body'], 'assets/app.js'),
        'Interface EVA esperada'
    );

    $requiredHeaders = [
        'x-content-type-options' => 'nosniff',
        'x-frame-options' => 'DENY',
        'referrer-policy' => 'no-referrer',
        'permissions-policy' => 'camera=()',
        'cross-origin-resource-policy' => 'same-origin',
        'content-security-policy' => "default-src 'self'",
        'cache-control' => 'no-store',
    ];

    foreach ($requiredHeaders as $name => $expected) {
        $value = $application['headers'][$name] ?? '';
        $check('header_' . str_replace('-', '_', $name), str_contains($value, $expected), $value);
    }

    $requestId = $application['headers']['x-request-id'] ?? '';
    $check('header_x_request_id', preg_match('/^[a-f0-9]{16}$/', $requestId) === 1, $requestId);
    $check('header_no_x_powered_by', !isset($application['headers']['x-powered-by']), 'X-Powered-By ausente');

    if (!$localMode) {
        $hsts = $application['headers']['strict-transport-security'] ?? '';
        $check('header_hsts', str_contains($hsts, 'max-age=31536000'), $hsts);
        $httpUrl = preg_replace('/^https:/', 'http:', $baseUrl) ?: $baseUrl;
        $redirect = deploymentRequest($httpUrl . '/', 'GET', false);
        $location = $redirect['headers']['location'] ?? '';
        $check(
            'http_redirects_to_https',
            in_array($redirect['status'], [301, 302, 307, 308], true) && str_starts_with($location, 'https://'),
            'HTTP ' . $redirect['status'] . ' → ' . $location
        );
        $trace = deploymentRequest($baseUrl . '/', 'TRACE', false);
        $check('trace_disabled', in_array($trace['status'], [403, 405, 501], true), 'HTTP ' . $trace['status']);
    }

    $health = deploymentRequest($baseUrl . '/api/health', 'GET', $localMode);
    $healthPayload = json_decode($health['body'], true);
    $check(
        'health_ready',
        $health['status'] === 200
            && is_array($healthPayload)
            && ($healthPayload['status'] ?? null) === 'ready'
            && ($healthPayload['database'] ?? null) === 'available',
        'HTTP ' . $health['status']
    );

    foreach (['.env', 'api_key.md', 'storage/logs/app.log', 'database/actual/eva.sql', 'public/app.html', '.git/config'] as $path) {
        $response = deploymentRequest($baseUrl . '/' . $path, 'GET', $localMode);
        $check(
            'blocked_' . preg_replace('/[^a-z0-9]+/i', '_', $path),
            in_array($response['status'], [403, 404], true),
            'HTTP ' . $response['status']
        );
    }
} catch (Throwable $exception) {
    $check('transport', false, $exception->getMessage());
}

$failures = array_values(array_filter($checks, static fn (array $item): bool => !$item['passed']));

foreach ($checks as $item) {
    echo ($item['passed'] ? 'PASS ' : 'FAIL ') . $item['name'];

    if ($item['detail'] !== '') {
        echo ' — ' . $item['detail'];
    }

    echo PHP_EOL;
}

echo sprintf("SUMMARY checks=%d failures=%d\n", count($checks), count($failures));
exit($failures === [] ? 0 : 1);
