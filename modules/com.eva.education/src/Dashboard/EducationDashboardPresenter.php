<?php

declare(strict_types=1);

namespace EvaModule\Education\Dashboard;

final class EducationDashboardPresenter
{
    /**
     * @param array{user_id: int|null, timeline: list<array<string, mixed>>} $dashboard
     * @param array{user_id: int|null, role: string} $actor
     * @param list<array<string, mixed>> $users
     * @return array{contract: string, html: string, css: string}
     */
    public function present(array $dashboard, array $actor, array $users): array
    {
        $timeline = is_array($dashboard['timeline'] ?? null) ? $dashboard['timeline'] : [];
        $selectedUserId = (int) ($dashboard['user_id'] ?? 0);
        $adminControl = '';

        if (($actor['role'] ?? '') === 'superadmin') {
            $options = '<option value="">Selecione um usuário</option>';

            foreach ($users as $user) {
                if (!(bool) ($user['active'] ?? false)) {
                    continue;
                }

                $id = (int) ($user['id'] ?? 0);
                $selected = $id === $selectedUserId ? ' selected' : '';
                $options .= '<option value="' . $id . '"' . $selected . '>' . $this->escape($user['username'] ?? '') . '</option>';
            }

            $adminControl = '<div class="form-field"><label for="education-dashboard-user">Usuário</label>'
                . '<select id="education-dashboard-user" data-module-filter="user_id">' . $options . '</select></div>';
        }

        $entries = implode('', array_map(
            fn (array $entry, int $index): string => $this->entry($entry, $index),
            $timeline,
            array_keys($timeline)
        ));
        $emptyMessage = $selectedUserId < 1 && ($actor['role'] ?? '') === 'superadmin'
            ? 'Selecione um usuário para consultar o trajeto.'
            : 'Nenhuma interação observada para este usuário.';
        $timelineMarkup = $entries !== ''
            ? '<div class="learning-timeline" data-module-entry-list aria-live="polite">' . $entries . '</div>'
            : '<div class="card empty">' . $this->escape($emptyMessage) . '</div>';
        $disabled = $entries === '' ? ' disabled' : '';
        $count = count($timeline);
        $html = '<div class="education-dashboard" data-dashboard-user-id="' . $selectedUserId . '">'
            . '<div class="page-heading"><div><p class="eyebrow">Governança pedagógica</p><h1>Trajeto<span>.</span></h1>'
            . '<p>Observações descritivas rastreáveis, sem notas, pesos, percentuais ou rankings.</p></div></div>'
            . '<div class="card learning-toolbar">' . $adminControl
            . '<button type="button" class="button button-quiet" data-module-refresh>Atualizar trajeto</button></div>'
            . '<div class="card learning-filter"><div class="form-field"><label for="education-dashboard-filter">Filtrar no trajeto</label>'
            . '<input id="education-dashboard-filter" type="search" autocomplete="off" data-module-content-filter '
            . 'placeholder="Pergunta, resposta, observação, documento ou conceito"' . $disabled . '></div>'
            . '<span class="learning-filter-count" data-module-filter-count data-module-item-singular="interação" data-module-item-plural="interações" aria-live="polite">' . $count . ' '
            . ($count === 1 ? 'interação' : 'interações') . '</span></div>'
            . $timelineMarkup
            . '<div class="card empty" data-module-filter-empty hidden>Nenhuma interação corresponde ao filtro informado.</div>'
            . '</div>';

        return ['contract' => 'eva.module.dashboard/1', 'html' => $html, 'css' => $this->styles()];
    }

