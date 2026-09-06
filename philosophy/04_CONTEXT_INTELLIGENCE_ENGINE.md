# Context Intelligence Engine — fronteiras query-local e consolidação global

**Estado:** mecanismo implementado; ganho comparativo ainda não demonstrado
**Atualização:** 8 de agosto de 2026
**English:** [Context Intelligence Engine — query-local boundaries and global consolidation](en/04_CONTEXT_INTELLIGENCE_ENGINE.md)

## Proposição

O Context Intelligence Engine (CIE) transforma a seleção de contexto semântico em observação determinística das distribuições produzidas pelo Retriever. Ele não substitui similaridade de cosseno, não atribui relevância absoluta e não decide qual documento é verdadeiro, melhor ou mais importante.

O fluxo vigente é:

```text
consulta q
  → população primária completa por obra
  → κq
  → primeiro CIE sobre fontes
  → núcleo superior ou fallback de convergence
  → cosine primário
  → κe + CIE primário por região e obra
  → união deduplicada dos núcleos primários locais Gq
  → CIE global
  → núcleo global Eq (ou convergence se o núcleo estiver vazio)
  → LLM
```

Correspondências literais exatas que não pertencem à população primária vetorial são âncoras protegidas e acompanham `Eq`.

## Por que não existe Top-k semântico

Um Top-k conhecido antes da consulta definiria artificialmente a população sobre a qual média, desvio padrão e CV são calculados. O EVA calcula cosine para todas as evidências `primary:node_content` validadas e vetorizadas de cada obra e deixa a ruptura κq emergir da curva ordenada.

Se κq não encontra ruptura identificável — população pequena, ausência de dispersão, curva contínua ou ruptura ambígua — a população completa segue ao CIE. Nenhum corte substituto é fabricado.

## Matemática intocada do CIE

Para `N` similaridades `sᵢ`:

```text
μ = (Σ sᵢ) / N
σ = √[(Σ(sᵢ − μ)²) / N]
CV = σ / μ
```

Quando `μ = 0`, `CV` é exposto como `null`. As regiões são sempre:

```text
discard:     s < μ
convergence: μ ≤ s < μ + σ
core:        s ≥ μ + σ
```

Se `core` estiver vazio, `convergence` é promovida. Se `σ = 0`, todos os valores iguais a `μ` pertencem ao núcleo. A implementação usa tolerância numérica relativa de `1e-12` apenas para impedir erro de classificação por ponto flutuante; as fórmulas não mudam.

## Três aplicações, três responsabilidades

### 1. CIE inicial sobre fontes

κq legitima a população de evidências primárias por obra. O CIE classifica descarte, convergência e núcleo. Neste estágio, somente o núcleo `s ≥ μ + σ` segue para a próxima etapa; a convergência é encaminhada apenas como fallback quando o núcleo está vazio.

### 2. κe e CIE primário

Como a população inicial já é primária, não há resolução de sínteses nessa passagem. Cada fonte herda a região inicial encaminhada: `core` prevalece e `convergence` aparece somente como fallback. O mesmo embedding transitório da consulta é reutilizado para calcular cosine contra os embeddings primários, sem nova chamada externa.

κe é calculado separadamente sobre as primárias herdadas de `core` e de `convergence`, em cada obra. Cada população legitimada recebe seu próprio CIE. O núcleo local é o `core` primário ou, quando vazio, sua `convergence`.

### 3. CIE global

Os núcleos primários locais são unidos e deduplicados:

```text
Gq = ⋃d (Ld,core ∪ Ld,convergence)
```

Como todos os cosines primários usam a mesma consulta e o mesmo modelo de embedding, o CIE pode classificar `Gq` globalmente. A população entregue à LLM é:

```text
Eq = CoreG, se CoreG ≠ ∅
Eq = ConvG, se CoreG = ∅
K(q) = |Eq|
```

`K(q)` não é configurado nem conhecido antes da consulta. O papel herdado `core` ou `convergence` permanece anexado à fonte final, mesmo que sua região no CIE global seja `core`.

## Cantelli como limite, não como quota

Para variância não nula, a desigualdade unilateral de Cantelli estabelece:

```text
P(X − μ ≥ σ) ≤ 1/2
```

Logo, o núcleo `s ≥ μ + σ` contém no máximo metade da população analisada. Isso não ordena selecionar metade e não define uma porcentagem; é apenas um teto matemático derivado da fronteira preservada do CIE. Em distribuições homogêneas (`σ = 0`), a premissa de variância não nula não se aplica e todos os candidatos permanecem no núcleo.

## Neutralidade e determinabilidade

Dadas as mesmas populações ordenadas e similaridades, a saída é invariável. O fluxo não:

- cria pesos, notas ou thresholds semânticos configuráveis;
- usa histórico, feedback ou reranking por IA;
- faz chamadas extras de embedding ou múltiplas chamadas de resposta em lotes;
- persiste vetores do input, scores, estatísticas, regiões ou contexto;
- altera evidências, derivações ou embeddings;
- usa similaridade como confiança epistêmica.

`QUERY_NON_SEMANTIC_MAX_EVIDENCE` atua somente nas rotas direta, estrutural e ampla. Não participa de κq, κe ou de qualquer CIE.

## Contrato com a LLM

O provedor recebe `Eq`, âncoras literais protegidas e os papéis iniciais herdados. Ele não recebe liberdade para buscar fontes externas. A presença de uma fonte no contexto autoriza seu uso, mas não obriga uma citação decorativa: somente fontes incorporadas analiticamente à prosa e citadas de forma visível permanecem em `used_evidence_ids`.

## Auditabilidade

`context_intelligence` identifica cada análise por `stage`:

- `hierarchical`: nome preservado por compatibilidade para o primeiro CIE sobre fontes após κq;
- `primary`: CIE após κe, com `source_region` igual a `core` ou `convergence`;
- `global`: CIE final sobre `Gq`.

Cada item expõe transitoriamente população, `μ`, `σ`, `CV`, limites, região eleita, núcleo, convergência, descarte e diagnóstico κ quando aplicável.

## Limite científico

Determinismo não prova relevância. O fluxo continua dependente da qualidade do corpus, da estrutura documental, das sínteses, dos embeddings e da capacidade de κ identificar rupturas úteis. CIE usa fronteiras relativas e não possui um limiar absoluto de “nenhum candidato relacionado”. O núcleo global também não garante cobertura equilibrada de todas as disciplinas.

A hipótese falsificável é que, sob o mesmo corpus, modelos e protocolo, o fluxo atual reduza ruído, tokens e instabilidade entre paráfrases sem perda inaceitável de recall. Isso exige comparação representativa com baselines de Top-k fixo, reranking e contexto longo; o teste funcional atual não demonstra superioridade estatística.

## Referências operacionais

- [Contrato determinístico de evidências](05_DETERMINISTIC_EVIDENCE_CONTRACT.md)
- [Fluxo detalhado da API](03_EVA_API_FLOW.md)
- [Documentação operacional do CIE](../docs/14_CONTEXT_INTELLIGENCE_ENGINE.md)
- [Consulta documental e composição da resposta](../docs/05_CONSULTA.md)
