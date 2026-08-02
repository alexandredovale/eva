# EVA Platform

# Context Intelligence Engine (CIE)

> **Estado implementado em 2 de agosto de 2026:** o núcleo lidera o contexto final e a faixa de convergência participa como análise complementar obrigatória. Quando não há núcleo, a convergência assume o papel principal. Depois da resolução até fontes primárias, a eleição é vinculante para a LLM: todos os IDs devem ser aceitos e cada evidência precisa ser incorporada à prosa analítica. A aplicação rejeita citações ausentes e inventários isolados. A formulação original abaixo é preservada como registro da proposta que originou o CIE.

## Proposta Arquitetural

### Objetivo

Adicionar uma camada matemática entre o mecanismo de recuperação vetorial (Retriever) e as Camadas Cognitivas do EVA.

Esta camada não substitui os operadores tradicionais de busca vetorial, como Similaridade de Cosseno, mas atua como um mecanismo estatístico de estabilização do contexto recuperado.

O objetivo não é julgar documentos, atribuir notas ou criar heurísticas subjetivas.

O objetivo é permitir que a própria distribuição matemática dos candidatos determine quais contextos representam o núcleo de convergência da consulta.

---

# Arquitetura

```text
Consulta
      │
      ▼
Retriever
      │
      ▼
Top-k (ex.: 30 candidatos)
      │
      ▼
Context Intelligence Engine (CIE)
      │
      ▼
Contexto Final
      │
      ▼
Camadas Cognitivas
      │
      ▼
LLM
```

---

# Mudança de Paradigma

Arquitetura tradicional:

```text
Retriever
      │
      ▼
Top-k
      │
      ▼
LLM
```

Arquitetura EVA:

```text
Retriever
      │
      ▼
Top-k
      │
      ▼
Análise Estatística da Distribuição
      │
      ▼
Contexto Final
      │
      ▼
LLM
```

O Retriever deixa de determinar sozinho o contexto enviado ao modelo.

Ele passa a fornecer um conjunto de candidatos que será analisado matematicamente antes da geração da resposta.

---

# Pipeline Estatístico

Após recuperar os candidatos, o CIE executa:

```text
Top-30

↓

Cálculo da Média (μ)

↓

Cálculo do Desvio Padrão (σ)

↓

Cálculo do Coeficiente de Variação (CV)

↓

Definição da Faixa de Convergência
(μ até μ + σ)

↓

Identificação do Núcleo
(acima de μ + σ)

↓

Descarte
(abaixo de μ)

↓

Contexto Final
```

---

# Variáveis

Considere:

* **N** = quantidade de candidatos recuperados.
* **sᵢ** = similaridade vetorial do candidato *i*.

---

# Média

A média representa o centro da distribuição das similaridades.

```text
μ = (Σ sᵢ) / N
```

---

# Desvio Padrão

O desvio padrão mede a dispersão da distribuição.

```text
σ = √[(Σ(sᵢ − μ)²)/N]
```

---

# Coeficiente de Variação

O coeficiente de variação mede a dispersão relativa da distribuição.

```text
CV = σ / μ
```

O CV não classifica documentos.

Ele informa o comportamento estatístico do conjunto recuperado.

Quanto menor o CV, mais homogêneo é o conjunto.

Quanto maior o CV, maior a dispersão entre os candidatos.

Esse indicador permitirá que futuras versões do CIE adaptem automaticamente o comportamento do mecanismo estatístico sem alterar sua arquitetura fundamental.

---

# Critério de Seleção

O Context Intelligence Engine não atribui notas.

O Context Intelligence Engine não cria rankings artificiais.

O Context Intelligence Engine apenas identifica regiões naturais da distribuição estatística produzida pelo mecanismo vetorial.

## Faixa de Convergência

```text
μ ≤ s < μ + σ
```

Representa o conjunto de contextos cuja similaridade permanece dentro da faixa de convergência estatística.

Esses contextos compõem a base contextual encaminhada às Camadas Cognitivas.

---

## Núcleo de Convergência

```text
s ≥ μ + σ
```

Representa os contextos cuja similaridade encontra-se acima da faixa de convergência.

Esses elementos constituem naturalmente o núcleo contextual da recuperação.

Nenhum peso artificial é atribuído.

Sua importância decorre exclusivamente de sua posição na distribuição estatística.

---

## Região de Descarte

```text
s < μ
```

Representa os contextos abaixo da média estatística.

Esses elementos são descartados por não pertencerem à região de convergência do conjunto recuperado.

---

# Neutralidade Matemática

O Context Intelligence Engine não executa qualquer julgamento semântico.

Ele não determina:

* melhor documento;
* pior documento;
* documento correto;
* documento incorreto;
* documento mais importante.

Toda decisão decorre exclusivamente da distribuição estatística produzida pelo próprio mecanismo vetorial.

Não existem pesos subjetivos.

