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
8. **Query-local boundaries and context intelligence:** κq legitimizes the complete primary population; the first CIE forwards its upper core or convergence fallback; κe and primary CIE refine those sources, and global CIE consolidates local nuclei.
9. **Lineage resolution:** preserves traceability for derived records, while source-first semantic retrieval begins directly from primary evidence.
10. **Answer:** produces one structured documentary response.
11. **Validation:** verifies evidence identifiers, visible citations, participants, orientation, and literal excerpts.
12. **Product:** exposes the interface, API, queue, access control, audit, metrics, and branding.
13. **Infrastructure:** provides the database, private files, logs, and configurable external integrations.

## Macro flow

```text
File → parser → tree → primary evidence → summaries → derivations → embeddings

Question → routing → complete `primary:node_content` retrieval → κq → first CIE (μ, σ, CV)
         → upper core or convergence fallback
         → primary cosine → κe → primary CIE by region and work
         → union of local nuclei → global CIE
         → global nucleus (or convergence fallback) + literal anchors
         → deterministic contract → answer + transient interactions → validation
```

## Separation of responsibilities

Embeddings locate semantically compatible primary evidence. Similarity globally orders the complete eligible primary population, κq establishes its query-local boundary, and the first CIE forwards only its core (`s ≥ μ + σ`), using convergence (`μ ≤ s < μ + σ`) only when that core is empty. The surviving sources pass through the unchanged κe and primary CIE calculations. A final global CIE classifies the deduplicated union of local primary nuclei. Its nucleus forms the final semantic context, with convergence used only when that nucleus is empty. Exact literal matches outside the vector population remain protected anchors. All analyses remain transient.

Derived evidence remains traceable and available to cognitive construction, but version 4.0.2 semantic retrieval is source-first and queries primary embeddings directly. The answer provider cannot introduce external sources or IDs outside the recovered set. The final evidence basis retains only sources incorporated into the prose with visible citations; a recovered but uncited source is discarded without invalidating the entire answer. Missing, out-of-context, or citation-only inventory references remain invalid. The answer provider may declare `simetry` or `assimetry` in the same call that produces the answer. Local code accepts an interaction only when both participants belong to the recovered context, were cited, and contain the declared literal excerpts.

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
