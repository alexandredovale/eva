# EVA Philosophy — Evidence Algorithm

Version: 3.3
Conceptual status: current architecture
Português: [Filosofia do EVA](../00_EVA_PHILOSOPHY.md)

## 1. Central proposition

EVA is a verifiable documentary-memory system. Its unit of trust is not a model answer, an isolated probabilistic association, or an autonomous graph: it is evidence preserved with origin, structural context, and derivation lineage.

Memory belongs to the persisted documentary collection and to the deterministic rules governing its retrieval. Models may help summarize, represent meaning, and formulate answers, but they have no authority to redefine a source, create evidence, or alter memory during a query.

The database is the persistence medium for this memory; it is not, by itself, its epistemological authority. Authority comes from the verifiable correspondence between every evidence record and its source document.

## 2. Evidence before interpretation

EVA distinguishes only two persistent evidence classes:

- **primary evidence:** the literal content of a document node, preserved as a complete semantic unit;
- **derived evidence:** a hierarchical summary produced from already known evidence and always connected to its sources by explicit lineage.

Derived evidence expands the ability to locate and understand a document, but it does not replace primary evidence as the final basis of an answer. When a summary points to a relevant region, the system returns to the primary sources that support it.

This distinction contains a rule of prudence: interpretation may guide retrieval, but the claim presented to the user must remain anchored in recovered documentary text.

## 3. Semantic organization, not arbitrary fragmentation

Documents have their own organization: titles, sections, paragraphs, lists, properties, elements, and hierarchical relationships. EVA respects that organization.

Embeddings are not generated from arbitrary character cuts or blind token windows. They are generated for content that already forms complete semantic units in the document tree, including primary evidence and derived hierarchical summaries.

Preserving the unit organized by the author preserves an essential part of meaning. Structure is not decoration around text; it participates in the context needed to understand it.

## 4. Query understanding

Every query is first understood in terms of its operational form. It may require direct location, structural navigation, broad documentary coverage, or conceptual and relational semantic retrieval.

EVA does not reduce every question to one strategy. Direct, structural, and broad queries may be resolved through document hierarchy. Conceptual and relational queries may use a transient vector representation of the current input to locate semantically close primary and derived evidence.

Similarity is an ordering mechanism, not a judgment of truth, importance, or cognitive strength. On vector routes, the complete eligible primary population reveals its geometry before κq establishes a query-local boundary. Through mean, standard deviation, and coefficient of variation, the first Context Intelligence Engine observes that legitimized population and forwards only the `s ≥ μ + σ` core, using convergence only when the core is empty. κe and primary CIE preserve the subsequent calculations over surviving sources; global CIE then observes the union of local primary nuclei. The global nucleus—or its convergence when the nucleus is empty—determines the semantic context sent to the model, with no configured Top-k or human evidence count. Only sources the model incorporates into the answer with visible citations remain in the final basis. Statistical values and analyses are discarded after the query and never become memory, weight, or a permanent relationship.

## 5. Transient cognitive interactions

In EVA, cognitive relationships are not persistent entities. They exist only while understanding a concrete interaction, within the context formed by the current input and between evidence records actually cited in the answer.

There are two fundamental interaction forms:

- **simetry:** a reciprocal interaction between two participants, with no privileged origin or destination;
- **assimetry:** a directional interaction with explicitly identified origin and destination.

These annotations describe interaction form without assigning weights, intensity, moral value, or judgmental labels. EVA does not assume that one evidence record “wins,” “is worth more,” or should be favored. An interaction is admitted only when both cited participants, their roles, and literal excerpts pass local validation; the number of interactions does not represent strength, importance, or rank.

The names `simetry` and `assimetry` belong to EVA's internal vocabulary and need not occur in the source. They are essential operators for relational cognitive understanding: they guide how AI understands interactions between evidence records without being mistaken for documentary concepts whose literal presence must be searched.

