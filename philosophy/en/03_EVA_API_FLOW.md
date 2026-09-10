# EVA — API and documentary-processing flow

This document presents the current EVA flow in text diagrams, from document attachment to the answer returned for user input.

The canonical English visual diagram is [`EVA_API_FLOW_CIE.svg`](EVA_API_FLOW_CIE.svg).
Português: [Fluxo de APIs e processamento documental](../03_EVA_API_FLOW.md)

## 1. Attachment and documentary-memory construction

```text
===============================================================================
PHASE 1 — ATTACHMENT AND DOCUMENTARY-MEMORY CONSTRUCTION
===============================================================================

[USER ATTACHES A DOCUMENT]
           |
           v
POST /api/documents                         <- EVA internal API
           |
           v
[LOCAL FILE VALIDATION]
- size
- upload integrity
- permitted format
           |
           v
[LOCAL PARSER]
Markdown / JSON / XML
           |
           v
[NORMALIZED DOCUMENT TREE]
           |
           +--> document nodes
           +--> literal primary evidence
           +--> source references
           +--> hashes
           |
           v
[DATABASE + ORIGINAL FILE]

       UP TO THIS POINT: NO EXTERNAL AI API
```

Cognitive processing starts separately:

```text
[USER CLICKS "PROCESS"]
           |
           v
POST /api/documents/{id}/process            <- EVA internal API
           |
           v
[PROCESSING QUEUE]
           |
           +--> job: embeddings

       STILL NO EXTERNAL CALL
```

## 2. Collection embeddings

```text
+-------------------------------------------------------------+
| SINGLE STAGE — COLLECTION EMBEDDINGS                        |
+-------------------------------------------------------------+
        |
        v
[LOAD PRIMARY EVIDENCE]
        |
        v
[ASSEMBLE STRUCTURED SEMANTIC UNITS]
- document
- structural path
- node type and title
- source reference
- literal content
        |
        v
+-------------------------------------------------------------+
| EXTERNAL API 1 — EMBEDDING PROVIDER                         |
|                                                             |
| Batched submission. Current default: up to 64 units/call.   |
| Larger documents may require multiple calls.                |
+-------------------------------------------------------------+
        |
        v
[VECTORS PERSISTED IN DATABASE]
        |
        v
[DOCUMENT READY FOR SEMANTIC QUERY]
```

## 3. User input and answer

```text
===============================================================================
PHASE 2 — USER INPUT AND ANSWER
===============================================================================

[USER SENDS CURRENT INPUT]
           |
           v
[BROWSER COMPOSES CONVERSATIONAL CONTEXT]
- current input first
- up to 3 prior rounds
- discard the oldest complete round above 20,000 bytes
           |
           v
POST /api/query                              <- EVA internal API
           |
           v
[LOCAL INPUT-TYPE DETECTION]
           |
           +-----------------------------------------------+
           |                                               |
           v                                               v
[DIRECT / STRUCTURAL / BROAD]                  [CONCEPTUAL / RELATIONAL]
           |                                               |
           v                                               v
[LOCAL RETRIEVAL]                              +-------------------------+
- IDs                                         | EXTERNAL API 3          |
- literal phrases                             | INPUT EMBEDDING         |
- titles                                      | Normally one call.      |
- structural paths                            +-------------------------+
           |                                               |
           |                                               v
           |                                  [LOCAL SIMILARITY AGAINST
           |                                   THE COLLECTION]
           |                                               |
           |                                               v
           |                                  [COMPLETE POPULATION
           |                                   + COSINE + κq]
           |                                               |
           |                                               v
           |                                  [QUERY-LOCAL κq BOUNDARY]
           |                                  - normalized curve and geometry
           |                                  - gap confirmed by its own μ and σ
           |                                  - no break: complete population
           |                                               |
           +-------------------------+---------------------+
                                     |
                                     v
                     [FIRST SOURCE CIE PER DOCUMENT]
                     - only core proceeds normally
                     - convergence is fallback when core is empty
                                     |
                                     v
                     [SURVIVING PRIMARY SOURCES]
                     - κe + primary CIE per region/document
                     - no truncation or new embedding call
                                     |
                                     v
                     [UNION OF LOCAL NUCLEI → GLOBAL CIE]
                     - global core + auxiliary convergence
                     - exact literal anchors remain protected
                     - the LLM cannot add external sources
                                     |
                                     v
                             < ANY EVIDENCE? >
                                /          \
                              NO           YES
                               |             |
                               v             v
                  [DETERMINISTIC REFUSAL]  +-----------------------------+
                                           | EXTERNAL API 4              |
                                           | ANSWER GENERATION           |
                                           | nominal call with input,    |
                                           | available context, limits   |
                                           +-----------------------------+
                                                         |
                                                         v
                                           [LOCAL OUTPUT VALIDATION]
                                           - citations belong to context
                                           - every retained source contributes
                                           - uncited candidates are discarded
                                           - no isolated citation inventory
                                           - literal excerpts
                                           - simetry/assimetry
                                           - forbidden fields
                                                         |
                               +-------------------------+
                               |
                               v
                     [AUDIT + OPTIONAL EVENT]
                     - document_queried records counts only
                     - interaction.completed if subscribed
                               |
                               v
                     [ANSWER TO USER]
                     - documentary text
                     - used evidence
                     - valid interactions
                     - transient CIE analysis
                     - limitations
                               |
                               v
                     [BROWSER TRANSCRIPT]
                     - retains every round on the page
                     - never becomes documentary memory
```

