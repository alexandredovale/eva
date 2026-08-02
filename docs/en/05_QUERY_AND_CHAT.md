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

For semantic routes, Retriever orders up to `QUERY_CANDIDATE_LIMIT` candidates and CIE calculates the mean, population standard deviation, and coefficient of variation. Candidates below the mean are discarded. The convergence core leads the final semantic selection; the convergence range follows as complementary analysis context. If no core exists, convergence becomes the primary context. Selected derived candidates are then resolved through `evidence_derivations` until primary sources are available. The answer provider receives the deterministic `core`/`convergence` role, but not similarity values as documentary authority.

## Project response governance

The superadmin can define an optional response profile for a project. Profiles may specialize audience, assistance role, vocabulary, tone, focus, or presentation, but they never replace the base system prompt or relax evidence, citation, limitation, interaction-validation, or JSON-output rules.

Activation follows the explicit chat scope:

- selecting a project root activates its configured profile and resolves every ready work in that project;
- selecting an individual work does not activate the profile of any project containing that work;
- selecting multiple project roots activates each configured profile separately;
- selecting a project plus an individual work applies the profile only to the project portion of the scope.

The backend merges authorized document IDs and deduplicates them before retrieval. If two selected projects share a work, that work is therefore retrieved once while both project profiles remain active. Compatible instructions can be combined. If profiles conflict over the same aspect, the provider preserves the base rules and uses a neutral formulation instead of choosing a profile arbitrarily.

## Evidence gate

If retrieval finds no primary evidence, EVA returns an explicit documentary limitation without calling the answer provider. When context exists, the answer provider must accept the complete deterministic election in `used_evidence_ids`; it cannot re-elect or reject evidence. Formal acceptance is not sufficient: every elected evidence must be cited where its analytical contribution is explained. Core evidence leads the answer, convergence evidence provides mandatory complementary analysis, and citation-only inventories are rejected.

## Query limits

- `QUERY_CANDIDATE_LIMIT`: semantic Top-k analyzed by CIE per document, default `30`, effective range `1..200`.
- `QUERY_MAX_EVIDENCE`: global primary-candidate limit, default `8`, effective range `1..50`.
- `QUERY_MAX_INTERACTIONS`: accepted transient-interaction limit, default `20`, effective range `0..100`.
- `AI_QUERY_MAX_OUTPUT_TOKENS`: per-attempt output ceiling, default `1800`, effective range `100..3000`.

`QUERY_CANDIDATE_LIMIT` defines the statistical population before lineage resolution. `QUERY_MAX_EVIDENCE` caps the primary context after CIE and remains global across all selected works.

A response truncated by the provider is never partially decoded. EVA allows at most one complete retry with an additional compactness instruction.

## Interactions

Whenever at least two elected evidences are available and the interaction limit is greater than zero, the answer call must analyze:

```text
simetry:    participant ↔ participant
assimetry:  origin → destination
```

Each accepted interaction requires two cited primary evidence records and one literal excerpt from each. It has no persistent identifier, confidence, weight, score, or embedding.

The public result exposes `selection_region` on each used evidence and an `evidence_selection` object with the elected core and convergence IDs, so the provider contract remains auditable after generation.

## Conversational continuity

The interface keeps the visible transcript while the current page remains open. Starting with the second query, it appends at most the three latest completed turns to the current input in chronological order. If the 20,000-byte API input ceiling would be exceeded, the oldest complete turn is removed.

The answer provider decides whether the current request continues an earlier turn. Previous questions and answers can clarify conversational references, but they never become documentary evidence. Every new response remains restricted to primary evidence recovered for that query.

**Reset chat** clears the transcript and temporary context while preserving selected projects and works. Conversation state is not persisted in the database, audit log, or browser storage.
