# EXPLORER

Módulo pedagógico independente do EVA para percursos de aprendizagem baseados em Temas de Aprendizado (TA). O pacote usa exclusivamente os contratos públicos do runtime modular e não altera arquivos, tabelas ou rotas do Core.

## Perfis

- **Professor:** publica um TA vinculado a um projeto e a um documento pronto do Core; acompanha as etapas e as análises qualitativas de cada aluno.
- **Aluno:** visualiza os cards publicados e realiza as relações cognitivas **Quizz**, **Nodes** e **Prova**.
- **Secretaria:** consulta indicadores institucionais de temas, participação, etapas concluídas, evolução por card e atividade por professor.

O superadmin acessa somente a configuração do módulo e vincula cada usuário do Core a, no máximo, um desses perfis.

## Fluxo pedagógico

1. O professor informa título, objetivo, projeto e documento base do TA.
2. O módulo publica um card para todos os usuários vinculados ao perfil Aluno.
3. Ao clicar em um círculo, o EXPLORER consulta somente o documento base autorizado e gera a atividade correspondente:
   - `quiz`: pergunta aberta; fundamentação, citações e contexto de geração permanecem em um objeto auxiliar invisível ao aluno e reutilizado na correção qualitativa;
   - `nodes`: relação entre dois assuntos específicos; o aluno recebe somente o comando direto, enquanto fundamentação, citações e evidências permanecem no objeto auxiliar usado na correção;
   - `prova`: múltipla escolha com quatro alternativas e uma resposta correta. O enunciado exibido ao aluno fica separado de um contrato interno com gabarito, justificativa da correta, justificativas dos distratores e referências às evidências integrais salvas.
4. A resposta é confrontada novamente com o documento base. O resultado é classificado como `correct` ou `deepen` e acompanhado de análise qualitativa, sem nota, ranking ou nível de domínio.
5. A etapa concluída fica amarela no card. Aluno, professor e Secretaria recebem apenas a visão permitida pelo seu perfil.

Perguntas, respostas, análises, evidências, perfis e referências documentais são armazenados em `modules/.runtime/data/com.eva.explorer/module.sqlite`. A referência usa `public_id` e `source_hash`; uma fonte substituída ou indisponível é recusada.

## Requisitos operacionais

- O projeto e o documento devem estar ativos/prontos no Core.
- Professores e alunos devem possuir acesso ao documento no Core. A consulta documental escopada revalida essa autorização em cada geração e análise.
- As chamadas reais de IA exigem a configuração normal do EVA e `AI_LIVE_ENABLED=true`.
- O módulo deve ser ativado no painel **Módulos** pelo superadmin.

## Validação offline

```powershell
php tests\ExplorerModuleTest.php
```

O teste verifica manifesto, perfis, isolamento do Core, dashboards exclusivos, publicação do TA, estado visual concluído, acompanhamento do professor e estatísticas da Secretaria sem realizar chamadas externas de IA.
