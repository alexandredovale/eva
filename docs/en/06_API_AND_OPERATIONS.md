# API and operations

## Authentication

`GET /api/health` and `GET /api/branding` are public. Other routes require either an authenticated user session or `Authorization: Bearer` with the configured administrative token according to route policy.

The browser keeps the administrative credential only in `sessionStorage` for the current tab. Logout removes it. User passwords and recovery codes are stored only as hashes.

## Access scopes

The superadmin can manage users, projects, and work-level access. A project grant includes its associated works; a work grant does not expose other works from the same project.

## Queue

Processing jobs are idempotent by document, stage, and capability version. The normal worker claims one job. Summary work interrupted by the configured safe limit returns to the queue with progress preserved. Failed jobs require an explicit allowed retry.

```bash
php bin/queue-worker.php --live
php bin/queue-worker.php --live --drain
```

Both commands still require `AI_LIVE_ENABLED=true`. `--drain` can consume many real provider calls and should be used deliberately.

## Audit and metrics

Audit records contain event type, entity, identifier, and sanitized operational metadata. Secrets, passwords, prompts, request bodies, inputs, and documentary content are redacted. Network addresses are stored only as hashes.

Metrics are descriptive counts of documents, evidence classes/types, derivations, embeddings, and jobs. They do not assign relevance, confidence, quality, intensity, or cognitive weight.

## Deletion

Deleting a work cascades through its nodes, evidence, derivations, embeddings, jobs, permissions, and project links, then removes its private source file. Deleting a project also removes every work still contained in it, including works shared with another project. Shared works that must survive need to be detached and saved before project deletion.

## Operational limits

- list endpoints return at most 100 records in the current API;
- request JSON is limited to 64 KiB;
- query input is limited to 20,000 bytes;
- uploads obey `DOCUMENT_MAX_BYTES`;
- jobs are unique by version and processed individually by default.

