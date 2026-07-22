# Architecture

## Modules

1. **Input:** validates format, size, integrity, and encoding.
2. **Parser:** reads Markdown, JSON, or XML without inference.
3. **Normalizer:** produces a shared documentary tree.
4. **Evidence:** persists literal primary content and traceable derived summaries.
5. **Embeddings:** vectorizes complete organized evidence units.
6. **Query routing:** classifies the local retrieval path.
7. **Retrieval:** locates primary or derived candidates and resolves lineage.
8. **Answer:** produces one structured documentary response.
9. **Validation:** verifies evidence identifiers, visible citations, participants, orientation, and literal excerpts.
10. **Product:** exposes the interface, API, queue, access control, audit, metrics, and branding.

## Separation of responsibilities

Embeddings locate semantically compatible evidence. Similarity exists only while ordering candidates and is discarded afterward. A candidate is not automatically evidence used in the answer.

Derived evidence can guide retrieval, but the answer receives its resolved primary sources. The answer provider may declare `simetry` or `assimetry` in the same call that produces the answer. Local code accepts an interaction only when both participants belong to the recovered context, were cited, and contain the declared literal excerpts.

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
- Asymmetry does not imply superiority or inferred causality.
- Interactions are transient and never become ranking signals.
- The web interface never accesses the database directly.
- Only `public/` is exposed by the web server.

