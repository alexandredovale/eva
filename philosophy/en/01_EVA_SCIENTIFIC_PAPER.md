# EVA (Evidence Algorithm): hierarchical documentary memory and transient cognitive interaction for verifiable answers

**Version:** 6.0.0
**Date:** August 2, 2026
**Author:** EVA Project
**Português:** [Artigo científico integral](../01_EVA_SCIENTIFIC_PAPER.md)

## Abstract

This paper presents EVA (Evidence Algorithm), an architecture for language-model-assisted documentary queries whose persistent memory is organized as traceable evidence rather than answers, cognitive relationships, or inferred graphs. The system transforms structured documents into a normalized tree, preserves literal content as primary evidence, and generates embeddings for these complete semantic units. The representation respects document organization instead of fragmenting it by arbitrary character or token limits.

At query time, EVA selects a retrieval route compatible with input type. Direct, structural, and broad questions may navigate hierarchy; conceptual and relational questions use a transient vector representation. On semantic routes, κq emerges from the complete primary population before the first Context Intelligence Engine (CIE). That stage forwards only its `s ≥ μ + σ` core, with convergence as fallback; surviving sources pass through κe and primary CIE, and the union of local nuclei receives global CIE. The global nucleus—or convergence when that nucleus is empty—forms semantic context without a configured Top-k or evidence count. Only sources incorporated into prose with visible citations enter the final basis. If no sufficient primary evidence is found, the flow stops without calling the answer provider.

Cognitive relationships are transient **simetry** or **assimetry** interactions produced only within the query, without weights, judgmental taxonomies, or persistence. In multidisciplinary projects, evidence from different specialized documents may support emergent conceptual syntheses without promoting the resulting interpretation to evidence or memory. Citations and interaction participants are validated locally against recovered context. The proposal separates documentary memory, retrieval, interpretation, and presentation while keeping models and providers externally configured and replaceable. This paper describes the current architecture, its testable hypotheses, limitations, and a protocol for future evaluation.

**Keywords:** evidence; information retrieval; RAG; embeddings; documentary memory; traceability; statistical context stabilization; cognitive interaction; interdisciplinarity; anti-evasion; simetry; assimetry; language models.

---

## 1. Introduction

Language models can formulate coherent answers even when required information is absent, incomplete, or incorrectly recovered. In documentary applications, fluency does not demonstrate correspondence with a source. Retrieval-Augmented Generation (RAG) reduces this risk by supplying external context [1], but vector search alone does not guarantee structural preservation, source identity, valid citations, or appropriate refusal.

A conventional implementation often splits text by size, embeds the fragments, and selects nearest neighbors. This strategy is useful and scalable, but it can separate definitions from titles, lists from introductions, and paragraphs from their argumentative position. It may also become difficult to reconstruct the documentary role of each fragment.

EVA makes a different decision: memory should reflect semantic organization already present in the document. Titles, sections, paragraphs, items, properties, and elements form a tree. Literal tree content becomes primary evidence, and only those sources receive embeddings and support the final answer.

The second decision separates memory from interaction. Relationships identified between input and evidence do not become permanent facts. They describe the cognitive configuration of that query and are discarded afterward. This boundary prevents a contingent, model-produced interpretation conditioned by limited context from acquiring the status of documentary source.

This paper documents current EVA. It does not claim empirical superiority over competing architectures. It states mechanisms, expected properties, limits, and hypotheses that can be tested through reproducible experiments.

## 2. Research problem

The central question is:

> How can a natural-language-queryable documentary memory preserve structure and provenance, use models without delegating memory authority to them, and produce verifiable answers and interactions without turning transient interpretations into persistent knowledge?

This question contains seven interdependent problems:

1. **semantic segmentation:** represent documents without arbitrary cuts;
2. **provenance:** preserve the source and structural origin of literal evidence;
3. **adaptive retrieval:** avoid forcing vector search on structurally answerable questions;
4. **grounding:** prevent documentary answers when no primary evidence is recovered;
5. **epistemological boundary:** prevent similarities, answers, and inferred relationships from automatically becoming memory;
6. **multidisciplinary articulation:** relate distinct specialized sources without erasing provenance or contaminating the collection with transient interpretation;
7. **context stabilization:** make semantic boundaries emerge from query-local geometry and consolidate primary nuclei through a deterministic, auditable transformation without delegating selection to another model.

