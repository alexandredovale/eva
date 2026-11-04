# Roadmap

## Introdução

# κq, κe e os CIEs no EVA

## Visão geral

No EVA, **κq, κe e os CIEs são mecanismos diferentes, usados em momentos diferentes da recuperação semântica**.

Eles não julgam qualidade, verdade, importância ou relevância em sentido subjetivo. Sua função é organizar matematicamente quais evidências seguem adiante durante uma consulta conceitual ou relacional.

A arquitetura trabalha em camadas:

```text
Pergunta
↓
recuperação primária
↓
κq
↓
CIE inicial sobre fontes
↓
fontes primárias selecionadas
↓
κe
↓
CIE primário
↓
união dos núcleos locais
↓
CIE global
↓
contexto final
↓
LLM
↓
resposta citada
```

---

# 1. κq — fronteira da consulta na camada primária inicial

`κq` atua primeiro, sobre as **evidências primárias validadas e vetorizadas** de cada documento.

O fluxo inicial é:

```text
Pergunta do usuário
↓
embedding da pergunta
↓
todas as evidências primárias elegíveis da obra
↓
similaridade cosine
↓
ordenação
↓
κq
```

A função do κq é procurar uma **ruptura natural na distribuição das similaridades**.

Um exemplo simplificado:

```text
0,88
0,86
0,84
0,81
0,79
---------
0,61
0,59
0,57
0,54
```

A diferença entre `0,79` e `0,61` pode indicar que existe uma fronteira natural entre um grupo semanticamente mais próximo da pergunta e o restante da população.

O EVA não adota uma regra fixa do tipo:

```text
pegue os 20 melhores
```

O κq procura fazer com que **a própria distribuição daquela consulta determine a fronteira**.

Formalmente, ele trabalha sobre a população completa de evidências primárias elegíveis, utilizando rank, similaridade normalizada e gaps entre candidatos.

Se não existir uma ruptura suficientemente clara, **nenhum corte é inventado**.

Nesse caso, toda a população segue para o CIE inicial sobre fontes.

Portanto, a pergunta conceitual representada pelo κq é:

```text
κq =
onde termina a população primária
plausivelmente relacionada à pergunta?
```

---

# 2. CIE inicial — organização estatística das fontes

Depois do κq entra o primeiro **Context Intelligence Engine**.

O CIE calcula:

```text
μ = média das similaridades

σ = desvio padrão populacional

CV = σ / μ
```

A partir desses valores, a população é dividida em três regiões:

```text
s < μ
→ descarte

μ ≤ s < μ + σ
→ convergência

s ≥ μ + σ
→ núcleo
```

Exemplo:

```text
μ = 0,70
σ = 0,08
```

Então:

```text
Descarte:
s < 0,70

Convergência:
0,70 ≤ s < 0,78

Núcleo:
s ≥ 0,78
```

O **núcleo** representa a região estatisticamente mais concentrada em relação à pergunta.

A **convergência** representa a região intermediária.

O **descarte** representa os candidatos abaixo da média da distribuição.

Nesta etapa inicial, somente o núcleo `s ≥ μ + σ` é encaminhado. A convergência é usada apenas como fallback quando o núcleo está vazio. O estágio continua exposto como `hierarchical` no contrato de diagnóstico por compatibilidade.

---

# 3. Da primeira seleção ao refinamento primário

Desde o primeiro cálculo, o EVA trabalha exclusivamente com **evidências primárias**.

O primeiro CIE encaminha seu núcleo superior ou, quando esse núcleo está vazio, sua convergência. Não existe etapa intermediária de síntese ou resolução de linhagem.

O fluxo encaminhado é:

```text
núcleo primário inicial
ou fallback de convergência
↓
texto documental literal
```

Essas fontes já literais seguem para o **κe**, preservando os cálculos posteriores do fluxo.

---

# 4. κe — fronteira aplicada às evidências primárias

`κe` atua sobre as **evidências primárias** encaminhadas pelo primeiro CIE.

A pergunta continua representada pelo mesmo embedding transitório.

O fluxo é:

```text
Pergunta
↓
embedding já existente
↓
evidências primárias do núcleo inicial ou fallback
↓
similaridade cosine
↓
κe
```

A diferença fundamental entre κq e κe é:

```text
κq
atua sobre:
evidências primárias

κe
atua sobre:
evidências primárias
```

Assim, o EVA primeiro delimita **quais fontes se destacam na população primária completa** e depois verifica **quais delas permanecem semanticamente relacionadas na população local sobrevivente**.

Uma forma simples de compreender a diferença é:

