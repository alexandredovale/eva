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
Query: input → adaptive retrieval → primary evidence → bounded answer
Interaction: cited sources → transient simetry/assimetry → literal validation
```

## Neutrality

The system describes evidence and explicit interactions without assigning truth, superiority, quality, priority, intensity, or importance. Provider brands, endpoints, models, and credential-variable names remain configurable and do not appear in domain contracts.

## Product scope

The implementation includes a white-label web interface, authenticated API, user/project/document access scopes, explicit processing queue, sanitized audit trail, descriptive metrics, deletion workflows, and short-lived conversational continuity.