EVA treats these problems as one chain. Final quality depends not only on the generator but on the contract among ingestion, persistence, retrieval, validation, and presentation.

## 3. Design principles

### 3.1 Source before interpretation

Literal content remains distinguishable from every model-produced transformation. Retrieval operates directly over the indexed source rather than an intermediate summary.

### 3.2 Structure before size

A unit is defined by its documentary function, not a fixed character count. Technical limits may prevent processing an oversized unit, but they are not the segmentation principle.

### 3.3 Retrieval before generation

The answer provider is called only when validated primary context exists. Lack of evidence is a legitimate result, not a gap to fill with parametric knowledge.

### 3.4 Similarity without authority

Vector similarity orders candidates. It does not measure truth, moral importance, agreement, or cognitive intensity. CIE observes the distribution to identify discard, convergence, and core without additional grades or AI judgment. Values and statistics are transient.

### 3.5 Interaction without judgment

Cognitive interactions describe reciprocity or direction. They receive no weights and do not persist labels such as “supports,” “contradicts,” or “causes” as ontological relationships. Natural-language explanation may describe convergence or divergence, but interaction structure remains neutral.

### 3.6 Memory only through controlled construction

Queries are read operations. Memory changes only through explicit backend-governed ingestion and build processes. Models never write directly to the database.

### 3.7 Provider independence and white label

Functional roles have neutral names. Provider, model, endpoint, and credential associations are external configuration. The conceptual architecture remains independent of specific brands.

### 3.8 Interdisciplinarity without source fusion

Grouping documents in a project expands authorized retrieval without merging documents, evidence, or disciplines into indistinct memory. A cross-field relationship is formulated only when the current input selects evidence capable of participating in the same analysis. The relationship remains transient while each participant retains documentary origin.

This permits emergent conceptual syntheses without granting permanence to interpretation. The system may articulate a relationship not fully present in one source when its components are traceable; it cannot turn that articulation into new evidence, intrinsic truth, or a silent premise for later queries.

## 4. Relation to prior work

RAG combines retrieval and generation to supply external knowledge [1]. Dense Passage Retrieval demonstrated dense representations for passage retrieval [2]. Later work addressed self-evaluation and retrieval control, including Self-RAG [3], and long answers with citations, including ALCE [4].

Other work explores long contexts [5], retrieval at different granularities [6], and GraphRAG structures of entities, relationships, communities, and summaries for local and global questions [7]. Research on long-context embeddings and text representation also shows that embedding models and represented-unit composition influence retrieval [8][9].

EVA is compatible with this field but adopts a specific boundary:

- not every input follows the same vector search;
- cognitive relationships do not form a persistent graph;
- no intermediate generated text substitutes literal sources;
- query-produced relationships do not persist;
- documentary generation requires recovered primary evidence;
- no AI reranker chooses final semantic context;
- source hierarchy remains part of memory.

EVA is therefore not a rejection of RAG or GraphRAG. It is an evidence architecture with a more restrictive permanence policy.

## 5. Documentary-memory model

### 5.1 Input and normalized tree

The current implementation accepts Markdown, JSON, and XML. Each parser converts its source to a normalized tree without erasing relevant original content, order, or hierarchy.

Depending on format, each node records structural type, title or label, complete content, sibling position, parent, depth, a verifiable source reference such as lines, JSON Pointer, or XPath, and a hash for identity and change control.

The parser does not resolve the philosophical meaning of a passage. Its responsibility is structural: preserve a stable representation on which evidence can be built.

### 5.2 Primary evidence

Every eligible non-empty node may produce primary `node_content` evidence. Its content is literal and references its document node.

For document `D` and normalized nodes `N(D)`:

\[
E_P(D) = \{e(n) \mid n \in N(D),\; content(n) \neq \varnothing\}
\]

Each `e(n)` retains `n`'s structural reference. Primary evidence is not an opinion about text; it is a traceable unit of that text.

### 5.3 Structural embeddings

Persistent embeddings are produced exclusively for primary evidence. The provider receives a unit already organized semantically by the document. The algorithm does not create segments merely to satisfy arbitrary size.

Each embedding is associated with evidence, model configuration, dimension, and content hash. Obsolescence can therefore be detected and representations rebuilt when content or configuration changes.

### 5.4 Persistent boundary