```text
κq:
"quais fontes se destacam na população completa da obra?"

κe:
"entre as fontes encaminhadas,
quais evidências continuam pertinentes?"
```

---

# 5. CIE primário — seleção entre as evidências reais

Depois do κe entra outro CIE.

O cálculo continua sobre **evidências primárias**, agora restritas ao núcleo inicial ou ao fallback.

Novamente são calculados:

```text
μ
σ
CV
```

E novamente a distribuição é dividida em:

```text
descarte
convergência
núcleo
```

Existe, porém, uma característica importante.

Essa análise ocorre **por obra e pela região inicial herdada**.

Por exemplo:

```text
Documento A
    núcleo inicial
        ↓
      κe
        ↓
    CIE primário
```

Somente quando o núcleo inicial estiver vazio:

```text
Documento A
    fallback de convergência inicial
        ↓
      κe
        ↓
    CIE primário
```

O mesmo processo ocorre com os demais documentos participantes da consulta.

Isso significa que cada obra possui primeiro sua própria estabilização local antes de ocorrer a consolidação multidocumental.

---

# 6. Núcleos primários locais

Depois do processamento de cada documento, o EVA passa a possuir conjuntos de evidências primárias nucleares locais.

Exemplo:

```text
Documento A
→ 7 evidências nucleares

Documento B
→ 4 evidências nucleares

Documento C
→ 6 evidências nucleares

Documento D
→ 3 evidências nucleares
```

Esses conjuntos representam as evidências literais que sobreviveram ao processo local de recuperação e estabilização de cada obra.

---

# 7. CIE global — consolidação entre documentos

Os núcleos primários locais são então reunidos:

```text
Documento A ┐
Documento B │
Documento C ├→ união dos núcleos locais
Documento D ┘
```

Essa união forma a população submetida ao **CIE global**.

Novamente são calculados:

```text
μ
σ
CV
```

E novamente a população é dividida em:

```text
descarte
convergência
núcleo
```

A diferença é que, agora, a população contém evidências provenientes de **várias obras**.

O CIE global funciona, portanto, como a última consolidação estatística da consulta.

Seu núcleo representa o contexto principal autorizado para a geração.

O núcleo permanece como corte principal. Sua faixa de convergência é acrescentada ao contexto final como subsídio auxiliar; quando o núcleo estiver vazio, ela também funciona como fallback.

---

# 8. O pipeline completo

A arquitetura atual pode ser visualizada da seguinte forma:

```text
PERGUNTA
│
├─ embedding
│
▼
TODAS AS EVIDÊNCIAS PRIMÁRIAS
│
├─ cosine
│
├─ κq
│
▼
CIE INICIAL SOBRE FONTES
│
├─ núcleo
├─ convergência (somente fallback)
└─ descarte
│
▼
NÚCLEO SUPERIOR OU FALLBACK
│
├─ cosine
├─ κe
│
▼
CIE PRIMÁRIO
│
├─ núcleo
├─ convergência
└─ descarte
│
▼
NÚCLEOS LOCAIS DE TODAS AS OBRAS
│
▼
CIE GLOBAL
│
├─ núcleo global
├─ convergência
└─ descarte
│
▼
CONTEXTO FINAL
├─ núcleo global (base principal)
└─ convergência global (contexto auxiliar)
│
▼
LLM
│
▼
RESPOSTA COM EVIDÊNCIAS CITADAS
```

---

# 9. Diferença essencial entre κ e CIE

A distinção conceitual principal é:

> **κq e κe detectam fronteiras. Os CIEs classificam distribuições.**

Os κ procuram identificar até onde uma população semanticamente plausível deve seguir.

Os CIEs recebem essa população e a classificam estatisticamente em núcleo, convergência e descarte.

Portanto:

```text
κ
→ detecta uma fronteira da população

CIE
→ classifica estatisticamente a população
```

---

# 10. Resumo comparativo

| Elemento | Atua sobre | Função |
|---|---|---|
| **κq** | evidências primárias | encontrar uma fronteira natural da consulta nas fontes |
| **CIE inicial** | fontes sobreviventes a κq | encaminhar núcleo superior ou convergência como fallback |
| **κe** | evidências primárias | refinar a pertinência no conteúdo literal |
| **CIE primário** | evidências primárias | formar núcleos locais de cada obra |
| **CIE global** | núcleos primários das obras | consolidar núcleo principal e convergência auxiliar no contexto final |

---

# 11. Interpretação conceitual simplificada

O fluxo também pode ser entendido em linguagem natural.

## κq

Pergunta:

```text
Em que regiões conceituais desta obra
vale a pena procurar?
```

## CIE inicial

