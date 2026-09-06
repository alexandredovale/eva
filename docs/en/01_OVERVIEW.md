# EVA — Evidence Algorithm overview

## Purpose

EVA, short for Evidence Algorithm, is the project's principal system and architecture. It builds, organizes, and queries verifiable documentary memory, preserving document hierarchy, traceable evidence, and answers limited to primary evidence recovered for the current request.

The project distinguishes two concepts:

- **Evidence Algorithm (EVA):** the principal persistent and operational architecture for documentary evidence.
- **Cnode:** an internal conceptual derivation of EVA: the transient understanding of an explicit interaction between evidence during one query. It is not a separate system, a superior hierarchical layer, a documentary-tree node, or a persistent entity.

## Persistent core

The persistent cognitive core contains:

- original document metadata and normalized nodes;
- literal `primary` + `node_content` evidence;
- contextual embeddings of organized primary evidence.

It does not contain persistent relationships, cognitive scores, confidence, importance, interaction vectors, or Cnode records.

## Fundamental flow

```text
Build: source → tree → primary evidence → embeddings
Semantic query: input → complete primary evidence → κq → first-stage CIE
                → upper core/fallback → κe/primary CIE → global CIE
Interaction: recovered sources → transient simetry/assimetry → literal validation
Answer: cited evidence → answer and limitations
```

## Neutrality

The system describes evidence and explicit interactions without assigning truth, superiority, quality, priority, intensity, or importance. Provider brands, endpoints, models, and credential-variable names remain configurable and do not appear in domain contracts.

For vector routes, the Context Intelligence Engine (CIE) uses mean, population standard deviation, and coefficient of variation at three stages: hierarchical, stratified primary, and global. Query-local κq and κe boundaries require no configured counts. The global core—or global convergence only when the core is empty—forms the semantic context delivered to the model, while exact literal anchors remain protected. Every primary source retained in the result must be cited; recovered but uncited sources are discarded.

## Product scope

The implementation includes a white-label web interface, authenticated API, user/project/document access scopes, explicit processing queue, sanitized audit trail, descriptive metrics, deletion workflows, and short-lived conversational continuity.

## Energy sustainability

EVA contains computational-containment mechanisms: it reuses the cognitive build, avoids transient embeddings on non-semantic routes, does not call the answer provider when no primary evidence is recovered, and does not precompute documentary relationships.

These properties may reduce energy demand at scale, but net savings still require experimental validation. The implemented mechanisms, scientific limits, and measurement protocol are documented in [Scientific scope and energy sustainability](08_SCIENTIFIC_AND_ENERGY.md).

## Documentation map

Start with [Architecture](02_ARCHITECTURE.md), [Installation](03_INSTALLATION.md), and [Ingestion and cognitive build](04_INGESTION_AND_BUILD.md). The system invariants are consolidated in [Mandatory rules](12_MANDATORY_RULES.md); dated verification results are preserved separately in [Go-live readiness validation](14_GO_LIVE_VALIDATION.md) and [Pre-deployment acceptance](15_PRE_DEPLOYMENT_ACCEPTANCE.md).
