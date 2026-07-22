<?php

declare(strict_types=1);

use Eva\Http\Product\ProductApi;
use Eva\Application\Product\BrandingPresenter;
use Eva\Infrastructure\Database\Connection;
use Eva\Infrastructure\Logging\FileLogger;
use Eva\Infrastructure\Logging\SafeFailureDiagnostics;

$container = require dirname(__DIR__) . '/bootstrap/app.php';
$requestId = bin2hex(random_bytes(8));

header_remove('X-Powered-By');
header('X-Request-Id: ' . $requestId);
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store');

$forwardedProtocol = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
$isHttps = strtolower((string) ($_SERVER['HTTPS'] ?? '')) === 'on'
    || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
    || $forwardedProtocol === 'https';
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));

if ($isHttps && !str_starts_with($host, 'localhost') && !str_starts_with($host, '127.0.0.1')) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

/** @param array<string, mixed> $payload */
function jsonResponse(int $status, array $payload, array $headers = []): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");

    foreach ($headers as $name => $value) {
        header($name . ': ' . $value);
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requestPath(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/.');
    $basePaths = $basePath === '' ? [] : [$basePath];

    if (str_ends_with($basePath, '/public')) {
        $projectBasePath = substr($basePath, 0, -strlen('/public'));

        if ($projectBasePath !== '') {
            $basePaths[] = $projectBasePath;
        }
    }

    usort($basePaths, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

    foreach ($basePaths as $candidateBasePath) {
        if ($path === $candidateBasePath) {
            $path = '/';
            break;
        }

        if (str_starts_with($path, $candidateBasePath . '/')) {
            $path = substr($path, strlen($candidateBasePath));
            break;
        }
    }

    if ($path === '/index.php') {
        return '/';
    }

    return '/' . ltrim($path, '/');
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = requestPath();
$logger = new FileLogger($container['logging']['path']);

if ($path === '/' || $path === '/app') {
    if ($method !== 'GET') {
        jsonResponse(405, ['error' => 'Método não permitido.'], ['Allow' => 'GET']);
    }

    header('Content-Type: text/html; charset=utf-8');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    readfile(__DIR__ . '/app.html');
    exit;
}

if ($path === '/api/health') {
    if ($method !== 'GET') {
        jsonResponse(405, ['error' => 'Método não permitido.'], ['Allow' => 'GET']);
    }

    $databaseStatus = 'unavailable';
    $httpStatus = 503;

    try {
        Connection::create($container['database'])->query('SELECT 1');
        $databaseStatus = 'available';
        $httpStatus = 200;
    } catch (Throwable $exception) {
        $logger->error('database_healthcheck_failed', SafeFailureDiagnostics::context($exception, [
            'request_id' => $requestId,
        ]));
    }

    jsonResponse($httpStatus, [
        'application' => (new BrandingPresenter($container['branding']))->toArray()['name'],
        'status' => $httpStatus === 200 ? 'ready' : 'degraded',
        'database' => $databaseStatus,
        'version' => '0.5.0',
    ]);
}

if (str_starts_with($path, '/api/')) {
    try {
        $database = Connection::create($container['database']);
        $response = (new ProductApi($database, $container, $logger, $requestId))->handle(
            $method,
            $path,
            $_SERVER,
            $_FILES,
            $_POST,
            (string) file_get_contents('php://input')
        );
        jsonResponse($response->status, $response->payload, $response->headers);
    } catch (Throwable $exception) {
        $logger->error('product_endpoint_unavailable', SafeFailureDiagnostics::context($exception, [
            'route' => $path,
            'request_id' => $requestId,
        ]));
        jsonResponse(503, ['error' => 'O serviço está indisponível.']);
    }
}

jsonResponse(404, ['error' => 'Rota não encontrada.']);
