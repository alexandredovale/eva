# API and operations

## Authentication

`GET /api/health` and `GET /api/branding` are public. Other routes require either an authenticated user session or `Authorization: Bearer` with the configured administrative token according to route policy.

The browser keeps the administrative credential only in `sessionStorage` for the current tab. Logout removes it. User passwords and recovery codes are stored only as hashes.

## Route surface

The table reflects the implemented dispatcher. Request and response bodies are JSON unless the upload row says multipart.

| Access | Method | Route | Purpose |
|---|---|---|---|
| Public | `GET` | `/api/health` | Application and database health |
| Public | `GET` | `/api/branding` | Sanitized branding configuration |
| Public | `POST` | `/api/auth/login` | Normal-user authentication and session issuance |
| Public | `POST` | `/api/auth/recover` | Password recovery with a recovery code and new password |
| Authenticated | `GET` | `/api/me` | Current actor identity and role |
| Authenticated | `POST` | `/api/logout` | Invalidate the current normal-user session or finish the current client session |
| Authenticated | `POST` | `/api/me/password` | Change the current normal user's password |
| Authenticated | `POST` | `/api/me/recovery-code` | Rotate and return a recovery code after password confirmation |
| Authenticated | `GET` | `/api/scopes` | Projects and works available to the current actor |
| Authenticated | `POST` | `/api/query` | Query authorized selected scopes |
| Authenticated | `GET` | `/api/modules` | Discover interface entries exposed by active connector modules |
| Authenticated | `GET` | `/api/modules/{id}/dashboard` | Load a connector-owned generic dashboard payload |
| Superadmin | `POST` | `/api/admin/queue/run` | Run one explicitly confirmed worker pass |
| Superadmin | `GET` | `/api/admin/modules` | List connector packages discovered under `modules/` |
| Superadmin | `PATCH` | `/api/admin/modules/{id}` | Activate or deactivate one connector module |
| Superadmin | `DELETE` | `/api/admin/modules/{id}` | Permanently remove a confirmed package and its private module data |
| Superadmin | `GET`, `POST` | `/api/admin/users` | List users or create a normal user |
| Superadmin | `PATCH` | `/api/admin/users/{id}` | Activate or deactivate a normal user |
| Superadmin | `POST` | `/api/admin/users/{id}/reset-password` | Reset a password and return a new recovery code |
| Superadmin | `PUT` | `/api/admin/users/{id}/permissions` | Replace project and individual-work grants |
| Superadmin | `GET`, `POST` | `/api/admin/projects` | List or create projects |
| Superadmin | `PUT`, `DELETE` | `/api/admin/projects/{id}` | Update or delete a project |
| Superadmin | `GET` | `/api/documents` | List works and descriptive counts |
| Superadmin | `POST` | `/api/documents` | Ingest one Markdown, JSON, or XML multipart upload |
| Superadmin | `DELETE` | `/api/documents/{id}` | Delete a work and its dependent state |
| Superadmin | `POST` | `/api/documents/{id}/process` | Idempotently enqueue summaries and embeddings |
| Superadmin | `GET` | `/api/jobs` | List current processing jobs |
| Superadmin | `POST` | `/api/jobs/{EVA-J...}/retry` | Explicitly retry an eligible failed job |
| Superadmin | `GET` | `/api/metrics` | Return descriptive operational counts |
| Superadmin | `GET` | `/api/audit` | Return sanitized audit events |

Unknown routes return 404. Known routes reject unsupported methods with 405 and an `Allow` header. Authentication, authorization, validation, queue conflicts, query-contract errors, provider unavailability, and unexpected failures remain distinct HTTP conditions with safe client messages.

The administrative token must never appear in documentation, source, URLs, or logs. Normal-user session tokens are stored server-side only as hashes and expire according to security configuration.

## Connector modules

The Core exposes only a generic module contract. It can discover zero, one, or many independent packages without knowing their business purpose, interface labels, HTML, CSS, or private schema. Active module interface entries are returned dynamically, so disabling a module also removes its menu entry.