The concept historically called **Cnode** remains only a way to understand this query-time cognitive interaction. In code, its concrete form is `RetrievedInteraction`, generated by the same call that formulates the answer and separated into `simetry_interactions` and `assimetry_interactions` in the result. It does not designate a table, persistent object, global identity, cache, dedicated embedding, or memory graph. When the query ends, its pairs, descriptions, and excerpts are discarded; only sanitized counts may remain in operational auditing.

## 6. Multidisciplinary application and reliability through constraint

An EVA project may bring together specialized documents from different disciplines without merging their identities, rewriting their sources, or building an ontology between them in advance. Every evidence record retains its document, structural position, and originating lineage. Administratively grouping works expands the authorized query space; it does not turn thematic proximity into a permanent factual relationship.

When a conceptual or relational input crosses disciplines, each document independently reveals κq and its first CIE directly over primary evidence. The upper core, or convergence as fallback, proceeds through κe and primary CIE, which elect local nuclei whose deduplicated union receives global CIE. Evidence from different fields may then participate in the same answer and in `simetry` or `assimetry` interactions, provided it belongs to the final global nucleus or protected literal anchors, is cited, and retains verifiable excerpts. The relationship begins with the query event and ends with it.

This behavior permits an **emergent conceptual synthesis**: an articulation that may not be fully expressed in any isolated document but whose composition is supported by the presented evidence. The emergent synthesis does not thereby acquire the status of evidence, a proven intrinsic concept, or new documentary memory. It remains situated interpretation, auditable and subject to the limitations of the recovered scope.

In this context, reliability does not mean infallibility, probability of truth, or absence of semantic error. It means that the system introduces verifiable conditions for trusting the process:

1. preservation of each source's identity and integrity;
2. separation of literal evidence, derived summary, and query interpretation;
3. resolution of summaries back to primary evidence;
4. local validation of citations, participants, and excerpts;
5. explicit declaration of what lacks sufficient support;
6. disposal of transient relationships after the answer.

Adding documents expands the candidate universe but does not alter existing evidence or create permanent connections among all works. This property preserves memory health and prevents cumulative contamination: later queries may reveal other multidisciplinary encounters without turning earlier interpretations into silent premises for future queries.

## 7. Neutrality and epistemic constraint

EVA must not fill gaps with plausibility. If sufficient primary evidence is absent, the correct operation is to declare the limitation and stop generation of a documentary answer.

Documentary sufficiency may be partial. If a question combines X, Y, and Z but only X and Y have support, EVA describes the supported relationship between X and Y, cites their sources, and identifies Z as an aspect without sufficient evidence. A gap in one part restricts that part; it does not erase what the document permits the system to answer about the others.

Recovering a candidate does not mean retaining it as final support. Retriever orders the complete primary population; κq legitimizes its boundary; the first CIE forwards only the upper core or its convergence fallback; κe and primary CIE elect local nuclei; and global CIE consolidates their union without a configured count. AI cannot introduce external sources or identifiers, but it may omit candidates that do not contribute to the prose. The inherited `core` or `convergence` role accompanies each source, exact literal anchors remain protected, and only effectively cited evidence remains in the final basis.

When input contradicts, shifts, or challenges recovered content, the system describes the divergence through available evidence. It does not judge the user, automatically accept the question's premise, or turn the document into universal authority. Its role is to present what the source supports and what it does not permit one to conclude.

This discipline constitutes the principle of **anti-evasion**:

1. do not answer beyond recovered evidence;
2. do not hide the absence of documentary support;
3. do not invent citations, relationships, or participants;
4. do not turn statistical similarity into certainty;
5. do not persist interpretations produced during conversation.

## 8. Validation and traceability

A verifiable answer requires more than a decorative citation. EVA locally validates that every cited identifier belongs to available context and that every retained evidence record appears in the analytical passage where it contributes. Recovered but uncited candidates are discarded without invalidating the whole answer. Every declared interaction must likewise involve cited evidence and excerpts recognized in that context.

Formal presence in `used_evidence_ids` does not prove use. The application does not add omitted markers and rejects isolated citation inventories because a list of IDs does not replace analytical incorporation. The system never creates a missing identifier or accepts an unknown reference.

