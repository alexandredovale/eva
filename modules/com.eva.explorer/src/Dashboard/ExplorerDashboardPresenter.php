<?php

declare(strict_types=1);

namespace EvaModule\Explorer\Dashboard;

final class ExplorerDashboardPresenter
{
    private const LABELS = ['quiz' => 'Quizz', 'nodes' => 'Nodes', 'prova' => 'Prova'];

    /**
     * @param list<array<string, mixed>> $profiles
     * @param list<array<string, mixed>> $users
     * @param array<int, int> $assignments
     * @return array{contract: string, html: string, css: string}
     */
    public function admin(array $profiles, array $users, array $assignments): array
    {
        $profileCards = '';

        foreach ($profiles as $profile) {
            $profileCards .= '<article class="explorer-profile"><div><h3>' . $this->escape($profile['name'])
                . '</h3><span class="status status-ready">habilitado</span></div><p>'
                . $this->escape($profile['description']) . '</p></article>';
        }

        $rows = '';

        foreach ($users as $user) {
            $userId = (int) ($user['id'] ?? 0);
            $assigned = $assignments[$userId] ?? null;
            $choices = '<label><input type="radio" name="profile_id" value=""'
                . ($assigned === null ? ' checked' : '') . '> Sem acesso</label>';

            foreach ($profiles as $profile) {
                $profileId = (int) $profile['id'];
                $choices .= '<label><input type="radio" name="profile_id" value="' . $profileId . '"'
                    . ($assigned === $profileId ? ' checked' : '') . '> '
                    . $this->escape($profile['name']) . '</label>';
            }

            $rows .= '<tr><td><strong>' . $this->escape($user['username'] ?? '') . '</strong><br><small>'
                . ((bool) ($user['active'] ?? false) ? 'usuário ativo' : 'usuário inativo') . '</small></td><td>'
                . '<form class="explorer-assignment" data-module-action-form="assign_profile">'
                . '<input type="hidden" name="user_id" value="' . $userId . '"><fieldset><legend class="sr-only">Perfil de '
                . $this->escape($user['username'] ?? '') . '</legend>' . $choices . '</fieldset>'
                . '<button class="button button-quiet" type="submit" data-module-action="assign_profile">Aplicar</button></form></td></tr>';
        }

        $html = '<div class="explorer-dashboard explorer-admin"><div class="page-heading"><div>'
            . '<p class="eyebrow">Configuração modular</p><h1>EXPLORER<span>.</span></h1>'
            . '<p>Perfis e vínculos são exclusivos deste módulo e permanecem no seu SQLite privado.</p></div>'
            . '<span class="status status-ready">3 perfis</span></div>'
            . '<section class="card"><div class="explorer-section-heading"><div><p class="eyebrow">Papéis operacionais</p>'
            . '<h2>Perfis do módulo</h2></div><button type="button" class="button button-quiet" data-module-refresh>Atualizar</button></div>'
            . '<p class="explorer-muted">Cada papel abre um dashboard exclusivo e cada usuário pode ocupar apenas um deles.</p>'
            . '<div class="explorer-profile-grid">' . $profileCards . '</div></section>'
            . '<section class="card"><p class="eyebrow">Acesso ao ambiente</p><h2>Usuários e perfis</h2>'
            . '<div class="table-wrap"><table><thead><tr><th>Usuário do Core</th><th>Perfil exclusivo do EXPLORER</th></tr></thead><tbody>'
            . ($rows !== '' ? $rows : '<tr><td colspan="2" class="empty">Nenhum usuário cadastrado no Core.</td></tr>')
            . '</tbody></table></div></section></div>';

        return $this->dashboard($html);
    }