After answer validation, the Core appends the neutral event idempotently to `module_events`, which is part of the consolidated schema, and attempts immediate processing before returning the HTTP response. Legacy databases use `20260803_010_module_events.sql` as their upgrade path. Each module owns its SQLite database and cursor under `modules/.runtime/data/<module-id>/`; its processing, idempotent event record, and cursor advance commit together in that private transaction. A mailbox missing from an incomplete deployment or one module failure is isolated from the documentary answer and from other subscribers. See [Connector modules](17_MODULE_CONNECTORS.md) for installation, lifecycle, storage, and extension rules.

## Access scopes

The superadmin can manage users, projects, and work-level access. A project grant includes its associated works; a work grant does not expose other works from the same project.

The superadmin bypasses assignment checks. A normal user's selected project or work IDs are resolved again on the server; the browser's visible tree is not an authorization boundary. Multiple authorized scopes are combined and document IDs are deduplicated before retrieval.

Projects may also contain an optional superadmin-managed response profile. The profile activates only when the project root is selected in chat; selecting a work individually does not inherit it. Multiple selected projects contribute their configured profiles independently. Shared works are deduplicated by document ID before retrieval, so they are queried once even though all selected-project profiles remain active.

Profile content is not exposed to normal users or written to audit metadata. Audit events record only whether a project profile is configured and how many profiles were active for a query.

## Queue

Processing jobs are idempotent by document, stage, and capability version. The normal worker claims one job. Summary work interrupted by the configured safe limit returns to the queue with progress preserved. Failed jobs require an explicit allowed retry.

```bash
php bin/queue-worker.php --live
php bin/queue-worker.php --live --drain
```

Both commands still require `AI_LIVE_ENABLED=true`. `--drain` can consume many real provider calls and should be used deliberately.

The superadmin interface offers the same deliberate drain flow without shell access. `POST /api/admin/queue/run` executes one worker pass and requires both `AI_LIVE_ENABLED=true` and a JSON body containing `{"confirm_live": true}`. The browser repeats that request until the returned worker status is `idle`; the tab must remain open during processing. The endpoint invokes `CognitiveQueueWorker` directly and never exposes arbitrary command execution.

The interface polls queue state every three seconds while work is `queued` or `running`. Summary progress follows persisted hierarchical units; embedding batches are persisted incrementally so their progress can advance during processing.

## Audit and metrics

Audit records contain event type, entity, identifier, and sanitized operational metadata. Secrets, passwords, prompts, request bodies, inputs, and documentary content are redacted. Network addresses are stored only as hashes.

Every public request receives a random `X-Request-Id`, which is also available to internal diagnostics. Safe categories distinguish truncated AI output, provider HTTP errors, transport failures, invalid responses, database failures, and general application failures without storing exception text or raw provider output.

Metrics are descriptive counts of documents, evidence classes/types, derivations, embeddings, and jobs. They do not assign relevance, confidence, quality, intensity, or cognitive weight.

Successful query payloads include `context_intelligence`. It is empty for non-vector routes and otherwise contains one transient CIE analysis per document: candidate count, mean, population standard deviation, coefficient of variation, convergence bounds, selected region, and core/convergence/discard groups. This supports reconstruction of the mathematical selection but is not written to documentary memory or the sanitized audit log.

## Deletion

Deleting a work cascades through its nodes, evidence, derivations, embeddings, jobs, permissions, and project links, then removes its private source file. Deleting a project also removes every work still contained in it, including works shared with another project. Shared works that must survive need to be detached and saved before project deletion.

## Operational limits

- list endpoints return at most 100 records in the current API;
- request JSON is limited to 64 KiB;
- query input is limited to 20,000 bytes;
- uploads obey `DOCUMENT_MAX_BYTES`;
- jobs are unique by version and processed individually by default.

Functional scripts and styles are served locally. Web fonts may be loaded only from the Google Fonts domains allowed by the Content Security Policy.