Pergunta:

```text
Entre essas regiões,
quais formam núcleo,
quais apenas convergem
e quais ficam abaixo da distribuição?
```

## κe

Pergunta:

```text
Dentro das regiões escolhidas,
quais evidências literais
continuam relacionadas à pergunta?
```

## CIE primário

Pergunta:

```text
Entre essas evidências reais,
quais constituem o núcleo local desta obra?
```

## CIE global

Pergunta:

```text
Considerando os núcleos encontrados
em todas as obras,
qual conjunto forma o contexto final
mais concentrado para esta consulta?
```

---

# 12. O que esses mecanismos não fazem

κq, κe e os CIEs não determinam:

- verdade;
- qualidade;
- autoridade;
- importância;
- prioridade;
- confiabilidade;
- correção;
- peso cognitivo;
- superioridade de uma fonte sobre outra.

Eles trabalham apenas sobre a **geometria transitória das similaridades produzidas naquela consulta**.

Portanto:

```text
alta similaridade
≠
verdade

núcleo
≠
fonte superior

convergência
≠
fonte inferior

descarte
≠
documento ruim
```

Uma evidência pode ser descartada em determinada consulta e ser nuclear em outra consulta completamente diferente.

---

# 13. Por que a arquitetura usa duas fronteiras

A existência de κq e κe evita que uma única decisão vetorial determine diretamente o contexto final.

O sistema realiza dois movimentos distintos.

Primeiro:

```text
pergunta
↓
regiões conceituais da obra
```

Depois:

```text
regiões conceituais
↓
conteúdo literal
```

Isso separa:

```text
localização conceitual
```

de:

```text
validação da pertinência documental primária
```

O κq atua na primeira dimensão.

O κe atua na segunda.

---

# 14. Por que existem três CIEs

Os três CIEs correspondem a três escalas diferentes do problema.

## Escala 1 — população primária completa

```text
CIE inicial
```

Organiza as evidências primárias elegíveis da obra e encaminha somente o núcleo superior ou seu fallback.

## Escala 2 — conteúdo literal

```text
CIE primário
```

Organiza as evidências primárias recuperadas dentro de cada obra e região herdada.

## Escala 3 — corpus multidocumental

```text
CIE global
```

Consolida os núcleos primários locais provenientes de todas as obras participantes da consulta.

Assim:

```text
fontes primárias completas
↓
conteúdo literal
↓
corpus multidocumental
```

corresponde a:

```text
CIE inicial
↓
CIE primário
↓
CIE global
```

---

# 15. O princípio arquitetural resultante

O EVA não envia diretamente ao modelo tudo aquilo que possui alguma similaridade com a pergunta.

Ele executa uma sequência de estabilizações:

```text
localizar
↓
delimitar
↓
classificar
↓
refinar
↓
classificar novamente
↓
consolidar entre obras
↓
classificar globalmente
↓
gerar
```

A LLM aparece somente depois de concluído o contexto documental autorizado.

Esse desenho preserva a separação entre:

```text
recuperação
≠
seleção estatística
≠
evidência
≠
interpretação generativa
```

---

# 16. Síntese final

A estrutura pode ser reduzida à seguinte ideia:

```text
κq
=
fronteira na população primária completa

CIE inicial
=
organização estatística das fontes

κe
=
fronteira nas evidências primárias

CIE primário
=
organização estatística das evidências de cada obra

CIE global
=
organização estatística multidocumental

resultado
=
contexto final autorizado para a LLM
```

O princípio central permanece:

> **κq e κe detectam fronteiras; os CIEs classificam distribuições.**

Nenhum deles decide o que é verdadeiro ou importante. Eles apenas determinam, de maneira transitória e matematicamente definida, quais regiões e evidências permanecem disponíveis para que a resposta final seja construída sobre fontes documentais rastreáveis.

---

## Referências internas do projeto EVA

Esta explicação corresponde à arquitetura documentada principalmente em:

- `02_ARQUITETURA.md`
- `04_CONSTRUCAO_COGNITIVA.md`
- `05_CONSULTA.md`
- `08_REGRAS.md`
- `14_CONTEXT_INTELLIGENCE_ENGINE.md`
- `01_VISAO_GERAL.md`


## Fase 1 — Fundação

- estrutura, configuração e esquema do banco — concluído;
- identificadores, estados e logs — concluído;
- modelo não julgamental `simetry`/`assimetry` — concluído.

## Fase 2 — Ingestão

- upload seguro — concluído;
- parsers Markdown, JSON e XML — concluído;
- árvore normalizada comum — concluído;
- documentos, nós e evidências primárias — concluído;
- testes com arquivos reais e inválidos — concluído.

