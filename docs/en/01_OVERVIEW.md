# Overview

## Purpose

EVA builds, organizes, and queries verifiable documentary memory. It receives structured documents, preserves their hierarchy, creates traceable evidence, and answers only from primary evidence recovered for the current request.

The project distinguishes two concepts:

- **Evidence Algorithm (EVA):** the persistent and operational architecture for documentary evidence.
- **Cnode:** the transient understanding of an explicit interaction between evidence during one query.

## Persistent core

The persistent cognitive core contains:

- original document metadata and normalized nodes;
- literal `primary` + `node_content` evidence;
- generated `derived` + `node_summary` evidence;
- derivations that record complete lineage;
- contextual embeddings of already organized units.

It does not contain persistent relationships, cognitive scores, confidence, importance, interaction vectors, or Cnode records.

## Fundamental flow

```text
Build: source → tree → primary evidence → derived summaries → embeddings
Semantic query: input → Retriever → Top-k → CIE → primary sources
Interaction: recovered sources → transient simetry/assimetry → literal validation
Answer: cited evidence → answer and limitations
```

## Neutrality

The system describes evidence and explicit interactions without assigning truth, superiority, quality, priority, intensity, or importance. Provider brands, endpoints, models, and credential-variable names remain configurable and do not appear in domain contracts.

For vector routes, the Context Intelligence Engine (CIE) uses the candidate distribution's mean, population standard deviation, and coefficient of variation to elect a leading convergence core plus mandatory complementary convergence context before cognitive processing. When no core exists, convergence assumes the primary role. The answer model must incorporate every resolved primary source. This local election is deterministic and model-independent.

## Product scope

The implementation includes a white-label web interface, authenticated API, user/project/document access scopes, explicit processing queue, sanitized audit trail, descriptive metrics, deletion workflows, and short-lived conversational continuity.

## Energy sustainability

EVA contains computational-containment mechanisms: it reuses the cognitive build, avoids transient embeddings on non-semantic routes, does not call the answer provider when no primary evidence is recovered, and does not precompute documentary relationships.

These properties may reduce energy demand at scale, but net savings still require experimental validation. The implemented mechanisms, scientific limits, and measurement protocol are documented in [Scientific scope and energy sustainability](08_SCIENTIFIC_AND_ENERGY.md).

## Documentation map

Start with [Architecture](02_ARCHITECTURE.md), [Installation](03_INSTALLATION.md), and [Ingestion and cognitive build](04_INGESTION_AND_BUILD.md). The system invariants are consolidated in [Mandatory rules](12_MANDATORY_RULES.md); dated verification results are preserved separately in [Go-live readiness validation](14_GO_LIVE_VALIDATION.md) and [Pre-deployment acceptance](15_PRE_DEPLOYMENT_ACCEPTANCE.md).