| Structure | Function |
|---|---|
| `documents` | document identity, format, hash, and state |
| `document_nodes` | normalized tree and source references |
| `evidences` | literal primary evidence |
| `evidence_embeddings` | versioned vector representations |
| `processing_jobs` | embedding stage |
| `audit_events` | sanitized operational events |

Current memory has no cognitive-node, cognitive-relationship, relationship-embedding, interaction-analysis, or query-cache tables. It has no relational-graph build stage. The model-assisted persistent build stage produces primary embeddings.

## 6. Ingestion processing

The build flow is:

1. receive and validate the file;
2. identify a supported format;
3. parse the complete source into a normalized tree;
4. persist document and nodes;
5. deterministically create primary evidence;
6. create embeddings for eligible primary evidence;
7. update processing and audit states.

The application controls transactions, validation, and persistence. Failures can resume at the embedding stage, and existing vectors are reused while model and hash remain compatible.

## 7. Query flow

### 7.1 Operational input detection

EVA locally classifies input as:

- **direct:** seeks an explicit passage or fact;
- **structural:** requests a section, chapter, or hierarchical position;
- **broad:** requests extensive document coverage;
- **conceptual:** expresses an idea that may not repeat source vocabulary;
- **relational:** requests or implies interaction among concepts or evidence.

Categories may guide different routes. Detection does not diagnose psychological intent; it selects retrieval mechanisms.

### 7.2 Hierarchical retrieval

Direct, structural, and broad inputs may use textual and hierarchical navigation. This route prioritizes primary evidence and avoids a query embedding when structure already supplies the path.

Hierarchy recovers a unit and its context while respecting order and parentage. A section is not presented as a disordered sequence of similar fragments.

Recovered units remain candidates until the application composes available context within the non-semantic operational limit. The provider cannot introduce external sources or identifiers but may omit candidates that do not contribute. Only visibly cited evidence incorporated into prose enters the final basis; uncited candidates are discarded without invalidating the entire generation. If no evidence is recovered, the system returns a justified absence without calling the answer provider.

### 7.3 Semantic retrieval

Conceptual and relational inputs receive a transient embedding. For query `q` and persistent evidence `eᵢ`:

\[
sim(q,e_i) = \frac{v_q \cdot v_i}{\|v_q\|\|v_i\|}
\]

The value orders every validated and embedded primary evidence record in each document. κq analyzes the ordered curve and, when a break is confirmed by that query's own gaps, legitimizes the population before it; without an identifiable break, the complete population proceeds to CIE. Similarity is not persisted and is not epistemic confidence.

For `N` similarities `sᵢ`, CIE calculates:

\[
\mu = \frac{1}{N}\sum_{i=1}^{N}s_i
\]

\[
\sigma = \sqrt{\frac{1}{N}\sum_{i=1}^{N}(s_i-\mu)^2}
\]

\[
CV = \frac{\sigma}{\mu}
\]

When `μ = 0`, CV is `null`. `s < μ` is discard; `μ ≤ s < μ + σ` is convergence; `s ≥ μ + σ` is core. At every CIE stage, core is elected and convergence takes its place only when core is empty. Minimal numerical tolerance protects threshold comparisons without changing values.

The transformation is deterministic, preserves Retriever order within regions, and creates no additional grade, weight, or rank. Query output may expose regions and statistics for audit, but the answer provider receives only resolved final primary context.

The first CIE operates directly over primary evidence. Its upper core, or its convergence when the core is empty, passes through κe and primary CIE using its own cosine against the same query.

### 7.4 Multi-document query and transient selection

For a project query, documents remain independent retrieval units. Each work independently executes κq, first source CIE, κe, and primary CIE. Local primary nuclei are united, deduplicated, and analyzed by global CIE. Composition is determined by current input, has no configured count, and creates no persistent inter-document relationship.

Evidence from different disciplines may reach the provider simultaneously. The model may formulate relational synthesis, but every documentary claim remains tied to cited evidence from each field.

Adding documents expands the candidate universe without precomputing a complete connection mesh or predetermining answer evidence count. `K(q)` emerges from global CIE and may grow or shrink with the distribution. Interdisciplinarity is query-activated, not accumulated as inferred memory.

### 7.5 Evidence gate

After retrieval, the system verifies that at least one usable primary evidence record exists. If none exists, it returns a limitation and does not call the answer provider. If only some input aspects have support, the gate preserves valid context; the provider answers supported aspects with citations and declares specific limitations for unsupported aspects.

