<?php

declare(strict_types=1);

namespace Eva\Infrastructure\Ai;

use Eva\Application\Cognitive\EmbeddingProviderInterface;
use Eva\Application\Cognitive\SummaryProviderInterface;
use Eva\Application\Query\QueryAnswerProviderInterface;
use Eva\Support\Env;

final readonly class CognitiveProviderFactory
{
    /** @param array<string, mixed> $config */
    public function __construct(private array $config)
    {
    }

    public function embeddings(?JsonHttpClientInterface $http = null): EmbeddingProviderInterface
    {
        $this->ensureLiveEnabled();
        $config = $this->providerConfig('embeddings');
        $keyName = (string) ($config['api_key_environment'] ?? '');

        return new EmbeddingProvider(
            $http ?? new CurlJsonHttpClient(),
            (string) Env::get($keyName, ''),
            (string) ($config['model'] ?? ''),
            (string) ($config['endpoint'] ?? ''),
            (int) ($this->config['request_timeout_seconds'] ?? 30),
            (int) ($config['max_units_per_request'] ?? 64)
        );
    }

    public function summaries(?JsonHttpClientInterface $http = null): SummaryProviderInterface
    {
        $this->ensureLiveEnabled();
        $config = $this->providerConfig('summaries');
        $keyName = (string) ($config['api_key_environment'] ?? '');

        return new SummaryProvider(
            $http ?? new CurlJsonHttpClient(),
            (string) Env::get($keyName, ''),
            (string) ($config['model'] ?? ''),
            (string) ($config['endpoint'] ?? ''),
            (int) ($config['max_output_tokens'] ?? 500),
            (int) ($this->config['request_timeout_seconds'] ?? 30)
        );
    }

    public function queryAnswers(?JsonHttpClientInterface $http = null): QueryAnswerProviderInterface
    {
        $this->ensureLiveEnabled();
        $config = $this->providerConfig('query_answers');
        $keyName = (string) ($config['api_key_environment'] ?? '');

        return new QueryAnswerProvider(
            $http ?? new CurlJsonHttpClient(),
            (string) Env::get($keyName, ''),
            (string) ($config['model'] ?? ''),
            (string) ($config['endpoint'] ?? ''),
            (int) ($config['max_output_tokens'] ?? 1800),
            (int) ($this->config['request_timeout_seconds'] ?? 30)
        );
    }

    private function ensureLiveEnabled(): void
    {
        if (($this->config['live_enabled'] ?? false) !== true) {
            throw new AiProviderException('Chamadas reais de IA estão desativadas. Defina AI_LIVE_ENABLED=true conscientemente.');
        }
    }

    /** @return array<string, mixed> */
    private function providerConfig(string $capability): array
    {
        $providers = $this->config['providers'] ?? null;
        $config = is_array($providers) ? ($providers[$capability] ?? null) : null;

        if (!is_array($config) || trim((string) ($config['provider'] ?? '')) === '') {
            throw new AiProviderException('O provedor da capacidade "' . $capability . '" não está configurado.');
        }

        return $config;
    }
}
