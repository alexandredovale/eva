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
recuperação hierárquica
↓
κq
↓
CIE hierárquico
↓
resolução da linhagem
↓
evidências primárias
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

# 1. κq — fronteira da consulta na camada hierárquica

`κq` atua primeiro, sobre os **resumos hierárquicos derivados** de cada documento.

O fluxo inicial é:

```text
Pergunta do usuário
↓
embedding da pergunta
↓
todos os resumos hierárquicos elegíveis da obra
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

Formalmente, ele trabalha sobre a população completa de resumos hierárquicos elegíveis, utilizando rank, similaridade normalizada e gaps entre candidatos.

Se não existir uma ruptura suficientemente clara, **nenhum corte é inventado**.

Nesse caso, toda a população segue para o CIE hierárquico.

Portanto, a pergunta conceitual representada pelo κq é:

```text
κq =
onde termina a população hierárquica
plausivelmente relacionada à pergunta?
```

---

# 2. CIE hierárquico — organização estatística da população

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

Nesta etapa hierárquica, núcleo e convergência são preservados porque ainda serão resolvidos até suas fontes primárias e analisados separadamente.

---

# 3. Da hierarquia para as evidências primárias

Até esse momento, o EVA está trabalhando principalmente com **sínteses derivadas**.

Mas uma síntese não é a fonte documental final da resposta.

O sistema precisa retornar ao conteúdo literal que originou aquela síntese.

Por isso, percorre a linhagem:

```text
Resumo selecionado
↓
evidence_derivations
↓
outros resumos inferiores
↓
evidências primárias
↓
texto documental literal
```

A arquitetura exige que uma evidência derivada selecionada seja resolvida integralmente até suas fontes primárias antes da geração da resposta.

É nesse ponto que aparece o **κe**.

---

# 4. κe — fronteira aplicada às evidências primárias

`κe` atua agora sobre as **evidências primárias** recuperadas através da linhagem.

A pergunta continua representada pelo mesmo embedding transitório.

O fluxo é:

```text
Pergunta
↓
embedding já existente
↓
evidências primárias encontradas pela linhagem
↓
similaridade cosine
↓
κe
```

A diferença fundamental entre κq e κe é:

```text
κq
atua sobre:
resumos hierárquicos

κe
atua sobre:
evidências primárias
```

Assim, o EVA primeiro encontra **onde procurar no documento** e depois verifica **quais conteúdos literais daquela região continuam semanticamente relacionados à pergunta**.

Uma forma simples de compreender a diferença é:

```text
κq:
"qual região conceitual da obra parece pertinente?"

κe:
"dentro dessa região,
quais evidências literais continuam pertinentes?"
```

---

# 5. CIE primário — seleção entre as evidências reais

Depois do κe entra outro CIE.

Agora o cálculo não ocorre mais sobre resumos derivados, mas sobre **evidências primárias**.

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

Essa análise ocorre **por obra e pela região hierárquica herdada**.

Por exemplo:

```text
Documento A
    núcleo hierárquico
        ↓
      κe
        ↓
    CIE primário
```

E separadamente:

```text
Documento A
    convergência hierárquica
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

Se, excepcionalmente, o núcleo estiver vazio, a faixa de convergência funciona como fallback.

---

# 8. O pipeline completo

A arquitetura atual pode ser visualizada da seguinte forma:

```text
PERGUNTA
│
├─ embedding
│
▼
TODOS OS RESUMOS HIERÁRQUICOS
│
├─ cosine
│
├─ κq
│
▼
CIE HIERÁRQUICO
│
├─ núcleo
├─ convergência
└─ descarte
│
▼
RESOLUÇÃO DA LINHAGEM
│
▼
EVIDÊNCIAS PRIMÁRIAS
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
| **κq** | resumos hierárquicos | encontrar uma fronteira natural da consulta na hierarquia |
| **CIE hierárquico** | resumos sobreviventes | separar núcleo, convergência e descarte |
| **κe** | evidências primárias | refinar a pertinência no conteúdo literal |
| **CIE primário** | evidências primárias | formar núcleos locais de cada obra |
| **CIE global** | núcleos primários das obras | consolidar o contexto multidocumental final |

---

# 11. Interpretação conceitual simplificada

O fluxo também pode ser entendido em linguagem natural.

## κq

Pergunta:

```text
Em que regiões conceituais desta obra
vale a pena procurar?
```

## CIE hierárquico

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

## Escala 1 — hierarquia

```text
CIE hierárquico
```

Organiza os resumos estruturais da obra.

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
hierarquia
↓
conteúdo literal
↓
corpus multidocumental
```

corresponde a:

```text
CIE hierárquico
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
resolver a linhagem
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
fronteira na hierarquia

CIE hierárquico
=
organização estatística da hierarquia

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

- sínteses ascendentes rastreáveis — concluído;
- evidências `primary`/`derived` e tipos semânticos — concluído;
- derivações de origem — concluído;
- embeddings contextuais de unidades completas — concluído;
- versionamento e retomada sem chamadas duplicadas — concluído.

## Fase 4 — Consulta

- detecção de input direto, estrutural, conceitual, relacional ou amplo — concluído;
- busca adaptativa em evidências primárias e derivadas — concluído;
- resolução de sínteses até fontes primárias — concluído;
- Cnode definido como derivação conceitual interna e transitória do EVA, sem hierarquia ou persistência — concluído;
- `simetry`/`assimetry` na mesma chamada de resposta — concluído;
- validação de participantes, orientação, citações e fragmentos literais — concluído;
- ausência de persistência relacional — concluído.

## Fase 5 — Produto

- interface administrativa e de consulta — concluído;
- fila limitada a sínteses e embeddings — concluído;
- configuração white label — concluído;
- auditoria, métricas e controles de acesso — concluído;
- testes sem consumo externo — concluído.

## Upgrade arquitetural — Evidence Algorithm como padrão

- remoção de `cnodes`, `cnode_evidences`, `cnode_embeddings` e `interaction_analyses` — concluído;
- remoção da etapa persistente `cnodes` — concluído;
- recuperação semântica usando classe, tipo e linhagem das evidências — concluído;
- interações exclusivamente contextuais e não persistentes — concluído;
- documentação, produto e testes atualizados — concluído.

As cinco fases e o primeiro upgrade arquitetural estão concluídos. Novas fases devem partir de uso real do produto sem reintroduzir entidades ou pesos relacionais redundantes.

## Upgrade arquitetural — Context Intelligence Engine

- substituição do Top-k por κq query-local sobre a hierarquia completa — concluído;
- média, desvio padrão populacional e coeficiente de variação — concluído;
- regiões de descarte, convergência e núcleo — concluído;
- fallback determinístico para convergência quando não houver núcleo — concluído;
- resolução integral de linhagem somente após a seleção estatística, preservando a região herdada — concluído;
- κe e CIE primário separados para fontes herdadas de núcleo e convergência — concluído;
- união deduplicada dos núcleos primários locais e CIE global de consolidação — concluído;
- remoção de `QUERY_MAX_EVIDENCE` das rotas semânticas e isolamento de `QUERY_NON_SEMANTIC_MAX_EVIDENCE` — concluído;
- saída transitória auditável em `context_intelligence` — concluído;
- núcleo como população eleita em cada estágio, com fallback para convergência somente quando vazio — concluído;
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
