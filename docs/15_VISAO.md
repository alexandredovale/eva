# Visão do EVA: utilidade real, aplicações e impacto esperado

## Escopo

Este documento apresenta uma análise técnica e estratégica do estado atual do EVA, comparando sua arquitetura, desempenho e organização documental com os desafios contemporâneos da inteligência artificial.

A pergunta central é:

> Qual utilidade real tem o sistema EVA hoje para o progresso no mundo?

A análise distingue explicitamente capacidades implementadas, resultados internos observados, limitações atuais e impacto futuro condicionado. O objetivo não é tratar o EVA como um sistema livre de vieses — nenhuma arquitetura baseada em embeddings, sínteses e modelos generativos pode sustentar essa afirmação —, mas avaliar o produto sem viés promocional.

## Parecer executivo

Hoje, o EVA tem utilidade real como uma camada de governança documental para IA: ele transforma acervos estruturados em respostas rastreáveis, limita o modelo às fontes recuperadas e rejeita parte das respostas sem sustentação verificável.

Sua contribuição potencial para o progresso mundial não está em criar uma inteligência mais poderosa, mas em tornar o uso da IA mais responsável, auditável e computacionalmente disciplinado.

O parecer objetivo é:

> O EVA já é um produto funcional e uma arquitetura de RAG diferenciada, adequada a acervos curados de pequeno ou médio porte. Ainda não é uma tecnologia cientificamente comprovada em escala, nem uma infraestrutura pronta para grandes volumes, tempo real ou uso autônomo em decisões críticas.

## Limites da análise

A auditoria que originou este documento foi exclusivamente de leitura:

- o `.env` não foi lido;
- chaves e credenciais não foram acessadas;
- não houve conexão ao banco operacional;
- documentos privados e backups de dados não foram inspecionados;
- nenhum provedor ou chamada paga foi executado;
- nenhum arquivo operacional foi alterado.

Os 114 arquivos PHP disponíveis foram submetidos à validação sintática e nenhum apresentou erro. A suíte funcional não foi reexecutada porque seu bootstrap carrega o `.env`. Resultados de testes e benchmarks já documentados pelo projeto foram tratados como evidência interna, não como validação independente.

## Como o EVA funciona atualmente

O fluxo implementado é:

1. O sistema recebe documentos Markdown, JSON ou XML.
2. O conteúdo é convertido para uma árvore documental comum, preservando ordem, hierarquia e referência de origem.
3. Evidências primárias literais são criadas para os nós com conteúdo documental direto.
4. Sínteses ascendentes versionadas podem ser produzidas, mantendo a linhagem entre cada síntese e suas fontes.
5. Embeddings são gerados para unidades documentais completas e previamente estruturadas.
6. Na consulta, o input é encaminhado para rotas diretas, estruturais, amplas ou semânticas.
7. Nas rotas semânticas, o Context Intelligence Engine (CIE) separa candidatos em núcleo, convergência e descarte.
8. Evidências derivadas selecionadas são resolvidas novamente até suas fontes primárias.
9. O modelo de linguagem recebe somente o contexto primário disponível.
10. A base final mantém apenas evidências incorporadas à prosa com citações visíveis; candidatas recuperadas mas não citadas são descartadas.
11. Quando há interação demonstrável entre evidências citadas, Cnode existe apenas como derivação conceitual transitória do EVA, não como sistema, camada hierárquica ou entidade.
12. A consulta e suas interações não alteram a memória documental persistente.

Essa separação entre fonte original, conteúdo gerado e resultado transitório é o principal mérito do sistema.

## Avaliação objetiva

