# Roadmap

## Phase 1 — Foundation

- project structure, configuration, and database schema — **completed**;
- identifiers, states, and logs — **completed**;
- non-judgmental `simetry`/`assimetry` model — **completed**.

## Phase 2 — Ingestion

- secure upload — **completed**;
- Markdown, JSON, and XML parsers — **completed**;
- shared normalized tree — **completed**;
- documents, nodes, and primary evidence — **completed**;
- valid and invalid real-file tests — **completed**.

## Phase 3 — Evidence Algorithm

- traceable bottom-up summaries — **completed**;
- `primary`/`derived` evidence and semantic types — **completed**;
- source derivations — **completed**;
- contextual embeddings of complete units — **completed**;
- versioning and resumption without duplicate calls — **completed**.

## Phase 4 — Query

- direct, structural, conceptual, relational, and broad input detection — **completed**;
- adaptive search over primary and derived evidence — **completed**;
- resolution of summaries to primary sources — **completed**;
- Cnode defined as an internal transient conceptual derivation of EVA, without hierarchy or persistence — **completed**;
- `simetry`/`assimetry` generated in the answer call — **completed**;
- validation of participants, orientation, citations, and literal excerpts — **completed**;
- no relational persistence — **completed**.

## Phase 5 — Product

- administrative and query interface — **completed**;
- queue limited to summaries and embeddings — **completed**;
- white-label configuration — **completed**;
- audit, metrics, and access controls — **completed**;
- tests without external consumption — **completed**.

## Architectural upgrade — Evidence Algorithm as the standard

- removal of `cnodes`, `cnode_evidences`, `cnode_embeddings`, and `interaction_analyses` — **completed**;
- removal of the persistent `cnodes` stage — **completed**;
- semantic retrieval through evidence class, type, and lineage — **completed**;
- interactions exclusively contextual and non-persistent — **completed**;
- aligned documentation, product, and tests — **completed**.

The five phases and the first architectural upgrade are complete. Further work must grow from real product use without reintroducing redundant relational entities or weights.

## Architectural upgrade — Context Intelligence Engine

- separation of Retriever Top-k from final context — **completed**;
- mean, population standard deviation, and coefficient of variation — **completed**;
- discard, convergence, and core regions — **completed**;
- deterministic convergence fallback when no core exists — **completed**;
- lineage resolution only after statistical selection — **completed**;
- transient auditable `context_intelligence` output — **completed**;
- core as primary reference and convergence as available complementary context — **completed**;
- `used_evidence_ids` contract derived from visible citations, with omitted candidates discarded — **completed**;
- closed validation of analytical incorporation, without automatic completion or citation inventories — **completed**;
- directed live reference validation with 10/10 evidence records incorporated and no truncation — **completed**;
- isolated tests without a database or external calls — **completed**;
- representative-corpus comparison of quality, stability, latency, and tokens — **pending**.

## Future work — strict interaction semantics

- distinguish thematic convergence from demonstrable `simetry` reciprocity — **future**;
- require explicit support for both directions of `simetry` — **future**;
- strengthen origin/destination demonstration for `assimetry` without inferring causation or hierarchy — **future**;
- preserve a valid documentary answer when no strict interaction can be proved — **current principle**.

## Advancement criterion

Each phase requires a working flow, critical tests, safe failures, and documentation consistent with actual behavior.

## Next experiment — energy sustainability

- measure joules per query and kWh per thousand queries in an instrumented environment;
- amortize build cost across different query volumes;
- compare EVA, fixed-block vector RAG, long-context retrieval, GraphRAG, and agentic RAG at equivalent documentary quality;
- separate direct, structural, broad, conceptual, relational, and negative-control queries;
- record external calls, embeddings, tokens, GPU time, latency, and build reuse;
- publish dispersion, experimental configuration, and generalization limits.

Until this protocol is executed, energy efficiency remains an architectural hypothesis supported by computational-containment mechanisms, not a claim of experimentally demonstrated superiority. See [Scientific scope and energy sustainability](08_SCIENTIFIC_AND_ENERGY.md).