    /** @param array<string, mixed> $entry */
    private function entry(array $entry, int $index): string
    {
        $interpretation = is_array($entry['interpretation'] ?? null) ? $entry['interpretation'] : [];
        $labels = is_array($interpretation['labels'] ?? null) ? $interpretation['labels'] : [];
        $observations = is_array($interpretation['observations'] ?? null) ? $interpretation['observations'] : [];
        $observationMarkup = '';

        if ($observations !== []) {
            $items = '';
            foreach ($observations as $observation) {
                if (!is_array($observation)) {
                    continue;
                }
                $dimension = $this->humanize($observation['dimension_label'] ?? $observation['dimension'] ?? '', '', true);
                $canonicalState = trim((string) ($observation['state'] ?? ''));
                $state = $this->humanize($observation['state_label'] ?? $observation['state'] ?? '', '');
                $heading = $dimension;
                if ($canonicalState !== '' && $canonicalState !== 'observed') {
                    $heading .= ' · ' . $state;
                }
                $evidences = is_array($observation['evidence_refs'] ?? null) ? $observation['evidence_refs'] : [];
                $evidenceMarkup = $evidences !== []
                    ? '<small>' . $this->escape($this->humanize($labels['evidences'] ?? null, 'Evidências', true)) . ': '
                        . $this->escape(implode(', ', array_map('strval', $evidences))) . '</small>'
                    : '';
                $items .= '<li><strong>' . $this->escape($heading) . '</strong><span>'
                    . $this->escape($observation['description'] ?? '') . '</span>' . $evidenceMarkup . '</li>';
            }
            $observationMarkup = '<ul class="learning-observations">' . $items . '</ul>';
        } else {
            $observationMarkup = '<p class="learning-context">'
                . $this->escape($this->humanize($labels['interpretation_pending'] ?? null, 'Interpretação ainda não disponível.')) . '</p>';
        }

        $projects = $this->names($entry['projects'] ?? [], ['name', 'id']);
        $documents = $this->names($entry['documents'] ?? [], ['title', 'public_id', 'id']);
        $directReferences = is_array($entry['direct_references'] ?? null)
            ? implode(', ', array_map('strval', $entry['direct_references']))
            : '';
        $concepts = [];
        $linguistic = is_array($interpretation['linguistic_analysis'] ?? null) ? $interpretation['linguistic_analysis'] : [];
        foreach (is_array($linguistic['concepts'] ?? null) ? $linguistic['concepts'] : [] as $concept) {
            if (!is_array($concept)) continue;
            $value = trim((string) ($concept['canonical'] ?? $concept['term'] ?? ''));
            if ($value !== '') $concepts[$value] = $value;
        }

        $none = $this->humanize($labels['none'] ?? null, 'nenhum');
        $context = [];
        if (!($projects === '' && $documents !== '')) {
            $context[] = $this->humanize($labels['scope'] ?? null, 'Escopo', true) . ': ' . ($projects !== '' ? $projects : $none);
        }
        $documentLabel = count(is_array($entry['documents'] ?? null) ? $entry['documents'] : []) === 1
            ? $this->humanize($labels['document'] ?? null, 'Documento', true)
            : $this->humanize($labels['documents'] ?? null, 'Documentos', true);
        $context[] = $documentLabel . ': ' . ($documents !== '' ? $documents : $none);
        $context[] = $this->humanize($labels['direct_references'] ?? null, 'Referências Diretas', true) . ': '
            . ($directReferences !== '' ? $directReferences : $this->humanize($labels['no_direct_references'] ?? null, 'Sem referências diretas'));
        $context[] = $this->humanize($labels['concepts'] ?? null, 'Conceitos', true) . ': '
            . ($concepts !== [] ? implode(', ', $concepts) : $none);

        $status = (string) ($entry['processing_status'] ?? 'pending');
        $tone = $status === 'completed' ? 'completed' : ($status === 'failed' ? 'failed' : 'queued');
        $bodyId = 'education-entry-body-' . $index;

        return '<article class="card learning-entry" data-module-entry><header class="learning-entry-header"><h2 class="learning-entry-heading">'
            . '<button type="button" class="learning-entry-toggle" data-module-accordion-toggle aria-expanded="false" aria-controls="' . $bodyId . '">'
            . '<span class="learning-entry-copy"><span class="eyebrow">' . $this->escape($this->dateTime($entry['occurred_at'] ?? '')) . '</span>'
            . '<span class="learning-entry-question">' . $this->escape($entry['current_input'] ?? '') . '</span></span>'
            . '<span class="learning-entry-controls"><span class="status status-' . $tone . '">'
            . $this->escape($this->humanize($labels[$status] ?? null, $status)) . '</span><span class="learning-entry-chevron" aria-hidden="true"></span></span>'
            . '</button></h2></header><div id="' . $bodyId . '" class="learning-entry-body" hidden><p class="learning-entry-answer">'
            . $this->escape($entry['answer'] ?? '') . '</p>' . $observationMarkup . '<p class="learning-context">'
            . $this->escape(implode(' · ', $context)) . '</p></div></article>';
    }

    /** @param mixed $items @param list<string> $keys */
    private function names(mixed $items, array $keys): string
    {
        $names = [];
        foreach (is_array($items) ? $items : [] as $item) {
            if (!is_array($item)) continue;
            foreach ($keys as $key) {
                if (isset($item[$key]) && trim((string) $item[$key]) !== '') {
                    $names[] = trim((string) $item[$key]);
                    break;
                }
            }
        }
        return implode(', ', $names);
    }

    private function humanize(mixed $value, string $fallback, bool $titleCase = false): string
    {
        $normalized = trim((string) ($value ?: $fallback));
        $normalized = preg_replace('/_+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        if ($normalized === '') return '';
        return $titleCase
            ? mb_convert_case($normalized, MB_CASE_TITLE, 'UTF-8')
            : mb_strtoupper(mb_substr($normalized, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($normalized, 1, null, 'UTF-8');
    }

    private function dateTime(mixed $value): string
    {
        $original = trim((string) $value);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2}):(\d{2})/', $original, $parts) !== 1) return $original;
        return $parts[3] . '-' . $parts[2] . '-' . $parts[1] . ' ' . $parts[4] . ':' . $parts[5] . ':' . $parts[6];
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function styles(): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/assets/dashboard.css');
        return is_string($contents) ? $contents : '';
    }
}
