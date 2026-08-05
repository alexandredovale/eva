# Context Intelligence Engine — fundamento arquitetural e hipótese científica

**Estado:** mecanismo implementado; ganho comparativo ainda não demonstrado
**Data:** 2 de agosto de 2026

## Proposição

O Context Intelligence Engine (CIE) transforma a seleção de contexto semântico em observação da distribuição produzida pelo próprio Retriever. Ele não substitui similaridade de cosseno, não adiciona julgamento linguístico e não procura decidir qual documento é verdadeiro, melhor ou mais importante.

Sua posição arquitetural é deliberadamente intermediária:

```text
Retriever → Top-k → CIE → candidatos selecionados (núcleo + convergência) → fontes primárias disponíveis → LLM
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

O núcleo lidera o contexto disponível quando existe, e a faixa de convergência o acompanha como contexto complementar. Quando a distribuição não produz núcleo, a faixa de convergência assume o papel principal sem introduzir uma heurística externa. Se a média for zero, o CV é indefinido e representado como `null`; isso não impede a classificação por `μ` e `σ`.

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

Um candidato selecionado não chega automaticamente à resposta como texto documental. Se ele for derivado, sua linhagem precisa ser resolvida até fontes primárias. Depois dessa resolução, a aplicação compõe deterministicamente o contexto primário disponível, preservando os papéis `core` e `convergence` e o limite global. O provedor não pode introduzir fontes externas, mas pode omitir candidatas que não contribuam para a resposta; somente fontes citadas analiticamente permanecem na base final. A aplicação valida IDs, papéis, citações, participantes e fragmentos literais.

Assim, existem três filtros com naturezas distintas:

1. o Retriever localiza por compatibilidade vetorial;
2. o CIE e a resolução de linhagem compõem o contexto primário disponível pela distribuição;
3. a resposta cita o subconjunto que efetivamente contribui e a validação documental descarta candidatas não citadas, rejeitando referências externas ou decorativas.

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

## Contrato de contexto disponível e citação visível

O resultado do CIE não autoriza o modelo a buscar fora do contexto. As fontes primárias resolvidas são enviadas com papéis explícitos de `core` e `convergence`; o núcleo possui precedência e a convergência pode reforçar, contextualizar, delimitar ou contrapor as conclusões quando seu conteúdo literal contribuir. `used_evidence_ids` é derivado das citações visíveis e contém somente as fontes efetivamente incorporadas.

Aceitação formal não basta. Cada evidência mantida deve estar citada na frase ou no parágrafo que explica sua contribuição. A aplicação não repara citações ausentes, rejeita listas isoladas de IDs e descarta candidatas recuperadas sem citação. Essa regra mantém o universo autorizado sob controle local e permite ao modelo selecionar apenas o subconjunto documental pertinente, sem inventar relações para acomodar o restante.

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

A implementação vigente contém apenas o analisador de distribuição estatística descrito neste documento. A calibração semântica estrita dos operadores `simetry` e `assimetry` permanece como evolução futura separada: ela não altera a seleção do CIE, a composição do contexto disponível nem a regra de que somente evidências citadas integram a base final.