## Fase 3 — Evidence Algorithm

- evidências `primary:node_content` literais — concluído;
- derivações de origem — concluído;
- embeddings contextuais de unidades completas — concluído;
- versionamento e retomada sem chamadas duplicadas — concluído.

## Fase 4 — Consulta

- detecção de input direto, estrutural, conceitual, relacional ou amplo — concluído;
- busca adaptativa em evidências primárias e derivadas — concluído;
- Cnode definido como derivação conceitual interna e transitória do EVA, sem hierarquia ou persistência — concluído;
- `simetry`/`assimetry` na mesma chamada de resposta — concluído;
- validação de participantes, orientação, citações e fragmentos literais — concluído;
- ausência de persistência relacional — concluído.

## Fase 5 — Produto

- interface administrativa e de consulta — concluído;
- fila limitada a embeddings primários — concluído;
- configuração white label — concluído;
- auditoria, métricas e controles de acesso — concluído;
- testes sem consumo externo — concluído.

## Upgrade arquitetural — Evidence Algorithm como padrão

- remoção de `cnodes`, `cnode_evidences`, `cnode_embeddings` e `interaction_analyses` — concluído;
- remoção da etapa persistente `cnodes` — concluído;
- recuperação semântica usando evidências primárias validadas — concluído;
- interações exclusivamente contextuais e não persistentes — concluído;
- documentação, produto e testes atualizados — concluído.

As cinco fases e o primeiro upgrade arquitetural estão concluídos. Novas fases devem partir de uso real do produto sem reintroduzir entidades ou pesos relacionais redundantes.

## Upgrade arquitetural — Context Intelligence Engine

- substituição do Top-k por κq query-local sobre a hierarquia completa — concluído;
- média, desvio padrão populacional e coeficiente de variação — concluído;
- regiões de descarte, convergência e núcleo — concluído;
- fallback determinístico para convergência quando não houver núcleo — concluído;
- encaminhamento direto das fontes após a seleção estatística, preservando a região herdada — concluído;
- κe e CIE primário preservados para as fontes encaminhadas pelo núcleo inicial ou por seu fallback de convergência — concluído;
- união deduplicada dos núcleos primários locais e CIE global de consolidação — concluído;
- remoção de `QUERY_MAX_EVIDENCE` das rotas semânticas e isolamento de `QUERY_NON_SEMANTIC_MAX_EVIDENCE` — concluído;
- saída transitória auditável em `context_intelligence` — concluído;
- núcleo como população eleita em cada estágio, com fallback para convergência somente quando vazio — concluído;
- convergência global acrescentada ao contexto final como subsídio auxiliar, sem alterar o corte do núcleo ou as fronteiras κ — concluído;
- contrato de `used_evidence_ids` derivado das citações visíveis, com descarte de candidatos omitidos — concluído;
- validação fechada da incorporação analítica, sem preenchimento automático ou inventário de citações — concluído;
- validação real de referência com 10/10 evidências incorporadas e sem truncamento — concluído;
- testes isolados sem banco ou chamadas externas — concluído;
- revalidação comparativa de qualidade, estabilidade, latência e tokens em corpus representativo — pendente.

## Evolução futura — semântica estrita das interações

- distinguir convergência temática de reciprocidade `simetry` por contrato demonstrável — futuro;
- exigir base explícita para as duas direções de `simetry` — futuro;
- reforçar a demonstração de origem e destino em `assimetry` sem inferir causalidade ou hierarquia — futuro;
- preservar a resposta documental válida quando nenhuma interação estrita puder ser comprovada — princípio vigente.

## Critério de avanço

Cada fase exige fluxo funcional, testes críticos, erros seguros e documentação coerente com o comportamento real.

## Próxima validação experimental — sustentabilidade energética

- medir joules por consulta e kWh por mil consultas em ambiente instrumentado;
- amortizar o custo de construção por diferentes volumes de consulta;
- comparar EVA, RAG vetorial por blocos, contexto longo, GraphRAG e RAG agente sob qualidade documental equivalente;
- separar consultas diretas, estruturais, amplas, conceituais, relacionais e controles negativos;
- registrar chamadas externas, embeddings, tokens, tempo de GPU, latência e reutilização da construção;
- publicar resultados com dispersão, configuração experimental e limites de generalização.

Até a execução desse protocolo, eficiência energética permanece uma hipótese arquitetural fundamentada nos mecanismos de contenção computacional, não uma superioridade experimental declarada. O protocolo completo está em [Sustentabilidade energética](13_SUSTENTABILIDADE_ENERGETICA.md).