\[
Answer(q) =
\begin{cases}
g(q, E_P^*) & \text{if } E_P^* \neq \varnothing \\
Limitation & \text{if } E_P^* = \varnothing
\end{cases}
\]

`E_P*` is validated primary context and `g` the configured answer provider. The gate reduces evasion but does not prove semantic correctness.

### 7.6 Joint answer and interaction generation

When evidence exists, the nominal provider attempt receives current input, up to three prior conversational rounds attached by the interface, and available primary context. The same generation produces answer text, used evidence identifiers, `simetry` and `assimetry` interactions when at least two evidence records and a positive configured interaction limit exist, and relevant routing points or limitations.

There is no separate relationship provider or graph-persistence analysis. Truncated output permits at most one compact regeneration within an attempt. Local validation separately permits at most three total attempts with the same context; each rejected output is discarded completely.

### 7.7 Local validation

Provider output is a proposal. The backend verifies that every used ID belongs to supplied context, no unknown citation is accepted, interaction participants are recovered and cited, attributed excerpts match available content, `simetry` has two reciprocal participants, and `assimetry` identifies origin and destination.

`used_evidence_ids` is derived from visible citations in `answer`. An uncited recovered candidate is discarded from the final basis without another attempt. The application does not add omitted identifiers and rejects isolated citation inventories.

### 7.8 Transience and privacy

Input embedding, similarities, CIE statistics and regions, assembled context, answer, and interactions do not return to documentary memory. `simetry`/`assimetry` pairs, roles, descriptions, and excerpts are discarded after the request.

Current behavior supports short transient continuity. The interface keeps the complete transcript only in page memory and attaches at most the three latest completed rounds. The model decides whether the current request depends on them and should ignore unrelated messages.

Prior inputs and answers may clarify anaphoric references but never become documentary sources. The new answer remains limited to primary evidence recovered for the current request. The visual transcript disappears on chat reset, logout, login, or reload. Sanitized `audit_events` may record completed-query metadata and interaction counts; subscribed modules may store the permitted `interaction.completed` envelope without altering documentary memory.

### 7.9 External cost by route

| Route result | Input embedding | Answer | Normal total |
|---|---:|---:|---:|
| direct, structural, or broad with evidence | 0 | 1 | 1 |
| conceptual or relational with evidence | 1 | 1 | 2 |
| direct, structural, or broad without evidence | 0 | 0 | 0 |
| conceptual or relational without evidence | 1 | 0 | 1 |

This is the normal current path, excluding network retries and administrative operations.

## 8. Simetry and assimetry

### 8.1 Foundation

Cognitive interaction is the observable form in which participants relate within a query, not a universal fact definitively extracted from a document.

- `simetry(A,B)`: `A` and `B` participate reciprocally;
- `assimetry(A → B)`: interaction explicitly proceeds from `A` to `B`.

The schema records structure, participants, and textual basis—not weight, intensity, approval, or ontological precedence. These are system operators, not source terms. They are evaluated only among effectively cited evidence when context and limit enable analysis. If interaction cannot be validated, the supported documentary answer remains and a relational limitation is declared.

### 8.2 Neutrality

Taxonomies such as `supports`, `contradicts`, `causes`, or `depends_on` can impose rigid or judgmental interpretation. They are not part of EVA's persistent model. The answer may explain divergence or convergence in natural language with evidence, but the explanation remains situated, traceable, and transient.

### 8.3 Repositioning Cnode

Earlier versions treated Cnode as a persistent entity with identity, relationships, and embeddings. That architecture was removed. Today the term may describe only the historical or phenomenological query-time encounter between input and evidence. It is not a table, record, global identity, graph, cache, or autonomous memory unit. Evidence Algorithm is the persistent pillar.

### 8.4 Multidisciplinary reliability and anti-evasion

EVA reliability is a property of a verifiable process, not a probability-of-truth score. In multidisciplinary queries it preserves five boundaries: source identity, primary-evidence integrity, transient context selection, local output validation, and declared limitations.

The system prevents fluency from replacing support. If one requested discipline lacks evidence, synthesis must be restricted to the supported subset and name the absence. If no area has support, documentary generation is blocked. An emergent synthesis is reliable only in the sense that it is auditable to documentary participants; retrieval and interpretation errors remain possible and must be measured.

## 9. Governance, security, and auditability

The public surface is separated from private application files. Administrative operations require a bearer token configured outside code. Credentials and provider associations are not exposed by public endpoints.

