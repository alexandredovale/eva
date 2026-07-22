# Part One

## Chapter I — System Foundations

### Definitions

1. A documentary evidence system receives structured source material and preserves its hierarchy before any semantic operation is attempted. This synthetic unit is intentionally long so the regression suite can verify that a complete semantic unit is never cut merely to satisfy an arbitrary character boundary. The source remains the authority, the normalized tree remains a structural representation, and every primary evidence record must remain a literal projection of content that belongs directly to one node. A derived summary may improve navigation, but it must remain distinguishable from literal material and retain a complete lineage to the evidence from which it was produced.

The same requirement applies when the source contains sections of very different sizes. A small unit and a large unit are both documentary units; their size does not authorize the parser, ingestion service, embedding service, or query layer to invent a new boundary. If a provider cannot accept the long unit, the system must use a real documentary subdivision or a traceable derived representation. It must never silently truncate the primary source. This paragraph exists only as original test material and does not describe any private deployment, credential, provider, person, or production document.

Traceability is preserved across every stage. The source hash identifies the received bytes, each normalized node records its path and source reference, primary evidence copies the node content, and derived evidence records its input lineage. Retrieval may rank compatible candidates transiently, but ranking does not change the source and does not become a persistent cognitive judgment. A response may cite only evidence that belongs to the recovered context. An interaction may be accepted only when both participants and their literal excerpts can be validated locally.

The fixture repeats these principles in sufficient detail to exercise long-input behavior without importing any third-party book or operational database. It is deliberately synthetic and is distributed under the same Apache License 2.0 as the software repository. Contributors may replace or extend it only when they also update the expected source hash and structural assertions. The fixture must remain free of personal data, secrets, copyrighted corpora, real access tokens, production endpoints, and customer material.

The ingestion boundary is deterministic. Markdown headings define structural levels, authored numbered blocks become item units, continuous text remains attached to its correct node, and source order is preserved. JSON and XML use different references but resolve to the same normalized contract. None of these parsers generates summaries, embeddings, confidence values, relationships, or interpretations. Those responsibilities belong to later services and remain constrained by the evidence contract.

The cognitive build proceeds from leaves toward the root. A summary provider receives structured units with their own content and child summaries. Identical versions are reusable by model and content hash. The embedding provider receives complete structured evidence units in bounded technical batches. A technical batch may group units for transport, but it may not split the meaning of an individual evidence record. The test suite uses simulated providers so validation never requires paid requests or live credentials.

The query stage distinguishes direct, structural, broad, conceptual, and relational inputs. Direct, structural, and broad routes can retrieve through identifiers and document hierarchy without a query embedding. Conceptual and relational routes may create one transient embedding. When no primary evidence is recovered, answer generation is skipped. When candidates exist, the answer provider must identify which evidence actually supports the response, and the application verifies those identifiers against the supplied context.

Conversational continuity is bounded and subordinate to documentary evidence. A short transcript can clarify whether the current request continues an earlier exchange, but prior questions and answers never become source evidence. Resetting the chat removes this temporary context without changing documents, evidence, derivations, embeddings, projects, or access grants. This separation keeps user experience conversational while preserving the epistemic boundary of the system.

Operational safety also depends on explicit configuration. Live artificial-intelligence calls remain disabled by default and require a configuration flag plus a deliberate command option. Credentials belong only to local environment files and must never appear in source code, logs, documentation, fixtures, database dumps, or repository history. Public distributions include a sanitized environment template, schema and migrations, but exclude operational databases, uploaded sources, and runtime logs.

This final paragraph completes the intentionally oversized item. Its only purpose is to ensure that summary and embedding regression checks receive at least one complete primary evidence unit exceeding five thousand characters. The test does not assert the truth or importance of the text. It asserts structural preservation, literal persistence, reusable derived evidence, safe fallback behavior, and the absence of arbitrary token or character chunking.

2. A primary evidence record contains literal source content and a source reference.
3. A derived evidence record contains generated content and explicit input lineage.
4. A normalized node preserves title, depth, order, path, content, and children.
5. A source hash verifies byte-level identity without judging documentary truth.
6. A retrieval score is transient and is discarded after candidate ordering.
7. A query response is valid only within the evidence supplied for that query.

### Evidence