    /**
     * @param array<string, mixed> $profile
     * @param list<array<string, mixed>> $scopes
     * @param list<array<string, mixed>> $themes
     * @param list<array<string, mixed>> $interactions
     * @param list<array<string, mixed>> $users
     * @param list<int> $studentIds
     * @return array{contract: string, html: string, css: string}
     */
    public function professor(
        array $profile,
        array $scopes,
        array $themes,
        array $interactions,
        array $users,
        array $studentIds
    ): array {
        $scopeOptions = '<option value="">Selecione o projeto e o documento base</option>';

        foreach ($scopes as $scope) {
            $scopeOptions .= '<option value="' . $this->escape($scope['key']) . '">'
                . $this->escape($scope['project_name']) . ' · ' . $this->escape($scope['document_title']) . '</option>';
        }

        $form = '<form class="card explorer-theme-form" data-module-action-form="create_theme">'
            . '<div class="explorer-section-heading"><div><p class="eyebrow">Tema de Aprendizado (T.A.)</p><h2>Publique uma nova interação</h2></div>'
            . '<span class="explorer-profile-badge">' . $this->escape($profile['name']) . '</span></div>'
            . '<div class="form-field"><label for="explorer-scope">Projeto e documento base</label>'
            . '<select id="explorer-scope" name="scope_key" required>' . $scopeOptions . '</select>'
            . '<small class="explorer-field-help">A geração e a análise usarão apenas este documento e o acesso autorizado no Core.</small></div>'
            . '<div class="form-field"><label for="explorer-title">Título do TA</label>'
            . '<input id="explorer-title" name="title" maxlength="180" required placeholder="Ex.: Relações entre linguagem e aprendizagem"></div>'
            . '<div class="form-field"><label for="explorer-description">Objetivo de aprendizagem</label>'
            . '<textarea id="explorer-description" name="description" rows="5" maxlength="4000" required '
            . 'data-module-character-limit placeholder="Descreva o recorte, o objetivo e o que o aluno deve compreender."></textarea>'
            . '<small class="explorer-character" data-module-character-counter="explorer-description" aria-live="polite">0 / 4.000 caracteres</small></div>'
            . '<div class="explorer-form-footer"><button class="button button-primary" type="submit" data-module-action="create_theme"'
            . ($scopes === [] ? ' disabled' : '') . '>Publicar TA</button></div></form>';
        $usersById = $this->usersById($users);
        $byTheme = $this->interactionsByTheme($interactions);
        $cards = '';

        foreach ($themes as $theme) {
            $themeId = (int) $theme['id'];
            $themeInteractions = $byTheme[$themeId] ?? [];
            $completed = count($themeInteractions);
            $possible = count($studentIds) * 3;
            $progress = $possible > 0 ? (int) round(($completed / $possible) * 100) : 0;
            $studentRows = '';

            foreach ($studentIds as $studentId) {
                $studentEntries = array_values(array_filter(
                    $themeInteractions,
                    static fn (array $entry): bool => (int) $entry['student_user_id'] === $studentId
                ));
                $entriesByType = [];

                foreach ($studentEntries as $entry) {
                    $entriesByType[$entry['interaction_type']] = $entry;
                }

                $steps = '';

                foreach (self::LABELS as $type => $label) {
                    $entry = $entriesByType[$type] ?? null;
                    $steps .= '<span class="explorer-mini-step' . ($entry !== null ? ' is-complete' : '') . '">'
                        . $this->escape($label) . '</span>';
                }

                $analyses = '';

                foreach ($studentEntries as $entry) {
                    $analyses .= '<details><summary>' . $this->escape(self::LABELS[$entry['interaction_type']] ?? $entry['interaction_type'])
                        . ' · ' . ($entry['outcome'] === 'correct' ? 'Resposta correta' : 'Requer aprofundamento') . '</summary>'
                        . '<p><strong>Resposta:</strong> ' . $this->escape($this->responseLabel($entry)) . '</p>'
                        . '<div class="explorer-analysis-copy">' . $this->analysisParagraphs((string) $entry['analysis_text']) . '</div>'
                        . $this->evidenceLocations($entry['evidences'] ?? [], (string) $entry['analysis_text']) . '</details>';
                }

                $studentRows .= '<article class="explorer-student-row"><div><strong>'
                    . $this->escape($usersById[$studentId]['username'] ?? ('Aluno #' . $studentId)) . '</strong><div class="explorer-mini-steps">'
                    . $steps . '</div></div><div class="explorer-student-analyses">'
                    . ($analyses !== '' ? $analyses : '<small>Nenhuma etapa concluída.</small>') . '</div></article>';
            }

            $active = (bool) $theme['active'];
            $cards .= '<article class="card explorer-professor-theme" data-module-entry data-module-entry-id="' . $themeId . '">'
                . '<header><div><p class="eyebrow">TA #' . $themeId . '</p><small class="eyebrow-date">' . $this->escape($this->date($theme['created_at'])) . '</small>'
                . '<h3 data-module-filter-source>' . $this->escape($theme['title']) . '</h3></div><span class="status '
                . ($active ? 'status-ready' : 'status-queued') . '">' . ($active ? 'publicado' : 'pausado') . '</span></header>'
                . '<p>' . nl2br($this->escape($theme['description'])) . '</p>'
                . '<div class="explorer-theme-meta"><span>' . $this->escape($theme['project_name']) . '</span><span>'
                . $this->escape($theme['document_title']) . '</span></div>'
                . '<div class="explorer-progress"><div><span>Progresso dos alunos</span><strong>' . $completed . ' de ' . $possible
                . ' etapas</strong></div><progress value="' . $progress . '" max="100">' . $progress . '%</progress><small>' . $progress . '% concluído</small></div>'
                . '<div class="explorer-student-list">'
                . ($studentRows !== '' ? $studentRows : '<div class="empty">Nenhum usuário está vinculado ao perfil Aluno.</div>') . '</div>'
                . '<form class="explorer-theme-toggle" data-module-action-form="set_theme_active">'
                . '<input type="hidden" name="theme_id" value="' . $themeId . '"><input type="hidden" name="active" value="' . ($active ? '0' : '1') . '">'
                . '<button class="button button-quiet" type="submit" data-module-action="set_theme_active">'
                . ($active ? 'Pausar publicação' : 'Reativar publicação') . '</button></form></article>';
        }

        $html = '<div class="explorer-dashboard explorer-professor"><div class="page-heading"><div>'
            . '<p class="eyebrow">Exploração pedagógica</p><h1>EXPLORER<span>.</span></h1>'
            . '<p>Publique Temas de Aprendizado e acompanhe as relações cognitivas de cada aluno.</p></div>'
            . '<span class="status status-ready">Professor</span></div>' . $form
            . '<section><div class="explorer-section-heading"><div><p class="eyebrow">Acompanhamento</p><h2>Temas publicados</h2></div>'
            . '<button type="button" class="button button-quiet" data-module-refresh>Atualizar</button></div>'
            . '<div class="card explorer-search"><label for="explorer-theme-search">Filtrar por tema</label>'
            . '<input id="explorer-theme-search" type="search" data-module-content-filter placeholder="Digite parte do título"></div>'
            . '<div class="explorer-professor-list" data-module-entry-list>'
            . ($cards !== '' ? $cards : '<div class="card empty">Nenhum Tema de Aprendizado publicado.</div>')
            . '</div><div class="card empty" data-module-filter-empty hidden>Nenhum tema corresponde ao filtro.</div></section></div>';

        return $this->dashboard($html);
    }