Operational events are sanitized. Core records `simetry` and `assimetry` counts in completed-query audits. When subscribed, modular Runtime may persist the contractual `interaction.completed` envelope in its neutral mailbox and private module storage. Queries remain read-only relative to documentary memory.

The current security model must not be overstated: superadmin may use the installation credential; authenticated users have revocable sessions and explicit project/document permissions; projects group works for multi-document query. This is not global identity, inter-organization collaboration, or multitenant isolation.

Auditability derives chiefly from primary evidence references to tree/source, content and embedding versioning, and validation of answer citations.

## 10. Architectural comparison

| Dimension | Block vector RAG | Long context | GraphRAG | Current EVA |
|---|---|---|---|---|
| Primary unit | windowed block | document or large passage | entity, relationship, community | document node and evidence |
| Original structure | often partial | present in input, selection-limited | converted to graph | persisted as tree |
| Summaries | optional | optional | central to communities | absent from the operational flow |
| Persistent relationships | usually no | no | yes | no cognitive interactions |
| Vector search | dominant route | optional | graph-combined | only when type requires |
| Final source | recovered block | supplied context | nodes, relations, reports | resolved primary evidence |
| No evidence | prompt-dependent | model-dependent | implementation-dependent | blocked before answer |
| Cognitive interaction | not standardized | not standardized | graph relationship | transient simetry/assimetry |
| Post-query memory | varies | varies | graph may be enriched | invariably unchanged |

This table describes design choices, not a quality ranking.

## 11. Expected properties and hypotheses

### H1 — Traceability

EVA answers should permit a high primary-source location rate because retrieval operates directly over the indexed sources.

### H2 — Semantic organization

Embeddings of complete structural units should improve precision and contextual coherence for section-dependent questions compared with fixed-size blocks.

### H3 — Grounded refusal

The evidence gate should reduce answers to unsupported questions while preserving answers when relevant sources are recovered.

### H4 — Routing efficiency

Hierarchical navigation for direct and structural questions should reduce embedding calls and latency compared with mandatory vector routing.

### H5 — Direct primary retrieval

Indexing and retrieving primary evidence directly should reduce information loss introduced by an intermediate summarization stage while preserving literal verifiability.

### H6 — Relational neutrality

Transient simetry/assimetry should explain convergence, divergence, and direction without weights or a persistent judgmental ontology.

### H7 — Portability

Neutral contracts and external configuration should allow provider replacement within the integration layer while preserving memory and core rules.

### H8 — Traceable multidisciplinary articulation

Projects with documents from different disciplines should permit relational syntheses with provenance by field, a low unsupported-relationship rate, and unchanged memory after interaction.

### H9 — Statistical context stabilization

For the same Retriever, corpus, and final budget, CIE should reduce noise and increase context stability across nearby paraphrases without unacceptable recall loss or AI reranking.

## 12. Proposed experimental protocol

### 12.1 Baselines

Compare fixed-size block RAG; overlapping-block RAG; primary-only retrieval without EVA routing; EVA routing and evidence gate without CIE; complete EVA with CIE; a reference reranker over a declared candidate budget; and long context when technically and economically comparable. Use the same documents, embedding provider, answer provider, and context budget wherever possible.

### 12.2 Corpora

Include long hierarchical Markdown; JSON with objects, lists, and depths; XML with attributes, repeated elements, and supported namespaces; short documents with few primary units; modified versions for hash/rebuild testing; and multidisciplinary projects containing explicit convergence, conceptual tension, different vocabularies, and genuine absence of relationship.

### 12.3 Question set

Independent evaluators should annotate literal location, structural navigation, broad synthesis, conceptual paraphrase, passage relationship, source-contradictory premise, partially supported question, fully out-of-collection question, ambiguous question, cross-discipline evidence relationship, and partially supported emergent synthesis. Difficult and negative examples are essential for measuring refusal.

### 12.4 Retrieval metrics

- primary-evidence Recall@k and Precision@k;
- Mean Reciprocal Rank and nDCG;
- direct primary-source location rate;
- structural coverage and context duplication;
- balanced document coverage in multidisciplinary queries;
- improper exclusion of a relevant discipline by κq, κe, or global nucleus;
- discard/convergence/core proportions;
- core/convergence stability across paraphrases;
- precision, recall, and context-token differences with and without CIE.

