# Roadmap

## Introduction

# κq, κe, and the CIEs in EVA

## Overview

In EVA, **κq, κe, and the CIEs are different mechanisms used at different stages of semantic retrieval**.

They do not judge quality, truth, importance, or relevance in a subjective sense. Their function is to mathematically organize which evidence proceeds during a conceptual or relational query.

The architecture operates in layers:

```text
Question
↓
primary retrieval
↓
κq
↓
first source CIE
↓
selected primary sources
↓
κe
↓
primary CIE
↓
union of local cores
↓
global CIE
↓
final context
↓
LLM
↓
cited response
```

---

# 1. κq — query boundary at the initial primary layer

`κq` acts first on the **validated and embedded primary evidence** of each document.

The initial flow is:

```text
User question
↓
question embedding
↓
all eligible primary evidence of the work
↓
cosine similarity
↓
ranking
↓
κq
```

The function of κq is to look for a **natural break in the similarity distribution**.

A simplified example:

```text
0.88
0.86
0.84
0.81
0.79
---------
0.61
0.59
0.57
0.54
```

The difference between `0.79` and `0.61` may indicate a natural boundary between a group that is semantically closer to the question and the rest of the population.

EVA does not adopt a fixed rule such as:

```text
take the top 20
```

κq seeks to let **the distribution of that specific query determine the boundary itself**.

Formally, it operates on the complete population of eligible primary evidence, using rank, normalized similarity, and gaps between candidates.

If there is no sufficiently clear break, **no cutoff is invented**.

In that case, the entire population proceeds to the first source CIE.

Therefore, the conceptual question represented by κq is:

```text
κq =
where does the primary population
plausibly related to the question end?
```

---

# 2. First CIE — statistical organization of the sources

The first **Context Intelligence Engine** follows κq.

The CIE calculates:

```text
μ = mean of the similarities

σ = population standard deviation

CV = σ / μ
```

Based on these values, the population is divided into three regions:

```text
s < μ
→ discard

μ ≤ s < μ + σ
→ convergence

s ≥ μ + σ
→ core
```

Example:

```text
μ = 0.70
σ = 0.08
```

Therefore:

```text
Discard:
s < 0.70

Convergence:
0.70 ≤ s < 0.78

Core:
s ≥ 0.78
```

The **core** represents the region that is statistically most concentrated in relation to the question.

**Convergence** represents the intermediate region.

**Discard** represents candidates below the distribution mean.

At this initial stage, only the `s ≥ μ + σ` core is forwarded. Convergence is used only as fallback when that core is empty. The diagnostic contract continues to expose this stage as `hierarchical` for compatibility.

---

# 3. From first selection to primary refinement

From the first calculation onward, EVA 6.0.0 works exclusively with **primary evidence**.

The first CIE forwards its upper core or, when that core is empty, its convergence. There is no intermediate summary or lineage-resolution stage.

The forwarded flow is:

```text
initial primary core
or convergence fallback
↓
literal documentary text
```

These already literal sources proceed to **κe**, preserving the subsequent calculations.

---

# 4. κe — boundary applied to primary evidence

`κe` acts on the **primary evidence** forwarded by the first CIE.

The question continues to be represented by the same transient embedding.

The flow is:

```text
Question
↓
existing embedding
↓
primary evidence from the initial core or fallback
↓
cosine similarity
↓
κe
```

The fundamental difference between κq and κe is:

```text
κq
acts on:
primary evidence

κe
acts on:
primary evidence
```

EVA thus first determines **which sources stand out in the complete primary population** and then verifies **which remain semantically related in the surviving local population**.

A simple way to understand the difference is:

```text
κq:
"which sources stand out in the work's complete population?"

κe:
"among the forwarded sources,
which evidence remains pertinent?"
```

---

# 5. Primary CIE — selection among real evidence

Another CIE follows κe.

The calculation remains on **primary evidence**, now restricted to the initial core or fallback.

The following are calculated again:

```text
μ
σ
CV
```

And the distribution is again divided into:

```text
discard
convergence
core
```

There is, however, an important characteristic.

This analysis occurs **by work and by inherited initial region**.

For example:

```text
Document A
    initial core
        ↓
      κe
        ↓
    primary CIE
```

Only when the initial core is empty:

```text
Document A
    initial convergence fallback
        ↓
      κe
        ↓
    primary CIE
```

The same process occurs for the other documents participating in the query.

This means that each work first undergoes its own local stabilization before multidocument consolidation occurs.

---

# 6. Local primary cores

After each document has been processed, EVA has sets of local core primary evidence.

Example:

```text
Document A
→ 7 core evidence items

Document B
→ 4 core evidence items

Document C
→ 6 core evidence items

Document D
→ 3 core evidence items
```

These sets represent the literal evidence that survived the local retrieval and stabilization process for each work.

---

# 7. Global CIE — consolidation across documents

The local primary cores are then brought together:

```text
Document A ┐
Document B │
Document C ├→ union of local cores
Document D ┘
```

This union forms the population submitted to the **global CIE**.

The following are calculated again:

```text
μ
σ
CV
```

