<?php

declare(strict_types=1);

namespace EvaModule\Explorer\Learning;

use Eva\ModuleRuntime\ModuleContext;
use Eva\ModuleRuntime\ModuleException;

final class LearningInteractionService
{
    private const TYPES = ['quiz', 'nodes', 'prova'];

    /** @param array<string, mixed> $theme @return array<string, mixed> */
    public function generatePrompt(ModuleContext $context, array $theme, string $type): array
    {
        $this->assertType($type);
        $typeInstruction = match ($type) {
            'quiz' => 'Crie uma pergunta aberta curta e adequada a um aluno iniciante. Trabalhe apenas um conceito central do Tema de Aprendizado por vez. No campo answer, use exatamente esta separação: Pergunta para o aluno: "<pergunta direta e, se necessário, uma breve orientação>". Fundamentação documental interna: <síntese breve com a citação documental visível exigida pelo EVA>. O texto entre aspas deve ter no máximo duas frases, em tom pedagógico, didático, claro e conciso, sem mencionar documento, evidências, elaboração, resposta esperada ou justificativas. A fundamentação interna não será exibida ao aluno. Não peça que o aluno explique vários elementos simultaneamente.',
            'nodes' => 'Selecione, com base no Tema de Aprendizado e no documento, dois assuntos específicos e distintos que possam ser relacionados. No campo answer, use exatamente esta separação: Proposta para o aluno: "<comando direto e natural>". Fundamentação documental interna: <síntese dos dois assuntos com as citações documentais visíveis exigidas pelo EVA>. No texto entre aspas, proponha ao aluno que construa, com suas próprias palavras, uma conexão conceitual entre os dois assuntos, nomeando-os no próprio comando. Não inclua introdução, explicação ou citação nesse texto: não explique a relação, não antecipe a resposta, não ofereça uma resposta-modelo e não forneça pistas que substituam a construção do aluno. A fundamentação interna não será exibida ao aluno.',
            'prova' => 'Crie uma questão de múltipla escolha com quatro alternativas A, B, C e D, apenas uma correta e distratores plausíveis. A parte destinada ao aluno deve conter no máximo dois parágrafos curtos de texto-base e, em um bloco final separado, um comando claro e direto para escolher a alternativa correta. Não repita as alternativas dentro do texto-base ou do comando. Evite contextualização histórica extensa, introduções genéricas e explicações que não sejam necessárias para compreender a questão. Para uso interno da correção, identifique o gabarito e justifique separadamente por que a alternativa correta procede e por que cada distrator não procede, sempre com base nas evidências documentais recuperadas. Esse material interno não deve fazer parte do enunciado apresentado ao aluno.',
        };
        $themeTitle = $this->clipBytes((string) $theme['title'], 800);
        $themeDescription = $this->clipBytes((string) $theme['description'], 9000);
        $query = $context->scopedQuery()->answer(
            $this->scopes($theme),
            "Tema de Aprendizado: {$themeTitle}\nObjetivo descrito pelo professor: {$themeDescription}\n\n{$typeInstruction}",
            'Use exclusivamente o documento selecionado e as evidências recuperadas. '
                . 'Não responda ao aluno e não exponha instruções internas. Produza somente a atividade solicitada, em português do Brasil.'
        );
        $answer = $this->queryAnswer($query, 'A IA não conseguiu gerar a atividade para este Tema de Aprendizado.');
        $evidences = $this->evidences($query);

        // Quizz e Nodes já chegam fundamentados pela consulta documental. Exigir uma
        // segunda resposta JSON para uma atividade aberta tornava o fluxo mais frágil
        // sem acrescentar qualidade pedagógica.
        if ($type !== 'prova') {
            return $this->validatePrompt([], $type, $evidences, $answer);
        }

        try {
            $normalized = $context->language->generateJson(
                'Você estrutura uma atividade educacional já produzida a partir de evidências documentais. '
                    . 'Use exclusivamente a atividade e os trechos de evidência fornecidos. Para cada alternativa, '
                    . 'registre uma justificativa objetiva e os IDs das evidências que a sustentam. Não acrescente '
                    . 'fatos ou conceitos externos. Na apresentação ao aluno, sintetize o texto-base em no máximo dois '
                    . 'parágrafos curtos e escreva o comando em um bloco separado, sem alternativas, gabarito, citações ou justificativas. '
                    . 'Responda somente em JSON.',
                [
                    'interaction_type' => $type,
                    'source_activity' => $answer,
                    'available_evidences' => $this->evidencePayload($evidences),
                    'required_contract' => [
                        'student_facing' => [
                            'context_paragraphs' => ['string curto', 'string curto opcional'],
                            'command' => 'string direto solicitando a escolha da alternativa correta',
                        ],
                        'options' => ['A' => 'string', 'B' => 'string', 'C' => 'string', 'D' => 'string'],
                        'correct_option' => 'A|B|C|D',
                        'option_justifications' => [
                            'A' => ['justification' => 'string', 'evidence_ids' => ['EVA-E...']],
                            'B' => ['justification' => 'string', 'evidence_ids' => ['EVA-E...']],
                            'C' => ['justification' => 'string', 'evidence_ids' => ['EVA-E...']],
                            'D' => ['justification' => 'string', 'evidence_ids' => ['EVA-E...']],
                        ],
                    ],
                ]
            );
        } catch (\Throwable) {
            $normalized = [];
        }

        return $this->validatePrompt($normalized, $type, $evidences, $answer);
    }

