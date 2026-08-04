<?php

declare(strict_types=1);

namespace Eva\ModuleRuntime;

use Eva\Infrastructure\Ai\CurlJsonHttpClient;
use Eva\Support\Env;
use JsonException;

final readonly class LanguageModelApi implements LanguageModelInterface
{
    /** @param list<string> $capabilities @param array<string, mixed> $aiConfiguration */
    public function __construct(
        private array $capabilities,
        private array $aiConfiguration
    ) {
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function generateJson(string $systemInstruction, array $input): array
    {
        if (!in_array('ai.language.generate', $this->capabilities, true)) {
            throw new ModuleException('O módulo não declarou a capacidade ai.language.generate.');
        }

        if (($this->aiConfiguration['live_enabled'] ?? false) !== true) {
            throw new ModuleException('As chamadas reais de IA estão desabilitadas.');
        }

        $provider = $this->aiConfiguration['providers']['query_answers'] ?? null;

        if (!is_array($provider)) {
            throw new ModuleException('O provedor de linguagem do EVA não está configurado.');
        }

        $endpoint = is_string($provider['endpoint'] ?? null) ? rtrim($provider['endpoint'], '/') : '';
        $model = is_string($provider['model'] ?? null) ? trim($provider['model']) : '';
        $keyEnvironment = is_string($provider['api_key_environment'] ?? null)
            ? trim($provider['api_key_environment'])
            : '';
        $apiKey = $keyEnvironment !== '' ? (string) Env::get($keyEnvironment, '') : '';

        if ($endpoint === '' || $model === '' || $apiKey === '') {
            throw new ModuleException('O provedor de linguagem do EVA está incompleto.');
        }

        try {
            $inputJson = json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new ModuleException('A entrada do módulo não pode ser serializada.', 0, $exception);
        }

        $client = new CurlJsonHttpClient();
        $request = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemInstruction],
                ['role' => 'user', 'content' => $inputJson],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0,
            'max_tokens' => max(3000, min(6000, (int) ($provider['max_output_tokens'] ?? 3000))),
        ];
        $providerHost = strtolower((string) parse_url($endpoint, PHP_URL_HOST));

        if ($providerHost === 'api.deepseek.com') {
            $request['thinking'] = ['type' => 'disabled'];
        }

        $response = $client->post(
            $endpoint,
            ['Authorization: Bearer ' . $apiKey],
            $request,
            max(5, min(120, (int) ($this->aiConfiguration['request_timeout_seconds'] ?? 30)))
        );
        $content = $response['choices'][0]['message']['content'] ?? null;

        if (!is_string($content) || trim($content) === '') {
            throw new ModuleException('O provedor de linguagem não retornou conteúdo JSON.');
        }

        try {
            $decoded = json_decode($content, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ModuleException('O provedor de linguagem retornou conteúdo inválido.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new ModuleException('O provedor de linguagem retornou uma estrutura inválida.');
        }

        return $decoded;
    }
}