| Dimensão | Situação atual | Parecer |
|---|---|---|
| Proveniência e rastreabilidade | Evidência literal, identificadores estáveis, caminhos e derivações | Forte |
| Proteção contra respostas sem fonte | Barreiras de IDs, citações e fragmentos | Forte, mas não garante correção semântica |
| Organização estrutural | Árvore, projetos, obras e perfis de respostas | Boa para acervo curado |
| Gestão do ciclo de vida | Sem versão documental explícita, aprovação, validade, tags ou atualização incremental da obra | Limitada |
| Recuperação semântica | Embeddings estruturados e CIE determinístico | Promissora, ainda não calibrada cientificamente |
| Escalabilidade | Vetores em `LONGTEXT` e cálculo de cosseno no PHP | Fraca para acervos grandes |
| Multimodalidade | Apenas Markdown, JSON e XML | Muito limitada diante da IA atual |
| Segurança de acesso | Escopo por usuário e projeto, hashes e auditoria sanitizada | Boa base |
| Segurança contra conteúdo adversarial | Sem defesa específica contra prompt injection documental | Insuficiente para acervos não confiáveis |
| Neutralidade de fornecedor | Interfaces abstratas por capacidade | Parcial: os adaptadores dependem de um contrato HTTP compatível com Chat Completions |
| Evidência científica | Testes internos e casos dirigidos | Sem comparação independente ou significância estatística |
| Eficiência energética | Mecanismos plausíveis de contenção | Benefício líquido ainda não medido |

## Desempenho real observável

O próprio benchmark do projeto declara corretamente que não demonstra superioridade. Na execução original:

- 3 de 5 consultas foram funcionalmente válidas;
- 2 consultas foram bloqueadas;
- 95,04% da latência medida foi externa;
- 58,95% dos tokens foram consumidos por saídas posteriormente rejeitadas;
- 843 embeddings foram percorridos em aproximadamente 371 ms;
- o maior incremento de memória observado foi de aproximadamente 54 MiB.

Esses resultados estão registrados no [benchmark interno](../philosophy/02_EVA_BENCHMARK_BASELINE.md). A execução antecede o CIE e, portanto, não prova a qualidade da arquitetura atual. O [roadmap](09_ROADMAP.md) mantém pendente a comparação representativa de qualidade, estabilidade, latência e tokens.

O caso dirigido mais recente do CIE terminou em 24,32 segundos com dez evidências, mas o próprio [relatório de validação](11_VALIDACAO_GO_LIVE.md) reconhece que uma matriz representativa continua pendente.

### Gargalos técnicos centrais

#### Varredura vetorial

A recuperação semântica carrega os vetores do documento, desserializa o JSON, calcula a similaridade de cosseno em PHP e somente depois limita o conjunto ao Top-k. Esse fluxo pode ser observado em [`DocumentContextRetriever.php`](../app/Application/Query/DocumentContextRetriever.php).

O custo é aproximadamente proporcional à quantidade de embeddings multiplicada por sua dimensão, para cada documento e consulta. Em consultas multidocumentais, o trabalho se repete por obra.

#### Limite por quantidade, não por tokens

`QUERY_MAX_EVIDENCE` limita a quantidade de evidências, não o tamanho total em bytes ou tokens. Uma única evidência extensa pode produzir um prompt caro ou superar a janela operacional do modelo. A construção de sínteses hierárquicas também não possui atualmente uma guarda equivalente à proteção aplicada às unidades de embedding.

#### Ausência de limiar absoluto de pertinência

O CIE usa limites relativos à distribuição: média e média mais desvio padrão. Não existe um limiar absoluto que represente “nenhum candidato suficientemente relacionado”.

Quando existem embeddings compatíveis, ao menos algum candidato tende a ficar igual ou acima da média e ser eleito, mesmo que todas as similaridades sejam baixas. Esse comportamento, implementado em [`ContextIntelligenceEngine.php`](../app/Application/Query/ContextIntelligenceEngine.php), pode enfraquecer a recusa negativa nas rotas semânticas.

#### Filtragem por citações após a recuperação

O Retriever e o CIE determinam localmente o contexto disponível, e as citações visíveis determinam a base documental final. A LLM não pode introduzir evidências externas ou IDs fora desse contexto, mas uma candidata recuperada que não contribua para a prosa pode ser descartada sem derrubar toda a resposta. Isso melhora o foco e a disponibilidade, embora ainda seja necessário medir se fontes pertinentes estão sendo omitidas.

#### Tentativas adicionais

A validação local pode solicitar até três gerações completas com o mesmo contexto. Cada tentativa pode admitir uma regeneração compacta quando a primeira saída é truncada. Esse desenho aumenta a chance de obter uma resposta válida, mas pode elevar latência, tokens e custo nos casos difíceis.

## Organização e logística de conteúdo

### Pontos fortes

