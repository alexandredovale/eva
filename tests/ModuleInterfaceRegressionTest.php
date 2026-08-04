<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$html = file_get_contents($root . '/public/app.html');
$script = file_get_contents($root . '/public/assets/app.js');
$style = file_get_contents($root . '/public/assets/app.css');
$manifest = file_get_contents($root . '/modules/com.eva.education/module.json');
$presenter = file_get_contents($root . '/modules/com.eva.education/src/Dashboard/EducationDashboardPresenter.php');
$moduleStyle = file_get_contents($root . '/modules/com.eva.education/assets/dashboard.css');
$index = file_get_contents($root . '/public/index.php');
$manifestSchema = file_get_contents($root . '/modules/runtime/contracts/module-manifest.schema.json');

if (!is_string($html) || !is_string($script) || !is_string($style)
    || !is_string($manifest) || !is_string($presenter) || !is_string($moduleStyle)
    || !is_string($index) || !is_string($manifestSchema)) {
    throw new RuntimeException('Não foi possível ler a interface modular.');
}

$assertions = [
    [$html, 'id="module-navigation"', 'O host de navegação modular não foi renderizado.'],
    [$html, 'id="view-module" data-view-panel="module"', 'O host visual genérico dos módulos está ausente.'],
    [$script, "api('modules')", 'O frontend não descobre interfaces de módulos ativos.'],
    [$script, 'data-module-id', 'A navegação não utiliza o identificador dinâmico do manifesto.'],
    [$script, 'escapeHtml(module.name)', 'A navegação não respeita o nome canônico do módulo.'],
    [$script, '[data-module-content-filter]', 'O host não oferece filtragem declarativa.'],
    [$script, '[data-module-accordion-toggle]', 'O host não oferece acordeão declarativo.'],
    [$script, "dashboard?.contract !== 'eva.module.dashboard/1'", 'O frontend não valida o contrato visual modular.'],
    [$script, "style.setAttribute('nonce', cspStyleNonce)", 'O CSS modular não recebe autorização da CSP.'],
    [$html, 'name="csp-style-nonce"', 'A página não transporta o nonce de estilo.'],
    [$index, "'nonce-{\$styleNonce}'", 'A CSP não autoriza estilos modulares por nonce.'],
    [$manifest, '"id": "com.eva.education"', 'O módulo ainda utiliza um identificador proprietário.'],
    [$manifest, '"name": "Education"', 'O nome canônico do módulo não foi aplicado.'],
    [$manifestSchema, '"order": {"type": "integer"', 'O contrato perdeu a ordenação genérica das interfaces.'],
    [$presenter, 'class="card learning-entry" data-module-entry', 'O módulo não produz seus próprios cards.'],
    [$presenter, 'data-module-content-filter', 'O filtro não pertence à apresentação do módulo.'],
    [$presenter, "return \$parts[3] . '-' . \$parts[2]", 'A data institucional não é formatada pelo módulo.'],
    [$moduleStyle, '.education-dashboard .learning-entry', 'O layout dos cards não está no pacote educacional.'],
    [$script, 'class="query-loading-dots" aria-hidden="true"', 'O estado de consulta não possui indicador visual acessível.'],
    [$style, '@keyframes query-loading-dot', 'Os pontos de espera não possuem animação.'],
    [$style, '.query-loading-dot { opacity: 1; transform: none; }', 'O indicador não respeita movimento reduzido.'],
];

foreach ($assertions as [$source, $needle, $message]) {
    if (!str_contains($source, $needle)) throw new RuntimeException($message);
}

$forbiddenCoreTerms = ['com.oceanno.education', 'com.eva.education', 'educationModule', 'learning-entry', 'Trajeto', 'Education'];
foreach ($forbiddenCoreTerms as $term) {
    if (str_contains($html, $term) || str_contains($script, $term) || str_contains($style, $term)) {
        throw new RuntimeException('O Core contém conhecimento específico de módulo: ' . $term);
    }
}

if (str_contains($script, 'module.label') || str_contains($manifestSchema, '"label"')) {
    throw new RuntimeException('O contrato ainda permite alias de navegação diferente de module.name.');
}

if (str_contains($html, 'nav-index') || str_contains($script, 'nav-index') || str_contains($style, '.nav-index')) {
    throw new RuntimeException('A navegação ainda contém numeração visual de itens.');
}

if (!preg_match('~assets/app\.css\?v=20260804-6~', $html)
    || !preg_match('~assets/app\.js\?v=20260804-6~', $html)) {
    throw new RuntimeException('Os assets públicos modulares não receberam a mesma versão.');
}

echo 'ModuleInterfaceRegressionTest: ' . (count($assertions) + count($forbiddenCoreTerms) + 3) . " verificações concluídas.\n";
