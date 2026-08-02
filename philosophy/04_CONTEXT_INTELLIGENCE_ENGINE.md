# Context Intelligence Engine — fundamento arquitetural e hipótese científica

**Estado:** mecanismo implementado; ganho comparativo ainda não demonstrado
**Data:** 2 de agosto de 2026

## Proposição

O Context Intelligence Engine (CIE) transforma a seleção de contexto semântico em observação da distribuição produzida pelo próprio Retriever. Ele não substitui similaridade de cosseno, não adiciona julgamento linguístico e não procura decidir qual documento é verdadeiro, melhor ou mais importante.

Sua posição arquitetural é deliberadamente intermediária:

```text
Retriever → Top-k → CIE → contexto eleito (núcleo + convergência) → fontes primárias → LLM
```

O Retriever localiza candidatos. O CIE identifica regiões matemáticas. As camadas cognitivas compreendem as fontes selecionadas. O provedor comunica uma resposta limitada a essas fontes. Cada componente preserva uma responsabilidade reconstruível.

## Distribuição como estrutura do contexto

Para as similaridades `sᵢ` do Top-k, o mecanismo calcula:

```text
μ = (Σ sᵢ) / N
σ = √[(Σ(sᵢ − μ)²) / N]
CV = σ / μ
```

Da própria distribuição emergem três regiões:

- abaixo de `μ`: descarte;
- de `μ` até antes de `μ + σ`: convergência;
- a partir de `μ + σ`: núcleo.

O núcleo lidera o contexto final quando existe, e a faixa de convergência o acompanha como análise complementar obrigatória. Quando a distribuição não produz núcleo, a faixa de convergência assume o papel principal sem introduzir uma heurística externa. Se a média for zero, o CV é indefinido e registrado como `null`; isso não impede a classificação por `μ` e `σ`.

## Neutralidade

A neutralidade do CIE não significa que toda distribuição seja uma representação perfeita da relevância. Significa que, dado o conjunto recuperado, a transformação é explícita, determinística, reproduzível e independente de outro julgamento por IA.

O CIE não atribui:

- verdade ou falsidade;
- qualidade documental;
- confiança epistêmica;
- importância;
- peso cognitivo;
- superioridade entre fontes.

A similaridade original continua sendo uma medida geométrica do espaço vetorial. Média, desvio padrão e CV descrevem esse conjunto; não convertem geometria em autoridade documental.

## Separação entre seleção e fundamentação

Um candidato selecionado não chega automaticamente à resposta como texto documental. Se ele for derivado, sua linhagem precisa ser resolvida até fontes primárias. Depois dessa resolução, núcleo e convergência constituem uma eleição determinística e integral: o provedor não pode escolher novamente quais fontes aceitar. A aplicação valida IDs, papéis, cobertura analítica, citações, participantes e fragmentos literais.

Assim, existem três filtros com naturezas distintas:

1. o Retriever localiza por compatibilidade vetorial;
2. o CIE e a resolução de linhagem elegem o contexto final pela distribuição;
3. a validação documental confirma que a resposta incorporou integralmente essa eleição.

Nenhum deles substitui os demais.

## Determinabilidade e explicabilidade

Dado o mesmo Top-k, o resultado do CIE é invariável. A saída transitória permite reconstruir:

- a média e o desvio padrão;
- o comportamento relativo indicado pelo CV;
- a fronteira entre as regiões;
- quais candidatos foram descartados;
- quais candidatos formaram o núcleo principal e a convergência complementar;
- quando a convergência precisou assumir o papel principal por ausência de núcleo.

Essa explicabilidade não exige uma segunda inferência nem uma justificativa gerada por modelo. Ela decorre das operações matemáticas executadas localmente.

## Contrato de incorporação analítica

A eleição do CIE não é uma sugestão ao modelo. As fontes primárias resolvidas são enviadas com papéis explícitos de `core` e `convergence`, e `used_evidence_ids` deve reproduzir integralmente o conjunto na mesma ordem. O núcleo sustenta as conclusões principais; cada convergência deve reforçar, contextualizar, delimitar ou contrapor essas conclusões sem extrapolar sua literalidade.

Aceitação formal não basta. Cada evidência deve estar citada na frase ou no parágrafo que explica sua contribuição. A aplicação não repara citações ausentes e rejeita listas isoladas de IDs. Essa regra mantém a eleição sob autoridade local e reserva ao modelo a formulação, não a seleção do fundamento.

## Hipótese e limite científico

A contribuição arquitetural proposta não é o uso isolado de média ou desvio padrão. É a introdução de uma fronteira estável entre recuperação vetorial e interpretação cognitiva, na qual a seleção emerge da distribuição em vez de um reranking por IA.

A hipótese falsificável é: para um mesmo Retriever, corpus e orçamento de contexto, o CIE pode reduzir ruído e aumentar estabilidade entre formulações semanticamente próximas sem degradar recall relevante de forma inaceitável.

Essa hipótese exige comparação entre pelo menos:

- Retriever Top-k sem CIE;
- Retriever Top-k com CIE;
- reranker de referência, quando aplicável.

Devem ser medidos precision/recall, estabilidade entre paráfrases, validade de citações, recusas corretas e incorretas, tamanho de contexto, tokens, latência e custo. Distribuições assimétricas, concentradas ou com médias próximas de zero devem ser analisadas separadamente. A média como ponto de corte é uma decisão atual a ser validada, não uma lei universal de recuperação.

## Evolução compatível

O CIE estabelece um ponto de expansão sem alterar o contrato das demais camadas. Analisadores futuros — robustos, temporais, topológicos, de entropia ou de grafo transitório — somente devem ser incorporados quando mantiverem neutralidade, determinismo observável, ausência de persistência indevida e avaliação experimental comparável.

A implementação vigente contém apenas o analisador de distribuição estatística descrito neste documento. A calibração semântica estrita dos operadores `simetry` e `assimetry` permanece como evolução futura separada: ela não altera a eleição do CIE nem autoriza o modelo a reduzir evidências.