### 12.5 Answer metrics

Source-relative factual correctness, completeness, citation precision, necessary-citation recall, unsupported-claim rate, correct and incorrect refusal rates, and explanation quality for contradictory premises.

### 12.6 Interaction metrics

Participant and excerpt validity, reciprocity precision in `simetry`, directional precision in `assimetry`, unsupported-interaction rate, human inter-annotator agreement, participant disciplinary-provenance precision, and multidisciplinary synthesis extrapolation rate.

### 12.7 Operational metrics

Latency by route, external calls per query, input/output tokens, cost per document and question, primary-index build time and space, failure rate, and retries.

### 12.8 Ablations

Individually remove type routing, evidence gate, local citation validation, relational detection, hierarchical-context preservation, κq, κe, and each CIE stage.

### 12.9 Reproducibility

Record code and schema versions, configured models, embedding dimensions, document hashes, retrieval parameters, generation temperature, and prompts. Sensitive data and credentials must never enter a reproduction package.

## 13. Preliminary operational observations

Manual tests indicated three design-consistent behaviors: supported questions received visibly grounded answers with low perceived latency; unsupported questions stopped before documentary generation; and inputs whose premises diverged from a work were explained from recovered evidence without automatic agreement.

These are functional checks, not generalizable scientific results. There was no controlled sampling, blind evaluation, statistical baseline comparison, or formal bias measurement.

## 14. Limitations

### 14.1 Embedding dependence

Relevant concepts may receive low similarity; embeddings may also bring only superficially similar passages close together. κq observes the complete eligible population but depends on embeddings, corpus, and break detection. κe and all CIE stages do not repair missing or structurally oversized index units. Mean as a cutoff assumes the arithmetic center is useful; skewed, concentrated, or near-zero-mean distributions may require robust measures. The current cutoff is not universally optimal.

### 14.2 Primary-only retrieval

Removing summaries avoids compression loss, but increases the indexed population and computational cost. Statistical selection still does not guarantee that every necessary source will be selected.

### 14.3 Input classification

Incorrect operational detection may choose an inferior route. Hybrid, ambiguous, or very short questions are difficult.

### 14.4 Local-validation limits

Valid IDs and participants do not prove entailment. A sentence may cite the correct evidence and still misinterpret it.

### 14.5 Answer-provider dependence

Even with valid context, the provider may omit aspects, return invalid structure, or explain inaccurately. The backend contains structural errors, not every reasoning error.

### 14.6 Format scope

The documented implementation covers Markdown, JSON, and XML. Other formats require structure- and reference-preserving parsers.

### 14.7 Short, non-persistent conversational context

EVA resolves anaphoric questions through up to three prior rounds attached to input. The model must distinguish continuity from subject change. Prior terms may affect retrieval; the short limit reduces but does not eliminate that possibility. The transcript exists only in the open interface and does not continue across devices, tabs, reloads, or sessions.

### 14.8 No persistent ontology

Absence of a relational graph reduces complexity and inference contamination but does not directly serve persistent ontological traversals or global network analysis.

### 14.9 Authorization scope

Current superadmin, user, session, project, and document authorization is not multitenant isolation among organizations. Shared institutional environments need additional tenant boundaries, delegated administration, and isolation testing.

### 14.10 Multidisciplinary coverage limited by context

Global CIE does not guarantee every relevant discipline appears in its nucleus. A numerous distribution from one field may change global `μ` and `σ`; no statistical boundary proves complete thematic coverage. A synthesis can be incomplete even when every presented claim has a valid citation.

## 15. Discussion

EVA's central commitment is to make the document/interpretation boundary explicit. Trees and primary evidence belong to memory. Similarity, assembled context, answer, and interaction belong to the query event.

This separation reduces the temptation to treat every model output as knowledge and makes evolution easier: retrieval strategies can change without migrating inferred relationships, and providers can change without altering source identity.

Direct primary retrieval removes the abstraction layer between indexing and source selection. Simetry and assimetry follow the same discipline: enough structure for reciprocity or direction without turning an encounter into ontology. Absence of weights refuses to present an implicit estimate as objective force.

In multidisciplinary projects, this discipline brings vocabularies and concepts together without dissolving sources. Reliability comes from auditing participants, accepted relationships, and declared gaps—not from completeness or persistence of emergent synthesis.