    /**
     * @param array<string, mixed> $profile
     * @param list<array<string, mixed>> $themes
     * @param list<array<string, mixed>> $interactions
     * @param array<string, mixed>|null $openTheme
     * @param array<string, mixed>|null $openItem
     * @return array{contract: string, html: string, css: string}
     */
    public function student(
        array $profile,
        array $themes,
        array $interactions,
        ?array $openTheme = null,
        ?array $openItem = null
    ): array {
        $interactionMap = [];

        foreach ($interactions as $interaction) {
            $interactionMap[(int) $interaction['theme_id']][(string) $interaction['interaction_type']] = $interaction;
        }

        $cards = '';
        $completedTotal = count($interactions);

        foreach ($themes as $theme) {
            $themeId = (int) $theme['id'];
            $completedByType = $interactionMap[$themeId] ?? [];
            $results = '';

            foreach (self::LABELS as $type => $label) {
                $entry = $completedByType[$type] ?? null;

                if ($entry === null) {
                    continue;
                }

                $results .= '<details class="explorer-result"><summary><span>' . $this->escape($label) . '</span><strong>'
                    . ($entry['outcome'] === 'correct' ? 'Resposta correta' : 'Aprofundamento recomendado') . '</strong>'
                    . '<i aria-hidden="true"></i></summary><div class="explorer-result-content">'
                    . '<div class="explorer-analysis-copy">' . $this->analysisParagraphs((string) $entry['analysis_text']) . '</div>'
                    . $this->evidenceLocations($entry['evidences'] ?? [], (string) $entry['analysis_text']) . '</div></details>';
            }

            $steps = '';

            foreach (self::LABELS as $type => $label) {
                $complete = isset($completedByType[$type]);
                $waitingLabel = ($complete ? 'Abrindo ' : 'Gerando ') . $label;
                $steps .= '<form data-module-action-form="prepare_interaction"><input type="hidden" name="theme_id" value="'
                    . $themeId . '"><input type="hidden" name="interaction_type" value="' . $type . '">'
                    . '<button class="explorer-step' . ($complete ? ' is-complete' : '') . '" type="submit" '
                    . 'data-module-action="prepare_interaction" aria-label="' . $this->escape(($complete ? 'Rever ' : 'Iniciar ') . $label) . '">'
                    . '<span aria-hidden="true"></span><small aria-live="polite"><span class="explorer-step-label">' . $this->escape($label)
                    . '</span><span class="explorer-step-waiting">' . $this->escape($waitingLabel) . '</span></small></button></form>';
            }

            $cards .= '<article class="explorer-learning-card" data-module-entry data-module-entry-id="' . $themeId . '">'
                . '<header><span>Professor</span><strong>' . $this->escape($theme['professor_name']) . '</strong></header>'
                . '<div class="explorer-card-body"><p class="eyebrow">TA #' . $themeId . '</p>'
                . '<h2 data-module-filter-source>' . $this->escape($theme['title']) . '</h2><p>'
                . nl2br($this->escape($theme['description'])) . '</p>'
                . ($results !== '' ? '<div class="explorer-results">' . $results . '</div>' : '') . '</div>'
                . '<footer><span>Relações cognitivas</span><div class="explorer-steps">' . $steps . '</div></footer></article>';
        }

        $possible = count($themes) * 3;
        $progress = $possible > 0 ? (int) round(($completedTotal / $possible) * 100) : 0;
        $dialog = $openTheme !== null && $openItem !== null ? $this->interactionDialog($openTheme, $openItem) : '';
        $html = '<div class="explorer-dashboard explorer-student"><div class="page-heading"><div>'
            . '<p class="eyebrow">Percurso de aprendizagem</p><h1>EXPLORER<span>.</span></h1>'
            . '<p>Explore cada tema por meio de Quizz, Nodes e Prova. Seu percurso é individual.</p></div>'
            . '<span class="status status-ready">' . $this->escape($profile['name']) . '</span></div>'
            . '<section class="card explorer-student-summary"><div><span>Etapas concluídas</span><strong>'
            . $completedTotal . ' de ' . $possible . '</strong></div><progress value="' . $progress . '" max="100">'
            . $progress . '%</progress><small>' . $progress . '% do percurso disponível</small></section>'
            . '<div class="card explorer-search"><label for="explorer-card-search">Filtrar por tema</label>'
            . '<input id="explorer-card-search" type="search" data-module-content-filter placeholder="Digite um tema ou professor"></div>'
            . '<section class="explorer-card-grid" data-module-entry-list>'
            . ($cards !== '' ? $cards : '<div class="card empty">Nenhum Tema de Aprendizado está disponível.</div>')
            . '</section><div class="card empty" data-module-filter-empty hidden>Nenhum card corresponde ao filtro.</div>'
            . $dialog . '</div>';

        return $this->dashboard($html);
    }

