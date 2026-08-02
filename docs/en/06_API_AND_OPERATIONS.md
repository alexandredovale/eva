# API and operations

## Authentication

`GET /api/health` and `GET /api/branding` are public. Other routes require either an authenticated user session or `Authorization: Bearer` with the configured administrative token according to route policy.

The browser keeps the administrative credential only in `sessionStorage` for the current tab. Logout removes it. User passwords and recovery codes are stored only as hashes.

## Access scopes

The superadmin can manage users, projects, and work-level access. A project grant includes its associated works; a work grant does not expose other works from the same project.

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

## Audit and metrics

Audit records contain event type, entity, identifier, and sanitized operational metadata. Secrets, passwords, prompts, request bodies, inputs, and documentary content are redacted. Network addresses are stored only as hashes.

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