Every persistent summary retains its derivation. Every primary evidence record preserves its position in the tree and its source reference. The chain of trust can therefore be traversed from the answer back to original documentary content.

## 9. Memory boundary

EVA persists only what is necessary to reconstruct and audit documentary knowledge:

- documents and their content identity;
- normalized structural tree;
- primary and derived evidence;
- derivation lineage;
- embeddings of persistent semantic units;
- processing states and sanitized audit events.

EVA does not persist raw queries, recovered context, similarities, CIE statistics or regions, answers, cognitive interactions, or conversation histories as documentary memory. For observability, `audit_events` may retain sanitized metadata, including `simetry` and `assimetry` counts. If a module subscribes, the `module_events` mailbox may record the permitted `interaction.completed` envelope, and each module governs its private state. These operational records do not rewrite documents, evidence, derivations, or embeddings. Querying is a read operation over the collection; it is not implicit authorization to rewrite knowledge.

## 10. Role of models

Models are replaceable components. While memory is built, they may produce derived summaries. During a query, they may generate the transient vector representation needed for semantic retrieval and formulate an answer from the evidence supplied.

Critical decisions remain under application control: which records may persist, which evidence enters available context, how derivations are resolved, which inherited core and convergence roles are preserved, which citations are valid, and when lack of evidence must terminate the flow. Through visible citations, the model determines only the subset it actually uses and cannot leave authorized context.

Providers, models, endpoints, and credentials are defined through neutral external configuration. This white-label commitment prevents any conceptual EVA component from depending on a particular company or model name.

## 11. Independence and scientific humility

EVA seeks provider independence, implementation reversibility, and auditability. Its architecture must remain understandable even when models, indexes, or internal strategies are replaced.

The system does not claim infallibility. Embeddings may bring unsuitable concepts close together, summaries may lose nuance, and answers may misinterpret evidence. Local validation proves identity, observable analytical coverage, and literal excerpts, but strict semantic calibration of `simetry` and `assimetry` remains a research frontier: thematic compatibility must not be mistaken for reciprocity or direction. The architecture therefore prioritizes traceability, local validation, return to primary sources, and explicit refusal when support is insufficient.

EVA's purpose is not to produce the appearance of knowledge. It is to make visible the boundary between what the document supports, what can be derived with lineage, and what remains unknown.

## 12. Operational simplicity and separation of responsibilities

EVA's architectural strength does not depend on accumulating cognitive layers, but on maintaining clear boundaries among document, evidence, location, validation, and response. Its essential flow can be expressed compactly:

```text
DOCUMENT
   ↓
EVIDENCE
   ↓
LOCATION / RETRIEVER
   ↓
κq → HIERARCHICAL CIE → LINEAGE
   ↓
κe → PRIMARY CIE → GLOBAL CIE
   ↓
ANSWER PROVIDER
   ↓
LOCAL VALIDATION
   ↓
ANSWER
```

Every stage has a verifiable responsibility. The document supplies origin; evidence preserves content and lineage; retrieval calculates the population; κq and κe legitimize query-local boundaries; the three CIE stages stabilize and consolidate the vector set; the model communicates the answer and proposes interactions within final context; and local validation retains only reconstructable citations and interactions.

This division limits model authority without discarding model capability:

```text
AI summarizes  → it does not create the source
AI represents → it does not determine truth
AI answers     → it does not choose what may persist
```

The operational commitment can be summarized in four functions:

```text
summary locates
source supports
application validates
model communicates
```

Simplicity in this context does not mean lack of rigor. It means that memory, interpretation, and presentation are not confused. The architecture remains compact because it avoids turning similarities, answers, and transient interactions into new permanent entities; it remains powerful because its constraints operate at persistence, retrieval, and local validation, not only in instructions sent to a model.

EVA development must preserve this architectural economy. New capabilities should expand the flow only when they maintain verifiable origin, the memory boundary, and explicit responsibility at every stage. Robustness must be demonstrated through reproducible tests of semantic preservation, stability under input variation, and non-evasive declaration of limitations.
