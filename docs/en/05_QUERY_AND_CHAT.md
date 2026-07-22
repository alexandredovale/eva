# Query and conversational continuity

## Input routes

- **Direct:** explicit evidence identifier or quotation.
- **Structural:** work, part, section, chapter, title, or path.
- **Broad:** overview through upper hierarchy.
- **Conceptual:** topic without a known location.
- **Relational:** request about an interaction between concepts.

One input can activate more than one route. Detection is local and deterministic; it does not call an AI provider.

## Retrieval

Direct, structural, and broad routes navigate identifiers and document hierarchy. Conceptual and relational routes create a transient input embedding and search primary and derived evidence.

Derived candidates are resolved through `evidence_derivations` until primary sources are available. Similarity is discarded after ordering. All candidates inside the configured limit reach the answer provider; the provider must identify only the evidence that actually supports its response.

## Evidence gate

If retrieval finds no primary candidate, EVA returns an explicit documentary limitation without calling the answer provider. If candidates exist but none supports the requested aspect, a response without used evidence is accepted only when it has no citations/interactions and contains an explicit limitation.

## Query limits

- `QUERY_MAX_EVIDENCE`: global primary-candidate limit, default `8`, effective range `1..50`.
- `QUERY_MAX_INTERACTIONS`: accepted transient-interaction limit, default `20`, effective range `0..100`.
- `AI_QUERY_MAX_OUTPUT_TOKENS`: per-attempt output ceiling, default `1800`, effective range `100..3000`.

A response truncated by the provider is never partially decoded. EVA allows at most one complete retry with an additional compactness instruction.

## Interactions

Relational answers may declare:

```text
simetry:    participant ↔ participant
assimetry:  origin → destination
```

Each accepted interaction requires two cited primary evidence records and one literal excerpt from each. It has no persistent identifier, confidence, weight, score, or embedding.

## Conversational continuity

The interface keeps the visible transcript while the current page remains open. Starting with the second query, it appends at most the three latest completed turns to the current input in chronological order. If the 20,000-byte API input ceiling would be exceeded, the oldest complete turn is removed.

The answer provider decides whether the current request continues an earlier turn. Previous questions and answers can clarify conversational references, but they never become documentary evidence. Every new response remains restricted to primary evidence recovered for that query.

**Reset chat** clears the transcript and temporary context while preserving selected projects and works. Conversation state is not persisted in the database, audit log, or browser storage.

