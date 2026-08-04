<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$script = file_get_contents($root . '/public/assets/app.js');
$html = file_get_contents($root . '/public/app.html');
$htaccess = file_get_contents($root . '/public/.htaccess');

if (!is_string($script) || !is_string($html) || !is_string($htaccess)) {
    throw new RuntimeException('Não foi possível ler os arquivos públicos.');
}

$assertions = [
    "const requestToken = state.token;" => 'A requisição deve capturar o token usado.',
    "!publicRoute && requestToken" => 'Rotas públicas não devem receber Bearer residual.',
    "state.token === requestToken" => 'Um 401 antigo não pode encerrar uma sessão nova.',
    "cache: 'no-store'" => 'Chamadas da API não devem reutilizar respostas em cache.',
    "sessionStorage.removeItem('eva_access_token');" => 'O login deve remover a sessão residual antes de autenticar.',
    "sessionStorage.setItem('eva_access_token', state.token);" => 'O token novo deve ser persistido antes da inicialização.',
    "elements.accessPanel.inert = true;" => 'O painel oculto deve impedir foco por meio de inert.',
    "elements.workspace.focus({ preventScroll: true });" => 'O foco deve sair do diálogo antes que ele seja ocultado.',
];

foreach ($assertions as $needle => $message) {
    if (!str_contains($script, $needle)) {
        throw new RuntimeException($message);
    }
}

if (str_contains($script, "elements.accessPanel.setAttribute('aria-hidden'")) {
    throw new RuntimeException('O painel de acesso não deve ocultar do leitor de tela um foco ainda ativo.');
}

if (!preg_match('~assets/app\.js\?v=20260804-6~', $html)) {
    throw new RuntimeException('A versão pública do JavaScript não foi atualizada.');
}

if (!str_contains($htaccess, 'Cache-Control "no-cache, no-store, must-revalidate"')) {
    throw new RuntimeException('Os assets de autenticação ainda podem ser servidos sem revalidação.');
}

echo "ClientAuthenticationRegressionTest: " . (count($assertions) + 3) . " verificações concluídas.\n";