1. Evidence identifiers are stable public references within one installation.
2. Primary evidence is never replaced by a generated summary.
3. Derived evidence can guide retrieval back to primary sources.
4. Empty structural nodes do not create primary evidence.
5. Evidence class and evidence type serve different semantic responsibilities.

### Traceability

1. Every primary evidence record points to its normalized document node.
2. Every derived evidence record declares the evidence used to produce it.
3. Source references preserve line, pointer, or path information.
4. A cited identifier must belong to the context of the current query.
5. Literal excerpts are checked against the indicated primary evidence.

### Validation

1. Validation confirms structural and literal contracts rather than universal truth.
2. Unknown evidence identifiers invalidate a generated citation.
3. Invalid transient interactions are discarded without creating memory.
4. A response without usable evidence must state a documentary limitation.
5. Local checks remain deterministic and provider independent.

## Chapter II — System Components

### Data and Control

1. Data records preserve documents, nodes, evidence, derivations, and embeddings.
2. Control rules limit calls, retries, context, and output size.
3. Operational configuration remains outside domain contracts.
4. Database transactions protect the normalized tree during ingestion.
5. Public identifiers avoid exposing internal numeric database keys.

### Storage

1. Uploaded source files remain outside the public web directory.
2. Runtime logs never include prompts, credentials, or documentary content.
3. Database dumps are operational artifacts and are excluded from publication.
4. Storage names derive from internal identifiers instead of user filenames.
5. Deletion removes private source files after database changes succeed.

### Retrieval

1. Direct retrieval resolves explicit evidence identifiers.
2. Structural retrieval navigates titles and document paths.
3. Broad retrieval starts from upper hierarchical layers.
4. Semantic retrieval compares a transient query vector with evidence vectors.
5. Derived retrieval resolves lineage until primary evidence is reached.

### Providers

1. Embedding providers expose vectorization capability without brand coupling.
2. Summary providers expose hierarchical summarization capability.
3. Query providers return structured answers and used evidence identifiers.
4. Provider endpoints, models, and credential names belong to the environment.
5. Test providers simulate behavior without external network consumption.

## Chapter III — Operations

### Ingestion

1. Input validation checks format, size, encoding, and document integrity.
2. Markdown, JSON, and XML converge into one normalized tree contract.
3. Ingestion persists literal primary evidence and no semantic interaction.
4. A failed transaction does not leave a partial documentary tree.
5. Original content remains available for audit outside the public surface.

### Processing

1. Hierarchical summaries are produced from leaves toward the root.
2. Version hashes prevent identical summaries from being regenerated.
3. Embeddings represent complete, previously organized evidence units.
4. Batch boundaries group units but never split their documentary meaning.
5. Oversized primary units require structural subdivision or traceable summaries.

### Query

1. Input routing is local and does not require an additional language-model call.
2. Conceptual and relational routes use one transient query embedding.
3. Candidate evidence is distinct from evidence actually used in an answer.
4. No-evidence queries terminate without calling the answer provider.
5. A compact retry is bounded and occurs only after output truncation.

### Audit

1. Audit events contain sanitized operational metadata.
2. Network addresses are stored only as hashes when required.
3. Metrics describe counts and never create cognitive rankings.
4. Request identifiers support diagnostics without exposing content.
5. Provider responses and exception payloads are not persisted in logs.

## Chapter IV — Lifecycle

### Intelligence and Instinct

1. Intelligence in this fixture denotes explicit processing rules rather than a human trait.
2. Instinct denotes a deterministic fallback defined before runtime.
3. Their interaction is evaluated only when evidence supports the requested comparison.
4. Similarity alone does not prove a reciprocal or directed interaction.
5. The system preserves uncertainty by reporting missing documentary support.

### Operation and Shutdown

1. Startup loads configuration and validates required infrastructure.
2. Normal operation keeps live provider consumption disabled unless authorized.
3. A worker claims one queued job per ordinary execution.
4. Shutdown leaves persistent evidence unchanged and closes transient context.
5. Recovery resumes versioned work without repeating completed provider calls.

### Maintenance

1. Schema migrations are versioned and reviewed before deployment.
2. Backups remain private and are tested through controlled restoration.
3. Dependency and platform updates require regression validation.
4. Security reports are handled privately before coordinated disclosure.
5. Releases use semantic versions and record notable changes.

### Reserved Future Section

