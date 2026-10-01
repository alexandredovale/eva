<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$html = file_get_contents($root . '/public/app.html');
$script = file_get_contents($root . '/public/assets/app.js');
$style = file_get_contents($root . '/public/assets/app.css');
$index = file_get_contents($root . '/public/index.php');

if (!is_string($html) || !is_string($script) || !is_string($style) || !is_string($index)) {
    throw new RuntimeException('Não foi possível ler os arquivos do ícone Phosphor.');
}

$assertions = [
    [$html, 'https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css', 'A fonte regular do Phosphor deve permanecer fixada na versão aprovada.'],
    [$script, '<i class="ph ph-copy" aria-hidden="true"></i>', 'O botão de cópia deve usar o glifo Phosphor Copy U+E1CA.'],
    [$style, '.button-copy-result .ph-copy', 'O ícone Phosphor deve manter o alinhamento visual do botão.'],
    [$index, 'style-src \'self\' \'nonce-{$styleNonce}\' https://fonts.googleapis.com https://cdn.jsdelivr.net', 'A CSP deve autorizar a folha de estilo Phosphor fixada.'],
    [$index, 'font-src https://fonts.gstatic.com https://cdn.jsdelivr.net', 'A CSP deve autorizar a webfont Phosphor fixada.'],
];

foreach ($assertions as [$source, $needle, $message]) {
    if (!str_contains($source, $needle)) {
        throw new RuntimeException($message);
    }
}

if (str_contains($script, 'button-copy-result" data-copy-query="${index}"><svg')) {
    throw new RuntimeException('O desenho SVG desfigurado ainda está associado ao botão de cópia.');
}

echo 'PhosphorIconRegressionTest: ' . (count($assertions) + 1) . " verificações concluídas.\n";