    /**
     * @param array<string, mixed> $profile
     * @param list<array<string, mixed>> $themes
     * @param list<array<string, mixed>> $interactions
     * @param list<array<string, mixed>> $users
     * @param list<int> $professorIds
     * @param list<int> $studentIds
     * @param array{period: string, professor_id: ?int} $filters
     * @return array{contract: string, html: string, css: string}
     */
    public function secretaria(
        array $profile,
        array $themes,
        array $interactions,
        array $users,
        array $professorIds,
        array $studentIds,
        array $filters
    ): array {
        $usersById = $this->usersById($users);
        $professorOptions = '<option value="">Todos os professores</option>';

        foreach ($professorIds as $professorId) {
            $professorOptions .= '<option value="' . $professorId . '"'
                . ($filters['professor_id'] === $professorId ? ' selected' : '') . '>'
                . $this->escape($usersById[$professorId]['username'] ?? ('Professor #' . $professorId)) . '</option>';
        }

        $byTheme = $this->interactionsByTheme($interactions);
        $participatingStudents = [];
        $correct = 0;
        $deepen = 0;
        $types = ['quiz' => 0, 'nodes' => 0, 'prova' => 0];
        $byProfessor = [];

        foreach ($interactions as $entry) {
            $participatingStudents[(int) $entry['student_user_id']] = true;
            $correct += $entry['outcome'] === 'correct' ? 1 : 0;
            $deepen += $entry['outcome'] === 'deepen' ? 1 : 0;
            $types[$entry['interaction_type']]++;
        }

        foreach ($themes as $theme) {
            $professorId = (int) $theme['professor_user_id'];
            $byProfessor[$professorId] ??= ['themes' => 0, 'steps' => 0];
            $byProfessor[$professorId]['themes']++;
            $byProfessor[$professorId]['steps'] += count($byTheme[(int) $theme['id']] ?? []);
        }

        $possible = count($themes) * count($studentIds) * 3;
        $progress = $possible > 0 ? (int) round((count($interactions) / $possible) * 100) : 0;
        $metrics = [
            ['Temas publicados', count($themes), 'no recorte selecionado'],
            ['Alunos participantes', count($participatingStudents), count($studentIds) . ' alunos vinculados'],
            ['Etapas concluídas', count($interactions), $possible . ' etapas disponíveis'],
            ['Evolução geral', $progress . '%', $correct . ' corretas · ' . $deepen . ' para aprofundar'],
        ];
        $metricCards = '';

        foreach ($metrics as [$label, $value, $detail]) {
            $metricCards .= '<article class="card explorer-metric"><span>' . $this->escape($label) . '</span><strong>'
                . $this->escape($value) . '</strong><small>' . $this->escape($detail) . '</small></article>';
        }

        $themeRows = '';

        foreach ($themes as $theme) {
            $entries = $byTheme[(int) $theme['id']] ?? [];
            $counts = ['quiz' => 0, 'nodes' => 0, 'prova' => 0];
            $students = [];

            foreach ($entries as $entry) {
                $counts[$entry['interaction_type']]++;
                $students[(int) $entry['student_user_id']] = true;
            }

            $themePossible = count($studentIds) * 3;
            $themeProgress = $themePossible > 0 ? (int) round((count($entries) / $themePossible) * 100) : 0;
            $themeRows .= '<tr><td><strong>' . $this->escape($theme['title']) . '</strong><br><small>'
                . $this->escape($theme['professor_name']) . '</small></td><td>' . count($students) . '</td><td>'
                . $counts['quiz'] . '</td><td>' . $counts['nodes'] . '</td><td>' . $counts['prova'] . '</td><td><strong>'
                . $themeProgress . '%</strong></td></tr>';
        }

        $professorRows = '';

        foreach ($byProfessor as $professorId => $summary) {
            $professorRows .= '<tr><td><strong>' . $this->escape($usersById[$professorId]['username'] ?? ('Professor #' . $professorId))
                . '</strong></td><td>' . $summary['themes'] . '</td><td>' . $summary['steps'] . '</td></tr>';
        }

        $maxType = max(1, ...array_values($types));
        $bars = '';

        foreach ($types as $type => $count) {
            $bars .= '<li><div><span>' . $this->escape(self::LABELS[$type]) . '</span><strong>' . $count
                . '</strong></div><progress value="' . $count . '" max="' . $maxType . '">' . $count . '</progress></li>';
        }

        $periodOptions = ['7' => 'Últimos 7 dias', '30' => 'Últimos 30 dias', '90' => 'Últimos 90 dias', 'all' => 'Todo o histórico'];
        $html = '<div class="explorer-dashboard explorer-secretaria"><div class="page-heading"><div>'
            . '<p class="eyebrow">Acompanhamento institucional</p><h1>EXPLORER<span>.</span></h1>'
            . '<p>Visão estatística da evolução dos alunos e dos Temas de Aprendizado.</p></div>'
            . '<span class="status status-ready">' . $this->escape($profile['name']) . ' · somente leitura</span></div>'
            . '<section class="card explorer-filters"><div><p class="eyebrow">Recorte analítico</p><h2>Filtros operacionais</h2></div>'
            . '<div class="form-field"><label for="explorer-period">Período</label><select id="explorer-period" name="period" data-module-filter>'
            . $this->options($periodOptions, $filters['period']) . '</select></div>'
            . '<div class="form-field"><label for="explorer-professor">Professor</label><select id="explorer-professor" name="professor_id" data-module-filter>'
            . $professorOptions . '</select></div></section>'
            . '<section class="explorer-metrics">' . $metricCards . '</section>'
            . '<section class="explorer-analytics-grid"><article class="card"><p class="eyebrow">Relações cognitivas</p><h2>Etapas por tipo</h2>'
            . '<ul class="explorer-bars">' . $bars . '</ul></article><article class="card"><p class="eyebrow">Produção pedagógica</p><h2>Temas por professor</h2>'
            . '<div class="table-wrap"><table><thead><tr><th>Professor</th><th>Temas</th><th>Etapas</th></tr></thead><tbody>'
            . ($professorRows !== '' ? $professorRows : '<tr><td colspan="3" class="empty">Sem atividade no período.</td></tr>')
            . '</tbody></table></div></article></section>'
            . '<section class="card"><div class="explorer-section-heading"><div><p class="eyebrow">Evolução por card</p><h2>Temas de Aprendizado</h2></div>'
            . '<button type="button" class="button button-quiet" data-module-refresh>Atualizar</button></div>'
            . '<div class="table-wrap"><table><thead><tr><th>Tema</th><th>Alunos</th><th>Quizz</th><th>Nodes</th><th>Prova</th><th>Evolução</th></tr></thead><tbody>'
            . ($themeRows !== '' ? $themeRows : '<tr><td colspan="6" class="empty">Nenhum tema no recorte selecionado.</td></tr>')
            . '</tbody></table></div></section></div>';

        return $this->dashboard($html);
    }

