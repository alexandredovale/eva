<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$script = file_get_contents($root . '/public/assets/app.js');
$markup = file_get_contents($root . '/public/app.html');
$styles = file_get_contents($root . '/public/assets/app.css');

if (!is_string($script) || !is_string($markup) || !is_string($styles)) {
    throw new RuntimeException('Não foi possível ler os arquivos da referência documental interativa.');
}

$scriptAssertions = [
    'renderAnswerWithCitations(answer, evidences, index)' => 'A resposta deve passar pelo renderizador determinístico de citações.',
    '/\[(EVA-E\d{6,})\]/g' => 'Somente o marcador canônico de evidência deve ser reconhecido.',
    'evidenceById.has(evidenceId)' => 'A citação deve se tornar interativa somente quando pertencer às evidências da rodada.',
    'data-query-index=' => 'A referência deve permanecer vinculada à rodada que originou a resposta.',
    'formatEvidenceBreadcrumb(evidence)' => 'O pop-up deve reutilizar a mesma referência apresentada na lista final.',
    'elements.evidenceReferencePath.textContent' => 'A referência deve ser inserida como texto, sem interpretar HTML documental.',
    "event.target.closest('[data-evidence-citation]')" => 'O chat deve abrir o pop-up por delegação de eventos.',
    "event.key === 'Escape' && !elements.evidenceReferenceDialog.hidden" => 'O pop-up deve fechar pela tecla Escape.',
    'focusTarget.focus({ preventScroll: true })' => 'O foco deve retornar à citação que abriu o pop-up.',
    "querySelectorAll('[data-evidence-citation]')" => 'Uma atualização concorrente do transcript deve restaurar o foco no marcador equivalente.',
    'if (elements.evidenceReferenceDialog.hidden)' => 'A conclusão concorrente de uma consulta não deve retirar o foco do pop-up aberto.',
    'if (alignLatestAnswer && elements.evidenceReferenceDialog.hidden)' => 'A conclusão concorrente não deve rolar o transcript atrás do pop-up aberto.',
];

foreach ($scriptAssertions as $needle => $message) {
    if (!str_contains($script, $needle)) {
        throw new RuntimeException($message);
    }
}

$markupAssertions = [
    'id="evidence-reference-dialog"' => 'O pop-up de referência não foi declarado.',
    'role="dialog" aria-modal="true"' => 'O pop-up deve expor semântica modal acessível.',
    'aria-describedby="evidence-reference-path"' => 'A referência deve descrever o pop-up para tecnologias assistivas.',
];

foreach ($markupAssertions as $needle => $message) {
    if (!str_contains($markup, $needle)) {
        throw new RuntimeException($message);
    }
}

$styleAssertions = [
    '.evidence-citation-link' => 'A citação interativa deve possuir estilo próprio.',
    'text-decoration: underline' => 'A citação deve ser visualmente apresentada como link sublinhado.',
    '.evidence-reference-modal' => 'O pop-up deve acompanhar o visual do sistema.',
    'overflow-wrap: anywhere' => 'Referências longas devem permanecer integralmente visíveis.',
];

foreach ($styleAssertions as $needle => $message) {
    if (!str_contains($styles, $needle)) {
        throw new RuntimeException($message);
    }
}

echo 'EvidenceCitationPopupRegressionTest: '
    . (count($scriptAssertions) + count($markupAssertions) + count($styleAssertions))
    . " verificações concluídas.\n";
