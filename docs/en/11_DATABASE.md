# Database

## Purpose

The database persists documentary memory with integrity and traceability without duplicating transient relationships or storing judgments and weights.

The consolidated empty schema is in [`database/schema.sql`](../../database/schema.sql) and creates all 14 current main-database tables, including `module_events`. Fresh installations import only that file. Existing installations evolve through every outstanding ordered migration in [`database/migrations/`](../../database/migrations/); migration `010` remains the upgrade path for pre-consolidation databases.

For the complete foreign-key map, logical relationships, access paths, and deletion behavior, see [Database relationships](18_DATABASE_RELATIONSHIPS.md).

## Persistent entities

- **`documents`:** source metadata, format, hash, private storage path, and processing state.
- **`document_nodes`:** normalized hierarchy, direct content, format metadata, structural path, documentary order, and exact source reference.
- **`evidences`:** persistent `primary` or `derived` units, additionally classified by `evidence_type`.
- **`evidence_derivations`:** lineage from derived evidence to the identified evidence used to generate it.
- **`evidence_embeddings`:** versioned vectors used only for semantic location.
- **`processing_jobs`:** queue records for the `summaries` and `embeddings` stages.
- **`audit_events`:** sanitized administrative and operational events.
- **`users`:** normal-user identities and password/recovery hashes.
- **`user_sessions`:** hashed, expiring authenticated sessions.
- **`projects`:** work groupings, active state, and an optional superadmin-managed response profile.
- **`project_documents`:** project-to-work membership.
- **`user_projects`:** project-level access grants.
- **`user_documents`:** individual-work access grants.
- **`module_events`:** neutral append-oriented mailbox for events allowed by the module contract, with explicit administrative retention.

There are no active `cnodes`, `cnode_evidences`, `cnode_embeddings`, or `interaction_analyses` tables. Historical migrations may mention removed architecture; the current schema and later migrations define the effective model.

## Module persistence

`module_events` is the only additional main-database table required by the Module Runtime. It is part of the consolidated schema and contains no module-specific business rule, analytical state, or schema. `database/migrations/20260803_010_module_events.sql` is retained for legacy upgrades. After answer validation and audit recording, the sanitized event is appended idempotently to the mailbox before the HTTP response; the Runtime then attempts immediate delivery to active subscribed modules. This append is not part of a documentary-mutation transaction because the query is read-only with respect to the corpus. If an incomplete deployment lacks the table or dispatch fails, `ProductApi` records a safe warning and preserves the already validated documentary answer.

Each module owns `modules/.runtime/data/<module-id>/module.sqlite`. These SQLite databases are private, independent from MySQL, migrated by their package, and excluded from Git. Modules require no foreign keys to, or alterations of, pre-existing Core tables.

## Evidence records

Nodes with usable direct content produce `primary` evidence of type `node_content`. The evidence keeps content and source hash identical to its origin and receives a stable public identifier beginning with `EVA-E`.

Hierarchical summaries produce `derived` evidence of type `node_summary`. `generation_model` and `generation_input_hash` technically version generated content. `evidence_derivations` connects each summary to its own direct evidence and the child summaries or evidence from which it was generated.

Original content is never overwritten by a summary. `validated` on a primary evidence record confirms extraction traceability, not the universal truth of the statement.

## Embeddings

Each vector references one persisted evidence record and stores the model, vector dimension, and structured-input content hash. Vectors from incompatible versions are not mixed.

Query similarities, mean, population standard deviation, coefficient of variation, CIE regions, and final context are transient and are not stored.

## Cognitive interactions

`simetry` and `assimetry` are not documentary-memory records. They are produced in the same call that formulates the answer whenever the available context contains at least two evidence records and the interaction limit is positive, regardless of the input's initial type. They are then validated against cited primary evidence. The database stores no interaction pairs, roles, descriptions, excerpts, confidence values, or negative interaction results.

For operational observability, the sanitized `document_queried` event records only the completed query's `simetry_count` and `assimetry_count` values in `audit_events`. These counts cannot reconstruct an interaction and are not cognitive memory. When an active module subscribes, the Runtime may also persist the allowed `interaction.completed` envelope in the `module_events` mailbox; that event contains the input, answer, public evidence references, and limitations, but does not persist the Core's `simetry`/`assimetry` objects.

## No cognitive weights

The schema does not store cognitive confidence, relevance scores, intensity, priority, importance, or connectivity counts. Vector data is a retrieval mechanism and does not become documentary authority.

## Integrity rules

- Public identifiers are stable and distinct from internal numeric keys.
- Original content is never replaced by generated content.
- Uploaded source files remain outside the public directory.
- Foreign keys cascade dependent records where the schema defines that lifecycle.
- Deleting a work also requires explicit cleanup of its private source file; the product deletion service performs both operations and reports storage cleanup failures.
- Tree and evidence mutations use transactions.
- Query interactions never change the persistent documentary core; only sanitized audit counts and permitted module events may record the operational occurrence.
- A project response profile governs generation only when that project is explicitly selected and never replaces the system's documentary rules.
- Module events are sanitized, reject sensitive fields, and never authorize writes back to documentary memory.

## Deletion semantics

Deleting a work cascades through nodes, evidence, derivations, embeddings, processing jobs, permissions, and project links, then removes its private source file.

Deleting a project removes every work still attached to it, including a work shared with another project. A shared work that must survive must be detached and saved before the project is deleted.

Back up the private database and `storage/documents/` before migrations or destructive administrative operations. Operational dumps and uploaded sources must not be committed or exposed by the web server.