    /** @param array<string, mixed> $theme @param array<string, mixed> $item */
    private function interactionDialog(array $theme, array $item): string
    {
        $type = (string) $item['interaction_type'];
        $label = self::LABELS[$type] ?? $type;
        $completed = is_string($item['analysis_text'] ?? null) && trim($item['analysis_text']) !== '';
        $body = '<p class="explorer-dialog-prompt">' . nl2br($this->escape($item['prompt_text'])) . '</p>';

        if ($completed) {
            $body .= '<div class="explorer-dialog-response"><span>Sua resposta</span><p>'
                . $this->escape($this->responseLabel($item)) . '</p></div>'
                . '<div class="explorer-dialog-analysis ' . ($item['outcome'] === 'correct' ? 'is-correct' : 'is-deepen') . '">'
                . '<span>' . ($item['outcome'] === 'correct' ? 'Resposta correta' : 'Necessita maior aprofundamento') . '</span>'
                . '<div class="explorer-analysis-copy">' . $this->analysisParagraphs((string) $item['analysis_text']) . '</div>'
                . $this->evidenceLocations($item['evidences'] ?? [], (string) $item['analysis_text']) . '</div>';
        } else {
            $responseField = '';

            if ($type === 'prova') {
                $responseField = '<fieldset class="explorer-options"><legend>Escolha uma alternativa</legend>';

                foreach ($item['options'] as $key => $option) {
                    $responseField .= '<label><input type="radio" name="response_text" value="' . $this->escape($key) . '" required>'
                        . '<span><strong>' . $this->escape($key) . '</strong>' . $this->escape($option) . '</span></label>';
                }

                $responseField .= '</fieldset>';
            } else {
                $responseField = '<div class="form-field"><label for="explorer-response">Sua resposta</label>'
                    . '<textarea id="explorer-response" name="response_text" rows="7" maxlength="5000" required '
                    . 'data-module-character-limit placeholder="Construa sua resposta com suas próprias palavras."></textarea>'
                    . '<small class="explorer-character" data-module-character-counter="explorer-response" aria-live="polite">0 / 5.000 caracteres</small></div>';
            }

            $body .= '<form class="explorer-response-form" data-module-action-form="submit_interaction">'
                . '<input type="hidden" name="theme_id" value="' . (int) $theme['id'] . '">'
                . '<input type="hidden" name="interaction_type" value="' . $this->escape($type) . '">'
                . $responseField . '<div class="explorer-dialog-actions"><button class="button button-primary" type="submit" '
                . 'data-module-action="submit_interaction">Enviar resposta</button></div>'
                . '<div class="module-action-progress" data-module-action-progress hidden role="status" aria-live="polite">'
                . '<div class="module-action-progress-copy"><span>Analisando sua resposta com o documento base</span>'
                . '<span class="query-loading-dots" aria-hidden="true"><span class="query-loading-dot"></span><span class="query-loading-dot"></span><span class="query-loading-dot"></span></span></div>'
                . '<progress max="100">Processando</progress><small>Aguarde a conclusão da análise qualitativa.</small></div></form>';
        }

        return '<dialog class="explorer-dialog" open aria-modal="true" aria-labelledby="explorer-dialog-title"><div class="explorer-dialog-shell">'
            . '<header><div><p class="eyebrow">' . $this->escape($theme['title']) . '</p><h2 id="explorer-dialog-title">'
            . $this->escape($label) . '</h2></div><button type="button" class="explorer-dialog-close" '
            . 'data-module-refresh aria-label="Fechar">×</button></header>'
            . '<div class="explorer-dialog-body">' . $body . '</div><div class="explorer-dialog-footer">'
            . '<button type="button" class="button button-quiet" data-module-refresh>Fechar</button></div></div></dialog>';
    }