The cost is real. EVA may refuse questions a general model could answer correctly from parametric knowledge and may omit persistent relationships useful in some domains. It accepts this cost because its goal is not to answer every question, but to answer consistently with the declared collection.

### 15.1 Compact flow and separated responsibilities

The epistemological chain reduces to seven operations:

```text
DOCUMENT → EVIDENCE → LOCATION → AVAILABLE CONTEXT
         → GENERATION + INTERACTIONS → LOCAL VALIDATION → ANSWER
```

The document establishes origin; evidence preserves literal content and structural reference; retrieval locates candidates; the application composes authorized context; the model writes and proposes interactions; local validation retains reconstructable citations and relationships:

```text
primary evidence is retrieved
source supports
application validates
model communicates
```

The embedding model does not create or determine the truth of a source, and the answer model does not decide persistence. This compactness is a design property, not evidence of superiority. Experiments must determine whether it preserves meaning, remains stable under stylistic and contradictory variations, and declares limits without evasion.

## 16. Conclusion

EVA organizes documentary memory as verifiable primary evidence over a preserved structural tree. Embeddings represent complete source units, and queries choose hierarchical or semantic routes according to operational form. On vector routes, κq, κe, and first-source, primary, and global CIE establish mathematical boundaries between retrieval and interpretation, making final quantity emerge from query geometry.

The system blocks documentary generation without recovered primary evidence, locally validates citations and participants, and treats cognitive relationships as transient simetry or assimetry. Cnode no longer denotes a persistent entity, only the contextual interaction phenomenon when needed.

In multidisciplinary scope, transience permits articulation across specialized documents without cumulative database contamination. A new query-local synthesis remains traceable, limited interpretation, never automatic evidence or incorporated truth.

The architecture does not eliminate retrieval and generation risks; it makes them more observable and auditable. Its proposed contribution is a discipline of memory and context: preserve source, record derivation, stabilize retrieval without subjective judgment, restrict persistence, and state clearly when the document does not support an answer. Superiority of the current statistical cutoff remains a comparative hypothesis.

## References

[1] Lewis, P. et al. (2020). *Retrieval-Augmented Generation for Knowledge-Intensive NLP Tasks*. NeurIPS 33. [Official publication](https://proceedings.neurips.cc/paper/2020/hash/6b493230205f780e1bc26945df7481e5-Abstract.html) · [arXiv:2005.11401](https://arxiv.org/abs/2005.11401)

[2] Karpukhin, V. et al. (2020). *Dense Passage Retrieval for Open-Domain Question Answering*. EMNLP, 6769–6781. [ACL Anthology](https://aclanthology.org/2020.emnlp-main.550/) · [arXiv:2004.04906](https://arxiv.org/abs/2004.04906)

[3] Asai, A. et al. (2024). *Self-RAG: Learning to Retrieve, Generate, and Critique through Self-Reflection*. ICLR. [arXiv:2310.11511](https://arxiv.org/abs/2310.11511)

[4] Gao, T. et al. (2023). *Enabling Large Language Models to Generate Text with Citations*. EMNLP, 6465–6488. [ACL Anthology](https://aclanthology.org/2023.emnlp-main.398/) · [arXiv:2305.14627](https://arxiv.org/abs/2305.14627)

[5] Liu, N. F. et al. (2024). *Lost in the Middle: How Language Models Use Long Contexts*. TACL 12, 157–173. [ACL Anthology](https://aclanthology.org/2024.tacl-1.9/) · [arXiv:2307.03172](https://arxiv.org/abs/2307.03172)

[6] Chen, T. et al. (2024). *Dense X Retrieval: What Retrieval Granularity Should We Use?* EMNLP, 15159–15177. [ACL Anthology](https://aclanthology.org/2024.emnlp-main.845/) · [arXiv:2312.06648](https://arxiv.org/abs/2312.06648)

[7] Edge, D. et al. (2024). *From Local to Global: A Graph RAG Approach to Query-Focused Summarization*. [arXiv:2404.16130](https://arxiv.org/abs/2404.16130)

[8] Wang, L. et al. (2022). *Text Embeddings by Weakly-Supervised Contrastive Pre-training*. [arXiv:2212.03533](https://arxiv.org/abs/2212.03533)

[9] Günther, M. et al. (2023). *Jina Embeddings 2: 8192-Token General-Purpose Text Embeddings for Long Documents*. [arXiv:2310.19923](https://arxiv.org/abs/2310.19923)
