# Architecture

## Purpose

The architecture separates responsibilities without duplicating concepts and without allowing AI to assign judgment or weight to documentary interactions.

## Modules

1. **Input:** validates format, size, integrity, and encoding.
2. **Parser:** reads Markdown, JSON, or XML without inference.
3. **Normalizer:** produces a shared documentary tree.
4. **Evidence:** persists exclusively literal primary content and its structural origin.
5. **Embeddings:** vectorizes complete organized evidence units.
6. **Query routing:** classifies the local retrieval path.
7. **Retrieval:** locates and orders primary candidates.
8. **Query-local boundaries and context intelligence:** κq legitimizes the complete primary population; the first CIE forwards its upper core or convergence fallback; κe and primary CIE refine those sources, and global CIE consolidates local nuclei.
9. **Answer:** produces one structured documentary response.
10. **Validation:** verifies evidence identifiers, visible citations, participants, orientation, and literal excerpts.
11. **Product:** exposes the interface, API, queue, access control, audit, metrics, and branding.
12. **Infrastructure:** provides the database, private files, logs, and configurable external integrations.

## Macro flow

```text
File → parser → tree → primary evidence → embeddings

Question → routing → complete `primary:node_content` retrieval → κq → first CIE (μ, σ, CV)
         → upper core or convergence fallback
         → primary cosine → κe → primary CIE by region and work
         → union of local nuclei → global CIE
         → global core + auxiliary convergence + literal anchors
         → deterministic contract → answer + transient interactions → validation
```

## Verified end-to-end flow

1. `POST /api/documents` validates the upload, chooses a parser from the extension, and reads the complete Markdown, JSON, or XML source.
2. The parser produces the normalized tree. The application registers the document as `received`, stores the original source privately, persists nodes and `primary:node_content` evidence in one transaction, and finishes the document as `ready`.
3. Ingestion neither creates embeddings nor schedules processing automatically. `POST /api/documents/{id}/process` idempotently queues only the `embeddings` stage for the document and capability version.
4. The worker organizes each complete primary evidence unit with work title, path, node, reference, and literal content. Every pending unit is checked before the first provider call; existing model-and-hash versions are reused, and remaining units are embedded in batches without arbitrary fragmentation.
5. The browser sends `POST /api/query` only the selected scopes and current input. The API authorizes and deduplicates works, applies profiles only for explicitly selected projects, and receives no prior-question state.
6. `InputTypeDetector` chooses local routes. Direct, structural, and broad inputs use identifiers, literal content, and the tree; conceptual or relational inputs also create one transient embedding reused across works in the same request.
7. For each semantic work, Retriever calculates cosine against every validated primary record with a compatible embedding, orders the population, applies κq, and runs the first CIE. Only core—or convergence fallback when core is empty—continues through κe and one local primary CIE.
8. `DocumentQueryService` deduplicates the union of local nuclei and runs global CIE. Global core has precedence and global convergence is appended as auxiliary context. Exact literal matches outside the analyzed primary population remain protected.
9. With no evidence, the documentary response stops locally without a generative call. Otherwise `QueryAnswerProvider` receives only the current input, complete authorized evidence, `core`/`convergence` roles, known limitations, and applicable governance layers.
10. The same call produces JSON containing a conclusion-first pedagogical answer, used IDs, interactions, and limitations. The prompt requests at most three essential interactions; separately, the backend rejects output above `QUERY_MAX_INTERACTIONS`. It derives the effectively used basis from visible citations and validates IDs, analytical incorporation, participants, orientation, and literal excerpts.
11. A locally invalid output may be regenerated for at most three total attempts with the same context. Within each attempt, `finish_reason=length` permits one complete compact regeneration; partial output is never accepted.
12. After validation, the API records only sanitized metadata, optionally emits `interaction.completed` to subscribers, and returns the result. The interface keeps only that question and answer; **Ask another question** discards transient result state, preserves scopes, and starts another request from zero.

## Separation of responsibilities

Embeddings locate semantically compatible primary evidence. Similarity globally orders the complete eligible primary population, κq establishes its query-local boundary, and the first CIE forwards only core (`s ≥ μ + σ`), using convergence (`μ ≤ s < μ + σ`) as fallback when core is empty. The surviving sources pass through κe and primary CIE. A final global CIE classifies the deduplicated union of local primary nuclei. Its core remains the primary cutoff and its convergence is appended as auxiliary provider context. Exact literal matches outside the vector population remain protected anchors. All analyses remain transient.

Version 6.0.0 construction and retrieval operate exclusively on primary evidence. The answer provider cannot introduce external sources or IDs outside the recovered set. The final evidence basis retains only sources incorporated into the prose with visible citations; a recovered but uncited source is discarded without invalidating the entire answer. Missing, out-of-context, or citation-only inventory references remain invalid. The answer provider may declare `simetry` or `assimetry` in the same call that produces the answer and is currently instructed to return at most three essential interactions even when the configured technical ceiling is higher; local quantity rejection uses that configured ceiling. Local code also accepts an interaction only when both participants belong to the recovered context, were cited, and contain the declared literal excerpts.

CIE and Cnode are different operations within EVA. CIE classifies the vector distribution without semantic interpretation. Cnode is an internal conceptual derivation that describes an explicit interaction only after evidence selection; it is not a separate system, a superior hierarchical layer, a documentary-tree node, or a persistent entity, and it does not create persistent pairs or relationship vectors.

## Provider boundaries

The application depends on capability interfaces:

```text
EmbeddingProvider
QueryAnswerProvider
```

The factory resolves implementations from environment configuration. Changing providers must not require changes to routes, commands, domain objects, or database concepts.

## Architectural invariants

- Parsers do not infer meaning.
- Primary evidence remains literal.
- Only literal primary evidence enters persistent cognitive construction.
- Similarity does not prove an interaction.
- CIE does not judge documents or create AI scores, subjective weights, or a learned reranking stage.
- Asymmetry does not imply superiority or inferred causality.
- Relationship taxonomies, cognitive confidence, intensity, quality, priority, and importance are outside the model.
- Interaction objects—pairs, roles, descriptions, and excerpts—are transient and never become ranking signals; only sanitized per-query counts may remain in operational audit metadata.
- The web interface never accesses the database directly.
- Only `public/` is exposed by the web server.