Não existem regras heurísticas.

Não existem classificações produzidas pela IA.

---

# Benefícios Esperados

* redução do ruído contextual;
* aumento da estabilidade entre consultas semelhantes;
* maior consistência matemática do contexto enviado às Camadas Cognitivas;
* adaptação natural às características estatísticas da recuperação;
* preservação da imparcialidade do processo;
* desacoplamento entre recuperação vetorial e geração da resposta;
* maior reprodutibilidade dos resultados.

---

# Papel Arquitetural

O Context Intelligence Engine não substitui o Retriever.

Ele constitui uma camada intermediária responsável por analisar matematicamente a distribuição dos candidatos recuperados antes da geração da resposta.

Sua responsabilidade limita-se à observação da distribuição estatística produzida pelo mecanismo vetorial, preservando integralmente a neutralidade do EVA.

---

## Observação Operacional

Caso nenhum contexto seja identificado no **Núcleo de Convergência** (`s ≥ μ + σ`), o Context Intelligence Engine deverá utilizar como contexto final os elementos pertencentes à **Faixa de Convergência** (`μ ≤ s < μ + σ`).

Essa regra garante que toda consulta possua um conjunto contextual estatisticamente consistente, mesmo quando a distribuição das similaridades não produzir candidatos acima do limite superior da convergência, preservando a neutralidade matemática do algoritmo e evitando a adoção de heurísticas subjetivas.

---

# Princípio Fundamental

O EVA não escolhe documentos.

O EVA evidencia a estrutura matemática presente na distribuição dos contextos recuperados.

A organização final do contexto emerge exclusivamente da estatística do conjunto recuperado, tornando o processo objetivo, reproduzível, auditável e independente de julgamentos subjetivos.


---

# Análise Documental deste update





Sim. Eu diria que ela é elegante por cinco razões arquiteturais.

A primeira é a **separação de responsabilidades**.

O retriever continua fazendo exatamente aquilo para o qual foi projetado: recuperar candidatos. O CIE faz apenas a análise estatística. As Camadas Cognitivas continuam sintetizando o conhecimento. Cada componente possui uma única responsabilidade.

A segunda é que vocês **não estão competindo com o estado da arte**.

Muitas arquiteturas tentam substituir o retriever ou criar um reranker "melhor". O EVA não faz isso. Ele aceita qualquer retriever — FAISS, Qdrant, Pinecone, pgvector, Elasticsearch, Milvus — e acrescenta uma camada independente de análise. Isso reduz o acoplamento e preserva compatibilidade futura.

A terceira é a **determinabilidade**.

Dado o mesmo conjunto de 30 candidatos, o algoritmo sempre produzirá o mesmo resultado. Não existe aleatoriedade, nem influência de temperatura, nem julgamento por outro modelo de IA. Isso é extremamente valioso em ambientes que exigem auditoria.

A quarta é a **explicabilidade**.

Cada decisão pode ser reconstruída matematicamente. Você consegue responder:

- qual foi a média;
- qual foi o desvio padrão;
- qual foi o coeficiente de variação;
- por que um contexto permaneceu na convergência;
- por que outro entrou no núcleo;
- por que outro foi descartado.

Não existe "a IA decidiu". Existe apenas matemática.

A quinta é que vocês criaram um **ponto de expansão**.

Hoje o CIE possui um único analisador estatístico.

Amanhã ele poderá possuir vários analisadores.

```text
Context Intelligence Engine

├── Statistical Distribution Analyzer
├── Graph Analyzer
├── Temporal Analyzer
├── Semantic Topology Analyzer
├── Entropy Analyzer
└── ...
```

O restante da arquitetura não muda.

---

A única observação técnica que eu faria é sobre a regra de seleção.

Hoje vocês têm:

- abaixo da média → descarte;
- média até média + σ → convergência;
- acima de média + σ → núcleo.

Ela é coerente e elegante, mas parte de uma hipótese implícita: **a média representa um bom ponto de corte**.

Essa hipótese deve ser validada experimentalmente. Em algumas bases vetoriais, a distribuição pode ser assimétrica ou apresentar concentrações muito altas próximas do topo. Se isso acontecer, talvez a fronteira ótima não seja exatamente a média, mas outro percentil ou uma medida robusta. Isso não invalida a arquitetura; apenas significa que o parâmetro de corte pode evoluir.

Na minha avaliação, o aspecto mais inovador não é o uso da média ou do desvio padrão isoladamente. É a decisão arquitetural de **transformar a seleção de contexto em um problema de análise da distribuição**, em vez de um problema de reranking por IA. Essa mudança de perspectiva é elegante, modular, explicável e suficientemente distinta para justificar um componente próprio dentro do EVA. Se os experimentos confirmarem ganho consistente em estabilidade e qualidade contextual, essa passa a ser uma contribuição técnica relevante da plataforma.
