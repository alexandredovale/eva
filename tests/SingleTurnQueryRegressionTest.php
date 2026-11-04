<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$javascript = file_get_contents($root . '/public/assets/app.js');
$html = file_get_contents($root . '/public/app.html');
$style = file_get_contents($root . '/public/assets/app.css');
$api = file_get_contents($root . '/app/Http/Product/ProductApi.php');
$provider = file_get_contents($root . '/app/Infrastructure/Ai/QueryAnswerProvider.php');

if (!is_string($javascript) || !is_string($html) || !is_string($style)
    || !is_string($api) || !is_string($provider)) {
    throw new RuntimeException('Não foi possível ler os arquivos do fluxo de consulta única.');
}

$copyActionPosition = strpos($javascript, 'data-copy-query="${index}"');
$newQuestionPosition = strpos($javascript, 'data-new-question');

$assertions = [
    !str_contains($javascript, 'buildConversationalInput')
        && !str_contains($javascript, '# Interação Anterior'),
    str_contains($javascript, 'JSON.stringify({ scopes, input })'),
    str_contains($javascript, 'state.completedQuery = { user, response: result.answer || \'\', result };'),
    str_contains($javascript, 'if (state.queryInFlight || state.completedQuery) return;'),
    str_contains($javascript, 'setQueryComposerState(state.completedQuery !== null);'),
    !str_contains($html, 'id="new-question"')
        && str_contains($javascript, 'elements.queryForm.hidden = completed || pending;')
        && str_contains($javascript, 'data-new-question')
        && str_contains($javascript, 'ph ph-arrow-counter-clockwise')
        && str_contains($javascript, 'Fazer outra pergunta')
        && $copyActionPosition !== false
        && $newQuestionPosition !== false
        && $copyActionPosition < $newQuestionPosition
        && str_contains($style, '.query-result-actions { display: flex; flex-direction: column;')
        && str_contains($style, '.query-form[hidden] { display: none; }'),
    !str_contains($api, '$payload[\'current_input\']'),
    substr_count($api, "'current_input' => \$input") === 1
        && substr_count($api, "'contextual_input' => \$input") === 1,
    str_contains($provider, 'consulta independente e autossuficiente')
        && !str_contains($provider, '# Interação Anterior'),
];

foreach ($assertions as $index => $condition) {
    if (!$condition) {
        throw new RuntimeException(sprintf(
            'A regressão de consulta única falhou na verificação %d.',
            $index + 1
        ));
    }
}

echo sprintf("Consulta de rodada única validada com %d verificações.\n", count($assertions));