    /**
     * @param array<string, mixed> $theme
     * @param array<string, mixed> $prompt
     * @return array{outcome: string, analysis: string, evidences: list<array<string, mixed>>}
     */
    public function analyze(
        ModuleContext $context,
        array $theme,
        array $prompt,
        string $response
    ): array {
        $type = (string) ($prompt['interaction_type'] ?? '');
        $this->assertType($type);

        if ($type === 'prova' && is_array($prompt['answer_contract'] ?? null)
            && ($prompt['answer_contract']['contract'] ?? null) === 'eva.explorer.prova-answer/1') {
            return $this->analyzeProva($context, $theme, $prompt, $response);
        }

        $options = is_array($prompt['options'] ?? null) ? $prompt['options'] : [];
        $answerKey = $type === 'prova' ? (string) ($prompt['correct_option'] ?? '') : '';
        $answerContract = is_array($prompt['answer_contract'] ?? null)
            ? $prompt['answer_contract']
            : [];
        $openInteractionSupport = in_array($type, ['quiz', 'nodes'], true) && $answerContract !== [];
        $savedEvidences = $answerContract !== [] && is_array($prompt['evidences'] ?? null)
            ? $prompt['evidences']
            : [];
        $encodedOptions = $options === [] ? '' : (string) json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $optionText = $encodedOptions === '' ? '' : "\nAlternativas: " . $this->clipBytes($encodedOptions, 3500);
        $keyText = $answerKey === '' ? '' : "\nGabarito interno: {$answerKey}";
        $encodedContract = $answerContract === [] ? '' : (string) json_encode($answerContract, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $contractLimit = $openInteractionSupport ? 3500 : 4500;
        $contractText = $encodedContract === '' ? '' : "\nObjeto auxiliar de correção: " . $this->clipBytes($encodedContract, $contractLimit);
        $encodedSavedEvidences = $savedEvidences === [] ? '' : (string) json_encode($savedEvidences, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $evidenceLimit = $openInteractionSupport ? 5000 : 6500;
        $savedEvidenceText = $encodedSavedEvidences === '' ? '' : "\nEvidências integrais salvas na geração: " . $this->clipBytes($encodedSavedEvidences, $evidenceLimit);
        $themeTitle = $this->clipBytes((string) $theme['title'], 800);
        $themeDescription = $this->clipBytes((string) $theme['description'], $openInteractionSupport ? 1800 : ($type === 'prova' ? 1500 : 3000));
        $promptText = $this->clipBytes((string) $prompt['prompt_text'], $openInteractionSupport ? 2000 : ($type === 'prova' ? 2500 : 4000));
        $studentResponse = $this->clipBytes($response, $openInteractionSupport ? 5500 : ($type === 'prova' ? 100 : 6500));
        $supportInstruction = $type === 'prova'
            ? ' Na Prova, use o contrato interno e as evidências salvas como referência principal da correção; '
                . 'explique pedagogicamente por que a alternativa marcada está correta ou incorreta, sem expor ao aluno a estrutura interna do contrato.'
            : ($type === 'quiz' && $answerContract !== []
                ? ' No Quizz, considere como escopo avaliativo principal a pergunta apresentada, o objeto auxiliar e as evidências salvas na geração. '
                    . 'Avalie somente os conceitos efetivamente solicitados pela pergunta; não trate como falha a ausência de assuntos laterais apenas porque apareceram nas novas evidências recuperadas. '
                    . 'Escreva como uma devolutiva dirigida diretamente a quem respondeu, usando “você”, “sua resposta” ou construções neutras quando forem naturais. '
                    . 'Nunca se refira à pessoa como “o estudante”, “o aluno”, “a estudante” ou “a aluna”, nem use expressões como “a resposta do estudante”. '
                    . 'Comece reconhecendo, de forma sincera e proporcional, os pontos adequados. Depois, ensine os pontos essenciais ausentes ou imprecisos, '
                    . 'explicando cada um com linguagem pedagógica, empática e construtiva e com sua respectiva citação documental. '
                    . 'Prefira formulações como “para enriquecer esse entendimento” e evite uma sequência punitiva de “não mencionou”. '
                    . 'Organize o feedback em parágrafos curtos separados obrigatoriamente por uma linha em branco: um parágrafo inicial de reconhecimento, '
                    . 'um parágrafo para cada ponto essencial a desenvolver e, somente quando necessário, um breve fechamento. '
                    . 'Quando houver mais de um ponto a desenvolver, use rótulos descritivos em texto simples. '
                    . 'Não exponha a estrutura interna do objeto auxiliar no feedback.'
                : ($type === 'nodes' && $answerContract !== []
                    ? ' No Nodes, use o objeto auxiliar e as evidências salvas como escopo principal para avaliar a conexão construída. '
                        . 'Reconheça primeiro as relações conceituais válidas e depois ensine, diretamente a quem respondeu, os vínculos essenciais ausentes ou imprecisos. '
                        . 'Mantenha a citação documental visível [EVA-E...] no mesmo parágrafo de cada relação validada ou orientação fundamentada. '
                        . 'Não use “o estudante”, “o aluno” ou equivalentes e não exponha a estrutura interna do objeto auxiliar.'
                    : ''));
        $query = $context->scopedQuery()->answer(
            $this->scopes($theme),
            "Tema de Aprendizado: {$themeTitle}\nDescrição: {$themeDescription}"
                . "\nTipo de interação: {$type}\nAtividade: {$promptText}{$optionText}{$keyText}{$contractText}{$savedEvidenceText}"
                . "\nResposta do aluno: {$studentResponse}",
            'Compare a resposta do aluno exclusivamente com o documento selecionado e com as evidências recuperadas. '
                . 'Produza uma análise qualitativa breve, respeitosa e instrutiva. Declare se a resposta está correta '
                . 'ou se necessita de maior aprofundamento, explique o motivo e indique o conceito que deve ser retomado. '
                . 'Não forneça nota, percentual, ranking ou nível de domínio.' . $supportInstruction
        );
        $answer = $this->queryAnswer($query, 'A IA não conseguiu concluir a análise qualitativa.');
        $evidences = $this->evidences($query);

        try {
            $normalized = $context->language->generateJson(
                'Classifique e estruture uma análise qualitativa já fundamentada em evidências. '
                    . 'Preserve o sentido, todas as citações documentais e os parágrafos separados por uma linha em branco, sem acrescentar fatos. '
                    . 'No campo analysis, escreva uma devolutiva dirigida diretamente a quem respondeu: use “você”, “sua resposta” '
                    . 'ou construções neutras e nunca use “o estudante”, “o aluno”, “a estudante”, “a aluna” ou “a resposta do estudante”. '
                    . 'Responda somente em JSON.',
                [
                    'source_analysis' => $answer,
                    'required_contract' => [
                        'outcome' => 'correct|deepen',
                        'analysis' => 'string em português do Brasil',
                    ],
                    'classification_rule' => 'correct apenas quando a análise afirma que a resposta está correta; caso contrário, deepen',
                ]
            );
        } catch (\Throwable) {
            $normalized = [];
        }

        $normalizedAnalysis = $this->firstText($normalized, [
            'analysis', 'feedback', 'qualitative_analysis', 'avaliacao', 'avaliação', 'explanation', 'explicacao', 'explicação',
        ]);
        $analysis = $normalizedAnalysis ?? $answer;

        if ($normalizedAnalysis !== null
            && !$this->preservesDocumentaryCitations($normalizedAnalysis, $answer)) {
            $analysis = $answer;
        }

        $analysis = $this->cleanText($analysis, 6000);
        $analysis = $this->learnerFacingAnalysis($analysis);
        $declaredOutcome = $this->firstText($normalized, [
            'outcome', 'result', 'resultado', 'classification', 'classificacao', 'classificação', 'status',
        ]);
        $outcome = $this->normalizeOutcome($declaredOutcome, $answer);

        if ($type === 'prova' && $answerKey !== '') {
            $submittedOption = strtoupper(trim($response));
            $outcome = preg_match('/^[A-D]$/', $submittedOption) === 1
                && hash_equals(strtoupper($answerKey), $submittedOption)
                ? 'correct'
                : 'deepen';
        }

        if ($analysis === '') {
            throw new ModuleException('A IA não conseguiu produzir uma análise qualitativa para esta resposta.');
        }

        return ['outcome' => $outcome, 'analysis' => $analysis, 'evidences' => $evidences];
    }

    /**
     * @param array<string, mixed> $theme
     * @param array<string, mixed> $prompt
     * @return array{outcome: string, analysis: string, evidences: list<array<string, mixed>>}
     */
    private function analyzeProva(
        ModuleContext $context,
        array $theme,
        array $prompt,
        string $response
    ): array {
        $basis = $this->provaCorrectionBasis($prompt, $response);
        $analysis = $basis['analysis'];

        try {
            $normalized = $context->language->generateJson(
                'Produza uma devolutiva pedagógica de uma questão de múltipla escolha usando exclusivamente o contrato de correção e as evidências fornecidas. '
                    . 'Fale diretamente com quem respondeu, usando “você”, “sua escolha” ou construções neutras; nunca use “o estudante” ou “o aluno”. '
                    . 'Explique a alternativa marcada e, quando estiver incorreta, ensine por que a alternativa do gabarito é a adequada. '
                    . 'Organize em parágrafos curtos separados por uma linha em branco e preserve todas as citações [EVA-E...] fornecidas. Responda somente em JSON.',
                [
                    'theme' => [
                        'title' => $this->clipBytes((string) ($theme['title'] ?? ''), 800),
                        'objective' => $this->clipBytes((string) ($theme['description'] ?? ''), 1800),
                    ],
                    'question' => $this->clipBytes((string) ($prompt['prompt_text'] ?? ''), 2500),
                    'options' => $prompt['options'] ?? [],
                    'selected_option' => strtoupper(trim($response)),
                    'outcome' => $basis['outcome'],
                    'answer_contract' => $prompt['answer_contract'],
                    'relevant_evidences' => $basis['evidences'],
                    'required_contract' => [
                        'analysis' => 'string em português do Brasil, com as citações preservadas',
                    ],
                ]
            );
            $candidate = $this->firstText($normalized, [
                'analysis', 'feedback', 'qualitative_analysis', 'avaliacao', 'avaliação', 'explanation', 'explicacao', 'explicação',
            ]);

            if (is_string($candidate) && $this->preservesDocumentaryCitations($candidate, $analysis)) {
                $analysis = $candidate;
            }
        } catch (\Throwable) {
            // O resultado objetivo permanece disponível pelo contrato persistido.
        }

        $analysis = $this->learnerFacingAnalysis($this->cleanText($analysis, 6000));

        return [
            'outcome' => $basis['outcome'],
            'analysis' => $analysis,
            'evidences' => $basis['evidences'],
        ];
    }

    /**
     * @param array<string, mixed> $prompt
     * @return array{outcome: string, analysis: string, evidences: list<array<string, mixed>>}
     */
    private function provaCorrectionBasis(array $prompt, string $response): array
    {
        $contract = is_array($prompt['answer_contract'] ?? null) ? $prompt['answer_contract'] : [];
        $contractOptions = is_array($contract['options'] ?? null) ? $contract['options'] : [];
        $options = is_array($prompt['options'] ?? null) ? $prompt['options'] : [];
        $selected = strtoupper(trim($response));
        $correct = strtoupper(trim((string) ($contract['correct_option'] ?? $prompt['correct_option'] ?? '')));

        if (!isset($options[$selected]) || !isset($options[$correct])
            || !is_array($contractOptions[$selected] ?? null)
            || !is_array($contractOptions[$correct] ?? null)) {
            throw new ModuleException('O contrato de correção desta Prova está incompleto.');
        }

        $outcome = hash_equals($correct, $selected) ? 'correct' : 'deepen';
        $selectedJustification = $this->cleanText((string) ($contractOptions[$selected]['justification'] ?? ''), 3000);
        $correctJustification = $this->cleanText((string) ($contractOptions[$correct]['justification'] ?? ''), 3000);
        $selectedIds = $this->contractEvidenceIds($contractOptions[$selected]);
        $correctIds = $this->contractEvidenceIds($contractOptions[$correct]);
        $relevantIds = array_values(array_unique([...$selectedIds, ...$correctIds]));

        if ($selectedJustification === '' || $correctJustification === '' || $relevantIds === []) {
            throw new ModuleException('O contrato de correção desta Prova não possui fundamentação suficiente.');
        }

        if ($outcome === 'correct') {
            $analysis = 'Sua escolha está correta. '
                . $this->justificationWithCitations($correctJustification, $correctIds);
        } else {
            $analysis = 'A alternativa ' . $selected . ' não é a mais adequada. '
                . $this->justificationWithCitations($selectedJustification, $selectedIds)
                . "\n\nA alternativa correta é " . $correct . '. '
                . $this->justificationWithCitations($correctJustification, $correctIds);
        }

        $savedEvidences = is_array($prompt['evidences'] ?? null) ? $prompt['evidences'] : [];
        $evidences = array_values(array_filter(
            $savedEvidences,
            static fn (mixed $evidence): bool => is_array($evidence)
                && is_string($evidence['id'] ?? null)
                && in_array($evidence['id'], $relevantIds, true)
        ));

        if ($evidences === []) {
            throw new ModuleException('As evidências do contrato desta Prova não estão mais disponíveis.');
        }

        return ['outcome' => $outcome, 'analysis' => $analysis, 'evidences' => $evidences];
    }

    /** @param array<string, mixed> $option */
    private function contractEvidenceIds(array $option): array
    {
        return array_values(array_unique(array_filter(
            is_array($option['evidence_ids'] ?? null) ? $option['evidence_ids'] : [],
            static fn (mixed $id): bool => is_string($id) && preg_match('/^EVA-E\d{6,}$/', $id) === 1
        )));
    }

    /** @param list<string> $evidenceIds */
    private function justificationWithCitations(string $justification, array $evidenceIds): string
    {
        $justification = rtrim(trim($justification), " .;:");
        $citations = implode(' ', array_map(static fn (string $id): string => '[' . $id . ']', $evidenceIds));

        return trim($justification . ' ' . $citations) . '.';
    }

    private function preservesDocumentaryCitations(string $candidate, string $source): bool
    {
        preg_match_all('/\[(EVA-E\d{6,})\]/u', $source, $sourceMatches);
        preg_match_all('/\[(EVA-E\d{6,})\]/u', $candidate, $candidateMatches);
        $sourceIds = array_values(array_unique($sourceMatches[1] ?? []));
        $candidateIds = array_values(array_unique($candidateMatches[1] ?? []));

        return $sourceIds === [] || array_diff($sourceIds, $candidateIds) === [];
    }

    private function learnerFacingAnalysis(string $analysis): string
    {
        $patterns = [
            '/\ba\s+resposta\s+(?:do|da)\s+(?:estudante|aluno|aluna)\b/iu' => 'sua resposta',
            '/\b(?:pelo|pela)\s+(?:estudante|aluno|aluna)\b/iu' => 'por você',
            '/\b(?:para|com)\s+(?:o|a)\s+(?:estudante|aluno|aluna)\b/iu' => static fn (array $match): string => str_starts_with(mb_strtolower($match[0], 'UTF-8'), 'para') ? 'para você' : 'com você',
            '/\b(?:do|da)\s+(?:estudante|aluno|aluna)\b/iu' => 'presente na resposta',
            '/\b(?:o|a)\s+(?:estudante|aluno|aluna)\b/iu' => 'você',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $rewritten = is_string($replacement)
                ? preg_replace($pattern, $replacement, $analysis)
                : preg_replace_callback($pattern, $replacement, $analysis);

            if (is_string($rewritten)) {
                $analysis = $rewritten;
            }
        }

        if ($analysis !== '') {
            $analysis = mb_strtoupper(mb_substr($analysis, 0, 1, 'UTF-8'), 'UTF-8')
                . mb_substr($analysis, 1, null, 'UTF-8');
        }

        return $analysis;
    }

    /** @param array<string, mixed> $theme @return list<array{type: string, id: int}> */
    private function scopes(array $theme): array
    {
        return [
            ['type' => 'document', 'id' => (int) $theme['document_id']],
        ];
    }

    /** @param array<string, mixed> $query */
    private function queryAnswer(array $query, string $message): string
    {
        $answer = is_string($query['answer'] ?? null) ? trim($query['answer']) : '';

        if ($answer === '') {
            throw new ModuleException($message);
        }

        return $answer;
    }

    /** @param array<string, mixed> $query @return list<array<string, mixed>> */
    private function evidences(array $query): array
    {
        $evidences = [];

        foreach (is_array($query['evidences_used'] ?? null) ? $query['evidences_used'] : [] as $evidence) {
            if (is_array($evidence) && is_string($evidence['id'] ?? null) && trim($evidence['id']) !== '') {
                $evidences[] = $evidence;
            }
        }

        if ($evidences === []) {
            throw new ModuleException('A atividade não encontrou evidências no documento base do Tema de Aprendizado.');
        }

        return $evidences;
    }

    /**
     * @param array<string, mixed> $normalized
     * @param list<array<string, mixed>> $evidences
     * @return array<string, mixed>
     */
    private function validatePrompt(
        array $normalized,
        string $type,
        array $evidences,
        string $sourceActivity = ''
    ): array
    {
        $sourceStructured = $this->structuredFromText($sourceActivity);

        if ($normalized === [] && $sourceStructured !== []) {
            $normalized = $sourceStructured;
        }

        $structuredProvaPrompt = $type === 'prova'
            ? $this->structuredProvaPrompt($normalized)
            : null;
        $prompt = $structuredProvaPrompt ?? $this->firstText($normalized, [
            'prompt', 'question', 'pergunta', 'activity', 'atividade', 'request', 'solicitacao', 'solicitação',
            'instruction', 'instrucao', 'instrução', 'enunciado', 'command', 'comando',
        ]) ?? $this->sourceActivityText($sourceActivity, $sourceStructured);
        if ($type === 'quiz') {
            $prompt = $this->studentFacingQuizText($prompt);
        } elseif ($type === 'nodes') {
            $prompt = $this->studentFacingNodesText($prompt);
        } elseif ($type === 'prova') {
            $prompt = $this->limitProvaPromptBlocks($prompt);
        }
        $prompt = $this->cleanText($prompt, 6000);

        if ($prompt === '') {
            throw new ModuleException('A IA não conseguiu produzir o enunciado desta atividade.');
        }

        $options = [];
        $correctOption = null;
        $answerContract = match ($type) {
            'quiz' => $this->quizCorrectionSupport($prompt, $sourceActivity, $evidences),
            'nodes' => $this->nodesCorrectionSupport($prompt, $sourceActivity, $evidences),
            default => [],
        };

        if ($type === 'prova') {
            $options = $this->normalizeOptions($normalized);

            if (count($options) !== 4) {
                throw new ModuleException('A questão de Prova precisa apresentar quatro alternativas compreensíveis.');
            }

            $declaredCorrect = $this->firstText($normalized, [
                'correct_option', 'correct_answer', 'answer', 'gabarito', 'resposta_correta', 'alternativa_correta',
            ]) ?? '';
            $correctOption = $this->normalizeCorrectOption($declaredCorrect, $options);

            if (!array_key_exists($correctOption, $options)) {
                throw new ModuleException('A questão de Prova precisa indicar uma única alternativa correta.');
            }

            $answerContract = $this->normalizeAnswerContract(
                $normalized,
                $correctOption,
                $options,
                $evidences
            );
        }

        return [
            'prompt' => $prompt,
            'options' => $options,
            'correct_option' => $correctOption,
            'answer_contract' => $answerContract,
            'evidences' => $evidences,
        ];
    }

    /** @param list<array<string, mixed>> $evidences @return array<string, mixed> */
    private function quizCorrectionSupport(string $studentPrompt, string $sourceActivity, array $evidences): array
    {
        $evidenceIds = [];

        foreach ($evidences as $evidence) {
            if (is_string($evidence['id'] ?? null) && trim($evidence['id']) !== '') {
                $evidenceIds[] = trim($evidence['id']);
            }
        }

        return [
            'contract' => 'eva.explorer.quiz-correction-support/1',
            'student_prompt' => $studentPrompt,
            'documentary_support' => $this->cleanText($sourceActivity !== '' ? $sourceActivity : $studentPrompt, 12_000),
            'evidence_ids' => array_values(array_unique($evidenceIds)),
        ];
    }

    private function studentFacingQuizText(string $value): string
    {
        $value = trim($value);

        if (preg_match('/(?:a\s+pergunta(?:\s+aberta)?\s+(?:é|será)|pergunta(?:\s+para\s+o\s+aluno)?)\s*:\s*["“]?(.+?\?)["”]?/isu', $value, $match) === 1) {
            return trim($match[1], " \t\n\r\0\x0B\"“”");
        }

        if (preg_match('/["“]([^"”]*\?)["”]/su', $value, $match) === 1) {
            return trim($match[1]);
        }

        return trim((string) preg_replace('/\s*\[EVA-E\d{6,}\]/u', '', $value));
    }

    private function studentFacingNodesText(string $value): string
    {
        $value = trim($value);

        if (preg_match('/proposta\s+para\s+o\s+aluno\s*:\s*["“](.+?)["”](?:\s|$)/isu', $value, $match) === 1) {
            return $this->cleanStudentFacingNodesText($match[1]);
        }

        if (preg_match('/proposta\s+para\s+o\s+aluno\s*:\s*(.+?)(?:fundamentação\s+documental\s+interna\s*:|$)/isu', $value, $match) === 1) {
            return $this->cleanStudentFacingNodesText($match[1]);
        }

        if (preg_match('/(?:peço|solicito)\s+que\s+você\s+(.+)$/isu', $value, $match) === 1) {
            $command = $this->cleanStudentFacingNodesText($match[1]);

            return mb_strtoupper(mb_substr($command, 0, 1, 'UTF-8'), 'UTF-8')
                . mb_substr($command, 1, null, 'UTF-8');
        }

        return $this->cleanStudentFacingNodesText($value);
    }

    private function cleanStudentFacingNodesText(string $value): string
    {
        $value = preg_replace('/\s*\[EVA-E\d{6,}\]/u', '', $value) ?? $value;
        $value = str_replace(['**', '__'], '', $value);

        return trim($value, " \t\n\r\0\x0B\"“”");
    }

    /** @param array<string, mixed> $normalized */
    private function structuredProvaPrompt(array $normalized): ?string
    {
        $studentFacing = $this->firstArray($normalized, [
            'student_facing', 'student_prompt', 'presentation', 'apresentacao', 'apresentação',
        ]);

        if ($studentFacing === null) {
            return null;
        }

        $rawContexts = null;

        foreach (['context_paragraphs', 'context', 'base_text', 'texto_base', 'paragraphs', 'paragrafos', 'parágrafos'] as $key) {
            if (isset($studentFacing[$key])) {
                $rawContexts = $studentFacing[$key];
                break;
            }
        }

        $contexts = [];

        foreach (is_array($rawContexts) ? $rawContexts : [$rawContexts] as $context) {
            if (!is_string($context)) {
                continue;
            }

            foreach (preg_split('/\n+/u', trim($context), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $paragraph) {
                $paragraph = $this->cleanText($paragraph, 900);

                if ($paragraph !== '') {
                    $contexts[] = $paragraph;
                }
            }
        }

        $command = $this->firstText($studentFacing, [
            'command', 'comando', 'question', 'pergunta', 'instruction', 'instrucao', 'instrução', 'enunciado',
        ]);
        $command = is_string($command) ? $this->cleanText($command, 600) : '';

        if ($command === '') {
            return null;
        }

        return implode("\n\n", [...array_slice($contexts, 0, 2), $command]);
    }

    private function limitProvaPromptBlocks(string $value): string
    {
        $value = trim((string) preg_replace('/\r\n?/u', "\n", $value));
        $blocks = preg_split('/\n+/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode("\n\n", array_slice(array_map('trim', $blocks), 0, 3));
    }

    /** @param list<array<string, mixed>> $evidences @return array<string, mixed> */
    private function nodesCorrectionSupport(string $studentPrompt, string $sourceActivity, array $evidences): array
    {
        $evidenceIds = [];

        foreach ($evidences as $evidence) {
            if (is_string($evidence['id'] ?? null) && trim($evidence['id']) !== '') {
                $evidenceIds[] = trim($evidence['id']);
            }
        }

        return [
            'contract' => 'eva.explorer.nodes-correction-support/1',
            'student_prompt' => $studentPrompt,
            'documentary_support' => $this->cleanText($sourceActivity !== '' ? $sourceActivity : $studentPrompt, 12_000),
            'evidence_ids' => array_values(array_unique($evidenceIds)),
        ];
    }

    /**
     * @param array<string, mixed> $normalized
     * @param array<string, string> $options
     * @param list<array<string, mixed>> $evidences
     * @return array<string, mixed>
     */
    private function normalizeAnswerContract(
        array $normalized,
        string $correctOption,
        array $options,
        array $evidences
    ): array {
        $rawJustifications = $this->firstArray($normalized, [
            'option_justifications', 'justifications', 'justificativas', 'alternative_justifications',
        ]) ?? [];
        $availableEvidenceIds = [];

        foreach ($evidences as $evidence) {
            if (is_string($evidence['id'] ?? null) && trim($evidence['id']) !== '') {
                $availableEvidenceIds[] = trim($evidence['id']);
            }
        }

        $contractOptions = [];

        foreach (array_keys($options) as $letter) {
            $entry = $this->justificationEntry($rawJustifications, $letter);
            $justification = is_string($entry)
                ? $entry
                : (is_array($entry) ? ($this->firstText($entry, [
                    'justification', 'explanation', 'reason', 'justificativa', 'explicacao', 'explicação',
                ]) ?? '') : '');
            $justification = $this->cleanText($justification, 3000);

            if ($justification === '') {
                throw new ModuleException(
                    "A questão de Prova precisa justificar internamente a alternativa {$letter} com base nas evidências."
                );
            }

            $evidenceIds = is_array($entry)
                ? $this->normalizeEvidenceIds($entry, $availableEvidenceIds)
                : [];

            // Uma justificativa válida sem IDs explícitos continua vinculada ao
            // conjunto documental recuperado para não fragilizar a geração.
            if ($evidenceIds === []) {
                $evidenceIds = $availableEvidenceIds;
            }

            $contractOptions[$letter] = [
                'role' => $letter === $correctOption ? 'correct' : 'distractor',
                'justification' => $justification,
                'evidence_ids' => $evidenceIds,
            ];
        }

        return [
            'contract' => 'eva.explorer.prova-answer/1',
            'correct_option' => $correctOption,
            'options' => $contractOptions,
        ];
    }

    /** @param array<string, mixed> $value @param list<string> $keys */
    private function firstArray(array $value, array $keys, int $depth = 0): ?array
    {
        foreach ($keys as $key) {
            if (is_array($value[$key] ?? null)) {
                return $value[$key];
            }
        }

        if ($depth >= 2) {
            return null;
        }

        foreach ($value as $child) {
            if (!is_array($child)) {
                continue;
            }

            $found = $this->firstArray($child, $keys, $depth + 1);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /** @param array<mixed> $justifications */
    private function justificationEntry(array $justifications, string $letter): mixed
    {
        foreach ($justifications as $key => $entry) {
            if (is_string($key) && strtoupper(trim($key)) === $letter) {
                return $entry;
            }

            if (is_array($entry)) {
                $declaredLetter = $this->firstText($entry, ['option', 'alternative', 'letter', 'letra', 'alternativa']);

                if (is_string($declaredLetter) && strtoupper(trim($declaredLetter)) === $letter) {
                    return $entry;
                }
            }
        }

        return null;
    }

    /** @param array<string, mixed> $entry @param list<string> $available */
    private function normalizeEvidenceIds(array $entry, array $available): array
    {
        $raw = null;

        foreach (['evidence_ids', 'evidences', 'evidencias', 'references', 'referencias'] as $key) {
            if (isset($entry[$key])) {
                $raw = $entry[$key];
                break;
            }
        }

        if (is_string($raw)) {
            $raw = preg_split('/[\s,;]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        if (!is_array($raw)) {
            return [];
        }

        $ids = [];

        foreach ($raw as $item) {
            $id = is_string($item)
                ? trim($item)
                : (is_array($item) && is_string($item['id'] ?? null) ? trim($item['id']) : '');

            if ($id !== '' && in_array($id, $available, true)) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /** @param list<array<string, mixed>> $evidences @return list<array<string, mixed>> */
    private function evidencePayload(array $evidences): array
    {
        $payload = [];

        foreach ($evidences as $evidence) {
            if (!is_array($evidence) || !is_string($evidence['id'] ?? null)) {
                continue;
            }

            $payload[] = array_filter([
                'id' => $evidence['id'],
                'document' => $evidence['document'] ?? null,
                'node' => $evidence['node'] ?? null,
                'structural_path' => $evidence['structural_path'] ?? null,
                'source_reference' => $evidence['source_reference'] ?? null,
                'content' => $evidence['content'] ?? null,
            ], static fn (mixed $value): bool => is_string($value) && trim($value) !== '');
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function structuredFromText(string $value): array
    {
        $value = $this->cleanText($value, 12_000);
        $decoded = json_decode($value, true, 32);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $structured */
    private function sourceActivityText(string $source, array $structured): string
    {
        if ($structured !== []) {
            $text = $this->firstText($structured, [
                'prompt', 'question', 'pergunta', 'activity', 'atividade', 'request', 'solicitacao', 'solicitação',
                'instruction', 'instrucao', 'instrução', 'enunciado',
            ]);

            if ($text !== null) {
                return $text;
            }
        }

        return $source;
    }

    /** @param array<string, mixed> $value @param list<string> $keys */
    private function firstText(array $value, array $keys, int $depth = 0): ?string
    {
        foreach ($keys as $key) {
            if (is_string($value[$key] ?? null) && trim($value[$key]) !== '') {
                return trim($value[$key]);
            }
        }

        if ($depth >= 2) {
            return null;
        }

        foreach ($value as $child) {
            if (!is_array($child)) {
                continue;
            }

            $found = $this->firstText($child, $keys, $depth + 1);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $normalized @return array<string, string> */
    private function normalizeOptions(array $normalized): array
    {
        $raw = null;

        foreach (['options', 'alternatives', 'alternativas', 'choices'] as $key) {
            if (is_array($normalized[$key] ?? null)) {
                $raw = $normalized[$key];
                break;
            }
        }

        if ($raw === null) {
            foreach ($normalized as $child) {
                if (!is_array($child)) {
                    continue;
                }

                $candidate = $this->normalizeOptions($child);

                if ($candidate !== []) {
                    return $candidate;
                }
            }

            return [];
        }

        $options = [];
        $letters = ['A', 'B', 'C', 'D'];

        foreach ($raw as $key => $value) {
            if (is_array($value)) {
                $text = $this->firstText($value, ['text', 'content', 'label', 'option', 'alternativa']);
                $declaredKey = $this->firstText($value, ['id', 'key', 'letter', 'letra']);
            } else {
                $text = is_string($value) ? trim($value) : '';
                $declaredKey = null;
            }

            if ($text === '') {
                continue;
            }

            $letter = is_string($key) && preg_match('/^[A-D]$/i', $key) === 1
                ? strtoupper($key)
                : (is_string($declaredKey) && preg_match('/^[A-D]$/i', trim($declaredKey)) === 1
                    ? strtoupper(trim($declaredKey))
                    : ($letters[count($options)] ?? ''));

            if ($letter === '') {
                continue;
            }

            $text = preg_replace('/^\s*[A-D]\s*[\)\.\-:]\s*/iu', '', $text) ?? $text;
            $text = $this->cleanText($text, 1200);

            if ($text !== '') {
                $options[$letter] = $text;
            }
        }

        ksort($options);

        return array_intersect_key($options, array_flip($letters));
    }

    /** @param array<string, string> $options */
    private function normalizeCorrectOption(string $value, array $options): string
    {
        $value = trim($value);

        if (preg_match('/(?:^|\b)([A-D])(?:\b|$)/iu', $value, $match) === 1) {
            return strtoupper($match[1]);
        }

        foreach ($options as $key => $option) {
            if ($this->normalizedText($value) === $this->normalizedText($option)) {
                return $key;
            }
        }

        return '';
    }

    private function normalizeOutcome(?string $declared, string $sourceAnalysis): string
    {
        $declaredOutcome = $this->outcomeFromText($declared ?? '');

        return $declaredOutcome ?? $this->outcomeFromText($sourceAnalysis) ?? 'deepen';
    }

    private function outcomeFromText(string $value): ?string
    {
        $value = $this->normalizedText($value);

        foreach (['deepen', 'necessita', 'incorret', 'nao esta correta', 'parcial', 'insuficient', 'equivoc', 'incomplet'] as $signal) {
            if (str_contains($value, $signal)) {
                return 'deepen';
            }
        }

        foreach (['correct', 'corret', 'adequad', 'coerente', 'satisfatori'] as $signal) {
            if (str_contains($value, $signal)) {
                return 'correct';
            }
        }

        foreach (['aprofund', 'imprecis', 'retomar'] as $signal) {
            if (str_contains($value, $signal)) {
                return 'deepen';
            }
        }

        return null;
    }

    private function cleanText(string $value, int $maximum): string
    {
        $value = trim($value);
        $value = preg_replace('/^```(?:json|markdown|text)?\s*|\s*```$/iu', '', $value) ?? $value;

        if (mb_strlen($value, 'UTF-8') > $maximum) {
            $value = rtrim(mb_substr($value, 0, $maximum - 1, 'UTF-8')) . '…';
        }

        return trim($value);
    }

    private function normalizedText(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');

        return strtr($value, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);
    }

    private function assertType(string $type): void
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new ModuleException('O tipo de relação cognitiva informado não existe no EXPLORER.');
        }
    }

    private function clipBytes(string $value, int $maximum): string
    {
        if (strlen($value) <= $maximum) {
            return $value;
        }

        return rtrim(mb_strcut($value, 0, max(1, $maximum - 3), 'UTF-8')) . '…';
    }
}