    /** @param list<array<string, mixed>> $users @return array<int, array<string, mixed>> */
    private function usersById(array $users): array
    {
        $result = [];

        foreach ($users as $user) {
            $result[(int) ($user['id'] ?? 0)] = $user;
        }

        return $result;
    }

    /** @param list<array<string, mixed>> $interactions @return array<int, list<array<string, mixed>>> */
    private function interactionsByTheme(array $interactions): array
    {
        $result = [];

        foreach ($interactions as $interaction) {
            $result[(int) $interaction['theme_id']][] = $interaction;
        }

        return $result;
    }

    /** @param array<string, mixed> $entry */
    private function responseLabel(array $entry): string
    {
        $response = (string) ($entry['response_text'] ?? '');

        if (($entry['interaction_type'] ?? null) === 'prova'
            && is_array($entry['options'] ?? null)
            && is_string($entry['options'][$response] ?? null)) {
            return $response . ' — ' . $entry['options'][$response];
        }

        return $response;
    }

    private function analysisParagraphs(string $analysis): string
    {
        $analysis = trim((string) preg_replace('/\r\n?/u', "\n", $analysis));
        $paragraphs = preg_split('/\n+/u', $analysis, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $markup = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph !== '') {
                $markup .= '<p>' . $this->escape($paragraph) . '</p>';
            }
        }