- A hierarquia original é preservada em vez de ser substituída por chunks arbitrários.
- Evidência primária e síntese gerada permanecem distinguíveis.
- Derivações permitem retornar de uma síntese às fontes que a originaram.
- Embeddings são versionados por modelo e hash.
- Projetos agrupam obras sem alterar a estrutura cognitiva dos documentos.
- Escopos de usuário limitam quais obras podem participar de uma consulta.
- Perfis de resposta são ativados somente por seleção explícita do projeto.
- O chat não grava automaticamente respostas na memória documental.

### Limitações

- O acervo aceita apenas Markdown, JSON e XML.
- Não há ingestão nativa de PDF, DOCX, HTML, páginas web, imagens, áudio ou vídeo.
- Não existe fluxo documental explícito de rascunho, revisão, aprovação, publicação e expiração.
- Documentos não possuem vínculo formal de versão anterior e posterior.
- Não há taxonomia de assuntos, tags, entidades, datas de vigência ou responsáveis pelo conteúdo.
- O hash da fonte é indexado, mas não impede duplicação documental.
- Não existe sincronização incremental com repositórios externos.
- Atualizar uma obra significa, na prática, ingerir outra unidade documental.
- Não há busca administrativa por conteúdo, metadados ou estado editorial.

O EVA organiza muito bem a estrutura interna de uma obra já preparada, mas ainda não oferece um sistema completo de governança do ciclo de vida do conteúdo.

## Comparação com os desafios atuais da IA

### Alucinação e proveniência

Modelos continuam produzindo informações incorretas com aparência de certeza. O NIST trata a confabulação como risco inerente aos sistemas generativos, e o AI Index 2026 registra que avaliação e segurança responsável continuam atrás da evolução das capacidades.

Referências:

- [NIST AI 600-1 — Generative AI Profile](https://www.nist.gov/publications/artificial-intelligence-risk-management-framework-generative-artificial-intelligence)
- [Stanford AI Index 2026 — Responsible AI](https://hai.stanford.edu/ai-index/2026-ai-index-report/responsible-ai)

A barreira de evidência do EVA responde diretamente a esse problema. Ela não prova que a interpretação é correta, mas dificulta que uma afirmação totalmente desconectada do acervo seja apresentada como resposta documental válida.

### Contextos extensos

Janelas maiores não eliminam falhas de recuperação. Modelos podem usar pior as informações posicionadas no meio de contextos extensos, conforme demonstrado por [Lost in the Middle](https://aclanthology.org/2024.tacl-1.9.pdf).

O EVA reduz o contexto antes da geração, preserva a estrutura documental e descarta candidatas não citadas, o que é relevante. Entretanto, limitar evidências por quantidade não substitui um orçamento real de tokens nem uma avaliação de pertinência.

### Avaliação de RAG

A avaliação de sistemas RAG continua difícil porque recuperação e geração podem falhar separadamente. Métricas agregadas escondem essas causas. O [RAGChecker](https://arxiv.org/abs/2408.08067) propõe métricas separadas e diagnósticas para Retriever e gerador.

O EVA ainda não mantém métricas contínuas de:

- precisão e recall de recuperação;
- fidelidade e completude da resposta;
- taxa de recusa correta e indevida;
- validade semântica das interações;
- estabilidade entre paráfrases;
- latências p50, p95 e p99;
- custo e tokens por classe de consulta;
- deriva de modelos e embeddings.

### Prompt injection documental

Documentos recuperados são conteúdo não confiável inserido no contexto linguístico da LLM. O prompt do EVA orienta o modelo a tratar evidências como dados, mas não existe detecção ou neutralização específica de instruções maliciosas contidas nas fontes.

RAG não elimina prompt injection, conforme o [OWASP LLM01:2025](https://genai.owasp.org/llmrisk/llm01-prompt-injection/). O risco operacional do EVA é menor porque o modelo não recebe ferramentas autônomas para executar ações, mas a integridade da resposta ainda pode ser atacada.

### Multimodalidade, agentes e tempo real

A IA contemporânea evolui para documentos multimodais, agentes, conectores, ferramentas e dados vivos. O EVA permanece textual, documental, estático e consultivo.

Isso limita sua abrangência comercial, mas também reduz a superfície de ataque, os efeitos irreversíveis e a complexidade operacional. Essa contenção deve ser tratada como uma escolha arquitetural, não apenas como atraso funcional.

### GraphRAG e relações persistentes

Arquiteturas como o [Microsoft GraphRAG](https://www.microsoft.com/en-us/research/project/graphrag/) materializam entidades, relações e comunidades para responder perguntas globais sobre grandes corpora.

O EVA escolhe o caminho oposto: não materializa Cnode porque ele é apenas uma derivação conceitual transitória, nem persiste combinações relacionais antecipadas. Isso reduz custo, armazenamento e explosão combinatória, mas limita navegação global, análise de comunidades e raciocínio sobre relações persistentes entre muitos documentos.

### Neutralidade de fornecedor

O domínio possui interfaces separadas para embeddings, sínteses e respostas. Essa é uma boa separação arquitetural. Entretanto, [`CognitiveProviderFactory.php`](../app/Infrastructure/Ai/CognitiveProviderFactory.php) sempre instancia os mesmos adaptadores, cujos payloads utilizam campos como `messages`, `response_format`, `thinking` e `max_tokens`.

Na prática, a portabilidade atual é compatibilidade com APIs de formato semelhante, não neutralidade universal entre fornecedores.

## Sustentabilidade energética

O EVA possui mecanismos relevantes de contenção:

- reaproveitamento de sínteses e embeddings;
- dispensa de embedding em rotas não semânticas;
- encerramento sem chamada generativa quando nenhuma evidência é recuperada;
- ausência de construção antecipada de pares relacionais;
- limites de evidências, saída e tentativas;
- possibilidade de substituir provedores e modelos.

Esses mecanismos justificam uma hipótese de eficiência, mas não comprovam economia líquida. A International Energy Agency estima que data centers representavam aproximadamente 1,5% do consumo elétrico mundial em 2024 e destaca a expansão acelerada da demanda associada à IA.

Referência: [IEA — Energy demand from AI](https://www.iea.org/reports/energy-and-ai/energy-demand-from-ai).

Para alegar impacto ambiental, o EVA precisará demonstrar menos joules por resposta com qualidade equivalente, incluindo construção inicial, consultas repetidas, armazenamento e tentativas rejeitadas. Hoje, “sustentável” deve permanecer uma hipótese mensurável, não uma promessa.

## Áreas de aplicação e impacto esperado

| Área | Aplicação do EVA | Impacto esperado | Condições |
|---|---|---|---|
| Regulação e compliance | Consultar normas, políticas, contratos e procedimentos com fonte identificada | Alto: menor tempo de localização e melhor auditabilidade | Acervo curado, versionado e atualizado |
| Indústria e manutenção | Manuais, relatórios de falha, procedimentos e histórico técnico | Alto em apoio à decisão | Não controlar equipamentos; manter validação humana |
| Energia e infraestrutura crítica | Contingência, segurança, manutenção e documentação regulatória | Alto potencial consultivo | Separação total de SCADA, EMS e sistemas de proteção |
| Educação e patrimônio cultural | Exploração de obras, currículos, arquivos históricos e bibliotecas | Médio-alto: acesso explicável ao conteúdo | Curadoria pedagógica e direitos de uso claros |
| Pesquisa científica | Organização de protocolos, relatórios e literatura estruturada | Médio-alto | Suporte a PDF, avaliação multidisciplinar e revisão humana |
| Saúde | Protocolos, manuais e documentação institucional | Potencial alto | Uso somente assistivo, validação clínica e governança rigorosa |
| Jurídico | Pesquisa em contratos, regulamentos e precedentes internos | Alto para busca e conferência | Inadequado para decisão jurídica autônoma |
| Conhecimento corporativo | Políticas, onboarding, suporte interno e documentação técnica | Alto em acervos estáveis | Gestão de validade e integração com repositórios |
| Pesquisa interdisciplinar | Identificar relações entre fontes de diferentes áreas | Promissor | Ainda requer comprovação experimental |
| Agentes autônomos e dados em tempo real | Execução de ações, monitoramento ou controle | Baixa adequação atual | Exigiria outra camada arquitetural e de segurança |

## Utilidade real do EVA para o progresso no mundo

A utilidade real do EVA é servir como ponte entre a capacidade linguística das IAs e a necessidade humana de responsabilidade documental.

Ele pode ajudar o progresso quando:

- torna conhecimento especializado mais acessível sem apagar sua origem;
- reduz o risco de decisões baseadas em afirmações sem fonte;
- permite auditoria de respostas em educação, ciência, indústria e administração pública;
- preserva diferenças entre documento original, síntese e interpretação;
- impede que cada conversa reescreva silenciosamente a memória institucional;
- oferece uma arquitetura aberta para experimentar RAG verificável;
- permite substituir modelos sem reconstruir toda a lógica documental;
- explicita quando o acervo não sustenta uma conclusão.

Hoje, seu impacto é principalmente metodológico e organizacional. O EVA demonstra uma maneira coerente de restringir modelos generativos a contratos de evidência. Ainda não há base para afirmar impacto mundial, superioridade sobre RAG convencional ou economia energética líquida.

A formulação mais justa é:

> O EVA é uma infraestrutura promissora de confiança documental para IA. Seu valor atual é real em acervos curados e auditáveis; seu valor mundial dependerá de validação independente, escalabilidade, novos formatos e adoção institucional.

## Riscos de interpretação

### Evidência não significa verdade

O estado `validated` de uma evidência primária comprova que o conteúdo foi extraído literalmente e pode ser rastreado. Ele não comprova que a afirmação contida no documento é verdadeira.

### Determinismo não significa ausência de viés

O CIE é determinístico para o mesmo conjunto de similaridades, mas os embeddings são produzidos por modelos, as sínteses são interpretações geradas e as regras locais refletem decisões de projeto. O sistema reduz arbitrariedade em etapas específicas; ele não elimina vieses linguísticos, documentais ou de modelo.

### Citação não significa implicação semântica

A validação local confirma IDs, presença de citações, quantidade mínima de palavras e literalidade de fragmentos. Ela não prova automaticamente que a frase gerada é uma consequência semanticamente correta da fonte citada.

### Recusa melhora segurança, mas reduz disponibilidade

Bloquear uma resposta sem citação documental válida ou com citação fora do contexto evita exposição de conteúdo não verificável. Uma candidata apenas recuperada, mas não citada, é descartada sem causar esse bloqueio. Confiabilidade e disponibilidade precisam ser medidas em conjunto.

## Prioridades para transformar potencial em impacto comprovado

1. Executar benchmark independente e multidisciplinar contra RAG por chunks, contexto longo, GraphRAG e RAG com reranker.
2. Introduzir e calibrar um limiar absoluto de não pertinência, medindo precisão, recall e recusa correta.
3. Migrar a busca para um índice vetorial ou ANN, preservando no MySQL a identidade e a linhagem documental.
4. Aplicar orçamento real de tokens ao contexto final e às unidades de síntese.
5. Criar ciclo de vida documental com versão, validade, aprovação, deduplicação, atualização e metadados.
6. Adicionar PDF, DOCX, HTML, OCR e conteúdo multimodal com proveniência por página ou região.
7. Implementar defesa contra prompt injection documental, rate limiting, MFA e recuperação de jobs abandonados.
8. Medir custo, latência, tokens, qualidade e energia continuamente.
9. Publicar protocolos, corpora permitidos e resultados com dispersão e reprodutibilidade.
10. Realizar validação externa por especialistas dos setores em que o EVA pretende operar.

Essas evoluções não exigem abandonar o princípio central do EVA. Elas transformariam uma arquitetura conceitualmente forte em uma plataforma comprovável, escalável e apta a gerar impacto institucional.

## Conclusão

O EVA possui uma identidade técnica clara: memória documental estruturada, fonte antes de interpretação, seleção local antes de geração, rastreabilidade até evidências primárias e interações sem persistência cognitiva artificial.

Essa identidade responde a problemas reais da evolução da IA: confabulação, opacidade, perda de proveniência, crescimento do contexto, custo computacional e dificuldade de auditoria.

Seu maior valor atual não é substituir modelos de fronteira, competir com mecanismos gerais de inteligência ou automatizar decisões humanas. É disciplinar como modelos generativos entram em contato com conhecimento institucional.

Se o sistema comprovar qualidade, recusa correta, eficiência e escalabilidade em avaliações independentes, poderá contribuir para uma classe de IA mais verificável e responsável. Até lá, deve ser apresentado com precisão: um produto funcional e uma proposta arquitetural relevante, com impacto real localizado e potencial global ainda por demonstrar.
