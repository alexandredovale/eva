# Architecture

## Purpose

The architecture separates responsibilities without duplicating concepts and without allowing AI to assign judgment or weight to documentary interactions.

## Modules

1. **Input:** validates format, size, integrity, and encoding.
2. **Parser:** reads Markdown, JSON, or XML without inference.
3. **Normalizer:** produces a shared documentary tree.
4. **Evidence:** persists literal primary content and traceable derived summaries.
5. **Embeddings:** vectorizes complete organized evidence units.
6. **Query routing:** classifies the local retrieval path.
7. **Retrieval:** locates and orders primary or derived candidates.
8. **Context intelligence:** stabilizes the semantic Top-k through its similarity distribution.
9. **Lineage resolution:** resolves selected derived candidates to primary sources.
10. **Answer:** produces one structured documentary response.
11. **Validation:** verifies evidence identifiers, visible citations, participants, orientation, and literal excerpts.
12. **Product:** exposes the interface, API, queue, access control, audit, metrics, and branding.
13. **Infrastructure:** provides the database, private files, logs, and configurable external integrations.

## Macro flow

```text
File → parser → tree → primary evidence → summaries → derivations → embeddings

Question → routing → primary/derived retrieval → Top-k → CIE (μ, σ, CV)
         → available core + convergence context → lineage resolution → available primary sources
         → deterministic contract → answer + transient interactions → validation
```

## Separation of responsibilities

Embeddings locate semantically compatible evidence. Similarity orders the semantic Top-k and is then observed by CIE. Candidates below the mean are discarded; the convergence core (`s ≥ μ + σ`) leads the available context, while the convergence range (`μ ≤ s < μ + σ`) provides complementary context. When the core is empty, convergence assumes the primary role. The analysis remains transient.

Derived evidence can guide retrieval, but the answer receives its resolved primary sources as available context with explicit `core` or `convergence` roles. The answer provider cannot introduce external sources or IDs outside that set. The final evidence basis retains only sources incorporated into the prose with visible citations; a recovered but uncited source is discarded without invalidating the entire answer. Missing, out-of-context, or citation-only inventory references remain invalid. The answer provider may declare `simetry` or `assimetry` in the same call that produces the answer. Local code accepts an interaction only when both participants belong to the recovered context, were cited, and contain the declared literal excerpts.

CIE and Cnode are different operations within EVA. CIE classifies the vector distribution without semantic interpretation. Cnode is an internal conceptual derivation that describes an explicit interaction only after evidence selection; it is not a separate system, a superior hierarchical layer, a documentary-tree node, or a persistent entity, and it does not create persistent pairs or relationship vectors.

## Provider boundaries

The application depends on capability interfaces:

```text
EmbeddingProvider
SummaryProvider
QueryAnswerProvider
```

The factory resolves implementations from environment configuration. Changing providers must not require changes to routes, commands, domain objects, or database concepts.

## Architectural invariants

- Parsers do not infer meaning.
- Primary evidence remains literal.
- Derived evidence never disguises itself as source content.
- Similarity does not prove an interaction.
- CIE does not judge documents or create AI scores, subjective weights, or a learned reranking stage.
- Asymmetry does not imply superiority or inferred causality.
- Relationship taxonomies, cognitive confidence, intensity, quality, priority, and importance are outside the model.
- Interaction objects—pairs, roles, descriptions, and excerpts—are transient and never become ranking signals; only sanitized per-query counts may remain in operational audit metadata.
- The web interface never accesses the database directly.
- Only `public/` is exposed by the web server.