And the population is again divided into:

```text
discard
convergence
core
```

The difference is that the population now contains evidence from **multiple works**.

The global CIE therefore acts as the final statistical consolidation of the query.

Its core represents the main context authorized for generation.

Core remains the primary cutoff. Its convergence range is appended to final context as auxiliary support; when core is empty, it also acts as fallback.

---

# 8. The complete pipeline

The current architecture can be visualized as follows:

```text
QUESTION
│
├─ embedding
│
▼
ALL PRIMARY EVIDENCE
│
├─ cosine
│
├─ κq
│
▼
FIRST SOURCE CIE
│
├─ core
├─ convergence (fallback only)
└─ discard
│
▼
UPPER CORE OR FALLBACK
│
├─ cosine
├─ κe
│
▼
PRIMARY CIE
│
├─ core
├─ convergence
└─ discard
│
▼
LOCAL CORES FROM ALL WORKS
│
▼
GLOBAL CIE
│
├─ global core
├─ convergence
└─ discard
│
▼
FINAL CONTEXT
├─ global core (primary basis)
└─ global convergence (auxiliary context)
│
▼
LLM
│
▼
RESPONSE WITH CITED EVIDENCE
```

---

# 9. Essential difference between κ and CIE

The main conceptual distinction is:

> **κq and κe detect boundaries. The CIEs classify distributions.**

The κ mechanisms seek to determine how far a semantically plausible population should proceed.

The CIEs receive that population and statistically classify it into core, convergence, and discard.

Therefore:

```text
κ
→ detects a population boundary

CIE
→ statistically classifies the population
```

---

# 10. Comparative summary

| Element | Operates on | Function |
|---|---|---|
| **κq** | primary evidence | find a natural query boundary in the sources |
| **First CIE** | sources surviving κq | forward upper core or convergence fallback |
| **κe** | primary evidence | refine pertinence in the literal content |
| **Primary CIE** | primary evidence | form local cores for each work |
| **Global CIE** | primary cores of the works | consolidate primary core and auxiliary convergence in final context |

---

# 11. Simplified conceptual interpretation

The flow can also be understood in natural language.

## κq

Question:

```text
Which conceptual regions of this work
are worth searching?
```

## First CIE

Question:

```text
Among these regions,
which form the core,
which merely converge,
and which fall below the distribution?
```

## κe

Question:

```text
Within the selected regions,
which literal evidence remains
related to the question?
```

## Primary CIE

Question:

```text
Among this real evidence,
which items constitute this work's local core?
```

## Global CIE

Question:

```text
Considering the cores found
across all works,
which set forms the final context
most concentrated for this query?
```

---

# 12. What these mechanisms do not do

κq, κe, and the CIEs do not determine:

- truth;
- quality;
- authority;
- importance;
- priority;
- reliability;
- correctness;
- cognitive weight;
- superiority of one source over another.

They operate solely on the **transient geometry of the similarities produced in that query**.

Therefore:

```text
high similarity
≠
truth

core
≠
superior source

convergence
≠
inferior source

discard
≠
bad document
```

An evidence item may be discarded in one query and be part of the core in a completely different query.

---

# 13. Why the architecture uses two boundaries

The existence of κq and κe prevents a single vector decision from directly determining the final context.

The system performs two distinct movements.

First:

```text
question
↓
conceptual regions of the work
```

Then:

```text
conceptual regions
↓
literal content
```

This separates:

```text
conceptual localization
```

from:

```text
validation of primary documentary pertinence
```

κq acts in the first dimension.

κe acts in the second.

---

# 14. Why there are three CIEs

The three CIEs correspond to three different scales of the problem.

## Scale 1 — complete primary population

```text
first CIE
```

Organizes the work's eligible primary evidence and forwards only the upper core or its fallback.

## Scale 2 — literal content

```text
primary CIE
```

Organizes the primary evidence retrieved within each work and inherited region.

## Scale 3 — multidocument corpus

```text
global CIE
```

Consolidates the local primary cores from all works participating in the query.

Thus:

```text
complete primary sources
↓
literal content
↓
multidocument corpus
```

corresponds to:

```text
first CIE
↓
primary CIE
↓
global CIE
```

---

# 15. The resulting architectural principle

EVA does not send everything with some degree of similarity to the question directly to the model.

It executes a sequence of stabilizations:

```text
locate
↓
delimit
↓
classify
↓
refine
↓
classify again
↓
consolidate across works
↓
classify globally
↓
generate
```

The LLM appears only after the authorized documentary context has been completed.

This design preserves the separation between:

```text
retrieval
≠
statistical selection
≠
evidence
≠
generative interpretation
```

---

# 16. Final synthesis

The structure can be reduced to the following idea:

```text
κq
=
boundary in the complete primary population

first CIE
=
statistical organization of the sources

κe
=
boundary in primary evidence

primary CIE
=
statistical organization of each work's evidence

global CIE
=
multidocument statistical organization

result
=
final context authorized for the LLM
```

The central principle remains:

> **κq and κe detect boundaries; the CIEs classify distributions.**