        return $markup;
    }

    /** @param list<array<string, mixed>> $evidences */
    private function evidenceLocations(array $evidences, string $analysis): string
    {
        $unique = [];

        foreach ($evidences as $evidence) {
            if (!is_array($evidence) || !is_string($evidence['id'] ?? null) || trim($evidence['id']) === '') {
                continue;
            }

            $unique[trim($evidence['id'])] = $evidence;
        }

        if ($unique === []) {
            return '';
        }

        uasort($unique, static function (array $left, array $right) use ($analysis): int {
            $leftPosition = strpos($analysis, '[' . $left['id'] . ']');
            $rightPosition = strpos($analysis, '[' . $right['id'] . ']');

            return ($leftPosition === false ? PHP_INT_MAX : $leftPosition)
                <=> ($rightPosition === false ? PHP_INT_MAX : $rightPosition);
        });

        $items = '';

        foreach ($unique as $evidence) {
            $items .= '<li><code>' . $this->escape($evidence['id']) . '</code><span>'
                . $this->escape($this->evidenceBreadcrumb($evidence)) . '</span></li>';
        }

        return '<footer class="explorer-evidence-footer"><strong>Evidências utilizadas</strong><ul>'
            . $items . '</ul></footer>';
    }

    /** @param array<string, mixed> $evidence */
    private function evidenceBreadcrumb(array $evidence): string
    {
        $parts = [];
        $document = trim((string) ($evidence['document'] ?? ''));

        if ($document !== '') {
            $parts[] = $document;
        }

        $path = trim((string) ($evidence['structural_path'] ?? ''), '/ ');

        if ($path !== '') {
            foreach (array_filter(explode('/', $path), static fn (string $part): bool => trim($part) !== '') as $part) {
                $part = str_replace('-', ' ', trim($part));
                $parts[] = mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8')
                    . mb_substr($part, 1, null, 'UTF-8');
            }
        } else {
            $node = trim((string) ($evidence['node'] ?? ''));

            if ($node !== '') {
                $parts[] = $node;
            }
        }

        return $parts !== [] ? implode(' › ', $parts) : 'Evidência documental';
    }

    /** @param array<string, string> $options */
    private function options(array $options, string $selected): string
    {
        $markup = '';

        foreach ($options as $value => $label) {
            $markup .= '<option value="' . $this->escape($value) . '"'
                . ($selected === $value ? ' selected' : '') . '>' . $this->escape($label) . '</option>';
        }

        return $markup;
    }

    private function date(mixed $value): string
    {
        $date = trim((string) $value);

        if ($date === '') {
            return '';
        }

        try {
            return (new \DateTimeImmutable($date, new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone('America/Sao_Paulo'))->format('d/m/Y H:i');
        } catch (\Exception) {
            return $date;
        }
    }

    /** @return array{contract: string, html: string, css: string} */
    private function dashboard(string $html): array
    {
        $styles = file_get_contents(dirname(__DIR__, 2) . '/assets/dashboard.css');

        return ['contract' => 'eva.module.dashboard/1', 'html' => $html, 'css' => is_string($styles) ? $styles : ''];
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
