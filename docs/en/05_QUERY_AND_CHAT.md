# Query and conversational continuity

## Input routes

- **Direct:** explicit evidence identifier or quotation.
- **Structural:** work, part, section, chapter, title, or path.
- **Broad:** overview through upper hierarchy.
- **Conceptual:** topic without a known location.
- **Relational:** request about an interaction between concepts.

One input can activate more than one route. Detection is local and deterministic; it does not call an AI provider.

Relational detection normalizes case and diacritics, recognizes tested morphological families such as Portuguese `relação`, `relacionam`, and `relacionado`, English `interact`/`interaction`, and formal operators such as `↔` and `→`. This rule set is deterministic and extensible, but it does not claim universal multilingual intent inference. New linguistic roots must be added explicitly and protected by regression tests.

## Partial input coverage

A user may combine supported and unsupported concepts or relationships. Documentary sufficiency is evaluated per aspect. If a requested relationship involves X, Y, and Z but recovered evidence covers only X and Y, EVA answers the supported X–Y portion with citations and names the missing support for Z.

An unsupported aspect never authorizes external knowledge and does not erase other supported aspects. Generation is blocked completely only when no primary evidence is recovered.

## Retrieval

Direct, structural, and broad routes navigate identifiers and document hierarchy. Conceptual and relational routes create a transient input embedding and search primary and derived evidence.

On conceptual or relational queries, an exact textual match does not terminate retrieval. The literal evidence enters first as `core`, preserving the direct answer as the anchor, and the same input continues through the vector Top-k and CIE. Semantically elected primary sources complete the context within `QUERY_MAX_EVIDENCE` without leaving the selected works. Exclusively direct, structural, or broad queries still consume no query embedding.

Literal, lexical, and structural matches are candidates rather than conclusions. On non-vector routes, the application composes the final context within the configured limit and delivers it as a complete deterministic election. The provider must incorporate every received evidence record without extending its literal meaning.

`simetry` and `assimetry` are internal cognitive operators. They guide relational understanding but are not treated as expressions that a documentary source must contain.

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

- `QUERY_CANDIDATE_LIMIT`: semantic Top-k analyzed by CIE per document, default `20`, effective range `1..200`.
- `QUERY_MAX_EVIDENCE`: global primary-candidate limit, default `8`, effective range `1..50`.
- `QUERY_MAX_INTERACTIONS`: accepted transient-interaction limit, default `20`, effective range `0..100`.
- `AI_QUERY_MAX_OUTPUT_TOKENS`: per-attempt output ceiling, default `1800`, effective range `100..3000`.

`QUERY_CANDIDATE_LIMIT` defines the statistical population before lineage resolution. `QUERY_MAX_EVIDENCE` caps the primary context after CIE and remains global across all selected works.

A response truncated by the provider is never partially decoded. EVA allows at most one complete retry with an additional compactness instruction.

The per-attempt output limit is a ceiling, not a generation target. The retry uses the same ceiling, discards all partial output, and cannot loop or automatically raise the configured budget.

## Silent regeneration after validation failure

When a generation reaches the backend intact but violates the local contract for evidence, citations, analytical incorporation, or interactions, `DocumentQueryService` discards that output completely and requests another generation with the same elected context. A single API request permits at most three total attempts to obtain a validated answer.

From the second attempt onward, the provider receives only a safe corrective code. When analytical incorporation is missing, EVA also identifies one already-elected evidence record that must be incorporated. Raw validator messages, internal rules, and rejected text are never returned to the provider or browser.

Rejected attempts never enter the transcript, alter the CIE election, or contribute partial text. While another attempt remains, the interface stays on **Consultando evidências…**, accompanied by three animated yellow dots. Reduced-motion browser preferences disable the animation. The waiting state exposes neither an evidence identifier nor a technical validation rule.

A later valid attempt replaces every rejected attempt. Only after three consecutive validation failures does the API return a generic browser error without evidence identifiers. Exhaustion is logged with a safe category, attempt count, and `request_id`; the final technical reason remains chained internally and is not exposed to the user.

This mechanism is separate from provider truncation recovery. One answer attempt may still use the single compact regeneration reserved for `finish_reason=length`; that path does not authorize unlimited retries or raise the configured output ceiling.

## Interactions

Whenever at least two elected evidences are available and the interaction limit is greater than zero, the answer call must analyze:

```text
simetry:    participant ↔ participant
assimetry:  origin → destination
```

Each accepted interaction requires two cited primary evidence records and one literal excerpt from each. It has no persistent identifier, confidence, weight, score, or embedding.

The response and its interactions are generated in the same `QueryAnswerProvider` call configured by `AI_QUERY_MODEL`; there is no separate interaction provider. Evaluation is required when at least two elected evidence records exist and the configured interaction limit is greater than zero, but an interaction is emitted only when it can be demonstrated literally. Otherwise the documentary answer remains and the result contains a relational limitation.

The local layer rejects or discards an interaction when its type, roles, orientation, participants, cited status, or excerpts are invalid. `simetry` accepts two `participant` roles and no direction. `assimetry` requires distinct `origin` and `destination` roles. A provider output with an unknown used evidence ID, an out-of-context visible citation, an excessive interaction count, or an incomplete final election invalidates the response.

The public result exposes `selection_region` on each used evidence and an `evidence_selection` object with the elected core and convergence IDs, so the provider contract remains auditable after generation.

## Conversational continuity

The interface keeps the visible transcript while the current page remains open. Starting with the second query, it appends at most the three latest completed turns to the current input in chronological order. If the 20,000-byte API input ceiling would be exceeded, the oldest complete turn is removed.

The answer provider decides whether the current request continues an earlier turn. Previous questions and answers can clarify conversational references, but they never become documentary evidence. Every new response remains restricted to primary evidence recovered for that query.

**Reset chat** clears the transcript and temporary context while preserving selected projects and works. Conversation state is not persisted in the database, audit log, or browser storage.

## Result contract

The public query result separates:

- `answer`;
- `evidences_used`;
- `evidence_selection`;
- `simetry_interactions`;
- `assimetry_interactions`;
- `routing_points`;
- `context_intelligence`;
- `limitations`.

Each used evidence item exposes `selection_region`. `evidence_selection` lists elected core and convergence IDs. `context_intelligence` is empty on exclusively non-semantic routes and otherwise exposes the transient per-document calculation. Neither CIE analysis nor interactions modify persistent memory.

## CLI

The CLI uses the same configured defaults but permits per-execution evidence and interaction overrides:

```powershell
php bin\query-document.php <document-id> --live --evidence-limit=10 --interaction-limit=20 "your question"
```

Arguments do not alter `.env`. Persistent PHP processes must be restarted after environment changes so configuration is reloaded.