None of them decides what is true or important. They only determine, transiently and in a mathematically defined manner, which regions and evidence remain available so that the final response can be built on traceable documentary sources.

---

## Internal references for the EVA project

This explanation corresponds mainly to the architecture documented in:

- `02_ARCHITECTURE.md`
- `04_INGESTION_AND_BUILD.md`
- `05_QUERY_AND_CHAT.md`
- `12_MANDATORY_RULES.md`
- `09_CONTEXT_INTELLIGENCE_ENGINE.md`
- `01_OVERVIEW.md`


## Phase 1 — Foundation

- project structure, configuration, and database schema — **completed**;
- identifiers, states, and logs — **completed**;
- non-judgmental `simetry`/`assimetry` model — **completed**.

## Phase 2 — Ingestion

- secure upload — **completed**;
- Markdown, JSON, and XML parsers — **completed**;
- common normalized tree — **completed**;
- documents, nodes, and primary evidence — **completed**;
- valid and invalid real-file tests — **completed**.

## Phase 3 — Evidence Algorithm

- traceable bottom-up syntheses — **completed**;
- `primary`/`derived` evidence and semantic types — **completed**;
- source derivations — **completed**;
- contextual embeddings of complete units — **completed**;
- versioning and resumption without duplicate calls — **completed**.

## Phase 4 — Query

- direct, structural, conceptual, relational, and broad input detection — **completed**;
- adaptive search across validated primary evidence — **completed**;
- resolution of syntheses down to primary sources — **completed**;
- Cnode defined as an internal transient conceptual derivation of EVA, without hierarchy or persistence — **completed**;
- `simetry`/`assimetry` in the same response call — **completed**;
- validation of participants, orientation, citations, and literal excerpts — **completed**;
- no relational persistence — **completed**.

## Phase 5 — Product

- administrative and query interface — **completed**;
- queue limited to syntheses and embeddings — **completed**;
- white-label configuration — **completed**;
- audit, metrics, and access controls — **completed**;
- tests without external consumption — **completed**.

## Architectural upgrade — Evidence Algorithm as the standard

- removal of `cnodes`, `cnode_evidences`, `cnode_embeddings`, and `interaction_analyses` — **completed**;
- removal of the persistent `cnodes` stage — **completed**;
- semantic retrieval through evidence class, type, and lineage — **completed**;
- interactions exclusively contextual and non-persistent — **completed**;
- aligned documentation, product, and tests — **completed**.

The five phases and the first architectural upgrade are complete. New phases must arise from real product use without reintroducing redundant relational entities or weights.

## Architectural upgrade — Context Intelligence Engine

- replacement of Top-k with query-local κq over the complete hierarchy — **completed**;
- mean, population standard deviation, and coefficient of variation — **completed**;
- discard, convergence, and core regions — **completed**;
- deterministic convergence fallback when no core exists — **completed**;
- direct source forwarding after statistical selection, preserving inherited region — **completed**;
- κe and primary CIE preserved for sources forwarded by the initial core or its convergence fallback — **completed**;
- deduplicated union of local primary cores and global consolidation CIE — **completed**;
- removal of `QUERY_MAX_EVIDENCE` from semantic routes and isolation of `QUERY_NON_SEMANTIC_MAX_EVIDENCE` — **completed**;
- transient auditable `context_intelligence` output — **completed**;
- core as the elected population at every stage, with convergence fallback only when core is empty — **completed**;
- global convergence appended to final context as auxiliary support without changing the core cutoff or κ boundaries — **completed**;
- `used_evidence_ids` contract derived from visible citations, with omitted candidates discarded — **completed**;
- closed validation of analytical incorporation, without automatic completion or citation inventories — **completed**;
- real reference validation with 10/10 evidence items incorporated and no truncation — **completed**;
- isolated tests without a database or external calls — **completed**;
- comparative revalidation of quality, stability, latency, and tokens on a representative corpus — **pending**.

## Future evolution — strict interaction semantics

- distinguish thematic convergence from `simetry` reciprocity through a demonstrable contract — **future**;
- require explicit support for both directions of `simetry` — **future**;
- strengthen origin/destination demonstration for `assimetry` without inferring causation or hierarchy — **future**;
- preserve a valid documentary answer when no strict interaction can be proved — **current principle**.

## Advancement criterion

Each phase requires a working flow, critical tests, safe failures, and documentation consistent with actual behavior.

## Next experimental validation — energy sustainability

- measure joules per query and kWh per thousand queries in an instrumented environment;
- amortize the construction cost across different query volumes;
- compare EVA, block-based vector RAG, long context, GraphRAG, and agentic RAG at equivalent documentary quality;
- separate direct, structural, broad, conceptual, relational, and negative-control queries;
- record external calls, embeddings, tokens, GPU time, latency, and construction reuse;
- publish dispersion, experimental configuration, and generalization limits.

Until this protocol is executed, energy efficiency remains an architectural hypothesis grounded in computational containment mechanisms, not a claim of experimental superiority. The complete protocol is available in [Energy sustainability](08_SCIENTIFIC_AND_ENERGY.md).