## 4. Multidisciplinary project query

```text
[PROJECT WITH SPECIALIZED DOCUMENTS]
           |
           +--> document A / discipline A
           +--> document B / discipline B
           +--> document C / discipline C
           |
           v
[CONCEPTUAL OR RELATIONAL INPUT]
           |
           v
[INDEPENDENT RETRIEVAL PER DOCUMENT]
           |
           +--> candidates from A
           +--> candidates from B
           +--> candidates from C
           |
           v
[κq + FIRST SOURCE CIE + κe + PRIMARY CIE PER DOCUMENT]
           |
           v
[DEDUPLICATED UNION OF LOCAL PRIMARY NUCLEI]
           |
           v
[GLOBAL CONSOLIDATION CIE]
           |
           v
[TRANSIENT PRIMARY-EVIDENCE SELECTION]
           |
           v
+-------------------------------------------------------------+
| EXTERNAL API — ANSWER GENERATION                            |
| Uses only the documentary subset that contributes, retains  |
| global core/convergence roles, evaluates simetry/assimetry, declares gaps.|
+-------------------------------------------------------------+
           |
           v
[LOCAL MULTI-DOCUMENT VALIDATION]
- every ID belongs to authorized and available context
- every retained evidence has an analytically cited contribution
- recovered but uncited candidates are discarded
- isolated citation lists are rejected
- every evidence retains source-document identity
- participants are cited and excerpts verifiable
- unsupported fields remain limitations
           |
           v
[EMERGENT, TRACEABLE CONCEPTUAL SYNTHESIS]
           |
           v
[DISCARD CONTEXT AND INTERACTIONS]

THE QUERY CREATES NO EVIDENCE OR PERSISTENT INTER-DOCUMENT CONNECTION
```

Reliability is not a truth estimate. It comes from source integrity, observable selection, local validation, anti-evasion, and the ability to trace claims to documentary participants. A multidisciplinary synthesis may be new within the question, but it is not persisted as evidence or an intrinsic collection concept.

Adding documents expands candidates and local nuclei. Global CIE consolidates them without a configured numerical limit; nevertheless, a statistical boundary neither guarantees every discipline's coverage nor creates persistent connections among works.

## 5. Calls by route

```text
UPLOAD
  └── 0 external calls

DOCUMENT PROCESSING
  └── M batched primary-evidence embedding calls

DIRECT / STRUCTURAL / BROAD QUERY
  ├── 0 embedding calls
  └── 1 answer call, only when evidence exists

CONCEPTUAL / RELATIONAL QUERY
  ├── 1 input-embedding call, reused at every stage
  ├── κq/first source CIE and κe/primary CIE per document, local
  ├── 1 global CIE, local
  └── 1 answer call, only when evidence exists

QUERY WITHOUT EVIDENCE
  └── 0 answer-generation calls
```

These are nominal counts. A `QueryAnswerProvider` attempt permits at most one compact regeneration after `finish_reason=length`. Separately, `DocumentQueryService` permits at most three total validated-answer attempts with the same available context. Rejected output is fully discarded and never reaches the transcript.

## 6. Compact flow

```text
DOCUMENT
   |
   v
LOCAL PARSER
   |
   v
PRIMARY EVIDENCE
   |
   +--> EMBEDDING API --> PERSISTENT VECTORS
                                  |
USER INPUT                        |
   |                              |
   +--> if conceptual/relational: INPUT-EMBEDDING API
   |                              |
   +----------> RETRIEVAL <-------+
                     |
     PRIMARY POPULATION → κq → FIRST SOURCE CIE
                     |
   CORE/FALLBACK → κe → PRIMARY CIE → GLOBAL CIE
                     |
         AVAILABLE PRIMARY CONTEXT
                     |
               any evidence?
                /        \
              no         yes
               |          |
            REFUSAL    ANSWER API
                          |
               ANSWER + INTERACTIONS
                          |
                   LOCAL VALIDATION
                          |
              AUDIT / OPTIONAL EVENT
                          |
                       ANSWER
```

## 7. Query persistence rule

```text
NOT PERSISTED AS DOCUMENTARY MEMORY:

- transient input embedding;
- similarity scores;
- CIE mean, standard deviation, CV, and regions;
- recovered context;
- generated answer;
- cognitive interactions;
- conversational history.
```

This does not mean zero observability. `audit_events` retains sanitized completed-query metadata, including `simetry_count` and `assimetry_count`, without pairs or excerpts. When a module subscribes, consolidated-schema `module_events` may receive the permitted `interaction.completed` envelope with current input, contextual input, validated answer, public evidence references, and limitations; each module governs private state. Legacy databases use migration `20260803_010_module_events.sql`. A missing table in an incomplete installation or module failure yields safe diagnostics but does not invalidate an already validated answer. None of these records changes documents, primary evidence, or embeddings.

Physical and logical relationships are mapped in [Database relationships](../../docs/en/18_DATABASE_RELATIONSHIPS.md).

The complete transcript exists only in JavaScript memory on the open page. Only the three most recent completed rounds are attached to the next input. They may clarify conversational references but never gain evidence authority. **Reset chat**, logout, login, or reload discards visual state without altering projects or documents. The API always returns validated transient interactions; the current UI shows CIE, `simetry`, `assimetry`, and technical limitations only to superadmin, while normal users see the answer and used evidence.
