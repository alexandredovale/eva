<?php

declare(strict_types=1);

namespace Eva\Infrastructure\Ai;

use JsonException;

final class CurlJsonHttpClient implements JsonHttpClientInterface
{
    public function post(string $url, array $headers, array $payload, int $timeoutSeconds): array
    {
        if (!function_exists('curl_init')) {
            throw new AiProviderException('A extensão cURL não está disponível.');
        }

        if (!str_starts_with($url, 'https://')) {
            throw new AiProviderException('O provedor de IA deve usar HTTPS.');
        }

        try {
            $body = json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException $exception) {
            throw new AiProviderException('Não foi possível serializar a requisição de IA.', 0, $exception);
        }

        $handle = curl_init($url);

        if ($handle === false) {
            throw new AiProviderException('Não foi possível iniciar a conexão com o provedor de IA.');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [...$headers, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeoutSeconds),
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);

        $responseBody = curl_exec($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $transportError = curl_error($handle);
        curl_close($handle);

        if ($responseBody === false) {
            throw new AiProviderException(
                'Falha de transporte ao acessar o provedor de IA' . ($transportError !== '' ? ': ' . $transportError : '.')
            );
        }

        try {
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new AiProviderException('O provedor de IA retornou JSON inválido.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new AiProviderException('O provedor de IA retornou uma resposta inválida.');
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = $decoded['error']['message'] ?? 'erro não detalhado';
            $message = is_string($message) ? mb_substr($message, 0, 300, 'UTF-8') : 'erro não detalhado';
            throw new AiProviderException(sprintf('O provedor de IA respondeu HTTP %d: %s', $statusCode, $message));
        }

        return $decoded;
    }
}