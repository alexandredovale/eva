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
$actionSchema = file_get_contents($root . '/modules/runtime/contracts/module-action.schema.json');
$productApi = file_get_contents($root . '/app/Http/Product/ProductApi.php');
$moduleManager = file_get_contents($root . '/modules/runtime/src/ModuleManager.php');
$coreQueryApi = file_get_contents($root . '/modules/runtime/src/CoreQueryApi.php');

if (!is_string($html) || !is_string($script) || !is_string($style)
    || !is_string($manifest) || !is_string($presenter) || !is_string($moduleStyle)
    || !is_string($index) || !is_string($manifestSchema) || !is_string($actionSchema)
    || !is_string($productApi) || !is_string($moduleManager) || !is_string($coreQueryApi)) {
    throw new RuntimeException('Não foi possível ler a interface modular.');
}

$assertions = [
    [$html, 'id="module-navigation"', 'O host de navegação modular não foi renderizado.'],
    [$html, 'id="view-module" data-view-panel="module"', 'O host visual genérico dos módulos está ausente.'],
    [$script, "api('modules')", 'O frontend não descobre interfaces de módulos ativos.'],
    [$script, 'data-module-id', 'A navegação não utiliza o identificador dinâmico do manifesto.'],
    [$script, 'escapeHtml(module.name)', 'A navegação não respeita o nome canônico do módulo.'],
    [$script, '[data-module-content-filter]', 'O host não oferece filtragem declarativa.'],
    [$script, '[data-module-id-filter]', 'O host não oferece filtragem declarativa específica por ID.'],
    [$script, 'dataset.moduleEntryId', 'O host não compara o filtro de ID com o identificador isolado do card.'],
    [$script, '[data-module-filter-source]', 'O host não permite que módulos delimitem o conteúdo pesquisável.'],
    [$script, 'input[type="checkbox"], input[type="radio"]', 'O host não respeita a seleção de filtros modulares por rádio.'],
    [$script, '[data-module-accordion-toggle]', 'O host não oferece acordeão declarativo.'],
    [$script, '[data-module-copy-target]', 'O host não oferece cópia declarativa de conteúdo modular.'],
    [$script, '[data-module-download-target]', 'O host não oferece download declarativo de conteúdo modular.'],
    [$script, '[data-module-mode-panel]', 'O host não alterna painéis declarativos de um formulário modular.'],
    [$script, '[data-module-action-progress]', 'O host não exibe progresso declarativo durante ações modulares.'],
    [$script, 'syncModuleCharacterCounters()', 'O host não atualiza contadores declarativos de caracteres.'],
    [$script, "dashboard?.contract !== 'eva.module.dashboard/1'", 'O frontend não valida o contrato visual modular.'],
    [$script, "style.setAttribute('nonce', cspStyleNonce)", 'O CSS modular não recebe autorização da CSP.'],
    [$html, 'name="csp-style-nonce"', 'A página não transporta o nonce de estilo.'],
    [$index, "'nonce-{\$styleNonce}'", 'A CSP não autoriza estilos modulares por nonce.'],
    [$manifest, '"id": "com.eva.education"', 'O módulo ainda utiliza um identificador proprietário.'],
    [$manifest, '"name": "Education"', 'O nome canônico do módulo não foi aplicado.'],
    [$manifestSchema, '"order": {"type": "integer"', 'O contrato perdeu a ordenação genérica das interfaces.'],
    [$actionSchema, '"const": "eva.module.action/1"', 'O contrato genérico de ações modulares está ausente.'],
    [$script, '[data-module-action-form]', 'O host não reconhece formulários declarativos de módulos.'],
    [$script, '[data-module-confirm-action]', 'O host não reconhece confirmações declarativas de módulos.'],
    [$script, 'executeModuleAction(', 'O host não executa ações modulares genéricas.'],
    [$script, 'const actionInput = serializeModuleActionInput(form, submitter);', 'O host não captura o formulário antes de bloquear os controles.'],
    [$script, 'input: actionInput,', 'O payload modular não utiliza o formulário capturado antes do bloqueio.'],
    [$productApi, "/actions/([a-z][a-z0-9_.-]", 'A API não expõe o conector genérico de ações.'],
    [$moduleManager, 'instanceof ModuleAccessInterface', 'O Runtime não aplica autorização modular opcional.'],
    [$moduleManager, 'instanceof ModuleActionInterface', 'O Runtime não valida módulos interativos.'],
    [$coreQueryApi, 'QueryContext::MAX_SUPPLEMENTARY_INSTRUCTION_LENGTH', 'O Runtime não compartilha o limite de instruções com o contexto de consulta.'],
    [$coreQueryApi, "['query']['non_semantic_max_evidence']", 'O Runtime modular ainda lê a antiga configuração geral de evidências.'],
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

$connectorCore = strtolower($script . $productApi . $moduleManager . $coreQueryApi);
$forbiddenConnectorTerms = [
    strtolower('Ena' . 'de'),
    strtolower('Profes' . 'sor'),
    'create_' . 'item',
    'review_' . 'item',
];

foreach ($forbiddenConnectorTerms as $term) {
    if (str_contains($connectorCore, $term)) {
        throw new RuntimeException('O conector contém conhecimento específico de um módulo: ' . $term);
    }
}

if (str_contains($script, 'module.label') || str_contains($manifestSchema, '"label"')) {
    throw new RuntimeException('O contrato ainda permite alias de navegação diferente de module.name.');
}

if (str_contains($coreQueryApi, "['query']['max_evidence']")) {
    throw new RuntimeException('O Runtime modular ainda contém a chave removida query.max_evidence.');
}

if (str_contains($html, 'nav-index') || str_contains($script, 'nav-index') || str_contains($style, '.nav-index')) {
    throw new RuntimeException('A navegação ainda contém numeração visual de itens.');
}

if (!preg_match('~assets/app\.css\?v=20260808-2~', $html)
    || !preg_match('~assets/app\.js\?v=20260808-2~', $html)) {
    throw new RuntimeException('Os assets públicos modulares não receberam a mesma versão.');
}

echo 'ModuleInterfaceRegressionTest: ' . (count($assertions) + count($forbiddenCoreTerms) + count($forbiddenConnectorTerms) + 3) . " verificações concluídas.\n";
