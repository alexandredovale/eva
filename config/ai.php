<?php

declare(strict_types=1);

use Eva\Support\Env;

$timeout = max(5, min(120, (int) Env::get('AI_REQUEST_TIMEOUT', '30')));
$maxOutputTokens = max(100, min(2_000, (int) Env::get('AI_MAX_OUTPUT_TOKENS', '1500')));
$queryMaxOutputTokens = max(100, min(3_000, (int) Env::get('AI_QUERY_MAX_OUTPUT_TOKENS', '1800')));
$languageProvider = (string) Env::get('AI_LANGUAGE_PROVIDER', '');
$languageEndpoint = rtrim((string) Env::get('AI_LANGUAGE_ENDPOINT', ''), '/');
$languageKeyEnvironment = (string) Env::get('AI_LANGUAGE_API_KEY_ENV', '');
$summaryProvider = trim((string) Env::get('AI_SUMMARY_PROVIDER', ''));
$summaryEndpoint = rtrim(trim((string) Env::get('AI_SUMMARY_ENDPOINT', '')), '/');
$summaryKeyEnvironment = trim((string) Env::get('AI_SUMMARY_API_KEY_ENV', ''));
$queryProvider = trim((string) Env::get('AI_QUERY_PROVIDER', ''));
$queryEndpoint = rtrim(trim((string) Env::get('AI_QUERY_ENDPOINT', '')), '/');
$queryKeyEnvironment = trim((string) Env::get('AI_QUERY_API_KEY_ENV', ''));

return [
    // Prevents accidental consumption. Real calls require AI_LIVE_ENABLED=true.
    'live_enabled' => Env::bool('AI_LIVE_ENABLED', false),
    'request_timeout_seconds' => $timeout,
    'max_new_summaries_per_run' => max(1, min(100, (int) Env::get('AI_MAX_NEW_SUMMARIES_PER_RUN', '5'))),
    // Provider-neutral structure; operational bindings come exclusively from .env.
    'providers' => [
        'embeddings' => [
            'provider' => Env::get('AI_EMBEDDING_PROVIDER', ''),
            'endpoint' => rtrim((string) Env::get('AI_EMBEDDING_ENDPOINT', ''), '/'),
            'model' => Env::get('AI_EMBEDDING_MODEL', ''),
            'api_key_environment' => Env::get('AI_EMBEDDING_API_KEY_ENV', ''),
            'max_units_per_request' => max(1, min(256, (int) Env::get('AI_EMBEDDING_BATCH_UNITS', '64'))),
            'max_input_tokens' => max(256, min(1_000_000, (int) Env::get('AI_EMBEDDING_MAX_INPUT_TOKENS', '8192'))),
        ],
        'summaries' => [
            'provider' => $summaryProvider !== '' ? $summaryProvider : $languageProvider,
            'endpoint' => $summaryEndpoint !== '' ? $summaryEndpoint : $languageEndpoint,
            'model' => Env::get('AI_SUMMARY_MODEL', ''),
            'max_output_tokens' => $maxOutputTokens,
            'api_key_environment' => $summaryKeyEnvironment !== '' ? $summaryKeyEnvironment : $languageKeyEnvironment,
        ],
        'query_answers' => [
            'provider' => $queryProvider !== '' ? $queryProvider : $languageProvider,
            'endpoint' => $queryEndpoint !== '' ? $queryEndpoint : $languageEndpoint,
            'model' => Env::get('AI_QUERY_MODEL', ''),
            'max_output_tokens' => $queryMaxOutputTokens,
            'api_key_environment' => $queryKeyEnvironment !== '' ? $queryKeyEnvironment : $languageKeyEnvironment,
        ],
    ],
    'query' => [
        'max_evidence' => max(1, min(50, (int) Env::get('QUERY_MAX_EVIDENCE', '8'))),
        'max_interactions' => max(0, min(100, (int) Env::get('QUERY_MAX_INTERACTIONS', '20'))),
    ],
];
