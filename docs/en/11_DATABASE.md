# Database

## Purpose

The database persists documentary memory with integrity and traceability without duplicating transient relationships or storing judgments and weights.

The initial empty schema is in [`database/schema.sql`](../../database/schema.sql). Existing installations evolve through the ordered migrations in [`database/migrations/`](../../database/migrations/).

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

There are no active `cnodes`, `cnode_evidences`, `cnode_embeddings`, or `interaction_analyses` tables. Historical migrations may mention removed architecture; the current schema and later migrations define the effective model.

## Evidence records

Nodes with usable direct content produce `primary` evidence of type `node_content`. The evidence keeps content and source hash identical to its origin and receives a stable public identifier beginning with `EVA-E`.

Hierarchical summaries produce `derived` evidence of type `node_summary`. `generation_model` and `generation_input_hash` technically version generated content. `evidence_derivations` connects each summary to its own direct evidence and the child summaries or evidence from which it was generated.

Original content is never overwritten by a summary. `validated` on a primary evidence record confirms extraction traceability, not the universal truth of the statement.

## Embeddings

Each vector references one persisted evidence record and stores the model, vector dimension, and structured-input content hash. Vectors from incompatible versions are not mixed.

Query similarities, mean, population standard deviation, coefficient of variation, CIE regions, and final context are transient and are not stored.

## Cognitive interactions

`simetry` and `assimetry` are not database records. They are assembled during a query and validated against primary evidence. The database therefore stores no interaction pairs, roles, descriptions, excerpts, confidence values, or negative interaction results.

## No cognitive weights

The schema does not store cognitive confidence, relevance scores, intensity, priority, importance, or connectivity counts. Vector data is a retrieval mechanism and does not become documentary authority.

## Integrity rules

- Public identifiers are stable and distinct from internal numeric keys.
- Original content is never replaced by generated content.
- Uploaded source files remain outside the public directory.
- Foreign keys cascade dependent records where the schema defines that lifecycle.
- Deleting a work also requires explicit cleanup of its private source file; the product deletion service performs both operations and reports storage cleanup failures.
- Tree and evidence mutations use transactions.
- Query interactions never change the persistent core.
- A project response profile governs generation only when that project is explicitly selected and never replaces the system's documentary rules.

## Deletion semantics

Deleting a work cascades through nodes, evidence, derivations, embeddings, processing jobs, permissions, and project links, then removes its private source file.

Deleting a project removes every work still attached to it, including a work shared with another project. A shared work that must survive must be detached and saved before the project is deleted.

Back up the private database and `storage/documents/` before migrations or destructive administrative operations. Operational dumps and uploaded sources must not be committed or exposed by the web server.
