# Deterministic evidence contract

**Status:** implemented
**Updated:** September 6, 2026
**Português:** [Contrato determinístico de evidências](../05_DETERMINISTIC_EVIDENCE_CONTRACT.md)

## Principle

In EVA, the answer model does not define the authorized documentary universe. The application determines it before generation:

```text
semantic route
  → complete primary population
  → κq → first source CIE
  → upper core or convergence fallback
  → κe → primary CIE
  → union of local nuclei → global CIE
  → global nucleus or convergence fallback
  → protected literal anchors
  → authorized final context
```

Direct, structural, and broad routes do not execute CIE and use only the isolated `QUERY_NON_SEMANTIC_MAX_EVIDENCE` limit.

## Available population and final basis

Authorized final context and the final documentary basis are different sets:

```text
authorized context = sources the application allows the LLM to examine
final basis        = subset analytically cited in the validated answer
```

A recovered but uncited source is discarded without invalidating the entire answer. An out-of-context source, nonexistent citation, or isolated ID inventory invalidates the output. The application never adds a citation omitted by the provider.

## Preserved roles

Global CIE decides final eligibility but does not erase first-stage origin. A source may be global `core` while retaining `source_region: convergence`; in that case it was forwarded by the initial convergence fallback. That role may indicate reinforcement, context, limitation, or counterpoint—never importance, truth, or confidence.

Exact literal matches outside the analyzed primary population remain protected anchors. Global CIE cannot remove them, and they remain subject to the same citation rules.

## Emergent quantity

Semantic routes have no `QUERY_MAX_EVIDENCE`. Their final count is:

```text
K(q) = |CoreG|, when CoreG ≠ ∅
K(q) = |ConvG|, when CoreG = ∅
```

The count therefore depends on that query's geometry. `QUERY_NON_SEMANTIC_MAX_EVIDENCE` never participates in κq, κe, or CIE.

## Transient interactions

When final context contains at least two evidence records and `QUERY_MAX_INTERACTIONS` is greater than zero, the same call that writes the answer may evaluate `simetry` and `assimetry`. Every accepted interaction:

- uses only present and cited participants;
- contains verifiable literal fragments;
- is validated locally;
- receives no weight or confidence;
- is not persisted as documentary memory.

The absence of a valid interaction does not erase a supported documentary answer; it produces only the corresponding relational limitation.

## Closed failure and attempts

If no primary evidence exists, EVA returns a limitation without calling the answer provider. Truncated output, invalid JSON, an external ID, a decorative citation, or a non-literal fragment is rejected. The application preserves the same authorized context across attempts and transmits only safe correction feedback; it does not widen the population to accommodate generation.

## Persistence boundary

Documents, nodes, evidence, derivations, embeddings, and sanitized audit events are persistent. The following remain transient:

- query embedding;
- similarities and κ diagnostics;
- initial (`hierarchical` for compatibility), primary, and global CIE analyses;
- authorized context and answer;
- `simetry`, `assimetry`, and conversational continuity.

Querying the collection is a read operation, not authorization to rewrite its memory.

## Invariants

1. Every documentary claim must trace back to cited primary evidence.
2. No source outside final context may be accepted.
3. κq and κe are query-local and receive no prior Top-k.
4. Initial semantic retrieval operates directly over primary evidence without replacing sources with summaries.
5. Global CIE receives only deduplicated local primary nuclei.
6. The LLM receives the global nucleus or convergence fallback, plus protected literal anchors.
7. Final semantic evidence count is not configured.
8. Only analytically cited evidence enters the final basis.
9. Query analyses and interactions never alter documentary memory.

## References

- [Context Intelligence Engine](04_CONTEXT_INTELLIGENCE_ENGINE.md)
- [Current API flow](03_EVA_API_FLOW.md)
- [Mandatory rules](../../docs/en/12_MANDATORY_RULES.md)
