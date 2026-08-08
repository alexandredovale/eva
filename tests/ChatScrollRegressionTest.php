<?php

declare(strict_types=1);

$script = file_get_contents(dirname(__DIR__) . '/public/assets/app.js');

if (!is_string($script)) {
    throw new RuntimeException('Não foi possível ler o JavaScript do chat.');
}

$assertions = [
    "renderConversation(input);" => 'O estado de espera deve continuar exibindo a interação mais recente.',
    "renderConversation('', true);" => 'A resposta concluída deve solicitar o alinhamento do card do assistente.',
    "querySelectorAll('.chat-message-assistant:not(.chat-message-pending)')" => 'O alinhamento deve ignorar o card temporário de consulta.',
    "latestAnswer.scrollIntoView({ block: 'start', inline: 'nearest' })" => 'O início da resposta deve ser alinhado ao viewport, mesmo quando o chat cresce junto com a página.',
    "focus({ preventScroll: true })" => 'O foco devolvido ao campo de consulta não deve deslocar a tela até o final das evidências.',
];

foreach ($assertions as $needle => $message) {
    if (!str_contains($script, $needle)) {
        throw new RuntimeException($message);
    }
}

echo 'ChatScrollRegressionTest: ' . count($assertions) . " verificações concluídas.\n";
