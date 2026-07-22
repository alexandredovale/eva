# Security and deployment

## Public boundary

Only `public/` should be web-accessible. Source code, environment files, database files, documentation, tests, storage, and logs must never be served directly.

The root Apache configuration blocks real files and directories outside the public surface, disables indexes, restricts unused mutation methods, and forwards only virtual application routes. Validate equivalent rules when using Nginx or another server.

## Secrets

- Never commit `.env`, `api_key.md`, tokens, passwords, provider responses, or production endpoints containing credentials.
- Use `.env.example` only for names and safe placeholders.
- Rotate any credential suspected of exposure; removing a file from the latest commit does not remove it from Git history.
- Enable GitHub secret scanning and push protection after publication.

## Private runtime data

Operational dumps, uploaded documents, logs, user records, access grants, and password hashes are not source code and are excluded from the public repository. The public package contains only the empty schema and versioned migrations.

## Production checklist

1. Use HTTPS and a production hostname.
2. Set `APP_ENV=production` and `APP_DEBUG=false`.
3. Generate unique database and administrative credentials.
4. Serve only `public/` and deny directory listing.
5. Apply global Apache hardening such as `TraceEnable Off` and reduced server tokens.
6. Restrict filesystem permissions for `.env`, storage, and logs.
7. Back up and restore the private database in an isolated verification environment.
8. Run offline regression tests and the deployment verifier.
9. Enable live providers only after limits, models, endpoints, and billing controls are reviewed.
10. Verify sanitized error responses, audit records, CSP, security headers, and access scopes online.

## Vulnerability reporting

Follow the private process in the repository root [`SECURITY.md`](../../SECURITY.md). Never publish an active exploit or real secret in an issue.

