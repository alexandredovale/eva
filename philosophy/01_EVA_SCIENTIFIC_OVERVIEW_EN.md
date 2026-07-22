# EVA — Scientific overview

## Abstract

The Evidence Algorithm (EVA) is a provider-neutral architecture for verifiable documentary memory. It parses structured sources into a normalized hierarchy, persists literal primary evidence, produces traceable hierarchical derived evidence, and embeds complete semantic units. During a query, it selects an adaptive retrieval route, resolves derived candidates back to primary sources, and constrains generated answers to the recovered evidence.

EVA treats a Cnode as the transient understanding of an explicit interaction between cited evidence. The interaction is never stored as a graph edge, score, vector, or permanent cognitive entity. Its two internal forms are `simetry`, for an explicit reciprocal interaction, and `assimetry`, for an explicit directed interaction. Neither form implies truth, importance, superiority, confidence, intensity, or inferred causality.

## Research problem

Documentary retrieval systems frequently lose provenance through arbitrary chunking, conflate generated summaries with source material, or materialize semantic relationships before a user request establishes their relevance. EVA investigates whether documentary hierarchy, evidence-class separation, explicit lineage, and transient interaction analysis can improve process auditability without creating redundant persistent cognitive structures.

## Architecture under study

```text
Structured source
  → deterministic parser
  → normalized documentary tree
  → literal primary evidence
  → hierarchical derived summaries with lineage
  → embeddings of complete organized units

User query
  → deterministic route detection
  → structural or semantic candidate retrieval
  → lineage resolution to primary evidence
  → one bounded structured answer
  → local validation of citations and interactions
```

The persistent state contains documents, nodes, evidence, derivations, and embeddings. Similarity is transient. Interactions are query-scoped. Previous chat turns can resolve conversational references but never become documentary evidence.

## Falsifiable hypotheses

The architecture motivates, but does not by itself prove, the following hypotheses:

1. Structure-preserving evidence improves provenance compared with fixed-size chunks.
2. Explicit separation of primary and derived evidence improves auditability.
3. Derived retrieval resolved to primary sources reduces unsupported citation behavior.
4. Query-scoped interactions can provide relational explanation without persistent graph expansion.
5. Deterministic routing and evidence gating can reduce unnecessary external calls.
6. Versioned reuse can amortize summary and embedding construction across repeated queries.

## Existing observations

The current implementation and offline tests verify structural preservation, literal persistence, lineage resolution, version reuse, bounded provider calls, citation validation, transient interaction validation, partial coverage reporting, and no-generation behavior when evidence is absent.

The recorded operational baseline is intentionally small. It demonstrates observable behavior and exposes failure modes, but it does not establish statistical superiority. Rejected generated outputs can still consume tokens before local validation; complete semantic units can produce large prompts; and provider compliance remains an empirical factor.

## Comparative protocol

A defensible evaluation should compare EVA with fixed-block vector RAG, long-context retrieval, GraphRAG, and agentic RAG using the same corpus, questions, provider capabilities, hardware, context budget, and answer-quality criteria.

Minimum measures include:

- evidence precision and recall;
- citation and literal-excerpt validity;
- correct and incorrect refusal rates;
- relational validation rate;
- input/output tokens and external calls;
- latency p50, p95, and p99;
- monetary and measured energy cost;
- build cost amortized by query volume;
- memory use and stability across runs;
- invariance of persistent documentary memory after queries.

## Epistemic boundary

EVA verifies that a response is traceable to supplied documentary evidence. It does not establish that the source itself is universally true. Validation is therefore process validation: known sources, literal content, explicit lineage, accepted identifiers, and locally reconstructible interactions.

## Source record

The complete paper, detailed citations, benchmark record, and API analysis are preserved in the original Portuguese documents:

- [Full scientific paper](01_EVA_SCIENTIFIC_PAPER.md)
- [Benchmark baseline](02_EVA_BENCHMARK_BASELINE.md)
- [API flow](03_EVA_API_FLOW.md)
- [Philosophical foundation](00_EVA_PHILOSOPHY.md)

